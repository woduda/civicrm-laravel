<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Definition of a single CiviCRM custom field.
 */
final readonly class CustomFieldDef
{
    /**
     * @param list<string> $optionValues Inline option values (used when htmlType is Select/Radio)
     * @param string|null  $optionGroup  Name of an existing CiviCRM option group to reference instead of inline values
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $dataType,
        public string $htmlType,
        public array $optionValues = [],
        public bool $isRequired = false,
        public ?string $optionGroup = null,
    ) {}

    /**
     * @param array<mixed, mixed> $data
     * @throws ValidationException When required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $name = isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
            ? $data['name']
            : throw new ValidationException('CustomField entry is missing required string field "name".');

        $label = isset($data['label']) && is_string($data['label']) && $data['label'] !== ''
            ? $data['label']
            : throw new ValidationException('CustomField entry is missing required string field "label".');

        $dataType = isset($data['dataType']) && is_string($data['dataType']) && $data['dataType'] !== ''
            ? $data['dataType']
            : throw new ValidationException('CustomField entry is missing required string field "dataType".');

        $htmlType = isset($data['htmlType']) && is_string($data['htmlType']) && $data['htmlType'] !== ''
            ? $data['htmlType']
            : throw new ValidationException('CustomField entry is missing required string field "htmlType".');

        $rawOptions = isset($data['optionValues']) && is_array($data['optionValues'])
            ? $data['optionValues']
            : [];

        $optionValues = [];
        foreach ($rawOptions as $v) {
            if (!is_string($v)) {
                throw new ValidationException('CustomField "optionValues" entries must be strings.');
            }
            $optionValues[] = $v;
        }

        $optionGroup = isset($data['optionGroup']) && is_string($data['optionGroup']) && $data['optionGroup'] !== ''
            ? $data['optionGroup']
            : null;

        if ($optionGroup !== null && $optionValues !== []) {
            throw new ValidationException('CustomField cannot have both "optionValues" and "optionGroup" set.');
        }

        return new self(
            name: $name,
            label: $label,
            dataType: $dataType,
            htmlType: $htmlType,
            optionValues: $optionValues,
            isRequired: (bool) ($data['isRequired'] ?? false),
            optionGroup: $optionGroup,
        );
    }
}
