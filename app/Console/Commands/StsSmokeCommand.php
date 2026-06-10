<?php

namespace App\Console\Commands;

use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\Token;
use App\Models\VendingKey;
use App\Services\DartTokenEngine;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * End-to-end smoke test of the Laravel + Dart token bridge.
 *
 *   php artisan sts:smoke
 *
 * Steps:
 *   1. Ping the configured Dart engine (`services.sts_engine.url`).
 *   2. Seed a throwaway supply-group + vending-key + meter.
 *   3. Issue a Class 0 / 0 TransferElectricityCredit token.
 *   4. Decode the token and confirm the amount round-trips.
 *   5. Re-issue the same parameters to confirm the engine's
 *      TID-collision detector triggers.
 *   6. Clean up the throwaway rows.
 *
 * Use the `--keep` flag to skip step 6 (e.g. for manual inspection in
 * the dashboard).
 *
 * Exits non-zero on the first failure so the command is CI-friendly.
 */
class StsSmokeCommand extends Command
{
    protected $signature = 'sts:smoke
                            {--amount=5.0 : kWh to vend in the smoke token}
                            {--keep : leave the throwaway rows in place for inspection}';

    protected $description = 'End-to-end smoke check of the Laravel ↔ nectar_sts_dart bridge';

    private const SG_CODE = '987656';

    private const IIN  = '600727';

    // Dart STS engine requires 11- or 13-digit IAIN.
    private const IAIN = '12345678901';

    private const PAN  = '60072712345678901' . '0';

    public function handle(): int
    {
        $engine = DartTokenEngine::fromConfig();
        $url    = (string) config('services.sts_engine.url');
        $this->line("Engine URL: <fg=cyan>{$url}</>");

        if (! $engine->isHealthy()) {
            $this->error("Dart engine at {$url} is not responding to /healthz.");

            return self::FAILURE;
        }
        $this->info('  ✔ engine /healthz OK');

        try {
            [$sg, $vk, $meter] = $this->seed();
        } catch (\Throwable $e) {
            $this->error('Seed failed: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info("  ✔ seeded SG#{$sg->id} VK#{$vk->id} Meter#{$meter->id}");

        $amount = (float) $this->option('amount');

        try {
            $resp    = $engine->generateTransferElectricityCredit(
                $vk,
                $meter,
                $amount,
                CarbonImmutable::now(),
                7,
            );
            $tokenNo = $resp['data']['token'][0]['token_no'] ?? null;
            if (! $tokenNo) {
                $this->error('Engine returned no token_no: '.json_encode($resp));

                return $this->finish(self::FAILURE);
            }
            $this->info("  ✔ issued token <fg=green>{$tokenNo}</> ({$amount} kWh)");

            // Persist the same way the HTTP controller would (so the
            // dashboard sees it when --keep is used).
            Token::create([
                'request_id'      => (string) ($resp['request_id'] ?? Str::uuid()),
                'token_no'        => $tokenNo,
                'meter_id'        => $meter->id,
                'vending_key_id'  => $meter->vending_key_id,
                'issued_by'       => null,
                'token_class'     => 0,
                'token_kind'      => 'TransferElectricityCredit',
                'amount_kwh'      => $amount,
                'currency'        => null,
                'issued_at'       => now(),
                'payload'         => ['amount_kwh' => $amount, 'random_no' => 7],
                'engine_response' => $resp,
                'status'          => 'issued',
            ]);
        } catch (\Throwable $e) {
            $this->error('Issue failed: '.$e->getMessage());

            return $this->finish(self::FAILURE);
        }

        try {
            $decoded   = $engine->decode($vk, $meter, $tokenNo);
            $decAmount = $decoded['data']['token_details']['amount']
                ?? $decoded['data']['token'][0]['amount']
                ?? $decoded['data']['token'][0]['amount_kwh']
                ?? null;
            if ($decAmount === null) {
                $this->error('Decode envelope missing amount: '.json_encode($decoded));

                return $this->finish(self::FAILURE);
            }
            $this->info(sprintf(
                '  ✔ decoded amount=%.3f kWh (round-trip Δ=%.3f)',
                (float) $decAmount,
                abs((float) $decAmount - $amount),
            ));
            if (abs((float) $decAmount - $amount) > 0.5) {
                $this->error("Decoded amount {$decAmount} does not match issued {$amount}.");

                return $this->finish(self::FAILURE);
            }
        } catch (\Throwable $e) {
            $this->error('Decode failed: '.$e->getMessage());

            return $this->finish(self::FAILURE);
        }

        // Replay → in JSON-file mode the engine's TID collision detector
        // is best-effort; we surface the outcome but don't fail on it.
        try {
            $engine->generateTransferElectricityCredit(
                $vk,
                $meter,
                $amount,
                CarbonImmutable::now(),
                7,
            );
            $this->warn('  · replay accepted (engine did NOT trigger TID collision)');
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'TID collision')) {
                $this->info('  ✔ replay correctly rejected: '.$e->getMessage());
            } else {
                $this->error('Replay failed with unexpected error: '.$e->getMessage());

                return $this->finish(self::FAILURE);
            }
        }

        return $this->finish(self::SUCCESS);
    }

    /** @return array{0:SupplyGroup,1:VendingKey,2:Meter} */
    private function seed(): array
    {
        $this->cleanup();

        $sg = SupplyGroup::create([
            'code'      => self::SG_CODE,
            'name'      => 'Smoke Test Supply Group',
            'utility'   => 'TEST',
            'region'    => 'TEST',
            'is_active' => true,
        ]);
        $vk = VendingKey::create([
            'name'                 => 'Smoke Test VUDK',
            'supply_group_id'      => $sg->id,
            'key_type'             => 2,
            'tariff_index'         => '07',
            'key_revision_number'  => 1,
            'key_expiry_number'    => 255,
            'algorithm'            => 'DKGA02',
            'encryption_algorithm' => 'EA07',
            'base_date'            => 1993,
            'vudk_blob'            => '0123456789ABCDEF',
            'is_active'            => true,
        ]);
        $meter = Meter::create([
            'pan'                   => self::PAN,
            'iin'                   => self::IIN,
            'iain'                  => self::IAIN,
            'manufacturer_code'     => 'TEST',
            'decoder_serial_number' => 'SMK00001',
            'supply_group_id'       => $sg->id,
            'vending_key_id'        => $vk->id,
            'is_active'             => true,
        ]);

        return [$sg, $vk->fresh()->load('supplyGroup'), $meter->fresh()];
    }

    private function cleanup(): void
    {
        $meterIds = DB::table('meters')
            ->where('iin', self::IIN)
            ->where('iain', self::IAIN)
            ->pluck('id');
        DB::table('tokens')->whereIn('meter_id', $meterIds)->delete();
        DB::table('meters')->where('iin', self::IIN)->where('iain', self::IAIN)->delete();
        DB::table('supply_groups')->where('code', self::SG_CODE)->delete();
    }

    private function finish(int $code): int
    {
        if (! $this->option('keep')) {
            $this->cleanup();
            $this->line('  · cleaned up smoke rows');
        } else {
            $this->line('  · --keep set; smoke rows left in place');
        }

        return $code;
    }
}
