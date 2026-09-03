<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property Role $role
 * @property int|null $area_id
 * @property Area|null $area
 */
#[Fillable(['name', 'email', 'password', 'role', 'area_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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
            'role' => Role::class,
        ];
    }

    protected function scopeRole($query, string $role): void
    {
        $query->where('role', $role);
    }

    /**
     * Count how many admin accounts are still active (not soft-deleted).
     */
    public static function activeAdminCount(): int
    {
        return static::role(Role::Admin->value)->count();
    }

    /**
     * Count how many client accounts are still active (not soft-deleted).
     */
    public static function activeClientCount(): int
    {
        return static::role(Role::Client->value)->count();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isClient(): bool
    {
        return $this->role === Role::Client;
    }

    public function hasRole(Role|string $role): bool
    {
        return $this->role === ($role instanceof Role ? $role : Role::from($role));
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
