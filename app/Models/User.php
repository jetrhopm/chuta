<?php

namespace App\Models;

use App\Domain\Access\Enums\AdminRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'must_change_password',
    ];

    /**
     * Los datos del segundo factor no se serializan nunca: acabarian en
     * respuestas JSON, en registros o en la sesion.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Los correos se guardan siempre en minusculas para que la busqueda y la
     * unicidad no dependan de como los haya escrito la persona.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => mb_strtolower(trim($value)),
        );
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(AdminRole::SuperAdmin->value);
    }

    /**
     * Solo entra al panel quien tiene un rol administrativo y la cuenta activa.
     * Los clientes de la tienda usan sus propias pantallas.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->hasAnyRole(array_column(AdminRole::cases(), 'value'));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }
}
