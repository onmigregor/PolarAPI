<?php

namespace Modules\CompanyRoute\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyRouteFactory extends Factory
{
    protected $model = \Modules\CompanyRoute\Models\CompanyRoute::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company,
            'code' => $this->faker->unique()->numerify('CR-###'),
            'route_name' => $this->faker->optional()->word,
            'rif' => $this->faker->bothify('J-########-#'),
            'fiscal_address' => $this->faker->address,
            'region_id' => \Modules\Region\Models\Region::factory(),
            'db_name' => $this->faker->unique()->slug,
        ];
    }
}
