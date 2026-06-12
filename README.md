# STS Vending

Laravel 12 vending front-end and dashboard for the
[`nectar_sts_dart`](../../dart/nectar_sts_dart) STS prepayment token
engine. Operators manage supply groups, vending keys, tariffs,
customers and meters from a Livewire dashboard, then mint 20-digit
STS tokens that customers key into their prepayment meters.

- **Backend** — Laravel 12, PHP 8.3, MySQL 8/9 (sqlite for unit tests)
- **Front-end** — Livewire 3 + Tailwind dashboard, Vite asset pipeline
- **Auth** — Sanctum bearer tokens for the API, web sessions for the
  dashboard
- **Token engine** — out-of-process Dart HTTP server invoked via
  [`App\Services\DartTokenEngine`](app/Services/DartTokenEngine.php)
- **CLI** — `php artisan sts:setup` (one-shot bootstrap),
  `php artisan sts:smoke` (post-deploy smoke check)

```
+----------------+      HTTP (bearer)     +-------------------+
|  Laravel app   |  ───────────────────►  |  nectar_sts_dart  |
|  (PHP 8.3)     |  /v1/tokens, /healthz  |  (Dart 3 server)  |
+----------------+                        +-------------------+
        │                                          │
        │  Eloquent + MySQL (shared schema)        │
        └─────────────► +-------------+ ◄──────────┘
                        |   MySQL 9   |
                        | sts_vending |
                        +-------------+
```

## Quick start

```powershell
# 1. Clone, install, set env
cd C:\www\web\laravel\sts-vending
composer install
npm install
copy .env.example .env

# 2. Create the database (any MySQL client)
mysql -uroot -e "CREATE DATABASE sts_vending CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 3. One-shot bootstrap (key:generate + migrate + seed + sync Dart .env)
php artisan sts:setup

# 4. Start the Dart engine in a separate terminal
cd C:\www\dart\nectar_sts_dart
dart run bin/server.dart

# 5. Start Laravel + assets
cd C:\www\web\laravel\sts-vending
php artisan serve --host=127.0.0.1 --port=8000
npm run dev          # optional, only during UI work
```

Open `http://127.0.0.1:8000`. Default admin: `admin@local` / `password`.

Verify the bridge end-to-end:

```powershell
php artisan sts:smoke
```

Full walkthrough in [docs/QUICKSTART.md](docs/QUICKSTART.md).

## Documentation

| Doc | What's inside |
| --- | ------------- |
| [docs/QUICKSTART.md](docs/QUICKSTART.md) | 5-minute happy-path setup for a new developer machine. |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production deployment — Windows (NSSM), Linux (systemd), Docker, nginx + TLS, secrets, backup/restore, monitoring, rollback. |
| [docs/DEPLOYMENT_AND_TESTING.md](docs/DEPLOYMENT_AND_TESTING.md) | Combined dev-setup + test-matrix reference — architecture, configuration knobs, all six test surfaces, troubleshooting table. |

## HTTP API

All endpoints sit under `/api/v1/` and require a Sanctum bearer
token (`Authorization: Bearer <personal-access-token>`).

| Method | Path                                       | Purpose |
| ------ | ------------------------------------------ | ------- |
| GET    | `/api/v1/user`                             | Inspect the authenticated user. |
| GET    | `/api/v1/supply-groups`                    | List / show / create / update supply groups. |
| GET    | `/api/v1/vending-keys`                     | Manage vending keys (VUDK metadata). |
| GET    | `/api/v1/tariffs`                          | Manage tariffs. |
| GET    | `/api/v1/customers`                        | Manage customers. |
| GET    | `/api/v1/meters`                           | Manage meters (PAN, IIN/IAIN, vending-key binding). |
| GET    | `/api/v1/tokens`                           | List issued tokens. |
| GET    | `/api/v1/tokens/{id}`                      | Show one token. |
| POST   | `/api/v1/tokens`                           | **Issue** a token. Body: `{meter_id, amount_kwh, random_no?}`. |
| POST   | `/api/v1/tokens/{tokenNo}/decode`          | **Decode** a 20-digit token back to amount + TID. |

Issuance example:

