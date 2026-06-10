# STS Vending — Deployment & Testing Manual

This document is the operator-facing reference for deploying and
testing the STS vending stack on a single Windows host. Linux notes
are inline where they diverge.

## 1. Architecture at a glance

```
+----------------+      HTTP (bearer)     +-------------------+
|  Laravel app   |  ───────────────────►  |  nectar_sts_dart  |
|  (PHP 8.3)     |  /v1/tokens, /healthz  |  (Dart 3 server)  |
+----------------+                        +-------------------+
        │                                          │
        │  Eloquent (mysql_dart for Dart)          │
        └─────────────► +-------------+ ◄──────────┘
                        |   MySQL 9   |
                        | sts_vending |
                        +-------------+
```

Three processes:

| Component            | Path                                                            | Default port |
| -------------------- | --------------------------------------------------------------- | ------------ |
| Laravel API + Livewire dashboard | `C:\www\web\laravel\sts-vending`                    | 8000         |
| `nectar_sts_dart` HTTP server    | `C:\www\dart\nectar_sts_dart`                        | 8787 (dev) / 18787 (integration tests) |
| MySQL 9                          | service `MySQL` (WAMP / standalone)                  | 3306         |

Both Laravel and Dart connect to the **same** `sts_vending` schema.
Laravel writes `tokens` rows via its `TokenController`; Dart, when
running in DB-backed mode, writes `tokens` rows via its own
`DbVendingLog`. The two writers must not be enabled at the same
time against the same DB or rows are duplicated.

## 2. Prerequisites

| Tool         | Minimum version | How to verify           |
| ------------ | --------------- | ----------------------- |
| PHP          | 8.3             | `php -v`                |
| Composer     | 2.7             | `composer --version`    |
| Node.js      | 20              | `node -v`               |
| Dart SDK     | 3.4             | `dart --version`        |
| MySQL        | 8.0 / 9.x       | `mysql --version`       |
| Git          | any recent      | `git --version`         |

Windows-specific: PHP must have the `pdo_mysql`, `openssl`,
`mbstring`, `fileinfo`, `gd`, `curl` extensions enabled in
`php.ini`. WAMP ships with all of these.

## 3. One-time setup

### 3.1 MySQL schema

```sql
CREATE DATABASE sts_vending CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

If MySQL 8/9 uses `caching_sha2_password` for the `root` account
(default on Win MySQL 9 installer), either keep the default and
flip the Dart side to TLS via `STS_DB_SSLMODE=require`, or run:

```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
```

Without one of those two steps the Dart `mysql_dart` driver fails
the auth handshake.

### 3.2 Laravel side

```powershell
cd C:\www\web\laravel\sts-vending
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run build
```

Edit `.env` with the values below (the rest stay at Laravel
defaults):

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sts_vending
DB_USERNAME=root
DB_PASSWORD=

STS_ENGINE_URL=http://127.0.0.1:8787
STS_ENGINE_TOKEN=dev-bearer-token
STS_ENGINE_TIMEOUT=10
```

Then:

```powershell
php artisan migrate
php artisan db:seed         # optional — admin user + sample data
```

### 3.3 Dart side

```powershell
cd C:\www\dart\nectar_sts_dart
dart pub get
dart test                   # unit + e2e suite (228 tests / 9 DB-only skipped)
```

The DB-backed tests under `test/db_*` skip cleanly when
`STS_DB_HOST` is not set. To exercise them, follow §6.2.

## 4. Configuration reference

### 4.1 Laravel `.env` keys that drive the bridge

| Key                  | Default                       | Purpose |
| -------------------- | ----------------------------- | ------- |
| `STS_ENGINE_URL`     | `http://127.0.0.1:8787`       | Base URL of the Dart engine. |
| `STS_ENGINE_TOKEN`   | _(none)_                      | Bearer sent on every Dart request. Must match `NECTAR_API_TOKEN` on the Dart side. |
| `STS_ENGINE_TIMEOUT` | `10`                          | HTTP timeout, seconds. |

