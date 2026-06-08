<?php

declare(strict_types=1);

uses(CiviCrm\Laravel\Tests\TestCase::class)->in('Feature');
uses(CiviCrm\Laravel\Tests\Support\OutboxTestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class)->in('Outbox');
