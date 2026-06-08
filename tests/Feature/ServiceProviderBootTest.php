<?php

declare(strict_types=1);

use CiviCrm\Laravel\CiviCrmServiceProvider;

it('boots without error', function (): void {
    expect(true)->toBeTrue();
});

it('registers the service provider', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(CiviCrmServiceProvider::class);
});
