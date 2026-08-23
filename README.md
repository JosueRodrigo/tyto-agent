# Tyto Agent for Laravel

The official Laravel telemetry collector for [Tyto](https://github.com/JosueRodrigo/Tyto). It captures application signals and sends buffered batches to your self-hosted Tyto instance.

## Supported signals

- HTTP requests and authenticated users
- exceptions and source context
- database queries
- queued jobs and scheduled tasks
- Artisan commands
- cache, mail and notifications
- outgoing HTTP requests
- application logs and security audits
- scheduler and custom process heartbeats

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require tyto/agent
php artisan tyto:install
```

The installer publishes `config/tyto.php` and writes the connection values to your `.env`:

```dotenv
TYTO_SERVER_URL=https://tyto.example.com
TYTO_TOKEN=your-project-token
```

Telemetry is sent to `/api/v1/ingest` with `X-Tyto-Token`. Every batch carries an idempotency key so the server can reject duplicate delivery safely.

## Configuration

```dotenv
TYTO_ENABLED=true
TYTO_INGEST_TIMEOUT=2
TYTO_INGEST_BUFFER=500
TYTO_INGEST_ATTEMPTS=3
TYTO_INGEST_BACKOFF_MS=100

TYTO_HEARTBEAT_ENABLED=true
TYTO_HEARTBEAT_SLUG=scheduler
TYTO_HEARTBEAT_NAME="Laravel scheduler"
TYTO_HEARTBEAT_INTERVAL=1

TYTO_CAPTURE_SOURCE_CODE=true
TYTO_CAPTURE_PAYLOAD=false
TYTO_REDACT_FIELDS=_token,password,password_confirmation
TYTO_REDACT_HEADERS=Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN

TYTO_SAMPLE_REQUESTS=1.0
TYTO_SAMPLE_COMMANDS=1.0
TYTO_SAMPLE_EXCEPTIONS=1.0
TYTO_SAMPLE_TASKS=1.0
```

The agent schedules a heartbeat automatically, which lets Tyto detect when Laravel's scheduler stops running. Keep `php artisan schedule:run` configured every minute in production.

Report custom recurring processes from application code:

```php
TytoAgent::heartbeat('nightly-import', 'Nightly customer import', 1440);
```

Or from cron and deployment scripts:

```bash
php artisan tyto:heartbeat nightly-import --name="Nightly customer import" --interval=1440
```

## Migration from LaraOwl

Existing installations remain compatible during the transition:

- `LARAOWL_*` environment values are used when the equivalent `TYTO_*` value is absent.
- `config/laraowl.php` remains readable.
- `php artisan laraowl:install` remains an alias.
- the existing `Laraowl\\Client` PHP namespace and `LaraowlClient` facade remain available.
- `laraowl/client` is declared as replaced by `tyto/agent`.

New applications should use the Tyto names exclusively.

## Privacy defaults

Request payload capture is disabled by default. Authentication headers, cookies, CSRF tokens and common password fields are redacted before transmission. Review `config/tyto.php` before enabling additional payload collection.

## License

MIT. See [LICENSE.md](LICENSE.md).
