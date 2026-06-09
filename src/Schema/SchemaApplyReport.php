<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Schema;

/**
 * Result of a schema apply operation, listing what was created, already existed, or would be created.
 *
 * Entry format: "Type:identifier", e.g. "Tag:volunteer" or "CustomField:VolunteerData.volunteer_status".
 */
final readonly class SchemaApplyReport
{
    /**
     * @param list<string> $created    Entities created during this run
     * @param list<string> $existing   Entities that already existed
     * @param list<string> $wouldCreate Entities that would be created (dry-run only)
     */
    public function __construct(
        public array $created = [],
        public array $existing = [],
        public array $wouldCreate = [],
    ) {}

    public function createdCount(): int
    {
        return count($this->created);
    }

    public function existingCount(): int
    {
        return count($this->existing);
    }

    public function wouldCreateCount(): int
    {
        return count($this->wouldCreate);
    }

    /**
     * Returns rows suitable for Artisan Command::table(['Status', 'Type', 'Name'], ...).
     *
     * @return list<array{string, string, string}>
     */
    public function toTable(): array
    {
        $rows = [];

        foreach ($this->existing as $entry) {
            [$type, $name] = $this->split($entry);
            $rows[] = ['Existing', $type, $name];
        }

        foreach ($this->created as $entry) {
            [$type, $name] = $this->split($entry);
            $rows[] = ['Created', $type, $name];
        }

        foreach ($this->wouldCreate as $entry) {
            [$type, $name] = $this->split($entry);
            $rows[] = ['Would Create', $type, $name];
        }

        return $rows;
    }

    /** @return array{string, string} */
    private function split(string $entry): array
    {
        $pos = strpos($entry, ':');

        if ($pos === false) {
            return [$entry, ''];
        }

        return [substr($entry, 0, $pos), substr($entry, $pos + 1)];
    }
}
