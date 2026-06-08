<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

final class CiviCrm extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'civicrm';
    }
}
