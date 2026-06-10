<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

/**
 * Definition of a CiviCRM option group together with the values it should contain.
 */
final readonly class OptionGroupDef
{
    /**
     * @param list<OptionValueDef> $values
     */
    public function __construct(
        public string $name,
        public array $values = [],
    ) {}
}
