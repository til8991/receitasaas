<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'cnpj' => CnpjGenerator::make(),
            'legal_name' => fake()->company().' LTDA',
            'uf' => 'RS',
            'municipality_ibge' => 4314902,
            'current_regime' => 'simples_nacional',
            'simples_annex' => 'I',
            'activities' => ['01'],
            'revenue_12m' => 1200000,
            'purchases_12m' => 500000,
            'b2b_sales_pct' => 60,
            'default_ncm' => '22030000',
            'default_cst' => '000',
            'default_c_class_trib' => '000001',
            'active' => true,
        ];
    }
}
