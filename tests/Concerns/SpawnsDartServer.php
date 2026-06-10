<?php

namespace Tests\Concerns;

use Symfony\Component\Process\Process;

/**
 * Spawns the local `nectar_sts_dart` HTTP server for integration
 * tests, polls `/healthz` until it answers, and tears the subprocess
 * down in `tearDownAfterClass`.
 *
 * Skips the test class if any of the prerequisites are missing:
 *   - the `STS_INTEGRATION_TESTS` env var is not truthy, OR
 *   - the `dart` CLI is not on PATH, OR
 *   - the Dart project at `STS_DART_PROJECT` (or the default path)
 *     does not exist.
 *
 * Configurable env vars:
 *   STS_INTEGRATION_TESTS  Required. "1" / "true" turns the suite on.
 *   STS_DART_PROJECT       Path to the nectar_sts_dart checkout.
 *                          Default: C:\www\dart\nectar_sts_dart
 *   STS_DART_PORT          Port to bind the spawned server to.
 *                          Default: 18787 (so it doesn't collide
 *                          with a dev-mode 8787 server).
 *   STS_DART_BEARER        Bearer token the spawned server requires.
 *                          Default: 'test-bearer'.
 *   STS_DART_VENDING_KEY   16-hex-char DES key. Default matches the
 *                          server's _defaultVendingKeyHex
 *                          ('0123456789ABCDEF') so factories that
 *                          seed the same value into vending_keys
 *                          decode round-trip.
 */
trait SpawnsDartServer
{
    protected static ?Process $dartProcess = null;

    protected static string $dartBaseUrl = '';

    protected static string $dartBearer = '';

    protected static ?string $dartSkipReason = null;

    protected static bool $dartBootAttempted = false;

    /**
     * Lazily boot the Dart subprocess on the first setUp() of the
     * test class. We can't do this in setUpBeforeClass() because
     * Laravel's .env isn't loaded until createApplication() runs in
     * setUp(), so env vars like STS_INTEGRATION_TESTS would not yet
     * be readable.
     */
    public static function bootDartServer(): void
    {
        if (self::$dartBootAttempted) {
            return;
        }
        self::$dartBootAttempted = true;

        // Load .env into $_ENV / $_SERVER ourselves. The test class
        // calls bootDartServer() *before* parent::setUp() (to satisfy
        // PHPUnit 12's risky-test detector), so Laravel's own dotenv
        // loader hasn't run yet. Dotenv is immutable, so when
        // createApplication() runs later it'll be a no-op for our keys.
        self::loadDotenvOnce();

        if (! self::dartIntegrationEnabled()) {
            self::$dartSkipReason = 'STS_INTEGRATION_TESTS not enabled. '
                .'Set $env:STS_INTEGRATION_TESTS=1 to run.';

            return;
        }

        $dartCli = self::findDartCli();
        if ($dartCli === null) {
            self::$dartSkipReason = 'dart CLI not found on PATH.';

            return;
        }

        $projectDir = self::envVar('STS_DART_PROJECT') ?: 'C:\\www\\dart\\nectar_sts_dart';
        if (! is_dir($projectDir.DIRECTORY_SEPARATOR.'bin')) {
            self::$dartSkipReason = "Dart project not found at {$projectDir}.";

            return;
        }

        $port   = (int) (self::envVar('STS_DART_PORT') ?: 18787);
        $bearer = (string) (self::envVar('STS_DART_BEARER') ?: 'test-bearer');
        $vudk   = (string) (self::envVar('STS_DART_VENDING_KEY') ?: '0123456789ABCDEF');

        self::$dartBaseUrl = "http://127.0.0.1:{$port}";
        self::$dartBearer  = $bearer;

        $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sts_int_'.bin2hex(random_bytes(4));
        @mkdir($tmp, 0777, true);

        $env = array_merge(self::inheritedEnv(), [
            'PORT'                 => (string) $port,
            'HOST'                 => '127.0.0.1',
            'VENDING_KEY_HEX'      => $vudk,
            'NECTAR_API_TOKEN'     => $bearer,
            'VENDING_LOG_FILE'     => $tmp.DIRECTORY_SEPARATOR.'vending.json',
            'METER_REGISTRY_FILE'  => $tmp.DIRECTORY_SEPARATOR.'meters.json',
            // Force JSON-file mode (NOT db-backed) so the spawned
            // server doesn't double-write to Laravel's tokens table.
            'STS_DB_HOST'          => '',
        ]);

        self::$dartProcess = new Process(
            command: [$dartCli, 'run', 'bin/server.dart'],
            cwd: $projectDir,
            env: $env,
            timeout: null,
        );
        self::$dartProcess->start();

        // Poll /healthz for up to ~30s.
        $deadline = microtime(true) + 30.0;
        while (microtime(true) < $deadline) {
            if (! self::$dartProcess->isRunning()) {
                $stderr = self::$dartProcess->getErrorOutput();
                $stdout = self::$dartProcess->getOutput();
                self::$dartSkipReason = "Dart server exited early.\n"
                    ."STDOUT:\n{$stdout}\nSTDERR:\n{$stderr}";
                self::$dartProcess = null;

                return;
            }
            try {
                $ctx = stream_context_create([
                    'http' => ['timeout' => 2, 'ignore_errors' => true],
                ]);
                $body = @file_get_contents(self::$dartBaseUrl.'/healthz', false, $ctx);
                if ($body !== false && str_contains((string) $body, '"ok"')) {
                    return;
                }
            } catch (\Throwable) {
                // not yet listening; keep polling
            }
            usleep(250_000);
        }

        $stderr = self::$dartProcess?->getErrorOutput() ?? '';
        $stdout = self::$dartProcess?->getOutput() ?? '';
        self::shutdownDartServer();
        self::$dartSkipReason = "Dart server did not become healthy within 30s.\n"
            ."STDOUT:\n{$stdout}\nSTDERR:\n{$stderr}";
    }

