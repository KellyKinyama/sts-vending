<?php

namespace Database\Factories;

use App\Models\SupplyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyGroup>
 */
class SupplyGroupFactory extends Factory
{
    protected $model = SupplyGroup::class;

    public function definition(): array
    {
        return [
            'code'      => (string) fake()->unique()->numerify('######'),
            'name'      => fake()->company().' Supply Group',
            'utility'   => fake()->randomElement(['ELEC', 'WATER', 'GAS']),
            'region'    => fake()->state(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
