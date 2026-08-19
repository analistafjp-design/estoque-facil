<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'papel' => UserRole::Operador,
            'status' => InvitationStatus::Pendente,
            'token' => Str::random(64),
            'invited_by_id' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