    public static function shutdownDartServer(): void
    {
        if (self::$dartProcess && self::$dartProcess->isRunning()) {
            self::$dartProcess->stop(5);
        }
        self::$dartProcess = null;
    }

    protected function skipIfDartDown(): void
    {
        if (self::$dartSkipReason !== null) {
            $this->markTestSkipped(self::$dartSkipReason);
        }
    }

    protected function dartBaseUrl(): string
    {
        return self::$dartBaseUrl;
    }

    protected function dartBearer(): string
    {
        return self::$dartBearer;
    }

    private static function dartIntegrationEnabled(): bool
    {
        $v = self::envVar('STS_INTEGRATION_TESTS');

        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Read an env var via the same chain Laravel's env() helper uses,
     * so values set in .env (which only populates $_ENV and $_SERVER,
     * not putenv, because Laravel uses immutable Dotenv) are visible
     * here too.
     */
    private static function envVar(string $key): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return (string) $_SERVER[$key];
        }
        $v = getenv($key);

        return $v === false ? null : $v;
    }

    /**
     * Load .env into $_ENV / $_SERVER before parent::setUp() runs.
     * Idempotent. Falls through to .env.testing first if it exists
     * (kept as a no-op fallback in case a contributor still has one
     * around locally).
     */
    private static function loadDotenvOnce(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        $base = dirname(__DIR__, 2);
        foreach (['.env.testing', '.env'] as $file) {
            $path = $base.DIRECTORY_SEPARATOR.$file;
            if (! is_file($path)) {
                continue;
            }
            $dotenv = \Dotenv\Dotenv::createImmutable($base, $file);
            try {
                $dotenv->load();
            } catch (\Throwable) {
                // ignore malformed env files; tests will skip with a
                // clearer reason if STS_INTEGRATION_TESTS ends up unset
            }
        }
    }

    private static function findDartCli(): ?string
    {
        $isWindows = stripos(PHP_OS_FAMILY, 'win') === 0;
        $cmd       = $isWindows ? 'where dart' : 'command -v dart';
        $output    = [];
        $code      = 0;
        @exec($cmd, $output, $code);
        if ($code !== 0 || empty($output)) {
            return null;
        }

        return trim($output[0]);
    }

    /**
     * Symfony Process refuses to inherit the parent environment when
     * an explicit `env` array is provided, so we build the merged
     * environment manually.
     */
    private static function inheritedEnv(): array
    {
        $env = [];
        foreach ($_ENV as $k => $v) {
            $env[$k] = (string) $v;
        }
        foreach ($_SERVER as $k => $v) {
            if (is_string($v)) {
                $env[$k] = $v;
            }
        }

        return $env;
    }
}
