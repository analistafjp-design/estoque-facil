<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'nome' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'categoria' => fake()->randomElement(['Geral', 'Bebidas', 'Limpeza', 'Papelaria', 'Eletrônicos']),
            'quantidade_atual' => fake()->numberBetween(0, 100),
            'estoque_minimo' => fake()->numberBetween(5, 20),
        ];
    }

    public function estoqueBaixo(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantidade_atual' => 2,
            'estoque_minimo' => 10,
        ]);
    }
}
