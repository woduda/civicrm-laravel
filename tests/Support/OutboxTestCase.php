<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Tests\Support;

use CiviCrm\Laravel\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;

/**
 * Base test case for outbox tests.
 *
 * Configures an in-memory SQLite database and creates the outbox table
 * inline (no .stub file resolution required; compatible with RefreshDatabase).
 */
abstract class OutboxTestCase extends TestCase
{
    /**
     * @param Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $app['config']->set('civicrm.outbox.enabled', true);
        $app['config']->set('civicrm.outbox.table', 'civicrm_outbox');
        $app['config']->set('civicrm.base_url', 'https://example.org/civicrm/ajax/api4/');
        $app['config']->set('civicrm.api_token', 'test-token');
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('civicrm_outbox', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->char('uuid', 36)->unique();
            $table->string('type');
            $table->json('payload');
            $table->string('dedupe_key')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });
    }
}
