<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Company;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'product_id' => Product::factory(),
            'user_id' => User::factory(),
            'tipo' => fake()->randomElement(MovementType::cases()),
            'quantidade' => fake()->numberBetween(1, 50),
            'observacao' => null,
        ];
    }
}
