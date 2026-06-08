<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Outbox;

use CiviCrm\Laravel\Data\ContactInput;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manages persistence of transactional outbox entries.
 *
 * ### Transactional pattern
 *
 * {@see push()} does NOT open its own database transaction — call it from
 * within the caller's transaction so the outbox write is committed atomically
 * alongside the domain model change:
 *
 * ```php
 * DB::transaction(function () use ($repository, $contactInput): void {
 *     $domain->save();
 *     $repository->pushSyncContact($contactInput);
 * });
 * ```
 *
 * If the outer transaction rolls back, the outbox entry is discarded with it.
 * If it commits, the entry is guaranteed to be visible to the drain command.
 */
final class OutboxRepository
{
    /**
     * Pushes an entry into the outbox.
     *
     * When `$dedupeKey` is provided and an entry with that key already exists
     * (any status), the existing entry is returned without creating a duplicate.
     * The unique DB constraint on `dedupe_key` acts as a safety net for races.
     *
     * @param array<string, mixed> $payload
     *
     * @throws \Illuminate\Database\QueryException on unexpected DB failure
     */
    public function push(string $type, array $payload, ?string $dedupeKey = null): OutboxEntry
    {
        $attrs = [
            'uuid'         => (string) Str::uuid(),
            'type'         => $type,
            'payload'      => $payload,
            'status'       => 'pending',
            'attempts'     => 0,
            'available_at' => now(),
        ];

        if ($dedupeKey !== null) {
            /** @var OutboxEntry */
            return OutboxEntry::firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                $attrs,
            );
        }

        /** @var OutboxEntry */
        return OutboxEntry::create($attrs);
    }

    /**
     * Convenience wrapper — pushes a sync_contact entry for the given DTO.
     *
     * @throws \Illuminate\Database\QueryException
     */
    public function pushSyncContact(ContactInput $input): OutboxEntry
    {
        $dedupeKey = $input->externalIdentifier !== null
            ? 'sync_contact:ext:' . $input->externalIdentifier
            : 'sync_contact:email:' . ($input->email ?? '');

        return $this->push(
            'sync_contact',
            ['contact_input' => $input->toArray()],
            $dedupeKey,
        );
    }

    /**
     * Convenience wrapper — pushes a create_activity entry.
     *
     * @param array<string, mixed> $params
     *
     * @throws \Illuminate\Database\QueryException
     */
    public function pushCreateActivity(
        int $contactId,
        string $type,
        array $params = [],
        ?string $dedupeKey = null,
    ): OutboxEntry {
        return $this->push(
            'create_activity',
            [
                'contact_id' => $contactId,
                'type'       => $type,
                'params'     => $params,
                'dedupe_key' => $dedupeKey,
            ],
            $dedupeKey,
        );
    }

    /**
     * Atomically reserves up to `$limit` pending due entries for processing.
     *
     * Uses SELECT … FOR UPDATE inside a transaction (no-op on SQLite, which is
     * fine for single-threaded test environments).
     *
     * @return Collection<int, OutboxEntry>
     */
    public function reserveBatch(int $limit): Collection
    {
        /** @var Collection<int, OutboxEntry> */
        return DB::transaction(function () use ($limit): Collection {
            /** @var Collection<int, OutboxEntry> $entries */
            $entries = OutboxEntry::dueNow()
                ->lockForUpdate()
                ->limit($limit)
                ->get();

            if ($entries->isEmpty()) {
                return $entries;
            }

            OutboxEntry::whereIn('id', $entries->pluck('id'))
                ->update([
                    'status'      => 'processing',
                    'reserved_at' => now(),
                ]);

            $now = now();

            $entries->each(static function (OutboxEntry $entry) use ($now): void {
                $entry->status      = 'processing';
                $entry->reserved_at = $now;
                // Sync so Eloquent's dirty-tracking baseline reflects 'processing',
                // otherwise a subsequent update(['status' => 'pending']) would be a no-op
                // (original was 'pending', new value is 'pending' → not dirty).
                $entry->syncOriginal();
            });

            return $entries;
        });
    }

    /**
     * Marks an entry as successfully processed.
     */
    public function markDone(OutboxEntry $entry): void
    {
        $entry->update([
            'status'       => 'done',
            'processed_at' => now(),
        ]);
    }

    /**
     * Records a failure on an entry.
     *
     * When `$retryDelaySeconds > 0` the entry is rescheduled for a future attempt
     * (status reverts to `pending`). When `$retryDelaySeconds === 0` the entry is
     * permanently failed.
     */
    public function markFailed(OutboxEntry $entry, \Throwable $e, int $retryDelaySeconds): void
    {
        if ($retryDelaySeconds > 0) {
            $entry->update([
                'status'       => 'pending',
                'attempts'     => $entry->attempts + 1,
                'available_at' => now()->addSeconds($retryDelaySeconds),
                'last_error'   => $e->getMessage(),
            ]);
        } else {
            $entry->update([
                'status'     => 'failed',
                'attempts'   => $entry->attempts + 1,
                'last_error' => $e->getMessage(),
            ]);
        }
    }
}
