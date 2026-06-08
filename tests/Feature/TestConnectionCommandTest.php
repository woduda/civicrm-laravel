<?php

declare(strict_types=1);

use Http\Mock\Client as MockClient;
use Illuminate\Support\Facades\Artisan;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;

function civiSuccessResponse(): Response
{
    return new Response(200, [], json_encode([
        'version' => 4,
        'count'   => 1,
        'values'  => [['id' => 1]],
    ], JSON_THROW_ON_ERROR));
}

function civiErrorResponse(int $status): Response
{
    return new Response($status, [], json_encode([
        'error_message' => 'Unauthorized',
        'error_code'    => $status,
    ], JSON_THROW_ON_ERROR));
}

beforeEach(function (): void {
    config([
        'civicrm.base_url'  => 'https://example.org/civicrm/ajax/api4/',
        'civicrm.api_token' => 'test-token',
    ]);
});

it('returns exit code 0 on a successful connection', function (): void {
    $mock = new MockClient();
    $mock->addResponse(civiSuccessResponse());
    app()->bind(ClientInterface::class, static fn() => $mock);

    $code = Artisan::call('civicrm:test-connection');

    expect($code)->toBe(0)
        ->and(Artisan::output())->toContain('OK');
});

it('returns non-zero exit code on HTTP 401', function (): void {
    $mock = new MockClient();
    $mock->addResponse(civiErrorResponse(401));
    app()->bind(ClientInterface::class, static fn() => $mock);

    $code = Artisan::call('civicrm:test-connection');

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('API error');
});

it('returns non-zero exit code on HTTP 500', function (): void {
    $mock = new MockClient();
    $mock->addResponse(civiErrorResponse(500));
    app()->bind(ClientInterface::class, static fn() => $mock);

    $code = Artisan::call('civicrm:test-connection');

    expect($code)->toBe(1)
        ->and(Artisan::output())->toContain('API error');
});
