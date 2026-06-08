<?php

declare(strict_types=1);

use CiviCrm\Laravel\Jobs\CreateActivityJob;
use CiviCrm\Laravel\Tests\Support\TestTransport;
use Illuminate\Support\Facades\Bus;
use Woduda\CiviCRM\CiviCrmClient;

beforeEach(function (): void {
    config([
        'civicrm.base_url'  => 'https://example.org/civicrm/ajax/api4/',
        'civicrm.api_token' => 'test-token',
    ]);
});

// ---------------------------------------------------------------------------
// uniqueId
// ---------------------------------------------------------------------------

it('uses dedupeKey as uniqueId when provided', function (): void {
    $job = new CreateActivityJob(42, 'Phone Call', [], 'my-dedupe-key');

    expect($job->uniqueId())->toBe('my-dedupe-key');
});

it('derives uniqueId from content when dedupeKey is absent', function (): void {
    $job = new CreateActivityJob(42, 'Phone Call', ['subject' => 'Intake']);

    expect($job->uniqueId())->toBe(
        sha1('42Phone Call' . json_encode(['subject' => 'Intake'], JSON_THROW_ON_ERROR)),
    );
});

it('returns the same uniqueId for identical inputs', function (): void {
    $a = new CreateActivityJob(1, 'Meeting', ['subject' => 'S']);
    $b = new CreateActivityJob(1, 'Meeting', ['subject' => 'S']);

    expect($a->uniqueId())->toBe($b->uniqueId());
});

it('returns different uniqueIds for different params', function (): void {
    $a = new CreateActivityJob(1, 'Meeting', ['subject' => 'A']);
    $b = new CreateActivityJob(1, 'Meeting', ['subject' => 'B']);

    expect($a->uniqueId())->not->toBe($b->uniqueId());
});

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

it('can be dispatched via Bus', function (): void {
    Bus::fake();

    dispatch(new CreateActivityJob(42, 'Phone Call', ['subject' => 'Intake call']));

    Bus::assertDispatched(
        CreateActivityJob::class,
        fn(CreateActivityJob $job): bool
            => $job->contactId === 42 && $job->activityType === 'Phone Call',
    );
});

// ---------------------------------------------------------------------------
// handle
// ---------------------------------------------------------------------------

it('calls Activity.create via logForContact with the correct values', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Activity', 'create', [['id' => 9]], 1);

    $client = new CiviCrmClient($transport);
    $job = new CreateActivityJob(42, 'Phone Call', ['subject' => 'Intake call']);
    $job->handle($client);

    $creates = $transport->callsFor('Activity', 'create');
    expect($creates)->toHaveCount(1);

    $actValues = $creates[0]['params']['values'] ?? null;
    expect(is_array($actValues) ? ($actValues['activity_type_id.name'] ?? null) : null)->toBe('Phone Call');
    expect(is_array($actValues) ? ($actValues['source_contact_id'] ?? null) : null)->toBe(42);
    expect(is_array($actValues) ? ($actValues['subject'] ?? null) : null)->toBe('Intake call');
    expect(is_array($actValues) ? ($actValues['status_id.name'] ?? null) : null)->toBe('Completed');
});

it('passes extra params through to logForContact', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Activity', 'create', [['id' => 11]], 1);

    $client = new CiviCrmClient($transport);
    $job = new CreateActivityJob(5, 'Meeting', ['duration' => 30, 'status_id.name' => 'Scheduled']);
    $job->handle($client);

    $extraValues = $transport->callsFor('Activity', 'create')[0]['params']['values'] ?? null;
    expect(is_array($extraValues) ? ($extraValues['duration'] ?? null) : null)->toBe(30);
    expect(is_array($extraValues) ? ($extraValues['status_id.name'] ?? null) : null)->toBe('Scheduled');
});

it('works when params array is empty', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Activity', 'create', [['id' => 12]], 1);

    $client = new CiviCrmClient($transport);
    $job = new CreateActivityJob(7, 'Email');
    $job->handle($client);

    expect($transport->callsFor('Activity', 'create'))->toHaveCount(1);
});
