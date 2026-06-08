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

// Full — match by externalIdentifier, with tags, groups, and custom fields
dispatch(new SyncContactJob(new ContactInput(
    externalIdentifier: 'crm-alice-001',
    email:              'alice@example.org',
    firstName:          'Alice',
    lastName:           'Smith',
    tags:               ['Donor', 'VIP'],
    groups:             ['Newsletter', 'Events'],
    extraFields:        ['Wolontariat.volunteer_status' => 'active'],
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
