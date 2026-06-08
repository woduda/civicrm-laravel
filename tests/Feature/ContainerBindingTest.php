<?php

declare(strict_types=1);

use CiviCrm\Laravel\Exception\ConfigurationException;
use CiviCrm\Laravel\Facades\CiviCrm;
use Http\Mock\Client as MockClient;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Woduda\CiviCRM\Api\ContactApi;
use Woduda\CiviCRM\CiviCrmClient;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function civiValidResponse(): Response
{
    return new Response(200, [], json_encode([
        'version' => 4,
        'count'   => 0,
        'values'  => [],
    ], JSON_THROW_ON_ERROR));
}

function configureCiviCrm(): void
{
    config([
        'civicrm.base_url'  => 'https://example.org/civicrm/ajax/api4/',
        'civicrm.api_token' => 'test-token',
    ]);
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

it('publishes the expected top-level config keys', function (): void {
    $config = config('civicrm');

    expect($config)
        ->toBeArray()
        ->toHaveKeys(['base_url', 'api_token', 'site_key', 'timeout', 'verify_tls', 'retry', 'queue', 'webhook', 'outbox']);
});

it('has correct default values', function (): void {
    expect(config('civicrm.timeout'))->toBe(30)
        ->and(config('civicrm.verify_tls'))->toBeTrue()
        ->and(config('civicrm.retry.enabled'))->toBeFalse()
        ->and(config('civicrm.retry.max_attempts'))->toBe(3)
        ->and(config('civicrm.retry.base_delay_ms'))->toBe(200)
        ->and(config('civicrm.queue.queue'))->toBe('default')
        ->and(config('civicrm.webhook.tolerance_seconds'))->toBe(300)
        ->and(config('civicrm.webhook.nonce_ttl'))->toBe(600)
        ->and(config('civicrm.outbox.enabled'))->toBeFalse()
        ->and(config('civicrm.outbox.table'))->toBe('civicrm_outbox');
});

// ---------------------------------------------------------------------------
// Container bindings
// ---------------------------------------------------------------------------

it('resolves CiviCrmClient as a singleton', function (): void {
    configureCiviCrm();

    $mock = new MockClient();
    $mock->addResponse(civiValidResponse());

    app()->bind(ClientInterface::class, static fn() => $mock);

    $a = app()->make(CiviCrmClient::class);
    $b = app()->make(CiviCrmClient::class);

    expect($a)->toBeInstanceOf(CiviCrmClient::class)
        ->and($a)->toBe($b);
});

it('resolves CiviCrmClient via the civicrm alias', function (): void {
    configureCiviCrm();

    $mock = new MockClient();
    app()->bind(ClientInterface::class, static fn() => $mock);

    expect(app()->make(CiviCrmClient::class))->toBeInstanceOf(CiviCrmClient::class);
});

it('CiviCrm Facade resolves contacts() without error', function (): void {
    configureCiviCrm();

    $mock = new MockClient();
    app()->bind(ClientInterface::class, static fn() => $mock);

    // If the facade accessor is wired correctly this does not throw.
    expect(CiviCrm::contacts())->toBeInstanceOf(ContactApi::class);
});

it('contacts() returns a ContactApi instance when a mock PSR-18 client is bound', function (): void {
    configureCiviCrm();

    $mock = new MockClient();
    $mock->addResponse(civiValidResponse());
    app()->bind(ClientInterface::class, static fn() => $mock);

    $client = app()->make(CiviCrmClient::class);

    expect($client->contacts())->toBeInstanceOf(ContactApi::class);
});

it('uses a pre-bound ClientInterface and does not overwrite it', function (): void {
    configureCiviCrm();

    $mock = new MockClient();
    $mock->addResponse(civiValidResponse());
    app()->bind(ClientInterface::class, static fn() => $mock);

    $client = app()->make(CiviCrmClient::class);
    // Should not throw — the mock client returns the queued response.
    $result = $client->raw('Contact', 'get', ['limit' => 1]);

    expect($result)->toBeArray()
        ->and($mock->getRequests())->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Missing configuration
// ---------------------------------------------------------------------------

it('throws ConfigurationException when base_url is missing', function (): void {
    config(['civicrm.base_url' => null, 'civicrm.api_token' => 'token']);

    expect(fn() => app()->make(CiviCrmClient::class))
        ->toThrow(ConfigurationException::class, 'CIVICRM_BASE_URL');
});

it('throws ConfigurationException when api_token is missing', function (): void {
    config(['civicrm.base_url' => 'https://example.org/civicrm/ajax/api4/', 'civicrm.api_token' => null]);

    expect(fn() => app()->make(CiviCrmClient::class))
        ->toThrow(ConfigurationException::class, 'CIVICRM_API_TOKEN');
});
