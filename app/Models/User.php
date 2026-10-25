<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Notifications\VerifyEmail;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_OWNER = 'owner';

    public const ROLE_SUPER_ADMIN = 'super_admin';

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (! $user->public_id) {
                $user->public_id = (string) Str::ulid();
            }
        });
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail());
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mfa_secret' => 'encrypted',
            'mfa_enabled_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class, 'owner_id');
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isCustomer(): bool
    {
        return $this->hasRole(self::ROLE_CUSTOMER);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(self::ROLE_OWNER);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::ROLE_SUPER_ADMIN);
    }

    public function termsAcceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }
}
