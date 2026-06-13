# CLAUDE.md — civicrm-laravel

## Project

Thin, idiomatic Laravel adapter around `woduda/civicrm-php` (PSR-18 CiviCRM APIv4
client). Provides: container bindings, a `CiviCrm` facade, queueable idempotent jobs,
an optional transactional outbox, artisan commands (apply-schema, test-connection),
webhook verification middleware, and a `CiviCrm::fake()` test double.
Depends on woduda/civicrm-php ^0.7.

Current release: **0.7.0**. Schema sections supported by `civicrm:apply-schema`:
`customGroups`, `relationshipTypes`, `tags`, `activityTypes`, `optionGroups`,
`optionValues`, `groups`, `contactTypes`.

## Stack & standards

- PHP >= 8.3 (target 8.4, use 8.4 features where they improve clarity)
- Laravel 11, 12 and 13 (illuminate/\* ^11|^12|^13)
- PSR-4 autoloading, PER-CS 2.0 code style (Pint)
- Conventional Commits for all commits
- Package scaffolding via spatie/laravel-package-tools (configure provider through
  the fluent Package API, not hand-rolled boot/register boilerplate)

## Non-negotiable code rules

- `declare(strict_types=1);` in EVERY php file
- Value objects / DTOs / payloads: `final readonly class`
  (NOTE: Eloquent models, jobs, commands, providers cannot be readonly — that's fine;
  the readonly rule applies to DTOs and value objects only)
- No state mutation in builders/DTOs — return new instances
- No `mixed` without an explicit, documented reason
- Full parameter and return types everywhere; no untyped properties
- Enums instead of string/int constants
- Never throw \Exception/\RuntimeException directly — reuse the typed hierarchy from
  woduda/civicrm-php (CiviCrm\Exception\*) or add a package-local exception extending it
- NEVER call env() outside config/\*.php — read everything through config()
- English identifiers, English PHPDoc, English commit messages

## Laravel conventions (idiomatic)

- Jobs implement ShouldQueue; idempotent jobs also implement ShouldBeUnique
  (uniqueId() derived from the business key, not random)
- Bind CiviCrmClient as a singleton; resolve the PSR-18 client from the container so
  tests can swap it (default: php-http/discovery finds the host app's Guzzle)
- Publish config with vendor:publish tag "civicrm-config"; publish migration with
  tag "civicrm-migrations"
- Register a middleware alias "civicrm.webhook"
- Schedule-friendly commands (no interactive prompts when --no-interaction)
- Backward-compatible: guard optional core features with class_exists()/interface_exists()
  so the package still installs against woduda/civicrm-php 0.7 even before retry/webhook
  land in the core lib

## Quality gates (MUST pass before every commit)

- `composer cs` -> Pint (PER-CS): 0 changes needed
- `composer phpstan` -> Larastan/PHPStan level max: 0 errors
- `composer rector` -> dry-run clean
- `composer test` -> all green
  Combined: `composer qa` runs all of the above.
  NO Infection in this package. Coverage is reported (testdox) but not a hard CI gate;
  aim high on new code, especially commands, jobs, middleware.

## Testing

- Pest 3 + orchestra/testbench
- tests/TestCase.php extends Orchestra\Testbench\TestCase, registers the package
  provider via getPackageProviders() and aliases via getPackageAliases()
- Unit tests: NO network. Bind a mock Psr\Http\Client\ClientInterface
  (php-http/mock-client) so the real CiviCrmClient runs against canned responses,
  OR mock CiviCrmClient via Mockery when testing the Laravel glue itself
- Feature tests (Testbench): boot the provider, hit commands via $this->artisan(),
  dispatch jobs, exercise the webhook middleware against a defined test route
- Use array cache + sync/array queue drivers in tests unless asserting queue behaviour
  (then Queue::fake / Bus::fake)

## Documentation

- PHPDoc on every public method, including @throws
- README with runnable examples; docs/ optional
- CHANGELOG.md (Keep a Changelog format)

## What this package is NOT

- Not the CRM logic (that's woduda/civicrm-php)
- Not a Twill or Filament package
- Not an ORM
- No foundation-specific business rules in src/ (keep it generic for Packagist)
