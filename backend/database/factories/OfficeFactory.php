<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Office> */
class OfficeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Escritório '.fake()->lastName(),
            'cnpj' => CnpjGenerator::make(),
            'email' => fake()->companyEmail(),
            'plan' => 'starter',
            'plan_status' => 'active',
        ];
    }

    public function plan(string $plan, string $status = 'active'): static
    {
        return $this->state(fn () => ['plan' => $plan, 'plan_status' => $status]);
    }
}
