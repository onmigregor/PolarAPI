<?php

namespace Modules\Region\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Region\Models\Region;

class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        return [
            'citCode' => $this->faker->unique()->bothify('CT###'),
            'citName' => $this->faker->city,
            'staCode' => $this->faker->bothify('ST##'),
        ];
    }
}
