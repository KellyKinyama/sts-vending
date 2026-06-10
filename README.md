<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

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

Skipped by default. Enable with:

```powershell
$env:STS_INTEGRATION_TESTS = '1'
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST       = '127.0.0.1'
$env:DB_PORT       = '3306'
$env:DB_DATABASE   = 'sts_vending'
$env:DB_USERNAME   = 'root'
$env:DB_PASSWORD   = ''
./vendor/bin/phpunit tests/Feature/Api/DartEngineRoundTripTest.php
```

Tunable env vars (all optional):

| Var | Default | Purpose |
| --- | --- | --- |
| `STS_DART_PROJECT` | `C:\www\dart\nectar_sts_dart` | path to the Dart checkout |
| `STS_DART_PORT` | `18787` | port the spawned server binds |
| `STS_DART_BEARER` | `test-bearer` | bearer the spawned server requires |
| `STS_DART_VENDING_KEY` | `0123456789ABCDEF` | 16-hex DES key (must match `VendingKey.vudk_blob`) |

The spawned server runs in **JSON-file mode** (`STS_DB_HOST` is forced
empty) so it does not double-write into the Laravel `tokens` table.
Laravel's own DB writes go through Eloquent and the shared MySQL
instance. Tests clean up by deleting their throwaway `supply_groups`
row (`code='987655'`) and the meter rows they created in `tearDown`.
