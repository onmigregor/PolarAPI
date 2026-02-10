<?php

namespace Modules\Client\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = \Modules\Client\Models\Client::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'code' => $this->faker->unique()->numerify('CLIENT-###'),
            'rif' => $this->faker->bothify('J-########-#'),
            'fiscal_address' => $this->faker->address,
            'region_id' => \Modules\Region\Models\Region::factory(),
            'db_name' => $this->faker->unique()->slug,
        ];
    }
}
