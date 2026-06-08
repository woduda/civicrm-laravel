<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Outbox;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Represents a single entry in the transactional outbox.
 *
 * @property int         $id
 * @property string      $uuid
 * @property string      $type
 * @property array<string, mixed> $payload
 * @property string|null $dedupe_key
 * @property string      $status   pending|processing|done|failed
 * @property int         $attempts
 * @property Carbon      $available_at
 * @property Carbon|null $reserved_at
 * @property Carbon|null $processed_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<OutboxEntry> pending()
 * @method static Builder<OutboxEntry> dueNow()
 */
class OutboxEntry extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    #[\Override]
    public function getTable(): string
    {
        /** @var string */
        return config('civicrm.outbox.table', 'civicrm_outbox');
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'available_at' => 'datetime',
            'reserved_at'  => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<OutboxEntry> $query
     * @return Builder<OutboxEntry>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  Builder<OutboxEntry> $query
     * @return Builder<OutboxEntry>
     */
    public function scopeDueNow(Builder $query): Builder
    {
        return $query
            ->where('status', 'pending')
            ->where('available_at', '<=', now());
    }
}
