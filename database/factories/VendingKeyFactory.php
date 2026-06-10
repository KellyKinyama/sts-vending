<?php

namespace Database\Factories;

use App\Models\SupplyGroup;
use App\Models\VendingKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendingKey>
 *
 * Default state produces a DKGA02 / EA07 (single-DES + STA) key with a
 * deterministic 8-byte VUDK matching the Dart server's default demo
 * key `0123456789ABCDEF`. Override with state methods or attribute
 * overrides where a different shape is needed.
 */
class VendingKeyFactory extends Factory
{
    protected $model = VendingKey::class;

    public function definition(): array
    {
        return [
            'name'                 => 'Test VUDK '.fake()->unique()->bothify('##??'),
            'supply_group_id'      => SupplyGroup::factory(),
            'key_type'             => 2,
            'tariff_index'         => '07',
            'key_revision_number'  => 1,
            'key_expiry_number'    => 255,
            'algorithm'            => 'DKGA02',
            'encryption_algorithm' => 'EA07',
            'base_date'            => 1993,
            // 8 bytes (16 hex) = DKGA02 single-DES key. Matches the
            // Dart server's _defaultVendingKeyHex so tokens minted by
            // the Dart side decode against this key.
            'vudk_blob'            => '0123456789ABCDEF',
            'is_active'            => true,
        ];
    }

    public function dkga04(): static
    {
        return $this->state(fn () => [
            'algorithm' => 'DKGA04',
            // 20 bytes (40 hex) for triple-DES style VUDK.
            'vudk_blob' => str_repeat('0123456789ABCDEF', 2).'01234567',
        ]);
    }

    public function withSupplyGroup(SupplyGroup $sg): static
    {
        return $this->state(fn () => ['supply_group_id' => $sg->id]);
    }
}
