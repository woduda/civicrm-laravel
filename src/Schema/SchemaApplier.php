<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

/**
 * Applies a SchemaDefinition to a CiviCRM instance via idempotent get-or-create operations.
 */
final readonly class SchemaApplier
{
    public function __construct(private CiviCrmClient $client) {}

    /**
     * Applies (or simulates applying) the schema definition.
     *
     * In dry-run mode no create/update calls are issued; the report's wouldCreate list
     * describes what would have been created.
     */
    public function apply(SchemaDefinition $schema, bool $dryRun = false): SchemaApplyReport
    {
        $created     = [];
        $existing    = [];
        $wouldCreate = [];

        foreach ($schema->tags as $tag) {
            $this->processEntity(
                label: "Tag:{$tag}",
                exists: $this->entityExists('Tag', 'name', $tag),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($tag): void {
                    $this->client->entity('Tag')->create(['name' => $tag]);
                },
            );
        }

        foreach ($schema->groups as $group) {
            $this->processEntity(
                label: "Group:{$group}",
                exists: $this->entityExists('Group', 'title', $group),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($group): void {
                    $this->client->entity('Group')->create(['title' => $group]);
                },
            );
        }

        foreach ($schema->activityTypes as $name) {
            $this->processEntity(
                label: "ActivityType:{$name}",
                exists: $this->optionValueExists('activity_type', $name),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($name): void {
                    $this->client->entity('OptionValue')->create([
                        'option_group_id:name' => 'activity_type',
                        'name'                 => $name,
                        'label'                => $name,
                    ]);
                },
            );
        }

        foreach ($schema->optionValues as $def) {
            $this->processEntity(
                label: "OptionValue:{$def->optionGroup}.{$def->name}",
                exists: $this->optionValueExists($def->optionGroup, $def->name),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($def): void {
                    $this->client->entity('OptionValue')->create([
                        'option_group_id:name' => $def->optionGroup,
                        'name'                 => $def->name,
                        'label'                => $def->label ?? $def->name,
                    ]);
                },
            );
        }

        foreach ($schema->relationshipTypes as $def) {
            $this->processEntity(
                label: "RelationshipType:{$def->nameAToB}",
                exists: $this->entityExists('RelationshipType', 'name_a_b', $def->nameAToB),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($def): void {
                    $values = [
                        'name_a_b'  => $def->nameAToB,
                        'name_b_a'  => $def->nameBToA,
                        'label_a_b' => $def->labelAToB,
                        'label_b_a' => $def->labelBToA,
                    ];
                    if ($def->contactTypeA !== null) {
                        $values['contact_type_a'] = $def->contactTypeA;
                    }
                    if ($def->contactTypeB !== null) {
                        $values['contact_type_b'] = $def->contactTypeB;
                    }
                    $this->client->entity('RelationshipType')->create($values);
                },
            );
        }

        foreach ($schema->contactTypes as $def) {
            $this->processEntity(
                label: "ContactType:{$def->name}",
                exists: $this->entityExists('ContactType', 'name', $def->name),
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
                create: function () use ($def): void {
                    $this->client->entity('ContactType')->create([
                        'name'           => $def->name,
                        'label'          => $def->label ?? $def->name,
                        'parent_id:name' => $def->parentName,
                    ]);
                },
            );
        }

        foreach ($schema->customGroups as $groupDef) {
            $groupId = $this->resolveCustomGroup(
                def: $groupDef,
                dryRun: $dryRun,
                created: $created,
                existing: $existing,
                wouldCreate: $wouldCreate,
            );

            foreach ($groupDef->fields as $fieldDef) {
                $fieldLabel = "CustomField:{$groupDef->name}.{$fieldDef->name}";

                if ($groupId === null) {
                    // Parent group doesn't exist yet in dry-run; all fields would be created.
                    $wouldCreate[] = $fieldLabel;
                    continue;
                }

                $this->processEntity(
                    label: $fieldLabel,
                    exists: $this->customFieldExists($groupId, $fieldDef->name),
                    dryRun: $dryRun,
                    created: $created,
                    existing: $existing,
                    wouldCreate: $wouldCreate,
                    create: function () use ($groupId, $fieldDef): void {
                        $values = [
                            'custom_group_id' => $groupId,
                            'name'            => $fieldDef->name,
                            'label'           => $fieldDef->label,
                            'data_type'       => $fieldDef->dataType,
                            'html_type'       => $fieldDef->htmlType,
                            'is_required'     => $fieldDef->isRequired,
                        ];
                        if ($fieldDef->optionValues !== []) {
                            $values['option_values'] = $fieldDef->optionValues;
                        }
                        $this->client->entity('CustomField')->create($values);
                    },
                );
            }
        }

        return new SchemaApplyReport(
            created: $created,
            existing: $existing,
            wouldCreate: $wouldCreate,
        );
    }

    /**
     * Resolves (or simulates creating) a custom group, returning its id or null in dry-run when absent.
     *
     * @param list<string> $created
     * @param list<string> $existing
     * @param list<string> $wouldCreate
     */
    private function resolveCustomGroup(
        CustomGroupDef $def,
        bool $dryRun,
        array &$created,
        array &$existing,
        array &$wouldCreate,
    ): ?int {
        $results = $this->client->entity('CustomGroup')->get(
            GetQuery::new()->where('name', Operator::Equals, $def->name)->select('id'),
        );

        $label = "CustomGroup:{$def->name}";

        if ($results !== []) {
            $existing[] = $label;
            /** @var array{id: int} $row */
            $row = $results[0];

            return $row['id'];
        }

        if ($dryRun) {
            $wouldCreate[] = $label;

            return null;
        }

        $result = $this->client->entity('CustomGroup')->create([
            'name'    => $def->name,
            'title'   => $def->title,
            'extends' => $def->extends,
        ]);
        $created[] = $label;

        /** @var array{id: int} $row */
        $row = $result[0];

        return $row['id'];
    }

    /**
     * @param list<string>     $created
     * @param list<string>     $existing
     * @param list<string>     $wouldCreate
     * @param callable(): void $create
     */
    private function processEntity(
        string $label,
        bool $exists,
        bool $dryRun,
        array &$created,
        array &$existing,
        array &$wouldCreate,
        callable $create,
    ): void {
        if ($exists) {
            $existing[] = $label;

            return;
        }

        if ($dryRun) {
            $wouldCreate[] = $label;

            return;
        }

        ($create)();
        $created[] = $label;
    }

    private function entityExists(string $entity, string $field, string $value): bool
    {
        return $this->client->entity($entity)->get(
            GetQuery::new()->where($field, Operator::Equals, $value)->select('id'),
        ) !== [];
    }

    private function optionValueExists(string $optionGroupName, string $name): bool
    {
        return $this->client->entity('OptionValue')->get(
            GetQuery::new()
                ->where('option_group_id:name', Operator::Equals, $optionGroupName)
                ->where('name', Operator::Equals, $name)
                ->select('id'),
        ) !== [];
    }

    private function customFieldExists(int $customGroupId, string $name): bool
    {
        return $this->client->entity('CustomField')->get(
            GetQuery::new()
                ->where('custom_group_id', Operator::Equals, $customGroupId)
                ->where('name', Operator::Equals, $name)
                ->select('id'),
        ) !== [];
    }
}
