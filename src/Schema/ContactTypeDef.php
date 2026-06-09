<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Definition of a CiviCRM contact sub-type.
 */
final readonly class ContactTypeDef
{
    public function __construct(
        public string $name,
        public string $parentName,
        public ?string $label = null,
    ) {}

    /**
     * @param array<mixed, mixed> $data
     * @throws ValidationException When required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $name = isset($data['name']) && is_string($data['name']) && $data['name'] !== ''
            ? $data['name']
            : throw new ValidationException('contactTypes entry is missing required string field "name".');

        $parentName = isset($data['parentName']) && is_string($data['parentName']) && $data['parentName'] !== ''
            ? $data['parentName']
            : throw new ValidationException('contactTypes entry is missing required string field "parentName".');

        return new self(
            name: $name,
            parentName: $parentName,
            label: isset($data['label']) && is_string($data['label']) ? $data['label'] : null,
        );
    }
}
