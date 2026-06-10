<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot auto-configuration for the STS demo setup.
 *
 *   php artisan sts:setup                  use the default demo key
 *   php artisan sts:setup --regenerate-key generate a fresh random 16-hex key
 *   php artisan sts:setup --key=ABCD...    use a specific 16-hex key
 *
 * Steps:
 *   1. Ensure Laravel's .env exists (copy from .env.example + key:generate).
 *   2. Pick the vending key (option flag → env value → built-in default).
 *   3. Write it as STS_DART_VENDING_KEY in Laravel's .env.
 *   4. Locate the Dart project (STS_DART_PROJECT or default), copy its
 *      .env.example → .env if needed, write the same key as VENDING_KEY_HEX.
 *   5. Run `migrate --force` then `db:seed --force` so the dashboard
 *      has a working SupplyGroup + VendingKey (matching key) + Tariff +
 *      Customer + Meter ready to issue tokens.
 *   6. Print a summary of next-step commands and credentials.
 */
class StsSetupCommand extends Command
{
    protected $signature = 'sts:setup
                            {--key=            : 16-hex-char vending key to use across both projects}
                            {--regenerate-key  : generate a fresh random 16-hex vending key}
                            {--skip-seed       : skip migrate + db:seed (just sync .env files)}
                            {--skip-dart       : do not touch the Dart server\'s .env}';

    protected $description = 'Auto-configure Laravel + Dart engine with matched sample keys and demo data';

    private const DEFAULT_KEY = '0123456789ABCDEF';

    public function handle(): int
    {
        $this->info('STS Vending — auto setup');
        $this->newLine();

        // 1. Ensure Laravel .env exists.
        if (! is_file(base_path('.env'))) {
            if (! is_file(base_path('.env.example'))) {
                $this->error('No .env.example to copy from.');

                return self::FAILURE;
            }
            copy(base_path('.env.example'), base_path('.env'));
            $this->info('  ✔ created Laravel .env from .env.example');
            Artisan::call('key:generate', ['--force' => true]);
            $this->info('  ✔ generated APP_KEY');
        }

        // 2. Resolve vending key.
        try {
            $key = $this->resolveVendingKey();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line('  Vending key (16 hex): <fg=cyan>'.$key.'</>');

        // 3. Sync Laravel .env.
        $this->upsertEnvKey(base_path('.env'), 'STS_DART_VENDING_KEY', $key);
        $this->info('  ✔ Laravel .env: STS_DART_VENDING_KEY synced');

        // 4. Sync Dart .env.
        if (! $this->option('skip-dart')) {
            $this->syncDartEnv($key);
        }

        // 5. Migrate + seed.
        if (! $this->option('skip-seed')) {
            $this->newLine();
            $this->info('Migrating database...');
            $code = Artisan::call('migrate', ['--force' => true]);
            $this->line(trim((string) Artisan::output()));
            if ($code !== 0) {
                $this->error('migrate failed.');

                return self::FAILURE;
            }

            // Reload .env so DatabaseSeeder sees the freshly written key.
            putenv('STS_DART_VENDING_KEY='.$key);
            $_ENV['STS_DART_VENDING_KEY'] = $key;
            $_SERVER['STS_DART_VENDING_KEY'] = $key;

            $this->info('Seeding demo data...');
            $code = Artisan::call('db:seed', ['--force' => true]);
            $this->line(trim((string) Artisan::output()));
            if ($code !== 0) {
                $this->error('db:seed failed.');

                return self::FAILURE;
            }
        }

        $this->newLine();
        $this->info('Setup complete. Ready to vend.');
        $this->table(['What', 'Where / value'], [
            ['Dashboard',         (string) config('app.url', 'http://127.0.0.1:8000')],
            ['Dart engine',       (string) config('services.sts_engine.url')],
            ['Admin login',       'admin@local / password'],
            ['Demo supply group', '123456 — "Demo Supply Group"'],
            ['Demo meter PAN',    '600727000000000009'],
            ['Vending key (hex)', $key],
            ['Smoke test',        'php artisan sts:smoke'],
            ['Restart tour',      url('/').'/?tour=1'],
        ]);

        if (! $this->option('skip-dart')) {
            $this->warn('Restart the Dart server (Ctrl-C + `dart run bin/server.dart`) so it picks up the new VENDING_KEY_HEX.');
        }

        return self::SUCCESS;
    }

    private function resolveVendingKey(): string
    {
        if ($k = (string) $this->option('key')) {
            $this->validateKey($k);

            return strtoupper($k);
        }
        if ($this->option('regenerate-key')) {
            return strtoupper(bin2hex(random_bytes(8)));
        }
        $env = (string) env('STS_DART_VENDING_KEY', self::DEFAULT_KEY);

        return strtoupper($env === '' ? self::DEFAULT_KEY : $env);
    }

    private function validateKey(string $k): void
    {
        if (! preg_match('/^[0-9a-fA-F]{16}$/', $k)) {
            throw new \InvalidArgumentException('Vending key must be exactly 16 hexadecimal characters (8 bytes).');
        }
    }

    private function syncDartEnv(string $key): void
    {
        $projectDir = (string) env('STS_DART_PROJECT', 'C:\\www\\dart\\nectar_sts_dart');
        if (! is_dir($projectDir)) {
            $this->warn("  ! STS_DART_PROJECT not found at {$projectDir} — skipped Dart .env sync.");

            return;
        }
        $envFile = $projectDir.DIRECTORY_SEPARATOR.'.env';
        $example = $projectDir.DIRECTORY_SEPARATOR.'.env.example';
        if (! is_file($envFile)) {
            if (! is_file($example)) {
                $this->warn("  ! {$envFile} missing and no .env.example next to it — skipped.");

                return;
            }
            copy($example, $envFile);
            $this->info('  ✔ created '.$envFile.' from .env.example');
        }
        $this->upsertEnvKey($envFile, 'VENDING_KEY_HEX', $key);
        $this->info('  ✔ Dart   .env: VENDING_KEY_HEX synced ('.$envFile.')');
    }

    private function upsertEnvKey(string $envFile, string $key, string $value): void
    {
        $contents = file_get_contents($envFile) ?: '';
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $key.'='.$value, $contents);
        } else {
            $contents = rtrim($contents, "\r\n")."\n".$key.'='.$value."\n";
        }
        file_put_contents($envFile, $contents);
    }
}
