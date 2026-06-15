<?php

declare(strict_types=1);

use CiviCrm\Laravel\Data\ContactInput;
use CiviCrm\Laravel\Outbox\OutboxEntry;
use CiviCrm\Laravel\Outbox\OutboxRepository;
use CiviCrm\Laravel\Tests\Support\TestTransport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Exception\AuthenticationException;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function outboxClient(TestTransport $transport): CiviCrmClient
{
    return new CiviCrmClient($transport);
}

// ---------------------------------------------------------------------------
// push — transactional guarantees
// ---------------------------------------------------------------------------

it('push inside a rolled-back transaction does not persist a row', function (): void {
    $repo = new OutboxRepository();

    try {
        DB::transaction(function () use ($repo): void {
            $repo->push('sync_contact', ['foo' => 'bar']);
            throw new \RuntimeException('force rollback');
        });
    } catch (\RuntimeException) {
        // expected
    }

    expect(OutboxEntry::count())->toBe(0);
});

it('push inside a committed transaction persists a pending row', function (): void {
    $repo = new OutboxRepository();

    DB::transaction(function () use ($repo): void {
        $repo->push('sync_contact', ['foo' => 'bar']);
    });

    $entry = OutboxEntry::firstOrFail();
    expect($entry->status)->toBe('pending')
        ->and($entry->type)->toBe('sync_contact');
});

// ---------------------------------------------------------------------------
// push — deduplication
// ---------------------------------------------------------------------------

it('push with the same dedupe_key returns the existing entry without creating a duplicate', function (): void {
    $repo = new OutboxRepository();

    $first  = $repo->push('sync_contact', ['a' => 1], 'key-1');
    $second = $repo->push('sync_contact', ['a' => 2], 'key-1');

    expect(OutboxEntry::count())->toBe(1)
        ->and($first->id)->toBe($second->id);
});

it('push without a dedupe_key always inserts a new row', function (): void {
    $repo = new OutboxRepository();

    $repo->push('sync_contact', ['a' => 1]);
    $repo->push('sync_contact', ['a' => 2]);

    expect(OutboxEntry::count())->toBe(2);
});

// ---------------------------------------------------------------------------
// ProcessOutboxCommand — happy path
// ---------------------------------------------------------------------------

it('processes a pending sync_contact entry and marks it done', function (): void {
    $transport = new TestTransport();
    // email path: Contact.get (not found) → Contact.create
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);

    app()->instance(CiviCrmClient::class, outboxClient($transport));

    $repo  = new OutboxRepository();
    $input = new ContactInput(email: 'ok@example.org', firstName: 'Done');
    $repo->push('sync_contact', ['contact_input' => $input->toArray()]);

    $exitCode = Artisan::call('civicrm:outbox:work');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Processed 1: 1 done, 0 failed');

    expect(OutboxEntry::firstOrFail()->status)->toBe('done');
});

// ---------------------------------------------------------------------------
// ProcessOutboxCommand — retryable failure
// ---------------------------------------------------------------------------

it('increments attempts and reschedules the entry on a retryable failure', function (): void {
    // Unknown type → \UnexpectedValueException (retryable, not a ValidationException)
    OutboxEntry::create([
        'uuid'         => (string) Str::uuid(),
        'type'         => 'unknown_type',
        'payload'      => ['x' => 1],
        'status'       => 'pending',
        'attempts'     => 0,
        'available_at' => now()->subSecond(),
    ]);

    app()->instance(CiviCrmClient::class, outboxClient(new TestTransport()));

    $exitCode = Artisan::call('civicrm:outbox:work', ['--max-attempts' => 5]);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Processed 1: 0 done, 1 failed');

    $entry = OutboxEntry::firstOrFail();
    expect($entry->status)->toBe('pending')
        ->and($entry->attempts)->toBe(1)
        ->and($entry->available_at->isFuture())->toBeTrue();
});

// ---------------------------------------------------------------------------
// ProcessOutboxCommand — max-attempts exhausted
// ---------------------------------------------------------------------------

it('permanently fails an entry when max-attempts is exhausted', function (): void {
    OutboxEntry::create([
        'uuid'         => (string) Str::uuid(),
        'type'         => 'unknown_type',
        'payload'      => ['x' => 1],
        'status'       => 'pending',
        'attempts'     => 4,
        'available_at' => now()->subSecond(),
    ]);

    app()->instance(CiviCrmClient::class, outboxClient(new TestTransport()));

    Artisan::call('civicrm:outbox:work', ['--max-attempts' => 5]);

    expect(OutboxEntry::firstOrFail()->status)->toBe('failed');
});

// ---------------------------------------------------------------------------
// ProcessOutboxCommand — ValidationException → immediate permanent failure
// ---------------------------------------------------------------------------

it('immediately fails an entry on ValidationException without retrying', function (): void {
    // Payload has no email/externalIdentifier → ContactInput::fromArray throws ValidationException
    OutboxEntry::create([
        'uuid'         => (string) Str::uuid(),
        'type'         => 'sync_contact',
        'payload'      => ['contact_input' => ['firstName' => 'NoMatchKey']],
        'status'       => 'pending',
        'attempts'     => 0,
        'available_at' => now()->subSecond(),
    ]);

    app()->instance(CiviCrmClient::class, outboxClient(new TestTransport()));

    $exitCode = Artisan::call('civicrm:outbox:work');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Processed 1: 0 done, 1 failed');

    $entry = OutboxEntry::firstOrFail();
    expect($entry->status)->toBe('failed')
        ->and($entry->attempts)->toBe(1);
});

// ---------------------------------------------------------------------------
// ProcessOutboxCommand — AuthenticationException → immediate permanent failure
// ---------------------------------------------------------------------------

it('immediately fails an entry on AuthenticationException without retrying', function (): void {
    $transport = new TestTransport();
    $transport->willThrow(new AuthenticationException('401 Unauthorized', 401));

    OutboxEntry::create([
        'uuid'         => (string) Str::uuid(),
        'type'         => 'sync_contact',
        'payload'      => ['contact_input' => (new ContactInput(email: 'auth@example.org'))->toArray()],
        'status'       => 'pending',
        'attempts'     => 0,
        'available_at' => now()->subSecond(),
    ]);

    app()->instance(CiviCrmClient::class, outboxClient($transport));

    $exitCode = Artisan::call('civicrm:outbox:work');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Processed 1: 0 done, 1 failed');

    $entry = OutboxEntry::firstOrFail();
    expect($entry->status)->toBe('failed')
        ->and($entry->attempts)->toBe(1);
});

// ---------------------------------------------------------------------------
// reserveBatch — double-processing prevention
// ---------------------------------------------------------------------------

it('does not return the same entry in two successive reserveBatch calls', function (): void {
    $repo  = new OutboxRepository();
    $input = new ContactInput(email: 'batch@example.org');
    $repo->push('sync_contact', ['contact_input' => $input->toArray()]);

    $first  = $repo->reserveBatch(10);
    $second = $repo->reserveBatch(10);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(0);
});
