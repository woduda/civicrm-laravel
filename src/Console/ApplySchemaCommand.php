<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Console;

use CiviCrm\Laravel\Schema\SchemaApplier;
use CiviCrm\Laravel\Schema\SchemaDefinition;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Woduda\CiviCRM\Exception\ValidationException;

/**
 * Applies a YAML schema file to the configured CiviCRM instance (idempotent get-or-create).
 */
final class ApplySchemaCommand extends Command
{
    protected $signature = 'civicrm:apply-schema
        {file? : Path to the YAML schema file}
        {--dry-run : Show what would be created without making any changes}';

    protected $description = 'Apply a YAML CiviCRM schema definition (idempotent)';

    /**
     * @throws \Throwable
     */
    public function handle(SchemaApplier $applier): int
    {
        $file = $this->resolveFile();

        if (!file_exists($file)) {
            $this->error(sprintf('Schema file not found: %s', $file));

            return self::FAILURE;
        }

        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $e) {
            $this->error('YAML parse error: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (!is_array($data)) {
            $this->error('Schema file must contain a YAML mapping at the top level.');

            return self::FAILURE;
        }

        try {
            $schema = SchemaDefinition::fromArray($data);
        } catch (ValidationException $e) {
            $this->error('Schema validation error: ' . $e->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $applier->apply($schema, $dryRun);

        if ($report->toTable() !== []) {
            $this->table(['Status', 'Type', 'Name'], $report->toTable());
        }

        if ($dryRun) {
            $this->line(sprintf(
                'Dry run: %d existing, %d would be created.',
                $report->existingCount(),
                $report->wouldCreateCount(),
            ));
        } else {
            $this->line(sprintf(
                'Done: %d created, %d already existed.',
                $report->createdCount(),
                $report->existingCount(),
            ));
        }

        return self::SUCCESS;
    }

    private function resolveFile(): string
    {
        /** @var string|null $arg */
        $arg = $this->argument('file');
        if (is_string($arg) && $arg !== '') {
            return $arg;
        }

        $configured = config('civicrm.schema_path');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return base_path('civicrm-schema.yaml');
    }
}
