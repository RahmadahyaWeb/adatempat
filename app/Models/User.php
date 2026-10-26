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

    public function getRouteKeyName()
    {
        return 'public_id';
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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'owner_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'recipient_user_id');
    }

    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class, 'owner_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class, 'owner_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'recipient_user_id');
    }

    public function openedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'opened_by');
    }

    public function respondedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'against_user_id');
    }

    public function disputeMessages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class, 'sender_user_id');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_user_id');
    }

    public function convertedGuestSessions(): HasMany
    {
        return $this->hasMany(GuestSession::class, 'converted_to_user_id');
    }
}
