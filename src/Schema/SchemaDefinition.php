<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Parsed representation of a CiviCRM YAML schema file.
 *
 * All sections are optional; omitting a section yields an empty list.
 */
final readonly class SchemaDefinition
{
    /**
     * @param list<CustomGroupDef>      $customGroups
     * @param list<string>              $tags
     * @param list<string>              $activityTypes
     * @param list<RelationshipTypeDef> $relationshipTypes
     * @param list<OptionValueDef>      $optionValues
     * @param list<string>              $groups
     * @param list<ContactTypeDef>      $contactTypes
     * @param list<OptionGroupDef>      $optionGroups
     */
    public function __construct(
        public array $customGroups = [],
        public array $tags = [],
        public array $activityTypes = [],
        public array $relationshipTypes = [],
        public array $optionValues = [],
        public array $groups = [],
        public array $contactTypes = [],
        public array $optionGroups = [],
    ) {}

    /**
     * @param array<mixed, mixed> $data Parsed YAML array
     * @throws ValidationException When the array shape is invalid
     */
    public static function fromArray(array $data): self
    {
        return new self(
            customGroups: self::parseCustomGroups($data),
            tags: self::parseStringList($data, 'tags'),
            activityTypes: self::parseStringList($data, 'activityTypes'),
            relationshipTypes: self::parseRelationshipTypes($data),
            optionValues: self::parseOptionValues($data),
            groups: self::parseStringList($data, 'groups'),
            contactTypes: self::parseContactTypes($data),
            optionGroups: self::parseOptionGroups($data),
        );
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<CustomGroupDef>
     */
    private static function parseCustomGroups(array $data): array
    {
        if (!array_key_exists('customGroups', $data)) {
            return [];
        }

        if (!is_array($data['customGroups'])) {
            throw new ValidationException('Schema section "customGroups" must be a list.');
        }

        $result = [];
        foreach ($data['customGroups'] as $i => $entry) {
            if (!is_array($entry)) {
                throw new ValidationException(
                    sprintf('Schema "customGroups[%s]" must be an object/mapping.', (string) $i),
                );
            }
            $result[] = CustomGroupDef::fromArray($entry);
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<RelationshipTypeDef>
     */
    private static function parseRelationshipTypes(array $data): array
    {
        if (!array_key_exists('relationshipTypes', $data)) {
            return [];
        }

        if (!is_array($data['relationshipTypes'])) {
            throw new ValidationException('Schema section "relationshipTypes" must be a list.');
        }

        $result = [];
        foreach ($data['relationshipTypes'] as $i => $entry) {
            if (!is_array($entry)) {
                throw new ValidationException(
                    sprintf('Schema "relationshipTypes[%s]" must be an object/mapping.', (string) $i),
                );
            }
            $result[] = RelationshipTypeDef::fromArray($entry);
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<OptionValueDef>
     */
    private static function parseOptionValues(array $data): array
    {
        if (!array_key_exists('optionValues', $data)) {
            return [];
        }

        if (!is_array($data['optionValues'])) {
            throw new ValidationException('Schema section "optionValues" must be a list.');
        }

        $result = [];
        foreach ($data['optionValues'] as $i => $entry) {
            if (!is_array($entry)) {
                throw new ValidationException(
                    sprintf('Schema "optionValues[%s]" must be an object/mapping.', (string) $i),
                );
            }
            $result[] = OptionValueDef::fromArray($entry);
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<ContactTypeDef>
     */
    private static function parseContactTypes(array $data): array
    {
        if (!array_key_exists('contactTypes', $data)) {
            return [];
        }

        if (!is_array($data['contactTypes'])) {
            throw new ValidationException('Schema section "contactTypes" must be a list.');
        }

        $result = [];
        foreach ($data['contactTypes'] as $i => $entry) {
            if (!is_array($entry)) {
                throw new ValidationException(
                    sprintf('Schema "contactTypes[%s]" must be an object/mapping.', (string) $i),
                );
            }
            $result[] = ContactTypeDef::fromArray($entry);
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<OptionGroupDef>
     */
    private static function parseOptionGroups(array $data): array
    {
        if (!array_key_exists('optionGroups', $data)) {
            return [];
        }

        if (!is_array($data['optionGroups'])) {
            throw new ValidationException('Schema section "optionGroups" must be a mapping of group name to value list.');
        }

        $result = [];
        foreach ($data['optionGroups'] as $groupName => $groupData) {
            if (!is_string($groupName) || $groupName === '') {
                throw new ValidationException('optionGroups: each key must be a non-empty string group name.');
            }

            if (!is_array($groupData)) {
                throw new ValidationException(
                    sprintf('optionGroups["%s"] must be an object/mapping with at least a "title" key.', $groupName),
                );
            }

            if (!isset($groupData['title']) || !is_string($groupData['title']) || $groupData['title'] === '') {
                throw new ValidationException(
                    sprintf('optionGroups["%s"] is missing required field "title".', $groupName),
                );
            }

            $entries = $groupData['values'] ?? [];

            if (!is_array($entries)) {
                throw new ValidationException(
                    sprintf('optionGroups["%s"].values must be a list of option value mappings.', $groupName),
                );
            }

            $values = [];
            foreach ($entries as $i => $entry) {
                if (!is_array($entry)) {
                    throw new ValidationException(
                        sprintf('optionGroups["%s"].values[%s] must be an object/mapping.', $groupName, (string) $i),
                    );
                }
                $values[] = OptionValueDef::fromArray(array_merge(['optionGroup' => $groupName], $entry));
            }

            $result[] = new OptionGroupDef(name: $groupName, title: $groupData['title'], values: $values);
        }

        return $result;
    }

    /**
     * @param array<mixed, mixed> $data
     * @return list<string>
     */
    private static function parseStringList(array $data, string $section): array
    {
        if (!array_key_exists($section, $data)) {
            return [];
        }

        if (!is_array($data[$section])) {
            throw new ValidationException(
                sprintf('Schema section "%s" must be a list of strings.', $section),
            );
        }

        $result = [];
        foreach ($data[$section] as $i => $value) {
            if (!is_string($value) || $value === '') {
                throw new ValidationException(
                    sprintf('Schema "%s[%s]" must be a non-empty string.', $section, (string) $i),
                );
            }
            $result[] = $value;
        }

        return $result;
    }
}
