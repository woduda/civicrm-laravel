# Contributing

## Setup

```bash
composer install
```

## Quality gates

Run all checks at once:

```bash
composer qa
```

Individual commands:

```bash
composer cs          # fix code style (Pint)
composer cs:check    # check code style (no changes)
composer phpstan     # static analysis (Larastan, level max)
composer rector      # upgrade/quality rules dry-run
composer test        # run tests (Pest)
```

All gates must be green before opening a pull request.

## Commit convention

This project follows [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>
```

Common types: `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `ci`.

Examples:

```
feat(jobs): add idempotent CiviCrmApiJob
fix(middleware): handle missing webhook signature header
chore: update dependencies
```

Breaking changes must include `BREAKING CHANGE:` in the commit footer.
