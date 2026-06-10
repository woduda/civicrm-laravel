<?php

declare(strict_types=1);

use CiviCrm\Laravel\Schema\ContactTypeDef;
use CiviCrm\Laravel\Schema\CustomGroupDef;
use CiviCrm\Laravel\Schema\OptionGroupDef;
use CiviCrm\Laravel\Schema\RelationshipTypeDef;
use CiviCrm\Laravel\Schema\SchemaDefinition;
use Woduda\CiviCRM\Exception\ValidationException;

it('parses a full valid array into a SchemaDefinition', function (): void {
    $schema = SchemaDefinition::fromArray([
        'tags'              => ['volunteer', 'donor'],
        'groups'            => ['Marketing consent'],
        'activityTypes'     => ['Materials sent'],
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
        'customGroups' => [
            [
                'name'    => 'VolunteerData',
                'title'   => 'Volunteer Data',
                'extends' => 'Contact',
                'fields'  => [
                    [
                        'name'         => 'volunteer_status',
                        'label'        => 'Volunteer Status',
                        'dataType'     => 'String',
                        'htmlType'     => 'Select',
                        'optionValues' => ['applied', 'active'],
                        'isRequired'   => true,
                    ],
                ],
            ],
        ],
    ]);

    expect($schema->tags)->toBe(['volunteer', 'donor'])
        ->and($schema->groups)->toBe(['Marketing consent'])
        ->and($schema->activityTypes)->toBe(['Materials sent'])
        ->and($schema->relationshipTypes)->toHaveCount(1)
        ->and($schema->relationshipTypes[0])->toBeInstanceOf(RelationshipTypeDef::class)
        ->and($schema->relationshipTypes[0]->nameAToB)->toBe('Reports to')
        ->and($schema->relationshipTypes[0]->contactTypeA)->toBe('Individual')
        ->and($schema->customGroups)->toHaveCount(1)
        ->and($schema->customGroups[0])->toBeInstanceOf(CustomGroupDef::class)
        ->and($schema->customGroups[0]->name)->toBe('VolunteerData')
        ->and($schema->customGroups[0]->fields[0]->name)->toBe('volunteer_status')
        ->and($schema->customGroups[0]->fields[0]->optionValues)->toBe(['applied', 'active'])
        ->and($schema->customGroups[0]->fields[0]->isRequired)->toBeTrue();
});

it('handles all sections being omitted', function (): void {
    $schema = SchemaDefinition::fromArray([]);

    expect($schema->customGroups)->toBe([])
        ->and($schema->tags)->toBe([])
        ->and($schema->activityTypes)->toBe([])
        ->and($schema->relationshipTypes)->toBe([])
        ->and($schema->optionValues)->toBe([])
        ->and($schema->groups)->toBe([])
        ->and($schema->contactTypes)->toBe([]);
});

it('throws ValidationException when customGroups entry is missing name', function (): void {
    SchemaDefinition::fromArray([
        'customGroups' => [['title' => 'Volunteer Data']],
    ]);
})->throws(ValidationException::class, 'name');

it('throws ValidationException when customGroups entry is missing title', function (): void {
    SchemaDefinition::fromArray([
        'customGroups' => [['name' => 'VolunteerData']],
    ]);
})->throws(ValidationException::class, 'title');

it('throws ValidationException when customGroups section is not a list', function (): void {
    SchemaDefinition::fromArray(['customGroups' => 'not-a-list']);
})->throws(ValidationException::class, 'customGroups');

it('throws ValidationException when relationshipTypes entry is missing nameAToB', function (): void {
    SchemaDefinition::fromArray([
        'relationshipTypes' => [
            ['nameBToA' => 'Manages', 'labelAToB' => 'Reports to', 'labelBToA' => 'Manages'],
        ],
    ]);
})->throws(ValidationException::class, 'nameAToB');

it('throws ValidationException when tags section is not a list', function (): void {
    SchemaDefinition::fromArray(['tags' => 'not-a-list']);
})->throws(ValidationException::class, 'tags');

it('throws ValidationException when a tag entry is not a string', function (): void {
    SchemaDefinition::fromArray(['tags' => [123]]);
})->throws(ValidationException::class);

it('throws ValidationException when customField is missing label', function (): void {
    SchemaDefinition::fromArray([
        'customGroups' => [
            [
                'name'   => 'VolunteerData',
                'title'  => 'Volunteer Data',
                'fields' => [['name' => 'volunteer_status', 'dataType' => 'String', 'htmlType' => 'Select']],
            ],
        ],
    ]);
})->throws(ValidationException::class, 'label');

it('parses customField with optionGroup', function (): void {
    $schema = SchemaDefinition::fromArray([
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
    ]);

    expect($schema->customGroups[0]->fields[0]->optionGroup)->toBe('event_type')
        ->and($schema->customGroups[0]->fields[0]->optionValues)->toBe([]);
});