### 4.2 Dart `bin/server.dart` env vars

| Var                    | Default                | Purpose |
| ---------------------- | ---------------------- | ------- |
| `PORT`                 | `2000`                 | TCP port. Use `8787` in dev / `18787` for the integration test. |
| `HOST`                 | `0.0.0.0`              | Bind address. Use `127.0.0.1` for local. |
| `VENDING_KEY_HEX`      | `0123456789ABCDEF`     | 16 hex chars (8-byte DES key). MUST match the `vudk_blob` on `vending_keys` rows that Laravel sends. |
| `NECTAR_API_TOKEN`     | _(none — API is OPEN)_ | Bearer token. Must match `STS_ENGINE_TOKEN`. |
| `VENDING_LOG_FILE`     | `vending.json` (cwd)   | JSON-mode audit log. Use `:none:` to disable. |
| `METER_REGISTRY_FILE`  | `meters.json` (cwd)    | JSON-mode meter registry. Use `:none:` to disable. |
| `STS_DB_HOST`          | _(empty → JSON mode)_  | When set, swap JSON-file backends for MySQL ones (`DbVendingLog`, `DbMeterRegistry`). |
| `STS_DB_PORT`          | `3306`                 | DB mode only. |
| `STS_DB_DATABASE`      | `sts_vending`          | DB mode only. |
| `STS_DB_USERNAME`      | `root`                 | DB mode only. |
| `STS_DB_PASSWORD`      | _(empty)_              | DB mode only. |
| `STS_DB_POOL_SIZE`     | `5`                    | DB mode only. |
| `STS_DB_SSLMODE`       | _(empty)_              | Set to `require` for MySQL 8/9 with `caching_sha2_password`. |

### 4.3 Vending-key coordination (IMPORTANT)

Laravel **deliberately does not send** the vending key over the wire
— the Dart server rejects the `vending_key` param with HTTP 400 via
`_rejectSensitiveParams`. The two sides must agree on the key
**out-of-band**:

1. Choose a key (16 hex chars for DKGA02, 40 hex for DKGA04).
2. Set it on the Dart side via `VENDING_KEY_HEX`.
3. Insert/update `vending_keys.vudk_blob` on the Laravel side with
   the same value (Laravel encrypts it at rest via the
   `Crypt::encryptString` accessor; supply the plain hex).

The default factory (`VendingKeyFactory`) seeds
`'0123456789ABCDEF'` so tests work against a Dart server with no
`VENDING_KEY_HEX` set (which falls back to the same demo key).

## 5. Running in development

Open **three** terminals.

**Terminal 1 — Dart engine (JSON-file mode):**

```powershell
cd C:\www\dart\nectar_sts_dart
$env:PORT             = '8787'
$env:HOST             = '127.0.0.1'
$env:VENDING_KEY_HEX  = '0123456789ABCDEF'
$env:NECTAR_API_TOKEN = 'dev-bearer-token'
dart run bin/server.dart
```

You should see:

```
[info] vending log: ...\vending.json (0 prior issue(s) loaded)
[info] meter registry: ...\meters.json (0 meter(s) loaded)
nectar_sts_dart HTTP server
  listening on http://127.0.0.1:8787
```

**Terminal 2 — Laravel app:**

```powershell
cd C:\www\web\laravel\sts-vending
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 3 — assets (optional, only during UI work):**

```powershell
cd C:\www\web\laravel\sts-vending
npm run dev
```

Now `http://127.0.0.1:8000` serves the dashboard and
`http://127.0.0.1:8000/api/v1/*` proxies token issuance into the
Dart engine.

### 5.1 DB-backed Dart mode (shared with Laravel)

If you want the Dart vending log to write directly into
`tokens` (skipping Laravel's controller — useful for batch /
out-of-band issuance), restart Terminal 1 with:

