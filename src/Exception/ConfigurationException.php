<?php

declare(strict_types=1);

namespace CiviCrm\Laravel\Exception;

use Woduda\CiviCRM\Exception\CivicrmException;

/**
 * Thrown when required CiviCRM configuration values are missing or invalid.
 */
final class ConfigurationException extends \RuntimeException implements CivicrmException
{
    /**
     * Creates an exception for a missing .env / config key.
     *
     * @param string $envKey The environment variable name that must be set
     */
    public static function missingKey(string $envKey): self
    {
        return new self(
            "CiviCRM configuration error: '{$envKey}' is not set. Define it in your .env file.",
        );
    }
}
