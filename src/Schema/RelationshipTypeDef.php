<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Definition of a CiviCRM relationship type (bidirectional).
 */
final readonly class RelationshipTypeDef
{
    public function __construct(
        public string $nameAToB,
        public string $nameBToA,
        public string $labelAToB,
        public string $labelBToA,
        public ?string $contactTypeA = null,
        public ?string $contactTypeB = null,
    ) {}

    /**
     * @param array<mixed, mixed> $data
     * @throws ValidationException When required fields are missing
     */
    public static function fromArray(array $data): self
    {
        $nameAToB = isset($data['nameAToB']) && is_string($data['nameAToB']) && $data['nameAToB'] !== ''
            ? $data['nameAToB']
            : throw new ValidationException('relationshipTypes entry is missing required string field "nameAToB".');

        $nameBToA = isset($data['nameBToA']) && is_string($data['nameBToA']) && $data['nameBToA'] !== ''
            ? $data['nameBToA']
            : throw new ValidationException('relationshipTypes entry is missing required string field "nameBToA".');

        $labelAToB = isset($data['labelAToB']) && is_string($data['labelAToB']) && $data['labelAToB'] !== ''
            ? $data['labelAToB']
            : throw new ValidationException('relationshipTypes entry is missing required string field "labelAToB".');

        $labelBToA = isset($data['labelBToA']) && is_string($data['labelBToA']) && $data['labelBToA'] !== ''
            ? $data['labelBToA']
            : throw new ValidationException('relationshipTypes entry is missing required string field "labelBToA".');

        return new self(
            nameAToB: $nameAToB,
            nameBToA: $nameBToA,
            labelAToB: $labelAToB,
            labelBToA: $labelBToA,
            contactTypeA: isset($data['contactTypeA']) && is_string($data['contactTypeA'])
                ? $data['contactTypeA']
                : null,
            contactTypeB: isset($data['contactTypeB']) && is_string($data['contactTypeB'])
                ? $data['contactTypeB']
                : null,
        );
    }
}