```powershell
$env:STS_DB_HOST     = '127.0.0.1'
$env:STS_DB_PORT     = '3306'
$env:STS_DB_DATABASE = 'sts_vending'
$env:STS_DB_USERNAME = 'root'
$env:STS_DB_PASSWORD = ''
$env:STS_DB_SSLMODE  = 'require'      # only on MySQL 8/9 caching_sha2
dart run bin/server.dart
```

**Do NOT** also call Laravel's `POST /api/v1/tokens` for the same
issuance — the controller will write its own row and you get a
duplicate `tokens` entry. Pick one writer per deployment.

## 6. Testing matrix

| Suite                               | Surface tested                                | Command |
| ----------------------------------- | --------------------------------------------- | ------- |
| Dart unit + e2e (JSON)              | HSM math, API server, JSON-file stores        | `dart test` (in `nectar_sts_dart`) |
| Dart DB-backed e2e                  | `Database`, `DbQueries`, `DbVendingLog`       | `STS_DB_HOST=127.0.0.1 ... dart test test/db_store_test.dart` |
| Laravel unit + feature (default)    | Controllers, models, scopes — sqlite :memory: | `php artisan test` |
| Laravel ↔ Dart integration          | Full HTTP round-trip via spawned Dart subprocess + real MySQL | `STS_INTEGRATION_TESTS=1 ./vendor/bin/phpunit tests/Feature/Api/DartEngineRoundTripTest.php` |
| End-to-end smoke (one shot)         | Health, seed, issue, decode, replay           | `php artisan sts:smoke` |

### 6.1 Dart unit + e2e

```powershell
cd C:\www\dart\nectar_sts_dart
dart test
```

Expected: `228 passed, 9 skipped` (the 9 are the DB-backed tests).

### 6.2 Dart DB-backed e2e

```powershell
cd C:\www\dart\nectar_sts_dart
$env:STS_DB_HOST     = '127.0.0.1'
$env:STS_DB_DATABASE = 'sts_vending'
$env:STS_DB_USERNAME = 'root'
$env:STS_DB_PASSWORD = ''
$env:STS_DB_SSLMODE  = 'require'
dart test test/db_store_test.dart
```

Expected: `9 passed`. Uses `supply_groups.code='987654'` as its
sandbox prefix and cleans up in `tearDown`.

### 6.3 Laravel default suite

```powershell
cd C:\www\web\laravel\sts-vending
php artisan test
```

Runs against sqlite `:memory:` (forced by `phpunit.xml`). The four
integration tests in `DartEngineRoundTripTest` skip cleanly. Result
should be all-green with 4 skipped.

### 6.4 Laravel ↔ Dart integration suite

The four tests in
[tests/Feature/Api/DartEngineRoundTripTest.php](../tests/Feature/Api/DartEngineRoundTripTest.php)
each:

1. Run inside a single class that spawns one `dart run bin/server.dart`
   subprocess (port 18787) in `setUpBeforeClass` via
   [tests/Concerns/SpawnsDartServer.php](../tests/Concerns/SpawnsDartServer.php).
2. Override the in-memory sqlite DB with real MySQL (env vars
   below).
3. Seed `SupplyGroup` (`code='987655'`), `VendingKey`, `Meter` via
   factories.
4. Drive `POST /api/v1/tokens` and `POST /api/v1/tokens/{tokenNo}/decode`
   under `Sanctum::actingAs($user)`.
5. Tear down by deleting the throwaway rows (the spawned Dart
   subprocess is in JSON-file mode so it does not touch the
   `tokens` table).

Enable with:

```powershell
cd C:\www\web\laravel\sts-vending
$env:STS_INTEGRATION_TESTS = '1'
$env:DB_CONNECTION         = 'mysql'
$env:DB_HOST               = '127.0.0.1'
$env:DB_PORT               = '3306'
$env:DB_DATABASE           = 'sts_vending'
$env:DB_USERNAME           = 'root'
$env:DB_PASSWORD           = ''
./vendor/bin/phpunit tests/Feature/Api/DartEngineRoundTripTest.php
```

Expected: `OK (4 tests, 21 assertions)` in ~8 seconds (one-time
Dart subprocess spawn dominates).

