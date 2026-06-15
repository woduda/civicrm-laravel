<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Console;

use CiviCrm\Laravel\Data\ContactInput;
use CiviCrm\Laravel\Jobs\CreateActivityJob;
use CiviCrm\Laravel\Jobs\SyncContactJob;
use CiviCrm\Laravel\Outbox\OutboxEntry;
use CiviCrm\Laravel\Outbox\OutboxRepository;
use Illuminate\Console\Command;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Exception\AuthenticationException;
use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Drains the transactional outbox by executing pending entries synchronously.
 *
 * Intended for the application scheduler:
 *
 * ```php
 * $schedule->command('civicrm:outbox:work')->everyMinute();
 * ```
 *
 * Idempotent and safe to overlap — {@see OutboxRepository::reserveBatch()}
 * uses row-level locking to prevent the same entry from being processed twice.
 */
class ProcessOutboxCommand extends Command
{
    protected $signature = 'civicrm:outbox:work
        {--limit=100 : Maximum number of entries to process per run}
        {--max-attempts=5 : Maximum attempts before an entry is permanently failed}';

    protected $description = 'Process pending CiviCRM outbox entries.';

    public function handle(OutboxRepository $repository, CiviCrmClient $client): int
    {
        $limit       = (int) $this->option('limit');
        $maxAttempts = (int) $this->option('max-attempts');

        $entries = $repository->reserveBatch($limit);

        $done   = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            try {
                $this->processEntry($entry, $client);
                $repository->markDone($entry);
                $done++;
            } catch (\Throwable $e) {
                $attempts = $entry->attempts + 1;

                if ($this->isPermanentFailure($e) || $attempts >= $maxAttempts) {
                    $repository->markFailed($entry, $e, 0);
                } else {
                    // Integer-safe exponential backoff: 120s, 240s, 480s, … capped at 3600s
                    $delay = min(60 * (1 << min($attempts, 6)), 3600);
                    $repository->markFailed($entry, $e, $delay);
                }

                $failed++;
            }
        }

        $total = $done + $failed;
        $this->info("Processed {$total}: {$done} done, {$failed} failed");

        return self::SUCCESS;
    }

    private function processEntry(OutboxEntry $entry, CiviCrmClient $client): void
    {
        $payload = $entry->payload;

        match ($entry->type) {
            'sync_contact'    => $this->runSyncContact($payload, $client),
            'create_activity' => $this->runCreateActivity($payload, $client),
            default           => throw new \UnexpectedValueException(
                "Unknown outbox entry type: {$entry->type}",
            ),
        };
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidationException if the stored payload cannot reconstruct a valid ContactInput
     */
    private function runSyncContact(array $payload, CiviCrmClient $client): void
    {
        $raw = $payload['contact_input'] ?? null;

        $contactInput = ContactInput::fromArray(
            is_array($raw) ? $this->toStringKeyedArray($raw) : [],
        );

        (new SyncContactJob($contactInput))->handle($client);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function runCreateActivity(array $payload, CiviCrmClient $client): void
    {
        $contactId = $payload['contact_id'] ?? null;
        $type      = $payload['type'] ?? null;
        $params    = $payload['params'] ?? null;
        $dedupeKey = $payload['dedupe_key'] ?? null;

        if (!is_int($contactId)) {
            throw new \UnexpectedValueException('Invalid payload: contact_id must be int');
        }

        if (!is_string($type)) {
            throw new \UnexpectedValueException('Invalid payload: type must be string');
        }

        if (!is_string($dedupeKey) && $dedupeKey !== null) {
            throw new \UnexpectedValueException('Invalid payload: dedupe_key must be string or null');
        }

        $params = is_array($params) ? $this->toStringKeyedArray($params) : [];

        (new CreateActivityJob($contactId, $type, $params, $dedupeKey))->handle($client);
    }

    private function isPermanentFailure(\Throwable $e): bool
    {
        if ($e instanceof ValidationException) {
            return true;
        }

        return $e instanceof AuthenticationException;
    }

    /**
     * Filters an array to only string-keyed entries. JSON payloads decoded from the
     * database always have string keys; this helper makes that invariant explicit to
     * the static analyser without relying on inline type annotations.
     *
     * @param  array<mixed, mixed> $input
     * @return array<string, mixed>
     */
    private function toStringKeyedArray(array $input): array
    {
        $result = [];

        foreach ($input as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
