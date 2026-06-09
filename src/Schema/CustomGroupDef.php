<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Definition of a CiviCRM custom group and its fields.
 */
final readonly class CustomGroupDef
{
    /**
     * @param list<CustomFieldDef> $fields
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $extends = 'Contact',
        public array $fields = [],
    ) {}

    /**
     * @param array<mixed, mixed> $data
     * @throws ValidationException When required fields are missing or fields list is malformed
     */
    public static function fromArray(array $data): self
    {
        $name = isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
            ? $data['name']
            : throw new ValidationException('customGroups entry is missing required string field "name".');

        $title = isset($data['title']) && is_string($data['title']) && $data['title'] !== ''
            ? $data['title']
            : throw new ValidationException('customGroups entry is missing required string field "title".');

        $extends = isset($data['extends']) && is_string($data['extends']) && $data['extends'] !== ''
            ? $data['extends']
            : 'Contact';

        $rawFields = isset($data['fields']) && is_array($data['fields']) ? $data['fields'] : [];

        $fields = [];
        foreach ($rawFields as $fieldData) {
            if (!is_array($fieldData)) {
                throw new ValidationException(
                    sprintf('customGroups["%s"] has a field entry that is not an object/mapping.', $name),
                );
            }
            $fields[] = CustomFieldDef::fromArray($fieldData);
        }

        return new self(
            name: $name,
            title: $title,
            extends: $extends,
            fields: $fields,
        );
    }
}
