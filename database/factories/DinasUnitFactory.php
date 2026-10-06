<?php

namespace Database\Factories;

use App\Models\DinasUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DinasUnit>
 */
class DinasUnitFactory extends Factory
{
    protected $model = DinasUnit::class;

    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->bothify('DU-###'));

        return [
            'name'        => 'Dinas ' . fake()->unique()->city(),
            'code'        => $code,
            'description' => null,
            'is_active'   => true,
            'sort_order'  => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
