<?php

declare(strict_types=1);

use CiviCrm\Laravel\Schema\SchemaApplier;
use CiviCrm\Laravel\Schema\SchemaDefinition;
use CiviCrm\Laravel\Tests\Support\TestTransport;
use Woduda\CiviCRM\CiviCrmClient;

function makeApplier(TestTransport $transport): SchemaApplier
{
    return new SchemaApplier(new CiviCrmClient($transport));
}

// ──────────────────────────────────────────────────────────────────────────────
// Tags
// ──────────────────────────────────────────────────────────────────────────────

it('reports existing when tag already exists', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [['id' => 1, 'name' => 'volunteer']], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['tags' => ['volunteer']]));

    expect($report->existing)->toBe(['Tag:volunteer'])
        ->and($report->created)->toBe([])
        ->and($transport->callsFor('Tag', 'create'))->toHaveCount(0);
});

it('creates tag and reports created when tag does not exist', function (): void {
    $transport = new TestTransport();
    // get → empty (not found); create → returns new record
    $transport->addResponse('Tag', 'get', [], 0);
    $transport->addResponse('Tag', 'create', [['id' => 2]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['tags' => ['donor']]));

    expect($report->created)->toBe(['Tag:donor'])
        ->and($report->existing)->toBe([])
        ->and($transport->callsFor('Tag', 'create'))->toHaveCount(1);
});

it('dry-run does not call create for a missing tag', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [], 0);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['tags' => ['candidate']]), dryRun: true);

    expect($report->wouldCreate)->toBe(['Tag:candidate'])
        ->and($report->created)->toBe([])
        ->and($transport->callsFor('Tag', 'create'))->toHaveCount(0);
});

it('is idempotent — second apply sees only existing entries', function (): void {
    $transport = new TestTransport();
    // Both tags already exist on the second apply
    $transport->addResponse('Tag', 'get', [['id' => 1]], 1);
    $transport->addResponse('Tag', 'get', [['id' => 2]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['tags' => ['volunteer', 'donor']]));

    expect($report->existing)->toHaveCount(2)
        ->and($report->created)->toBe([])
        ->and($transport->callsFor('Tag', 'create'))->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Groups
// ──────────────────────────────────────────────────────────────────────────────

it('creates a group when it does not exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Group', 'get', [], 0);
    $transport->addResponse('Group', 'create', [['id' => 3]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['groups' => ['Marketing consent']]));

    expect($report->created)->toBe(['Group:Marketing consent'])
        ->and($transport->callsFor('Group', 'create'))->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Activity types
// ──────────────────────────────────────────────────────────────────────────────

it('creates an activity type when it does not exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionValue', 'get', [], 0);
    $transport->addResponse('OptionValue', 'create', [['id' => 10]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['activityTypes' => ['Materials sent']]));

    expect($report->created)->toBe(['ActivityType:Materials sent'])
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(1);
});

it('reports existing activity type when it already exists', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionValue', 'get', [['id' => 10]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray(['activityTypes' => ['Materials sent']]));

    expect($report->existing)->toBe(['ActivityType:Materials sent'])
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Relationship types
// ──────────────────────────────────────────────────────────────────────────────

it('creates a relationship type when it does not exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('RelationshipType', 'get', [], 0);
    $transport->addResponse('RelationshipType', 'create', [['id' => 5]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'relationshipTypes' => [
            [
                'nameAToB'     => 'Reports to',
                'nameBToA'     => 'Manages',
                'labelAToB'    => 'Reports to',
                'labelBToA'    => 'Manages',
                'contactTypeA' => 'Individual',
                'contactTypeB' => 'Individual',
            ],
        ],
    ]));

    expect($report->created)->toBe(['RelationshipType:Reports to'])
        ->and($transport->callsFor('RelationshipType', 'create'))->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Custom groups + fields
// ──────────────────────────────────────────────────────────────────────────────

it('creates custom group and field when neither exists', function (): void {
    $transport = new TestTransport();
    // CustomGroup.get → not found
    $transport->addResponse('CustomGroup', 'get', [], 0);
    // CustomGroup.create → returns id=7
    $transport->addResponse('CustomGroup', 'create', [['id' => 7]], 1);
    // CustomField.get → not found
    $transport->addResponse('CustomField', 'get', [], 0);
    // CustomField.create → returns id=20
    $transport->addResponse('CustomField', 'create', [['id' => 20]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'customGroups' => [[
            'name'    => 'VolunteerData',
            'title'   => 'Volunteer Data',
            'extends' => 'Contact',
            'fields'  => [
                ['name' => 'start_date', 'label' => 'Start Date', 'dataType' => 'Date', 'htmlType' => 'Select'],
            ],
        ]],
    ]));

    expect($report->created)->toBe(['CustomGroup:VolunteerData', 'CustomField:VolunteerData.start_date'])
        ->and($transport->callsFor('CustomGroup', 'create'))->toHaveCount(1)
        ->and($transport->callsFor('CustomField', 'create'))->toHaveCount(1);
});