```powershell
$body = @{ meter_id = 7; amount_kwh = 12.5 } | ConvertTo-Json
curl.exe -X POST http://127.0.0.1:8000/api/v1/tokens `
    -H "Authorization: Bearer $env:STS_API_TOKEN" `
    -H "Content-Type: application/json" `
    -H "Accept: application/json" `
    -d $body
```

Response (HTTP 201):

```json
{
  "token": {
    "id": 412,
    "request_id": "req-hjc3ib4fek",
    "token_no": "12810812506036069849",
    "meter_id": 7,
    "vending_key_id": 3,
    "status": "issued",
    "amount_kwh": "12.5000",
    "issued_at": "2026-06-10T05:12:39.011151Z"
  },
  "engine": { "status": {...}, "data": {...} }
}
```

The 20-digit `token.token_no` is the value the customer keys into the
meter. See [docs/DEPLOYMENT_AND_TESTING.md §9](docs/DEPLOYMENT_AND_TESTING.md)
for the full issuance + delivery + reconciliation flow.

## STS Vending: Dart Engine Integration Testing

> Full operator manual: [docs/DEPLOYMENT_AND_TESTING.md](docs/DEPLOYMENT_AND_TESTING.md).

This Laravel app delegates STS token math to the
[`nectar_sts_dart`](../../dart/nectar_sts_dart) HTTP server via
[`App\Services\DartTokenEngine`](app/Services/DartTokenEngine.php).
Two end-to-end test surfaces drive the full stack against a real
MySQL database and a live Dart subprocess:

### 1. Artisan smoke command

```powershell
php artisan sts:smoke               # uses services.sts_engine.* from .env
php artisan sts:smoke --amount=12.5
php artisan sts:smoke --keep        # leaves smoke rows in place for inspection
```

The command pings `/healthz`, seeds a throwaway supply group + vending
key + meter, issues a 20-digit token, decodes it, asserts the amount
round-trips, then cleans up. Exits non-zero on any failure.

Requires a running Dart server at `services.sts_engine.url` (default
`http://127.0.0.1:8787`). To start one in a separate terminal:

```powershell
cd C:\www\dart\nectar_sts_dart
$env:VENDING_KEY_HEX = '0123456789ABCDEF'
$env:NECTAR_API_TOKEN = 'dev-bearer-token'
dart run bin/server.dart
```

### 2. PHPUnit integration suite

`tests/Feature/Api/DartEngineRoundTripTest.php` spawns its own Dart
subprocess (via [`Tests\Concerns\SpawnsDartServer`](tests/Concerns/SpawnsDartServer.php)),
polls `/healthz`, then drives the full Laravel HTTP routes
(`POST /api/v1/tokens`, `POST /api/v1/tokens/{tokenNo}/decode`) under
`Sanctum::actingAs()`.

Skipped by default. To enable, flip `STS_INTEGRATION_TESTS=1` in
your local `.env` and run:

```powershell
./vendor/bin/phpunit tests/Feature/Api/DartEngineRoundTripTest.php
```

All required env vars (MySQL connection, Dart project path, port,
bearer) live in `.env` — no shell-level `$env:` lines needed.
Override individual values there if your local setup differs.

The spawned server runs in **JSON-file mode** (`STS_DB_HOST` is forced
empty) so it does not double-write into the Laravel `tokens` table.
Laravel's own DB writes go through Eloquent and the shared MySQL
instance. Tests clean up by deleting their throwaway `supply_groups`
row (`code='987655'`) and the meter rows they created in `tearDown`.

## Security

- API access is Sanctum bearer only. Mint a personal access token via
  Tinker or the dashboard before issuing requests.
- Vending keys are encrypted at rest in `vending_keys.vudk_blob`
  (Laravel `Crypt::encryptString`) and **never** transmitted over the
  Laravel ↔ Dart bridge — see
  [docs/DEPLOYMENT_AND_TESTING.md §4.3](docs/DEPLOYMENT_AND_TESTING.md).
- Report security issues privately to the maintainer; do not file
  public GitHub issues.

## License

MIT — see [LICENSE](LICENSE) (or the upstream Laravel license if no
project-specific file is present).

