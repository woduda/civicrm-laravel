<?php

declare(strict_types=1);

namespace CiviCrm\Laravel;

use CiviCrm\Laravel\Console\TestConnectionCommand;
use CiviCrm\Laravel\Exception\ConfigurationException;
use Illuminate\Contracts\Foundation\Application;
use Psr\Http\Client\ClientInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Http\Transport;

class CiviCrmServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('civicrm-laravel')
            ->hasConfigFile('civicrm')
            ->hasCommands([TestConnectionCommand::class]);
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
    }

    private function buildClient(Application $app): CiviCrmClient
    {
        $config = $app->make(Config::class);

        if ($app->bound(ClientInterface::class)) {
            /** @var ClientInterface $psrClient */
            $psrClient = $app->make(ClientInterface::class);

            return new CiviCrmClient(new Transport(new Client($config, $psrClient)));
        }

        // Retry support pending woduda/civicrm-php PR #11 (ExponentialBackoff not in v0.7).
        // When that lands: if class_exists(ExponentialBackoff::class) && config('civicrm.retry.enabled')
        // inject the strategy into the transport before returning.

        return CiviCrmClient::create($config);
    }
}
