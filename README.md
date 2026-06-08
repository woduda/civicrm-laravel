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