To disable again, just unset `STS_INTEGRATION_TESTS`:

```powershell
Remove-Item Env:STS_INTEGRATION_TESTS
```

Optional knobs (any can be left at default):

| Env var                | Default                          | Purpose                                |
| ---------------------- | -------------------------------- | -------------------------------------- |
| `STS_DART_PROJECT`     | `C:\www\dart\nectar_sts_dart`    | Path to the Dart checkout              |
| `STS_DART_PORT`        | `18787`                          | Port the spawned server binds          |
| `STS_DART_BEARER`      | `test-bearer`                    | Bearer the spawned server requires     |
| `STS_DART_VENDING_KEY` | `0123456789ABCDEF`               | 16-hex DES key (must match `vudk_blob`) |

### 6.5 `php artisan sts:smoke`

A one-shot CLI script that exercises the full chain against
whatever `STS_ENGINE_URL` resolves to (i.e. your real dev Dart
server, not a spawned one).

```powershell
php artisan sts:smoke                # default 5.0 kWh
php artisan sts:smoke --amount=12.5  # custom amount
php artisan sts:smoke --keep         # leave SG/VK/Meter rows for inspection
```

Output on success (exit 0):

```
Engine URL: http://127.0.0.1:8787
  ✔ engine /healthz OK
  ✔ seeded SG#14 VK#12 Meter#37
  ✔ issued token 05949222778058714319 (5 kWh)
  ✔ decoded amount=5.000 kWh (round-trip Δ=0.000)
  · replay accepted (engine did NOT trigger TID collision)
  · cleaned up smoke rows
```

Exit code 1 on any failure of seed / issue / decode / amount
mismatch. Suitable as a deployment smoke check (e.g. fire from a
post-deploy hook before flipping traffic).

## 7. Production-ish deployment

For a single-host production install (`hostname:80` reverse-proxy
into `php artisan serve` is fine for low-traffic kiosks; use
nginx + php-fpm for serious load):

### 7.1 Dart engine as a Windows service

