# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] — 2026-06-08

### Added
- `config/civicrm.php` — publishable config with keys for `base_url`, `api_token`, `site_key`,
  `timeout`, `verify_tls`, `retry`, `queue`, `webhook`, and `outbox`
- `CiviCrmServiceProvider` — binds `Woduda\CiviCRM\Config` and `Woduda\CiviCRM\CiviCrmClient`
  as singletons; registers `civicrm` container alias; respects a pre-bound
  `Psr\Http\Client\ClientInterface` for injection / testing
- `CiviCrm` facade — IDE-friendly `@method` tags for all entity accessors and `raw()`
- `civicrm:test-connection` artisan command — pings CiviCRM with a `Contact.get limit=1`
  request and prints the base URL and latency; exits non-zero on HTTP or transport errors
- `ConfigurationException` — package-local exception implementing `CivicrmException` thrown when
  `base_url` or `api_token` are missing from config
