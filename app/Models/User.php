<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\PlatformRole;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlidRouteKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property PlatformRole|string $global_role
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ulid', 'tenant_id', 'name', 'email', 'password', 'global_role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasUlidRouteKey, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'two_factor_confirmed_at' => 'datetime',
            'global_role' => PlatformRole::class,
        ];
    }

    public function isAdministrator(): bool
    {
        return $this->global_role === PlatformRole::Administrator;
    }

    public function isFaculty(): bool
    {
        return $this->global_role === PlatformRole::Faculty;
    }

    public function isStudent(): bool
    {
        return $this->global_role === PlatformRole::Student;
    }

    /**
     * @return BelongsToMany<Section, $this>
     */
    public function facultySections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'section_faculty')
            ->withPivot(['tenant_id', 'role'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['tenant_id', 'seat_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<SectionSimulation, $this>
     */
    public function createdSectionSimulations(): HasMany
    {
        return $this->hasMany(SectionSimulation::class, 'created_by_user_id');
    }
}
