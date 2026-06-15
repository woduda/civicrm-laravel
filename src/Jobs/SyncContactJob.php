<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Jobs;

use CiviCrm\Laravel\Data\ContactInput;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Entity\Contact;
use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

/**
 * Queues an idempotent upsert of a CiviCRM contact.
 *
 * Idempotency guarantee:
 * - {@see ShouldBeUnique} acquires a lock keyed on the match field for the duration
 *   of the job, preventing duplicate dispatches for the same contact from running
 *   concurrently.
 * - The upsert itself is idempotent at the API level (get → create or update).
 *
 * Deduplication key:
 * - When `externalIdentifier` is present it is used as the unique ID.
 * - Otherwise the key is `"email:{email}"`.
 *
 * externalIdentifier path — non-atomic compromise:
 * `woduda/civicrm-php ^0.7` has no `upsertByExternalId` helper. This job performs
 * a two-step Contact.get + conditional Contact.create/update, which is not atomic.
 * A concurrent insert between the get and the create may produce a duplicate.
 * The atomic solution (`Contact.save` with `match=['external_identifier']`) is
 * planned for a future core-lib PR once `ActionRequest::save` supports the `match`
 * parameter.
 */
class SyncContactJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int Maximum number of attempts before the job is marked failed */
    public int $tries = 5;

    /**
     * @throws \InvalidArgumentException Propagated from ContactInput if config is malformed
     */
    public function __construct(public readonly ContactInput $contact)
    {
        $connection = config('civicrm.queue.connection');
        $queueName = config('civicrm.queue.queue');

        if (is_string($connection) || $connection instanceof \UnitEnum || $connection === null) {
            $this->onConnection($connection);
        }

        if (is_string($queueName) || $queueName instanceof \UnitEnum || $queueName === null) {
            $this->onQueue($queueName);
        }
    }

    /**
     * Returns the exponential backoff schedule in seconds.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    /**
     * Unique lock key derived from the business match field.
     *
     * - externalIdentifier is used when present.
     * - Falls back to `"email:{email}"`.
     */
    public function uniqueId(): string
    {
        if ($this->contact->externalIdentifier !== null) {
            return $this->contact->externalIdentifier;
        }

        // email is guaranteed non-null by ContactInput constructor validation.
        return 'email:' . ($this->contact->email ?? '');
    }

    /**
     * Upserts the contact in CiviCRM, then applies tags, groups, and custom fields.
     *
     * @throws ApiErrorException        On CiviCRM HTTP 4xx/5xx errors
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function handle(CiviCrmClient $client): void
    {
        $contactId = $this->contact->externalIdentifier !== null
            ? $this->upsertByExternalId($client)
            : $this->upsertByEmail($client);

        $this->applyTags($client, $contactId);
        $this->applyGroups($client, $contactId);
        $this->applyCustomFields($client, $contactId);
    }

    private function upsertByExternalId(CiviCrmClient $client): int
    {
        $externalId = $this->contact->externalIdentifier;
        assert($externalId !== null);

        $existing = $client->contacts()->get(
            GetQuery::new()
                ->select('id')
                ->where('external_identifier', Operator::Equals, $externalId)
                ->limit(1),
        );

        if ($existing->isEmpty()) {
            $values = array_merge(
                $this->contact->toCiviValues(),
                ['external_identifier' => $externalId],
            );

            if ($this->contact->email !== null) {
                $values['email'] = $this->contact->email;
            }

            $result = $client->contacts()->create($values);
        } else {
            /** @var Contact $found */
            $found = $existing->first();
            assert($found instanceof Contact);
            assert($found->id !== null);

            $result = $client->contacts()->update($found->id, $this->contact->toCiviValues());

            if ($this->contact->email !== null) {
                $client->contacts()->updatePrimaryEmail($found->id, $this->contact->email);
            }
        }

        /** @var Contact $saved */
        $saved = $result->first();
        assert($saved instanceof Contact);
        assert($saved->id !== null);

        return $saved->id;
    }

    private function upsertByEmail(CiviCrmClient $client): int
    {
        $email = $this->contact->email;
        assert($email !== null);

        $result = $client->contacts()->upsertByEmail($email, $this->contact->toCiviValues());

        /** @var Contact $saved */
        $saved = $result->first();
        assert($saved instanceof Contact);
        assert($saved->id !== null);

        return $saved->id;
    }

    private function applyTags(CiviCrmClient $client, int $contactId): void
    {
        if ($this->contact->tags !== []) {
            $client->contacts()->withTags($contactId, $this->contact->tags);
        }
    }

    private function applyGroups(CiviCrmClient $client, int $contactId): void
    {
        if ($this->contact->groups !== []) {
            $client->contacts()->addToGroups($contactId, $this->contact->groups);
        }
    }

    private function applyCustomFields(CiviCrmClient $client, int $contactId): void
    {
        if ($this->contact->extraFields === []) {
            return;
        }

        /** @var array<string, array<string, mixed>> $byGroup */
        $byGroup = [];

        foreach ($this->contact->extraFields as $dotKey => $value) {
            if (! str_contains($dotKey, '.')) {
                continue;
            }

            [$groupName, $fieldName] = explode('.', $dotKey, 2);
            $byGroup[$groupName][$fieldName] = $value;
        }

        foreach ($byGroup as $groupName => $fields) {
            $client->contacts()->setCustomFields($contactId, $groupName, $fields);
        }
    }
}