it('skips creating field when custom group already exists and field also exists', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('CustomGroup', 'get', [['id' => 7]], 1);
    $transport->addResponse('CustomField', 'get', [['id' => 20]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'customGroups' => [[
            'name'   => 'VolunteerData',
            'title'  => 'Volunteer Data',
            'fields' => [
                ['name' => 'start_date', 'label' => 'Start Date', 'dataType' => 'Date', 'htmlType' => 'Select'],
            ],
        ]],
    ]));

    expect($report->existing)->toBe(['CustomGroup:VolunteerData', 'CustomField:VolunteerData.start_date'])
        ->and($report->created)->toBe([])
        ->and($transport->callsFor('CustomGroup', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('CustomField', 'create'))->toHaveCount(0);
});

it('creates field when group exists but field does not', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('CustomGroup', 'get', [['id' => 7]], 1);
    // Field.get → not found
    $transport->addResponse('CustomField', 'get', [], 0);
    $transport->addResponse('CustomField', 'create', [['id' => 21]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'customGroups' => [[
            'name'   => 'VolunteerData',
            'title'  => 'Volunteer Data',
            'fields' => [
                ['name' => 'start_date', 'label' => 'Start Date', 'dataType' => 'Date', 'htmlType' => 'Select'],
            ],
        ]],
    ]));

    expect($report->existing)->toBe(['CustomGroup:VolunteerData'])
        ->and($report->created)->toBe(['CustomField:VolunteerData.start_date'])
        ->and($transport->callsFor('CustomField', 'create'))->toHaveCount(1);
});

it('dry-run marks group and all fields as wouldCreate when group does not exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('CustomGroup', 'get', [], 0);

    $applier = makeApplier($transport);
    $report  = $applier->apply(
        SchemaDefinition::fromArray([
            'customGroups' => [[
                'name'   => 'VolunteerData',
                'title'  => 'Volunteer Data',
                'fields' => [
                    ['name' => 'start_date', 'label' => 'Start Date', 'dataType' => 'Date', 'htmlType' => 'Select'],
                ],
            ]],
        ]),
        dryRun: true,
    );

    expect($report->wouldCreate)->toBe(['CustomGroup:VolunteerData', 'CustomField:VolunteerData.start_date'])
        ->and($transport->callsFor('CustomGroup', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('CustomField', 'create'))->toHaveCount(0);
});

it('passes option_group_id:name when field has optionGroup', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('CustomGroup', 'get', [['id' => 7]], 1);
    $transport->addResponse('CustomField', 'get', [], 0);
    $transport->addResponse('CustomField', 'create', [['id' => 22]], 1);

    $applier = makeApplier($transport);
    $applier->apply(SchemaDefinition::fromArray([
        'customGroups' => [[
            'name'    => 'VolunteerData',
            'title'   => 'Volunteer Data',
            'extends' => 'Contact',
            'fields'  => [[
                'name'        => 'event_category',
                'label'       => 'Event Category',
                'dataType'    => 'String',
                'htmlType'    => 'Select',
                'optionGroup' => 'event_type',
            ]],
        ]],
    ]));

    $createCalls = $transport->callsFor('CustomField', 'create');
    /** @var array<string, mixed> $values */
    $values = $createCalls[0]['params']['values'];
    expect($createCalls)->toHaveCount(1)
        ->and($values['option_group_id:name'])->toBe('event_type')
        ->and(array_key_exists('option_values', $values))->toBeFalse();
});

// ──────────────────────────────────────────────────────────────────────────────
// Option groups
// ──────────────────────────────────────────────────────────────────────────────

it('creates option group and its values when neither exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionGroup', 'get', [], 0);
    $transport->addResponse('OptionGroup', 'create', [['id' => 5]], 1);
    $transport->addResponse('OptionValue', 'get', [], 0);
    $transport->addResponse('OptionValue', 'create', [['id' => 11]], 1);
    $transport->addResponse('OptionValue', 'get', [], 0);
    $transport->addResponse('OptionValue', 'create', [['id' => 12]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'optionGroups' => [
            'event_type' => [
                'title'  => 'Event Type',
                'values' => [
                    ['name' => 'webinar', 'label' => 'Webinar'],
                    ['name' => 'conference'],
                ],
            ],
        ],
    ]));

    $groupCreateCalls = $transport->callsFor('OptionGroup', 'create');
    /** @var array<string, mixed> $groupValues */
    $groupValues = $groupCreateCalls[0]['params']['values'];

    expect($report->created)->toBe([
        'OptionGroup:event_type',
        'OptionValue:event_type.webinar',
        'OptionValue:event_type.conference',
    ])
        ->and($groupCreateCalls)->toHaveCount(1)
        ->and($groupValues['name'])->toBe('event_type')
        ->and($groupValues['title'])->toBe('Event Type')
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(2);
});

