<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Exception\ApiErrorException;

/**
 * Queues creation of a CiviCRM activity linked to a contact.
 *
 * Idempotency note:
 * CiviCRM does not deduplicate activities natively — every call to `Activity.create`
 * produces a new record. Idempotency here relies entirely on {@see ShouldBeUnique}
 * (queue lock keyed on `uniqueId`). If the lock expires before the job completes
 * (at-least-once delivery) a duplicate activity may be created. Full deduplication
 * with a persistent `dedupe_key` column in the outbox is planned for LPR #3.
 *
 * Usage:
 * - Pass a stable `$dedupeKey` (e.g. a form submission UUID) to get safe retries.
 * - Omit `$dedupeKey` to auto-derive a key from `contactId + activityType + params`;
 *   note that dispatching the same conceptual activity twice with different params
 *   will produce two jobs with different lock keys and therefore may both execute.
 */
class CreateActivityJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int Maximum number of attempts before the job is marked failed */
    public int $tries = 3;

    /**
     * @param array<string, mixed> $params    Extra fields passed to logForContact (e.g. subject, duration)
     * @param string|null          $dedupeKey Explicit dedup key; auto-derived from content when null
     */
    public function __construct(
        public readonly int $contactId,
        public readonly string $activityType,
        public readonly array $params = [],
        public readonly ?string $dedupeKey = null,
    ) {
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
     * Unique lock key used by {@see ShouldBeUnique}.
     *
     * Uses the caller-supplied `$dedupeKey` when available; falls back to a SHA-1
     * digest of `contactId + activityType + JSON(params)`.
     */
    public function uniqueId(): string
    {
        if ($this->dedupeKey !== null) {
            return $this->dedupeKey;
        }

        return sha1(
            $this->contactId
            . $this->activityType
            . json_encode($this->params, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Creates the activity in CiviCRM via logForContact.
     *
     * @throws ApiErrorException        On CiviCRM HTTP 4xx/5xx errors
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function handle(CiviCrmClient $client): void
    {
        $client->activities()->logForContact($this->contactId, $this->activityType, $this->params);
    }
}
