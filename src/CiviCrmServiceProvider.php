<?php

declare(strict_types=1);

namespace CiviCrm\Laravel;

use CiviCrm\Laravel\Console\ApplySchemaCommand;
use CiviCrm\Laravel\Console\ProcessOutboxCommand;
use CiviCrm\Laravel\Console\TestConnectionCommand;
use CiviCrm\Laravel\Exception\ConfigurationException;
use CiviCrm\Laravel\Outbox\OutboxRepository;
use CiviCrm\Laravel\Schema\SchemaApplier;
use Illuminate\Contracts\Foundation\Application;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Http\Transport;
use Woduda\CiviCRM\Retry\ExponentialBackoff;

class CiviCrmServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('civicrm-laravel')
            ->hasConfigFile('civicrm')
            ->hasMigration('create_civicrm_outbox_table')
            ->hasCommands([TestConnectionCommand::class, ApplySchemaCommand::class]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(Config::class, static function (): Config {
            $rawUrl = config('civicrm.base_url');
            if (!is_string($rawUrl) || $rawUrl === '') {
                throw ConfigurationException::missingKey('CIVICRM_BASE_URL');
            }

            $rawToken = config('civicrm.api_token');
            if (!is_string($rawToken) || $rawToken === '') {
                throw ConfigurationException::missingKey('CIVICRM_API_TOKEN');
            }

            $headers = [];
            $siteKey = config('civicrm.site_key');
            if (is_string($siteKey) && $siteKey !== '') {
                $headers['X-Civi-Key'] = $siteKey;
            }

            return new Config($rawUrl, $rawToken, $headers);
        });

        $this->app->singleton(CiviCrmClient::class, fn(Application $app): CiviCrmClient => $this->buildClient($app));
        $this->app->alias(CiviCrmClient::class, 'civicrm');
        $this->app->singleton(SchemaApplier::class);
    }

    public function packageBooted(): void
    {
        if (config('civicrm.outbox.enabled')) {
            $this->commands([ProcessOutboxCommand::class]);
        }

        $this->app->singleton(OutboxRepository::class);
    }

    private function buildClient(Application $app): CiviCrmClient
    {
        $config = $app->make(Config::class);

        if ($app->bound(ClientInterface::class)) {
            /** @var ClientInterface $psrClient */
            $psrClient = $app->make(ClientInterface::class);

            return new CiviCrmClient(new Transport(new Client($config, $psrClient)));
        }

        $retry = null;
        if (config('civicrm.retry.enabled')) {
            $maxAttempts = config('civicrm.retry.max_attempts', 3);
            $baseDelayMs = config('civicrm.retry.base_delay_ms', 200);
            $retry = new ExponentialBackoff(
                maxAttempts: is_int($maxAttempts) ? $maxAttempts : 3,
                baseDelayMs: is_int($baseDelayMs) ? $baseDelayMs : 200,
            );
        }

        $logger = $app->bound(LoggerInterface::class)
            ? $app->make(LoggerInterface::class)
            : null;

        return new CiviCrmClient(Transport::createDefault($config, $retry, $logger));
    }
}