it('reports existing when option group and values already exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionGroup', 'get', [['id' => 5]], 1);
    $transport->addResponse('OptionValue', 'get', [['id' => 11]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'optionGroups' => ['event_type' => ['title' => 'Event Type', 'values' => [['name' => 'webinar']]]],
    ]));

    expect($report->existing)->toBe(['OptionGroup:event_type', 'OptionValue:event_type.webinar'])
        ->and($report->created)->toBe([])
        ->and($transport->callsFor('OptionGroup', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(0);
});

it('creates value when option group exists but value does not', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionGroup', 'get', [['id' => 5]], 1);
    $transport->addResponse('OptionValue', 'get', [], 0);
    $transport->addResponse('OptionValue', 'create', [['id' => 13]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'optionGroups' => ['event_type' => ['title' => 'Event Type', 'values' => [['name' => 'webinar']]]],
    ]));

    expect($report->existing)->toBe(['OptionGroup:event_type'])
        ->and($report->created)->toBe(['OptionValue:event_type.webinar'])
        ->and($transport->callsFor('OptionGroup', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(1);
});

it('dry-run marks option group and values as wouldCreate when absent', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('OptionGroup', 'get', [], 0);
    $transport->addResponse('OptionValue', 'get', [], 0);

    $applier = makeApplier($transport);
    $report  = $applier->apply(
        SchemaDefinition::fromArray([
            'optionGroups' => ['event_type' => ['title' => 'Event Type', 'values' => [['name' => 'webinar']]]],
        ]),
        dryRun: true,
    );

    expect($report->wouldCreate)->toBe(['OptionGroup:event_type', 'OptionValue:event_type.webinar'])
        ->and($transport->callsFor('OptionGroup', 'create'))->toHaveCount(0)
        ->and($transport->callsFor('OptionValue', 'create'))->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Contact types
// ──────────────────────────────────────────────────────────────────────────────

it('creates a contact type when it does not exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('ContactType', 'get', [], 0);
    $transport->addResponse('ContactType', 'create', [['id' => 9]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'contactTypes' => [
            ['name' => 'Volunteer', 'parentName' => 'Individual'],
        ],
    ]));

    expect($report->created)->toBe(['ContactType:Volunteer'])
        ->and($transport->callsFor('ContactType', 'create'))->toHaveCount(1);
});

it('reports existing when contact type already exists', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('ContactType', 'get', [['id' => 9]], 1);

    $applier = makeApplier($transport);
    $report  = $applier->apply(SchemaDefinition::fromArray([
        'contactTypes' => [
            ['name' => 'Volunteer', 'parentName' => 'Individual'],
        ],
    ]));

    expect($report->existing)->toBe(['ContactType:Volunteer'])
        ->and($transport->callsFor('ContactType', 'create'))->toHaveCount(0);
});

it('dry-run does not call create for a missing contact type', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('ContactType', 'get', [], 0);

    $applier = makeApplier($transport);
    $report  = $applier->apply(
        SchemaDefinition::fromArray([
            'contactTypes' => [
                ['name' => 'Foundation', 'parentName' => 'Organization', 'label' => 'Foundation'],
            ],
        ]),
        dryRun: true,
    );

    expect($report->wouldCreate)->toBe(['ContactType:Foundation'])
        ->and($transport->callsFor('ContactType', 'create'))->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Report helpers
// ──────────────────────────────────────────────────────────────────────────────

it('toTable returns rows with correct status labels', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [['id' => 1]], 1);  // existing
    $transport->addResponse('Tag', 'get', [], 0);             // missing → would create (dry-run)

    $applier = makeApplier($transport);
    $report  = $applier->apply(
        SchemaDefinition::fromArray(['tags' => ['volunteer', 'donor']]),
        dryRun: true,
    );

    $table = $report->toTable();

    expect($table)->toHaveCount(2)
        ->and($table[0][0])->toBe('Existing')
        ->and($table[0][2])->toBe('volunteer')
        ->and($table[1][0])->toBe('Would Create')
        ->and($table[1][2])->toBe('donor');
});
