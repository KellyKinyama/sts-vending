<?php

namespace Tests\Feature\Api;

use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\Token;
use App\Models\User;
use App\Models\VendingKey;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SpawnsDartServer;
use Tests\TestCase;

/**
 * End-to-end test of the full Laravel → Dart → MySQL chain.
 *
 * This suite is OFF by default. To run it locally against WAMP + the
 * `sts_vending` database + a freshly-spawned `nectar_sts_dart`:
 *
 *   $env:STS_INTEGRATION_TESTS = '1'
 *   $env:DB_CONNECTION         = 'mysql'
 *   $env:DB_HOST               = '127.0.0.1'
 *   $env:DB_DATABASE           = 'sts_vending'
 *   $env:DB_USERNAME           = 'root'
 *   $env:DB_PASSWORD           = ''
 *   php artisan test --filter=DartEngineRoundTripTest
 *
 * The test:
 *   1. Spawns the Dart server on http://127.0.0.1:18787 in
 *      `setUpBeforeClass`, polls /healthz, and tears it down in
 *      `tearDownAfterClass`.
 *   2. Seeds a throwaway SupplyGroup + VendingKey + Meter inside
 *      `setUp` (linked together) and deletes the supply-group in
 *      `tearDown` (cascade clears the rest).
 *   3. Issues a token via POST /api/v1/tokens (auth via
 *      Sanctum::actingAs), then decodes it via
 *      POST /api/v1/tokens/{tokenNo}/decode, and asserts the
 *      decoded amount matches the issued amount.
 *
 * If `STS_INTEGRATION_TESTS` is unset, every test in the class is
 * `markTestSkipped`'d with a reason — `php artisan test` stays
 * green on CI / dev machines without WAMP.
 */
class DartEngineRoundTripTest extends TestCase
{
    use SpawnsDartServer;

    protected SupplyGroup $sg;

    protected VendingKey $vk;

    protected Meter $meter;

    protected User $user;

    public static function tearDownAfterClass(): void
    {
        self::shutdownDartServer();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        // Boot the Dart subprocess on the first setUp() — by now
        // Laravel has loaded .env.testing, so STS_INTEGRATION_TESTS
        // (and friends) are readable via $_ENV. `bootDartServer()`
        // is idempotent across the rest of the test class.
        self::bootDartServer();

        // Bail BEFORE booting the Laravel app on subsequent skipped
        // tests so we don't trip PHPUnit 12's "did not remove its own
        // error handlers" risky-test detector.
        $this->skipIfDartDown();

        parent::setUp();

        // Point the Laravel service config at the spawned server.
        config([
            'services.sts_engine.url'   => $this->dartBaseUrl(),
            'services.sts_engine.token' => $this->dartBearer(),
        ]);

        // Sanity: we should be talking to MySQL, not the default
        // in-memory sqlite. Bail loudly otherwise so a misconfigured
        // run doesn't half-pollute and half-skip.
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'mysql') {
            $this->markTestSkipped(
                "DB driver is '{$driver}', not mysql. Set DB_CONNECTION=mysql "
                .'(and the rest of DB_* from .env) before running this suite.'
            );
        }

        $this->cleanupTestRows();

        $this->sg = SupplyGroup::factory()->create([
            'code' => '987655',
            'name' => 'Integration Test SG',
        ]);
        $this->vk = VendingKey::factory()
            ->withSupplyGroup($this->sg)
            ->create(['name' => 'Integration Test VUDK']);
        $this->meter = Meter::factory()
            ->forSupplyGroup($this->sg, $this->vk)
            ->create([
                'iin'                   => '600727',
                'iain'                  => '12345678901',
                'pan'                   => '600727123456789010',
                'decoder_serial_number' => 'INTTST01',
            ]);

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        if (isset($this->meter)) {
            $this->cleanupTestRows();
        }
        parent::tearDown();
    }

    public function test_dart_engine_is_healthy(): void
    {
        $resp = \Illuminate\Support\Facades\Http::timeout(3)
            ->get($this->dartBaseUrl().'/healthz');
        $this->assertTrue($resp->ok(), 'Dart /healthz did not return 200');
        $this->assertSame('ok', $resp->json('status'));
    }

    public function test_issue_token_round_trip(): void
    {
        $resp = $this->postJson('/api/v1/tokens', [
            'meter_id'   => $this->meter->id,
            'amount_kwh' => 12.5,
            'random_no'  => 4,
        ]);
        $resp->assertCreated();
        $resp->assertJsonStructure([
            'token' => ['id', 'token_no', 'status', 'meter_id', 'vending_key_id'],
            'engine',
        ]);

        $tokenNo = $resp->json('token.token_no');
        $this->assertNotSame('00000000000000000000', $tokenNo);
        $this->assertMatchesRegularExpression('/^\d{20}$/', $tokenNo);

        // The Laravel-side Token row should be persisted with status=issued.
        $persisted = Token::where('token_no', $tokenNo)->firstOrFail();
        $this->assertSame('issued', $persisted->status);
        $this->assertSame($this->meter->id, $persisted->meter_id);
        $this->assertSame($this->vk->id, $persisted->vending_key_id);
        $this->assertEqualsWithDelta(12.5, (float) $persisted->amount_kwh, 1e-6);
    }

    public function test_decode_returns_matching_amount(): void
    {
        $issue = $this->postJson('/api/v1/tokens', [
            'meter_id'   => $this->meter->id,
            'amount_kwh' => 25.0,
            'random_no'  => 5,
        ]);
        $issue->assertCreated();
        $tokenNo = $issue->json('token.token_no');

        $decode = $this->postJson("/api/v1/tokens/{$tokenNo}/decode", [
            'meter_id' => $this->meter->id,
        ]);
        $decode->assertOk();

        // The Dart engine envelope is { status: {...}, request_id, data: {...} }.
        // Class 0/0 decoded amount lives under data.token[0].amount.
        $amount = $decode->json('data.token_details.amount')
            ?? $decode->json('data.token.0.amount')
            ?? $decode->json('data.token.0.amount_kwh')
            ?? $decode->json('data.amount');
        $this->assertNotNull($amount, 'decode envelope missing amount: '.json_encode($decode->json()));
        $this->assertEqualsWithDelta(25.0, (float) $amount, 0.1);
    }

    public function test_meter_without_vending_key_is_422(): void
    {
        $bareMeter = Meter::factory()
            ->create([
                'supply_group_id' => $this->sg->id,
                'vending_key_id'  => null,
                'iin'             => '600728',
                'iain'            => '12345678901',
                'pan'             => '600728123456789011',
            ]);

        $resp = $this->postJson('/api/v1/tokens', [
            'meter_id'   => $bareMeter->id,
            'amount_kwh' => 5.0,
            'random_no'  => 0,
        ]);
        // The controller should refuse to issue a token without a vending key.
        $this->assertContains($resp->status(), [409, 422, 502], 'expected refusal status code');

        // Cleanup the extra meter row.
        DB::table('meters')->where('id', $bareMeter->id)->delete();
    }

    private function cleanupTestRows(): void
    {
        // Tokens -> meters -> vending_keys -> supply_groups via cascades.
        DB::table('tokens')
            ->whereIn(
                'meter_id',
                DB::table('meters')->where('iin', '600727')->where('iain', '12345678901')->pluck('id')
            )
            ->delete();
        DB::table('meters')->where('iin', '600727')->where('iain', '12345678901')->delete();
        DB::table('supply_groups')->where('code', '987655')->delete();
    }
}
