<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Console;

use Illuminate\Console\Command;
use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Exception\ApiErrorException;

/**
 * Verifies connectivity to the configured CiviCRM instance.
 */
final class TestConnectionCommand extends Command
{
    protected $signature = 'civicrm:test-connection';

    protected $description = 'Verify connectivity to the configured CiviCRM instance';

    /**
     * @throws ApiErrorException        On CiviCRM HTTP error responses
     * @throws ClientExceptionInterface On transport-level errors
     */
    public function handle(CiviCrmClient $client): int
    {
        $rawUrl  = config('civicrm.base_url');
        $baseUrl = is_string($rawUrl) ? $rawUrl : '(not set)';
        $start   = hrtime(true);

        try {
            $client->raw('Contact', 'get', ['limit' => 1]);
        } catch (ApiErrorException $e) {
            $this->error(sprintf('API error [%d]: %s', $e->getCode(), $e->getMessage()));

            return self::FAILURE;
        } catch (ClientExceptionInterface $e) {
            $this->error('Transport error: ' . $e->getMessage());

            return self::FAILURE;
        }

        $ms = (int) round((hrtime(true) - $start) / 1_000_000);
        $this->info(sprintf('OK  %s  (%d ms)', $baseUrl, $ms));

        return self::SUCCESS;
    }
}
