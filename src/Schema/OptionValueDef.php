<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Definition of a standalone CiviCRM option value in a named option group.
 */
final readonly class OptionValueDef
{
    public function __construct(
        public string $optionGroup,
        public string $name,
        public ?string $label = null,
        public ?string $value = null,
    ) {}

    /**
     * @param array<mixed, mixed> $data
     * @throws ValidationException When required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $optionGroup = isset($data['optionGroup']) && is_string($data['optionGroup']) && $data['optionGroup'] !== ''
            ? $data['optionGroup']
            : throw new ValidationException('optionValues entry is missing required string field "optionGroup".');

        $name = isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
            ? $data['name']
            : throw new ValidationException('optionValues entry is missing required string field "name".');

        return new self(
            optionGroup: $optionGroup,
            name: $name,
            label: isset($data['label']) && is_string($data['label']) ? $data['label'] : null,
            value: isset($data['value']) && is_string($data['value']) ? $data['value'] : null,
        );
    }
}
