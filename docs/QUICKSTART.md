# STS Vending — Quickstart

Get a development copy running in under 5 minutes. For production
see [DEPLOYMENT.md](DEPLOYMENT.md). For the full reference (every
env var, every test surface, troubleshooting matrix) see
[DEPLOYMENT_AND_TESTING.md](DEPLOYMENT_AND_TESTING.md).

## Prerequisites

| Tool      | Min version | Verify with         |
| --------- | ----------- | ------------------- |
| PHP       | 8.3         | `php -v`            |
| Composer  | 2.7         | `composer --version`|
| Node.js   | 20          | `node -v`           |
| Dart SDK  | 3.4         | `dart --version`    |
| MySQL     | 8.0 / 9.x   | `mysql --version`   |
| Git       | any recent  | `git --version`     |

Windows: PHP needs `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`,
`gd`, `curl` extensions enabled in `php.ini`. WAMP ships them all.

## 1. Create the database

```powershell
mysql -uroot -e "CREATE DATABASE sts_vending CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If MySQL 8/9 uses `caching_sha2_password` (the default), either
keep it and set `STS_DB_SSLMODE=require` on the Dart side, **or**
allow native:

```sql
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '';
```

## 2. Install both projects

```powershell
# Laravel app
cd C:\www\web\laravel\sts-vending
composer install
npm install
copy .env.example .env

# Dart engine (separate checkout)
cd C:\www\dart\nectar_sts_dart
dart pub get
```

## 3. Bootstrap in one shot

The `sts:setup` artisan command syncs `.env` keys between Laravel
and the Dart engine, generates `APP_KEY`, runs migrations, and
seeds a working demo dataset (admin user, supply group, vending
key, tariff, meter).

```powershell
cd C:\www\web\laravel\sts-vending
php artisan sts:setup
```

Output (abbreviated):

```
STS Vending — auto setup
  ✔ created Laravel .env from .env.example
  ✔ generated APP_KEY
  Vending key (16 hex): 0123456789ABCDEF
  ✔ Laravel .env: STS_DART_VENDING_KEY synced
  ✔ Dart .env: VENDING_KEY_HEX synced
Migrating database...
Seeding demo data...

Setup complete. Ready to vend.
+---------------------+-----------------------------+
| What                | Where / value               |
+---------------------+-----------------------------+
| Dashboard           | http://127.0.0.1:8000       |
| Dart engine         | http://127.0.0.1:8787       |
| Admin login         | admin@local / password      |
| Demo supply group   | 123456 — "Demo Supply Group"|
| Demo meter PAN      | 600727000000000009          |
| Vending key (hex)   | 0123456789ABCDEF            |
| Smoke test          | php artisan sts:smoke       |
+---------------------+-----------------------------+
```

Flags:

- `--regenerate-key` — mint a fresh 16-hex vending key instead of
  reusing the demo default.
- `--key=ABCDEF...` — use a specific 16-hex key.
- `--skip-seed` — sync `.env` only, no migrations or demo data.
- `--skip-dart` — don't touch the Dart project's `.env`.

## 4. Start the three processes

Open three terminals.

**Terminal 1 — Dart token engine:**

```powershell
cd C:\www\dart\nectar_sts_dart
dart run bin/server.dart
```

Wait for:

```
nectar_sts_dart HTTP server
  listening on http://127.0.0.1:8787
```

**Terminal 2 — Laravel app:**

```powershell
cd C:\www\web\laravel\sts-vending
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 3 — Vite dev server** (only during UI work; optional):

```powershell
cd C:\www\web\laravel\sts-vending
npm run dev
```

## 5. Verify

```powershell
cd C:\www\web\laravel\sts-vending
php artisan sts:smoke
```

Success looks like:

```
Engine URL: http://127.0.0.1:8787
  ✔ engine /healthz OK
  ✔ seeded SG#14 VK#12 Meter#37
  ✔ issued token 05949222778058714319 (5 kWh)
  ✔ decoded amount=5.000 kWh (round-trip Δ=0.000)
  · cleaned up smoke rows
```

Open the dashboard at <http://127.0.0.1:8000> and sign in with
`admin@local` / `password`.

## 6. Mint your first token from the UI

1. Sign in.
2. Sidebar → **Tokens**.
3. Click **Issue token**.
4. Pick the demo meter from the dropdown (PAN `600727000000000009`).
5. Enter an amount in kWh (e.g. `12.5`).
6. **Issue** — a 20-digit number appears in the result card,
   pre-formatted as `12345 67890 12345 67890`.

That string is the token the customer keys into the prepayment
meter. The matching `tokens` row in the DB has `status='issued'`,
the engine's audit log captured the issuance in
`nectar_sts_dart/vending.json`, and the meter's local HSM (or our
`VirtualMeter` test harness) will accept it on first redemption,
reject it on replay.

## 7. Next steps

- **Add real meters** — Sidebar → **Meters** → **New**. PAN must be
  18 digits and pass Luhn; IAIN must be 11 or 13 digits (not 12).
- **Add a real vending key** — Sidebar → **Vending Keys** → **New**.
  Paste the 16-hex VUDK from your KMS; Laravel encrypts it at rest.
  Then either restart the Dart engine with `VENDING_KEY_HEX`
  matching, or use `php artisan sts:setup --key=YOURHEXHERE` to
  sync both sides.
- **Mint an API token** — `php artisan tinker`, then
  `\App\Models\User::find(1)->createToken('my-cli')->plainTextToken`.
  Use it as `Authorization: Bearer ...` against `/api/v1/tokens`.
- **Run the full test suite** — see
  [DEPLOYMENT_AND_TESTING.md §6](DEPLOYMENT_AND_TESTING.md).
- **Deploy to production** — see [DEPLOYMENT.md](DEPLOYMENT.md).

## Troubleshooting

| Symptom | Fix |
| ------- | --- |
| `Could not open connection: Access denied` on `php artisan migrate` | Wrong `DB_USERNAME` / `DB_PASSWORD` in `.env`. Check with `mysql -u<user> -p<pw> sts_vending`. |
| `Connection refused` on `php artisan sts:smoke` | Dart engine not running. Start terminal 1 first. |
| `Bearer token mismatch (401)` | `STS_ENGINE_TOKEN` (Laravel) ≠ `NECTAR_API_TOKEN` (Dart). Re-run `php artisan sts:setup` to resync. |
| `mysql_dart: Unknown authentication plugin` | MySQL 8/9 caching_sha2. Add `STS_DB_SSLMODE=require` to the Dart side, **or** `ALTER USER ... IDENTIFIED WITH mysql_native_password`. |
| `sts:smoke` reports amount mismatch | Vending key in `vending_keys.vudk_blob` doesn't match `VENDING_KEY_HEX` on the Dart side. Re-run `php artisan sts:setup`. |

Full troubleshooting table in
[DEPLOYMENT_AND_TESTING.md §8](DEPLOYMENT_AND_TESTING.md).