Use [NSSM](https://nssm.cc/) (Non-Sucking Service Manager):

```powershell
nssm install nectar-sts "C:\Program Files\dart-sdk\bin\dart.exe" `
    run bin/server.dart
nssm set    nectar-sts AppDirectory    C:\www\dart\nectar_sts_dart
nssm set    nectar-sts AppEnvironmentExtra `
    PORT=8787 HOST=127.0.0.1 `
    VENDING_KEY_HEX=<32 hex chars from your KMS> `
    NECTAR_API_TOKEN=<long random> `
    STS_DB_HOST=127.0.0.1 STS_DB_DATABASE=sts_vending `
    STS_DB_USERNAME=sts_engine STS_DB_PASSWORD=<secret> `
    STS_DB_SSLMODE=require
nssm set    nectar-sts AppStdout C:\logs\nectar-sts.out.log
nssm set    nectar-sts AppStderr C:\logs\nectar-sts.err.log
nssm start  nectar-sts
```

Linux equivalent: a `systemd` unit with `ExecStart=/usr/bin/dart
run bin/server.dart`, `EnvironmentFile=/etc/nectar-sts.env`, and
`Restart=on-failure`.

### 7.2 Laravel queue & scheduler

If the dashboard is enabled, you need the standard Laravel
worker pair:

```powershell
nssm install sts-queue "C:\php\php.exe" artisan queue:work --tries=3
nssm install sts-cron  "C:\php\php.exe" artisan schedule:work
```

Both run from `C:\www\web\laravel\sts-vending`.

### 7.3 Post-deploy verification

After each deployment run the smoke check; exit code 0 means the
bridge is healthy.

```powershell
cd C:\www\web\laravel\sts-vending
php artisan sts:smoke
if ($LASTEXITCODE -ne 0) { Write-Error 'STS smoke FAILED'; exit 1 }
```

## 8. Troubleshooting

| Symptom                                                                  | Cause / fix |
| ------------------------------------------------------------------------ | ----------- |
| `Invalid IAIN: 123456789012`                                             | Dart validator requires 11 OR 13 digits, not 12. Use `12345678901` (11) or `1234567890123` (13). Reflected in `MeterFactory`. |
| `vending_key not allowed` (HTTP 400)                                     | Laravel sent the key to Dart. `DartTokenEngine::meterParams()` must NOT include `'vending_key' => ...`. Already fixed; verify by `grep -n 'vending_key' app/Services/DartTokenEngine.php`. |
| Dart server logs `[warn] VENDING_KEY_HEX not set`                        | Cosmetic: fires whenever the active key equals the demo default. Set an explicit `VENDING_KEY_HEX` (even the same value) to silence — or ignore in dev. |
| `mysql_dart` auth failure / `Unknown authentication plugin`              | MySQL 8/9 uses `caching_sha2_password`. Either set `STS_DB_SSLMODE=require` on the Dart side, or `ALTER USER ... IDENTIFIED WITH mysql_native_password` in MySQL. |
| PHPUnit reports `Test code or tested code did not remove its own error handlers` for skipped tests | Move `$this->skipIfDartDown()` BEFORE `parent::setUp()`. Booting the Laravel app installs handlers that PHPUnit 12 flags when the test then skips. |
| `Dart server did not become healthy within 30s` in integration test     | Port 18787 already bound, OR Symfony Process failed to forward env vars. The trait builds env from `$_ENV + $_SERVER`; verify `php -i | grep variables_order` includes `E`. |
| Tokens duplicate in `tokens` table                                       | Both Laravel's `TokenController` AND a DB-backed Dart server wrote the same issuance. Pick one writer (see §5.1). |
| `409 TID collision` on legitimate retries                                | Same `(meter, tid_minutes)` issued twice within the same wall-clock minute. Either wait ≥60 s or supply an explicit `token_id` one minute apart. |
| Smoke command warns `replay accepted (engine did NOT trigger TID collision)` | JSON-file mode's collision detector is best-effort and currently does not fire for `Class 0/0`. Cosmetic — does not fail the smoke check. |
| `sts:smoke` not found                                                    | Run `composer dump-autoload` so Laravel's package discovery picks up `app/Console/Commands/StsSmokeCommand.php`. |

## 9. Deploying tokens to the meter

In STS, "deploying a token" is not a network push — the 20-digit
number returned by `POST /api/v1/tokens` **is** the deployable
payload. All the cryptography happened in the Dart engine; the
remaining task is to (a) transport that string to whoever stands
at the meter and (b) get it keyed in.

This codebase implements the two surfaces below today. Everything
else (SMS, print, IVR, webhook to a smart-meter HES) is a clean
Laravel extension point — see §9.4.

### 9.1 The issuance lifecycle (one token, end-to-end)

```
operator / kiosk          Laravel API            Dart engine            meter HSM
      │                        │                       │                     │
      │  1. POST /api/v1/tokens│                       │                     │
      │ ──────────────────────►│                       │                     │
      │   {meter_id, amount}   │  2. POST /v1/tokens   │                     │
      │                        │ ─────────────────────►│                     │
      │                        │                       │ 3. derive DK, build │
      │                        │                       │    64-bit token,    │
      │                        │                       │    DES-OFB encrypt, │
      │                        │                       │    nibbleate → 20d  │
      │                        │  4. {data: {token: [{token_no: "...20d..."}]}}
      │                        │ ◄─────────────────────│                     │
      │                        │ 5. persist tokens row │                     │
      │                        │    (status='issued')  │                     │
      │  6. 201 {token, engine}│                       │                     │
      │ ◄──────────────────────│                       │                     │
      │                        │                       │                     │
      │  7. show / hand / SMS / print the 20-digit string                    │
      │  ─────────────────────────────────────────────────────────────────►  │
      │                                                                     │
      │                                          8. customer keys 20 digits │
      │                                                                     ▼
      │                                          9. meter HSM: same key,    │
      │                                             same params, decrypts,  │
      │                                             checks CRC, checks TID  │
      │                                             not seen before, then   │
      │                                             credits kWh and prints  │
      │                                             "Accepted, balance N".  │
```

Steps 1–6 are automated by Laravel + Dart. Step 7 is the
"deployment" the operator performs. Steps 8–9 happen entirely
inside the meter — there is no network link back to Laravel from a
standard STS prepayment meter.

### 9.2 Delivery surfaces in this codebase today

| Surface                       | Code                                                                                  | Use when |
| ----------------------------- | ------------------------------------------------------------------------------------- | -------- |
| HTTP JSON response            | [`app/Http/Controllers/Api/TokenController@issue`](../app/Http/Controllers/Api/TokenController.php) | Another system (kiosk, mobile app, USSD bridge, third-party vendor) calls Laravel and forwards the `token.token_no` field to its own UI. |
| Livewire dashboard            | [`app/Livewire/Tokens/TokenManager.php`](../app/Livewire/Tokens/TokenManager.php) → `lastTokenNo` rendered in [`token-manager.blade.php`](../resources/views/livewire/tokens/token-manager.blade.php) | Walk-up cashier vending: cashier picks a meter, types the kWh amount, presses Issue, then reads the 20-digit number off the screen and hands a paper slip / dictates it to the customer. |
| `php artisan sts:smoke` output | [`app/Console/Commands/StsSmokeCommand.php`](../app/Console/Commands/StsSmokeCommand.php) | Operator-side validation only — proves the chain can mint a token end-to-end. Not a real delivery channel. |

#### HTTP example (curl)

```powershell
$body = @{ meter_id = 7; amount_kwh = 12.5; random_no = 4 } | ConvertTo-Json
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

The 20-digit `token.token_no` is the only field the customer
needs. Everything else is for reconciliation.

#### Dashboard flow

1. Navigate to `/livewire/tokens` (or the route mapped to `TokenManager`).
2. Click **Issue token**, pick a meter from the dropdown, enter
   the kWh amount.
3. On success the component sets `lastTokenNo` and the view
   renders the number in a large monospaced block formatted as
   `12810 81250 60360 69849` for easier dictation.
4. Operator copies / prints / dictates that string.

### 9.3 Keying the token into the meter

The customer-side procedure is dictated by the meter firmware,
not by this codebase. The general STS-compliant flow:

1. Press the meter's `Enter` (or numeric prefix) key to start
   credit entry.
2. Type the 20 digits. Most meters group the display as
   `XXXXX XXXXX XXXXX XXXXX`; spaces are not entered.
3. Press `Enter` to commit. The meter's HSM internally:
   - reconstructs the supply-group decoder key from
     `(IIN, IAIN, key_type, tariff_index, key_revision, base_date)`
     — the same params Laravel sent to the engine,
   - removes the class bits, DES-decrypts with the local copy
     of the VUDK,
   - checks the CRC-16 inside the token,
   - checks the token's TID (minutes-since-base-date) is newer
     than the most-recent accepted TID,
   - on success: credits the kWh, persists the TID, displays
     `Accepted — new balance N kWh`.
4. Common meter rejection codes:

   | Display | Meaning | Operator action |
   | ------- | ------- | --------------- |
   | `Used` / `Old` | TID is not newer than the last accepted TID — the customer already redeemed an equal-or-later token, or vending issued two tokens in the same minute. | Issue a fresh token at least 1 minute after the last one. |
   | `Reject` / `Err` | CRC failure — the customer mistyped, OR the meter's key revision doesn't match what Laravel used. | Re-dictate the number first. If still rejected, check that `meters.vending_key_id → vending_keys.key_revision_number` matches what is loaded in the meter. |
   | `Bad key` | Decoder-key derivation produced gibberish — wrong `(supply_group, tariff_index, base_date)`. | Verify the meter's commissioning parameters against the `meters` + `vending_keys` rows in Laravel. |
   | `Time` / `Clock` | Meter's RTC is far enough off that TID validation rolled. | Re-sync meter RTC (vendor procedure). |

There is **no automatic feedback** from a standard STS prepayment
meter back to Laravel. The only signal that a token was accepted
is the customer telling you (or, in smart-meter deployments, the
HES sending a separate event — see §9.4).

### 9.4 Reconciliation: did the meter accept it?

`tokens.status` has three values defined in the migration:

| Status     | Set by                                                                 | Meaning                                                  |
| ---------- | ---------------------------------------------------------------------- | -------------------------------------------------------- |
| `issued`   | `TokenController::persist` on a successful engine 201                  | The engine minted a 20-digit number. **Not** proof of meter acceptance. |
| `failed`   | `TokenController::persist` when the engine threw or returned no token  | Engine refused — wrong params, TID collision, key mismatch. |
| `reversed` | _no code path sets this today_                                         | Reserved for "customer reported the meter rejected; refund issued." |

For walk-up cashier vending, the cashier should manually mark a
token `reversed` in the dashboard if the customer comes back
saying the meter rejected it. A future extension is to add a
"Mark as accepted / reversed" button on the row, which is one
Livewire action.

### 9.5 Extension points (NOT yet implemented)

The following are standard delivery channels for STS tokens.
None ship in this codebase; they are clean Laravel extension
points you can wire in without touching the engine:

| Channel | How to add |
| ------- | ---------- |
| **SMS to customer phone** | Add a `phone` column to `customers`. Create `App\Notifications\TokenIssued` implementing `via(['sms'])`. Wire a `Notification::route('sms', $customer->phone)->notify(...)` from `TokenController::issue` after the persist. Use a custom `SmsChannel` backed by Twilio, Africa's Talking, Infobip, etc. |
| **Email receipt** | Same `TokenIssued` notification with a `via(['mail'])` channel. Ship a blade view at `resources/views/mail/token-issued.blade.php`. |
| **Thermal receipt printing** | Add a `php-escpos-php` dependency. Create `App\Services\ReceiptPrinter` that opens the USB/network printer and emits the 20-digit number plus customer + meter metadata. Call from `TokenController::issue` or from a Livewire button on the row. |
| **USSD / IVR session** | Build a `routes/ussd.php` endpoint that takes a meter PAN + amount via Africa's Talking USSD callbacks, calls the same `DartTokenEngine`, and returns the token in the USSD response. |
| **Smart-meter HES push** | If the meter has a back-channel (PLC, RF mesh, cellular), POST the token to the HES endpoint from a queued job. Add an `accepted_at` column on `tokens` and have the HES callback update it. |
| **Voucher / paper batch** | Add an `App\Console\Commands\TokenVoucherExport` artisan command that takes a CSV of `(meter_pan, amount)`, mints tokens for each row, and writes a PDF voucher booklet to `storage/app/vouchers/`. |

All of these consume the same `DartTokenEngine` and the same
`tokens` table — they are pure presentation layers above the
engine.

## 10. File / artefact map

```
C:\www\web\laravel\sts-vending\
  app/
    Console/Commands/StsSmokeCommand.php         # `php artisan sts:smoke`
    Http/Controllers/Api/TokenController.php     # POST /api/v1/tokens(/{tokenNo}/decode)
    Services/DartTokenEngine.php                 # Thin HTTP client for the Dart server
  database/
    factories/{SupplyGroup,VendingKey,Meter}Factory.php
    migrations/
      *_create_supply_groups_table.php
      *_create_vending_keys_table.php
      *_create_meters_table.php
      *_create_tokens_table.php
  tests/
    Concerns/SpawnsDartServer.php                # Spawns Dart subprocess for integration tests
    Feature/Api/DartEngineRoundTripTest.php      # The 4-test integration suite
  docs/
    DEPLOYMENT_AND_TESTING.md                    # ← this file

C:\www\dart\nectar_sts_dart\
  bin/server.dart                                # HTTP server entry
  lib/src/server/{api_server,database,db_vending_log,db_meter_registry,db_queries}.dart
  test/db_store_test.dart                        # 9 DB-backed e2e tests
```
