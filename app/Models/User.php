<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'company_id', 'papel'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'papel' => UserRole::class,
        ];
    }

    // User NÃO usa BelongsToCompany/CompanyScope de propósito — a spec só
    // pede o isolamento automático em products, stock_movements e
    // invitations. Filtrar usuário por empresa também esconderia o próprio
    // usuário logado de si mesmo antes dele estar autenticado (ex.: no
    // meio do login), então esse filtro é feito à mão nas telas que listam
    // colegas de empresa (Auth::user()->company->users).
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function invitationsSent(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by_id');
    }

    public function isAdmin(): bool
    {
        return $this->papel === UserRole::Admin;
    }
}
