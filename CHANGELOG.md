# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.6.0] — 2026-06-10

### Added
- `OptionGroupDef` DTO — declares a CiviCRM option group together with the values it
  should contain; parsed from the `optionGroups` mapping section in YAML
- `optionGroups` section in `SchemaDefinition` and `SchemaApplier` — nested dict syntax
  (`groupName → list<{name, label?}>`); the option group itself is created via
  `OptionGroup.create` if it does not exist, then each value is ensured via the existing
  `OptionValue` get-or-create path; dry-run and idempotency are fully supported
- `resources/schema/example.yaml` updated with an `optionGroups` example

### Fixed
- `optionGroups` section in YAML was silently ignored by `SchemaDefinition::fromArray()`

## [0.5.0] — 2026-06-10

### Added
- `CustomFieldDef::$optionGroup` — optional `string|null` field that references an existing
  CiviCRM option group by name instead of defining inline `optionValues`; mutually exclusive
  with `optionValues` (throws `ValidationException` when both are set); parsed from the
  `optionGroup` key in YAML; `SchemaApplier` passes it to CiviCRM as `option_group_id:name`
- `optionGroup` documented in `resources/schema/example.yaml` and README YAML-keys reference

## [0.4.0] — 2026-06-09

### Added
- `ContactTypeDef` DTO — declares a CiviCRM contact sub-type with required `name` and
  `parentName` (base type: `Individual`, `Organization`, or `Household`) and optional `label`
- `contactTypes[]` section in `SchemaDefinition` and `SchemaApplier` — idempotent
  get-or-create of `ContactType` entities via `civicrm:apply-schema`
- `ContactInput::$contactSubType` — optional field passed as `contact_sub_type` in
  `toCiviValues()`; supported by `fromArray()` / `toArray()` round-trip and `SyncContactJob`

## [0.3.0] — 2026-06-09

### Added
- `civicrm:apply-schema {file?} {--dry-run}` artisan command — applies a YAML schema file
  to the configured CiviCRM instance via idempotent get-or-create; supports dry-run mode that
  prints what would be created without issuing any write calls; prints a summary table with
  Status / Type / Name columns
- `SchemaDefinition` DTO — parses and validates a YAML schema array; sections:
  `customGroups[]`, `tags[]`, `activityTypes[]`, `relationshipTypes[]`, `optionValues[]`,
  `groups[]`; all sections optional; throws `ValidationException` with a human-readable
  message identifying the bad section/entry when the shape is invalid
- `SchemaApplier` service — applies a `SchemaDefinition` via `entity()` calls; builds a
  `SchemaApplyReport` that distinguishes created / existing / wouldCreate entries
- `SchemaApplyReport` — immutable report DTO; `toTable()` method returns rows for Artisan's
  `Command::table()` helper
- Sub-DTOs: `CustomGroupDef`, `CustomFieldDef` (with inline `optionValues`),
  `RelationshipTypeDef`, `OptionValueDef`
- `resources/schema/example.yaml` — annotated example schema with a Volunteer Data custom
  group, relationship types, tags, activity type, and group
- `civicrm.schema_path` config key (`CIVICRM_SCHEMA_PATH` env) — default path for the
  schema file when the `{file}` argument is omitted

## [0.2.0] — 2026-06-08

### Added
- Transactional outbox — `OutboxEntry` Eloquent model, `OutboxRepository` with `push()` /
  `pushSyncContact()` / `pushCreateActivity()` / `reserveBatch()` / `markDone()` / `markFailed()`;
  `push()` deliberately opens no transaction of its own so callers can include it in their domain
  `DB::transaction()` for atomicity
- `civicrm:outbox:work` artisan command — drains pending outbox entries synchronously;
  supports `--limit` and `--max-attempts`; exponential backoff (capped at 3600 s);
  `ValidationException` and `AuthenticationException` (forward-compat guard) cause immediate
  permanent failure; idempotent and overlap-safe via row-level locking in `reserveBatch()`
- `create_civicrm_outbox_table` migration stub — publishable via
  `vendor:publish --tag=civicrm-laravel-migrations`; table name configurable via
  `civicrm.outbox.table`; columns include `dedupe_key` (nullable unique), `status`
  (pending/processing/done/failed), `attempts`, `available_at`, `last_error`
- `ContactInput::toArray()` — lossless inverse of `fromArray()`, used by outbox serialisation

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
