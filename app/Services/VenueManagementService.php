<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Venue;
use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class VenueManagementService
{
    /**
     * Onboard a new venue onto the platform with optional operator account linking.
     *
     * @param  array<string, mixed>  $data
     */
    public function onboardVenue(
        array $data,
        ?int $operatorId = null,
        ?string $operatorEmail = null,
    ): Venue {
        if (empty($data['slug'])) {
            $baseSlug = Str::slug((string) $data['name']);
            $slug = $baseSlug;
            $counter = 1;

            while (Venue::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $data['slug'] = $slug;
        }

        $venue = Venue::create($data);

        if ($operatorId !== null || ! empty($operatorEmail)) {
            $this->linkOperatorByCredentials($venue, $operatorId, $operatorEmail);
        }

        return $venue->load('operators');
    }

    /**
     * Resolve an operator account by user ID or email and link to the venue.
     */
    public function linkOperatorByCredentials(
        Venue $venue,
        ?int $userId = null,
        ?string $email = null,
    ): User {
        if ($userId !== null) {
            $user = User::findOrFail($userId);
        } elseif (! empty($email)) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => Str::headline(Str::before($email, '@')),
                    'role' => UserRole::VenueOperator,
                ],
            );
        } else {
            throw new InvalidArgumentException('Either user_id or email must be provided to link an operator.');
        }

        return $this->linkOperator($venue, $user);
    }

    /**
     * Link a user as a designated venue operator for the venue.
     */
    public function linkOperator(Venue $venue, User $user): User
    {
        $attributes = [
            'venue_id' => $venue->id,
        ];

        if (! $user->isSuperadmin()) {
            $attributes['role'] = UserRole::VenueOperator;
        }

        $user->update($attributes);

        return $user->fresh();
    }

    /**
     * Unlink a venue operator user from this venue.
     */
    public function unlinkOperator(Venue $venue, User $user): User
    {
        if ($user->venue_id === $venue->id) {
            $user->update([
                'venue_id' => null,
            ]);
        }

        return $user->fresh();
    }

    /**
     * Update venue SaaS subscription status and optional expiration timestamp.
     */
    public function updateSubscription(
        Venue $venue,
        SubscriptionStatus $status,
        ?DateTimeInterface $expiresAt = null,
    ): Venue {
        $venue->update([
            'subscription_status' => $status,
            'subscription_expires_at' => $expiresAt,
        ]);

        return $venue->fresh();
    }
}