it('throws ValidationException when customField has both optionValues and optionGroup', function (): void {
    SchemaDefinition::fromArray([
        'customGroups' => [[
            'name'    => 'VolunteerData',
            'title'   => 'Volunteer Data',
            'extends' => 'Contact',
            'fields'  => [[
                'name'         => 'event_category',
                'label'        => 'Event Category',
                'dataType'     => 'String',
                'htmlType'     => 'Select',
                'optionValues' => ['a', 'b'],
                'optionGroup'  => 'event_type',
            ]],
        ]],
    ]);
})->throws(ValidationException::class, 'optionValues');

it('parses contactTypes section', function (): void {
    $schema = SchemaDefinition::fromArray([
        'contactTypes' => [
            ['name' => 'Volunteer', 'parentName' => 'Individual', 'label' => 'Volunteer'],
            ['name' => 'Foundation', 'parentName' => 'Organization'],
        ],
    ]);

    expect($schema->contactTypes)->toHaveCount(2)
        ->and($schema->contactTypes[0])->toBeInstanceOf(ContactTypeDef::class)
        ->and($schema->contactTypes[0]->name)->toBe('Volunteer')
        ->and($schema->contactTypes[0]->parentName)->toBe('Individual')
        ->and($schema->contactTypes[0]->label)->toBe('Volunteer')
        ->and($schema->contactTypes[1]->name)->toBe('Foundation')
        ->and($schema->contactTypes[1]->label)->toBeNull();
});

it('throws ValidationException when contactTypes entry is missing name', function (): void {
    SchemaDefinition::fromArray([
        'contactTypes' => [['parentName' => 'Individual']],
    ]);
})->throws(ValidationException::class, 'name');

it('throws ValidationException when contactTypes entry is missing parentName', function (): void {
    SchemaDefinition::fromArray([
        'contactTypes' => [['name' => 'Volunteer']],
    ]);
})->throws(ValidationException::class, 'parentName');

it('throws ValidationException when contactTypes section is not a list', function (): void {
    SchemaDefinition::fromArray(['contactTypes' => 'not-a-list']);
})->throws(ValidationException::class, 'contactTypes');

it('parses optionGroups section', function (): void {
    $schema = SchemaDefinition::fromArray([
        'optionGroups' => [
            'event_type' => [
                ['name' => 'webinar', 'label' => 'Webinar'],
                ['name' => 'conference'],
            ],
        ],
    ]);

    expect($schema->optionGroups)->toHaveCount(1)
        ->and($schema->optionGroups[0])->toBeInstanceOf(OptionGroupDef::class)
        ->and($schema->optionGroups[0]->name)->toBe('event_type')
        ->and($schema->optionGroups[0]->values)->toHaveCount(2)
        ->and($schema->optionGroups[0]->values[0]->optionGroup)->toBe('event_type')
        ->and($schema->optionGroups[0]->values[0]->name)->toBe('webinar')
        ->and($schema->optionGroups[0]->values[0]->label)->toBe('Webinar')
        ->and($schema->optionGroups[0]->values[1]->name)->toBe('conference')
        ->and($schema->optionGroups[0]->values[1]->label)->toBeNull();
});

it('parses multiple groups in optionGroups section', function (): void {
    $schema = SchemaDefinition::fromArray([
        'optionGroups' => [
            'group_a' => [['name' => 'val1']],
            'group_b' => [['name' => 'val2'], ['name' => 'val3', 'label' => 'Three']],
        ],
    ]);

    expect($schema->optionGroups)->toHaveCount(2)
        ->and($schema->optionGroups[0]->name)->toBe('group_a')
        ->and($schema->optionGroups[1]->name)->toBe('group_b')
        ->and($schema->optionGroups[1]->values)->toHaveCount(2);
});

it('returns empty optionGroups when section is absent', function (): void {
    expect(SchemaDefinition::fromArray([])->optionGroups)->toBe([]);
});

it('throws ValidationException when optionGroups is not a mapping', function (): void {
    SchemaDefinition::fromArray(['optionGroups' => 'not-a-mapping']);
})->throws(ValidationException::class, 'optionGroups');

it('throws ValidationException when optionGroups entry is not a list', function (): void {
    SchemaDefinition::fromArray(['optionGroups' => ['event_type' => 'not-a-list']]);
})->throws(ValidationException::class, 'optionGroups["event_type"]');

it('throws ValidationException when optionGroups value entry is missing name', function (): void {
    SchemaDefinition::fromArray(['optionGroups' => ['event_type' => [['label' => 'Webinar']]]]);
})->throws(ValidationException::class, 'name');

it('parses optionValues section', function (): void {
    $schema = SchemaDefinition::fromArray([
        'optionValues' => [
            ['optionGroup' => 'event_type', 'name' => 'webinar', 'label' => 'Webinar'],
        ],
    ]);

    expect($schema->optionValues)->toHaveCount(1)
        ->and($schema->optionValues[0]->optionGroup)->toBe('event_type')
        ->and($schema->optionValues[0]->name)->toBe('webinar')
        ->and($schema->optionValues[0]->label)->toBe('Webinar');
});
