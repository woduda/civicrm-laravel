<?php

declare(strict_types=1);

use CiviCrm\Laravel\Data\ContactInput;
use CiviCrm\Laravel\Jobs\SyncContactJob;
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

it('uses externalIdentifier as uniqueId when present', function (): void {
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-abc'));

    expect($job->uniqueId())->toBe('ext-abc');
});

it('uses email-prefixed string as uniqueId when externalIdentifier is absent', function (): void {
    $job = new SyncContactJob(new ContactInput(email: 'jane@example.org'));

    expect($job->uniqueId())->toBe('email:jane@example.org');
});

it('returns the same uniqueId for repeated calls', function (): void {
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-1'));

    expect($job->uniqueId())->toBe($job->uniqueId());
});

// ---------------------------------------------------------------------------
// Dispatch
// ---------------------------------------------------------------------------

it('can be dispatched via Bus', function (): void {
    Bus::fake();

    $input = new ContactInput(email: 'a@b.com', firstName: 'Anna');
    dispatch(new SyncContactJob($input));

    Bus::assertDispatched(SyncContactJob::class, fn(SyncContactJob $job): bool => $job->contact->email === 'a@b.com');
});

// ---------------------------------------------------------------------------
// handle — email path
// ---------------------------------------------------------------------------

it('calls upsertByEmail when externalIdentifier is absent', function (): void {
    $transport = new TestTransport();
    // upsertByEmail internally: Contact.get (find by email) → empty → Contact.create
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'a@b.com', firstName: 'Anna'));
    $job->handle($client);

    $gets = $transport->callsFor('Contact', 'get');
    expect($gets)->toHaveCount(1);

    $where = $gets[0]['params']['where'] ?? null;
    $firstClause = is_array($where) ? ($where[0] ?? null) : null;
    expect($firstClause)->toBe(['email_primary.email', '=', 'a@b.com']);
});

it('creates a new contact on the email path when not found', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 2]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'b@example.com', firstName: 'Bob'));
    $job->handle($client);

    expect($transport->callsFor('Contact', 'create'))->toHaveCount(1);
});

it('updates an existing contact on the email path when found', function (): void {
    $transport = new TestTransport();
    // upsertByEmail: Contact.get returns existing → Contact.update
    $transport->addResponse('Contact', 'get', [['id' => 5]], 1);
    $transport->addResponse('Contact', 'update', [['id' => 5]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'c@example.com', lastName: 'Smith'));
    $job->handle($client);

    expect($transport->callsFor('Contact', 'update'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// handle — externalIdentifier path
// ---------------------------------------------------------------------------

it('creates a new contact when externalIdentifier is not found', function (): void {
    $transport = new TestTransport();
    // SyncContactJob: Contact.get by external_identifier → not found → Contact.create
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 7]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-007', firstName: 'James'));
    $job->handle($client);

    $creates = $transport->callsFor('Contact', 'create');
    expect($creates)->toHaveCount(1);

    $createValues = $creates[0]['params']['values'] ?? null;
    $extId = is_array($createValues) ? ($createValues['external_identifier'] ?? null) : null;
    expect($extId)->toBe('ext-007');
});

it('updates an existing contact when externalIdentifier is found', function (): void {
    $transport = new TestTransport();
    // Contact.get → found → Contact.update
    $transport->addResponse('Contact', 'get', [['id' => 10]], 1);
    $transport->addResponse('Contact', 'update', [['id' => 10]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-010', lastName: 'Bond'));
    $job->handle($client);

    expect($transport->callsFor('Contact', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('Contact', 'update'))->toHaveCount(1);
});

it('includes email in create values on externalIdentifier path when email is provided', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 3]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-3', email: 'e@x.com'));
    $job->handle($client);

    $creates = $transport->callsFor('Contact', 'create');
    expect($creates[0]['params']['values'])->toHaveKey('email', 'e@x.com');
});

it('calls updatePrimaryEmail when updating and email is provided', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Contact', 'get', [['id' => 20]], 1);
    $transport->addResponse('Contact', 'update', [['id' => 20]], 1);
    // updatePrimaryEmail → Email.get (primary), then Email.create
    $transport->addResponse('Email', 'get', [], 0);
    $transport->addResponse('Email', 'create', [['id' => 1, 'contact_id' => 20, 'location_type_id' => 1, 'is_primary' => true, 'is_billing' => false, 'email' => 'new@x.com']], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(externalIdentifier: 'ext-20', email: 'new@x.com'));
    $job->handle($client);

    // Email.get is called to find the primary email before creating/updating
    expect($transport->callsFor('Email', 'get'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// handle — tags
// ---------------------------------------------------------------------------

it('calls withTags when tags are non-empty', function (): void {
    $transport = new TestTransport();
    // upsertByEmail path
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);
    // withTags: Tag.get (existing tags) → returns 'Donor'
    $transport->addResponse('Tag', 'get', [['id' => 11, 'name' => 'Donor']], 1);
    // EntityTag.save
    $transport->addResponse('EntityTag', 'save', [], 0);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'a@b.com', tags: ['Donor']));
    $job->handle($client);

    expect($transport->callsFor('EntityTag', 'save'))->toHaveCount(1);
});

it('does not call withTags when tags are empty', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'a@b.com'));
    $job->handle($client);

    expect($transport->callsFor('EntityTag', 'save'))->toHaveCount(0);
});

// ---------------------------------------------------------------------------
// handle — groups
// ---------------------------------------------------------------------------

it('calls addToGroups when groups are non-empty', function (): void {
    $transport = new TestTransport();
    // upsertByEmail path
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);
    // addToGroups: Group.get → returns 'Newsletter'
    $transport->addResponse('Group', 'get', [['id' => 3, 'title' => 'Newsletter']], 1);
    // GroupContact.save
    $transport->addResponse('GroupContact', 'save', [], 0);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(email: 'a@b.com', groups: ['Newsletter']));
    $job->handle($client);

    expect($transport->callsFor('GroupContact', 'save'))->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// handle — extraFields / custom fields
// ---------------------------------------------------------------------------

it('calls setCustomFields for dotted extraFields keys', function (): void {
    $transport = new TestTransport();
    // upsertByEmail path
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);
    // setCustomFields → CustomFieldResolver.resolve → CustomField.get
    $transport->addResponse('CustomField', 'get', [['id' => 99, 'name' => 'volunteer_status']], 1);
    // setCustomFields → Contact.update
    $transport->addResponse('Contact', 'update', [['id' => 1]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(
        email: 'a@b.com',
        extraFields: ['Wolontariat.volunteer_status' => 'active'],
    ));
    $job->handle($client);

    expect($transport->callsFor('CustomField', 'get'))->toHaveCount(1);
});

it('ignores extraFields keys without a dot separator', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Contact', 'get', [], 0);
    $transport->addResponse('Contact', 'create', [['id' => 1]], 1);

    $client = new CiviCrmClient($transport);
    $job = new SyncContactJob(new ContactInput(
        email: 'a@b.com',
        extraFields: ['no_dot_key' => 'val'],
    ));
    $job->handle($client);

    expect($transport->callsFor('CustomField', 'get'))->toHaveCount(0);
});
