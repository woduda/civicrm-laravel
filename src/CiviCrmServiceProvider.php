<?php

declare(strict_types=1);

namespace CiviCrm\Laravel;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CiviCrmServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('civicrm-laravel');
    }
}
