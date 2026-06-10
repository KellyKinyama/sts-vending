<?php

namespace Database\Factories;

use App\Models\Meter;
use App\Models\SupplyGroup;
use App\Models\VendingKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 *
 * Default state produces a fully-linked meter (supply group + vending
 * key both created via sub-factories). The Dart STS engine validates
 * the IAIN as either 11 or 13 digits (Luhn check digit included). We
 * generate an 11-digit IAIN and pad the 17-digit `iin||iain` with a
 * single trailing digit so the 18-char `pan` constraint passes.
 */
class MeterFactory extends Factory
{
    protected $model = Meter::class;

    public function definition(): array
    {
        $iin  = (string) fake()->numerify('######');
        $iain = (string) fake()->numerify('###########'); // 11 digits

        return [
            'pan'                   => $iin.$iain.((string) fake()->numberBetween(0, 9)),
            'iin'                   => $iin,
            'iain'                  => $iain,
            'manufacturer_code'     => fake()->bothify('??##'),
            'decoder_serial_number' => strtoupper(fake()->bothify('????####')),
            'supply_group_id'       => SupplyGroup::factory(),
            'vending_key_id'        => VendingKey::factory(),
            'tariff_id'             => null,
            'customer_id'           => null,
            'location'              => fake()->city().', '.fake()->stateAbbr(),
            'balance_kwh'           => 0,
            'installed_at'          => now()->subDays(30),
            'is_active'             => true,
        ];
    }

    public function forSupplyGroup(SupplyGroup $sg, VendingKey $key): static
    {
        return $this->state(fn () => [
            'supply_group_id' => $sg->id,
            'vending_key_id'  => $key->id,
        ]);
    }
}
