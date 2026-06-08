# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- `ContactInput` DTO (`CiviCrm\Laravel\Data\ContactInput`) — serialisable payload for
  contact sync jobs; requires at least one of `email` or `externalIdentifier`; validates
  at construction and throws `Woduda\CiviCRM\Exception\ValidationException` when both are absent;
  `toCiviValues()` returns the standard CiviCRM contact fields for create/update calls
- `SyncContactJob` (`CiviCrm\Laravel\Jobs\SyncContactJob`) — queueable, idempotent contact
  upsert; implements `ShouldQueue` + `ShouldBeUnique` (lock key = `externalIdentifier` or
  `"email:{email}"`); 5 attempts with exponential backoff `[10, 30, 60, 120, 300]` seconds;
  uses `externalIdentifier` path (manual get→create/update, non-atomic) or `upsertByEmail`
  path; applies tags, groups, and `GroupName.field`-keyed custom fields after upsert; reads
  `civicrm.queue.connection` and `civicrm.queue.queue` from config
- `CreateActivityJob` (`CiviCrm\Laravel\Jobs\CreateActivityJob`) — queueable, `ShouldBeUnique`
  activity logger; 3 attempts; lock key = caller-supplied `dedupeKey` (recommended for
  at-least-once safety) or SHA-1 of `contactId + activityType + JSON(params)`; delegates to
  `ActivityApi::logForContact()`; note: CiviCRM does not deduplicate activities natively —
  full deduplication via persistent `dedupe_key` is planned for LPR #3

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
