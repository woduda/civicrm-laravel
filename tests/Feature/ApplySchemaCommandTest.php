<?php

declare(strict_types=1);

use CiviCrm\Laravel\Schema\SchemaApplier;
use CiviCrm\Laravel\Tests\Support\TestTransport;
use Illuminate\Support\Facades\Artisan;
use Woduda\CiviCRM\CiviCrmClient;

/**
 * Write a temporary YAML file and return its path.
 * The file is automatically deleted when the test ends.
 */
function writeTempYaml(string $content): string
{
    $path = tempnam(sys_get_temp_dir(), 'civicrm_schema_') . '.yaml';
    file_put_contents($path, $content);

    return $path;
}

function bindTestApplier(TestTransport $transport): void
{
    app()->instance(SchemaApplier::class, new SchemaApplier(new CiviCrmClient($transport)));
}

beforeEach(function (): void {
    config([
        'civicrm.base_url'  => 'https://test.example.com/',
        'civicrm.api_token' => 'test-token',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

it('returns 0 and shows existing in output when all entities exist', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [['id' => 1]], 1);
    bindTestApplier($transport);

    $file = writeTempYaml("tags:\n  - volunteer\n");
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file]);
    @unlink($file);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('Existing');
});

it('returns 0 and shows Created for a new entity', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [], 0);
    $transport->addResponse('Tag', 'create', [['id' => 2]], 1);
    bindTestApplier($transport);

    $file = writeTempYaml("tags:\n  - new-tag\n");
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file]);
    @unlink($file);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('Created');
});

// ──────────────────────────────────────────────────────────────────────────────
// Dry-run
// ──────────────────────────────────────────────────────────────────────────────

it('dry-run returns 0 and shows Would Create without calling create', function (): void {
    $transport = new TestTransport();
    $transport->addResponse('Tag', 'get', [], 0);
    bindTestApplier($transport);

    $file = writeTempYaml("tags:\n  - candidate\n");
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file, '--dry-run' => true]);
    @unlink($file);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('Would Create')
        ->and($transport->callsFor('Tag', 'create'))->toHaveCount(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Error cases
// ──────────────────────────────────────────────────────────────────────────────

it('returns 1 when the schema file does not exist', function (): void {
    $code = Artisan::call('civicrm:apply-schema', ['file' => '/nonexistent/path/schema.yaml']);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('not found');
});

it('returns 1 for malformed YAML', function (): void {
    $file = writeTempYaml("tags:\n  - valid\nbad: [\n");
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file]);
    @unlink($file);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('YAML parse error');
});

it('returns 1 when yaml file is empty (null parse result)', function (): void {
    $file = writeTempYaml('');
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file]);
    @unlink($file);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('mapping');
});

it('returns 1 when schema validation fails', function (): void {
    $transport = new TestTransport();
    bindTestApplier($transport);

    $file = writeTempYaml("customGroups:\n  - title: Missing name\n");
    $code = Artisan::call('civicrm:apply-schema', ['file' => $file]);
    @unlink($file);

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('Schema validation error');
});

// ──────────────────────────────────────────────────────────────────────────────
// Example YAML
// ──────────────────────────────────────────────────────────────────────────────

it('example.yaml parses and passes dry-run without errors', function (): void {
    $transport = new TestTransport();
    // All get calls return empty → all entities are wouldCreate
    bindTestApplier($transport);

    $exampleFile = __DIR__ . '/../../resources/schema/example.yaml';
    $code        = Artisan::call('civicrm:apply-schema', ['file' => $exampleFile, '--dry-run' => true]);

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('Would Create');
});
