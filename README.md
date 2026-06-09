# civicrm-laravel

Thin, idiomatic Laravel adapter around [`woduda/civicrm-php`](https://github.com/woduda/civicrm-php) —
a PSR-18 CiviCRM APIv4 client. Provides container bindings, a `CiviCrm` facade, queueable idempotent
jobs, an optional transactional outbox, artisan commands, webhook verification middleware, and a
`CiviCrm::fake()` test double.

**Requires** PHP ≥ 8.3, Laravel 11 / 12 / 13.

## Quickstart

### 1. Install

```bash
composer require woduda/civicrm-laravel
```

Laravel's package auto-discovery registers the service provider and `CiviCrm` facade automatically.

### 2. Configure `.env`

```dotenv
CIVICRM_BASE_URL=https://your-site.example.org/civicrm/ajax/api4/
CIVICRM_API_TOKEN=your_civicrm_api_key
# CIVICRM_SITE_KEY=optional_site_key
```

Publish the config file to customise queue, webhook, and retry settings:

```bash
php artisan vendor:publish --tag=civicrm-config
```

### 3. Verify the connection

```bash
php artisan civicrm:test-connection
# OK  https://your-site.example.org/civicrm/ajax/api4/  (42 ms)
```

### 4. Use the facade

```php
use CiviCrm\Laravel\Facades\CiviCrm;
use Woduda\CiviCRM\Query\GetQuery;

$contacts = CiviCrm::contacts()->get(
    GetQuery::new()->where('email', \Woduda\CiviCRM\Query\Operator::Equals, 'alice@example.org')
);

foreach ($contacts as $contact) {
    echo $contact->displayName;
}
```

## Queued Jobs

### SyncContactJob — idempotent contact upsert

Dispatching the job enqueues an upsert that:
1. Looks up the contact by `externalIdentifier` (if provided) or by email via `upsertByEmail`
2. Creates or updates the contact with the provided fields
3. Applies tags, groups, and custom fields in post-upsert steps

```php
use CiviCrm\Laravel\Data\ContactInput;
use CiviCrm\Laravel\Jobs\SyncContactJob;

// Minimal — match by email
dispatch(new SyncContactJob(ContactInput::fromArray([
    'email'     => 'alice@example.org',
    'firstName' => 'Alice',
    'lastName'  => 'Smith',
])));

// Full — match by externalIdentifier, with tags, groups, custom fields, and contact sub-type
dispatch(new SyncContactJob(new ContactInput(
    externalIdentifier: 'crm-alice-001',
    email:              'alice@example.org',
    firstName:          'Alice',
    lastName:           'Smith',
    tags:               ['Donor', 'VIP'],
    groups:             ['Newsletter', 'Events'],
    extraFields:        ['Wolontariat.volunteer_status' => 'active'],
    contactSubType:     'Volunteer',
)));
```

The job implements `ShouldBeUnique` — duplicate dispatches for the same contact are
de-duplicated at the queue layer (lock key = `externalIdentifier` or `"email:{email}"`).

> **Note:** The `externalIdentifier` path is implemented as a non-atomic
> `Contact.get` + conditional `Contact.create/update`. A concurrent insert between the
> get and the create may produce a duplicate. An atomic `Contact.save` with
> `match=['external_identifier']` is planned for a future core-lib release.

### CreateActivityJob — idempotent activity logger

```php
use CiviCrm\Laravel\Jobs\CreateActivityJob;

// Basic — auto-derived dedup key
dispatch(new CreateActivityJob(
    contactId:    42,
    activityType: 'Phone Call',
    params:       ['subject' => 'Intake call', 'duration' => 15],
));

// With an explicit dedupe key for safe at-least-once retries
dispatch(new CreateActivityJob(
    contactId:    42,
    activityType: 'Phone Call',
    params:       ['subject' => 'Intake call'],
    dedupeKey:    'form-submission-uuid-abc123',
));
```

> **Note:** CiviCRM does not deduplicate activities natively. The `ShouldBeUnique`
> lock prevents concurrent duplicates, but if the lock expires before completion
> a duplicate may be created. Full deduplication via a persistent `dedupe_key` column
> is planned for LPR #3 (transactional outbox).

## Transactional Outbox

The outbox guarantees that CiviCRM side-effects are persisted atomically alongside domain model changes — no distributed transaction required. A drain command picks up and executes pending entries.

### 1. Enable and publish the migration

In `.env` (or `config/civicrm.php`):

```dotenv
CIVICRM_OUTBOX=true
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=civicrm-laravel-migrations
php artisan migrate
```

### 2. Write to the outbox inside a DB transaction

```php
use CiviCrm\Laravel\Data\ContactInput;
use CiviCrm\Laravel\Outbox\OutboxRepository;
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($outbox, $contactInput): void {
    // Save your domain model here …
    $domain->save();

    // … then enqueue the CiviCRM side-effect in the same transaction.
    $outbox->pushSyncContact($contactInput);
});
```

If the transaction rolls back the outbox entry is discarded with it. If it commits, `civicrm:outbox:work` will pick it up.

### 3. Register the drain command in the scheduler

```php
// routes/console.php or App\Console\Kernel::schedule()
$schedule->command('civicrm:outbox:work')->everyMinute();
```

The command is idempotent and safe to overlap — row-level locking in `reserveBatch()` prevents duplicate processing.

### Available push helpers

| Method | Description |
|---|---|
| `pushSyncContact(ContactInput $input)` | Enqueues a contact upsert; dedupe key derived from `externalIdentifier` or `email` |
| `pushCreateActivity(int $contactId, string $type, array $params, ?string $dedupeKey)` | Enqueues an activity; pass an explicit `$dedupeKey` for at-least-once safety |
| `push(string $type, array $payload, ?string $dedupeKey)` | Raw push for custom entry types |

### Drain command options

```
php artisan civicrm:outbox:work [--limit=100] [--max-attempts=5]
```

- `--limit` — maximum entries processed per run (default 100)
- `--max-attempts` — attempts before permanent failure (default 5); exponential backoff capped at 3600 s

`ValidationException` and `AuthenticationException` (when available) cause immediate permanent failure without retry.

## Schema (`civicrm:apply-schema`)

Declare your CiviCRM schema in a YAML file and apply it idempotently with a single command.
Each section is optional; unknown sections are ignored.

### Quick start

Copy `vendor/woduda/civicrm-laravel/resources/schema/example.yaml` to your app, adjust it,
then run:

```bash
# Preview what would be created (no changes made)
php artisan civicrm:apply-schema civicrm-schema.yaml --dry-run

# Apply
php artisan civicrm:apply-schema civicrm-schema.yaml
```

You can set a default path in `.env` so the `{file}` argument is optional:

```dotenv
CIVICRM_SCHEMA_PATH=/absolute/path/to/civicrm-schema.yaml
```

### YAML keys

```yaml
# Custom groups and their fields
customGroups:
  - name: VolunteerData          # machine name (snake_case or CamelCase)
    title: Volunteer Data        # human label
    extends: Contact             # default: Contact
    fields:
      - name: volunteer_status
        label: Volunteer Status
        dataType: String         # String | Integer | Date | Boolean | Memo | Money | Float | Link
        htmlType: Select         # Text | Select | Radio | CheckBox | TextArea | Hidden | …
        optionValues: [applied, active, inactive]   # inline options for Select/Radio
        isRequired: false        # default: false

# Bidirectional relationship types
relationshipTypes:
  - nameAToB: Reports to
    nameBToA: Manages
    labelAToB: Reports to
    labelBToA: Manages
    contactTypeA: Individual     # optional
    contactTypeB: Individual     # optional

# Tag names (used for contact tagging)
tags:
  - volunteer
  - donor

# Activity types (added to the built-in `activity_type` option group)
activityTypes:
  - Materials sent

# Option values in arbitrary option groups
optionValues:
  - optionGroup: event_type
    name: webinar
    label: Webinar               # optional; defaults to name

# Smart groups / mailing lists
groups:
  - Marketing consent

# Contact sub-types (must reference a base type: Individual, Organization, or Household)
contactTypes:
  - name: Volunteer              # machine name
    parentName: Individual       # base type
    label: Volunteer             # optional; defaults to name
```

All entities are created via **get-or-create** — running the command twice is safe.

## Configuration reference

All options live in `config/civicrm.php` after publishing. The most important keys:

| Key | Env variable | Default | Description |
|-----|-------------|---------|-------------|
| `base_url` | `CIVICRM_BASE_URL` | `null` | CiviCRM APIv4 endpoint URL |
| `api_token` | `CIVICRM_API_TOKEN` | `null` | Bearer token / API key |
| `site_key` | `CIVICRM_SITE_KEY` | `null` | Optional site key (sent as `X-Civi-Key` header) |
| `timeout` | `CIVICRM_TIMEOUT` | `30` | Request timeout in seconds (PSR-18 client level) |
| `verify_tls` | `CIVICRM_VERIFY_TLS` | `true` | TLS certificate verification |
| `retry.enabled` | `CIVICRM_RETRY` | `false` | Exponential-backoff retry (requires core ≥ 0.8) |
| `queue.connection` | `CIVICRM_QUEUE_CONNECTION` | `null` | Queue connection for jobs |
| `queue.queue` | `CIVICRM_QUEUE` | `default` | Queue name for jobs |

## License

MIT — see [LICENSE](LICENSE).
