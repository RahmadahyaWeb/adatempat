<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Mail\MagicLinkMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

test('submitting a new email creates player user JIT, sends magic link email, caches nonce, and returns 200 with cooldown', function () {
    Mail::fake();

    $response = $this->postJson('/auth/magic-link', [
        'email' => 'newplayer@example.com',
        'intended' => '/checkout',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'If this email address is valid, a login link has been sent to your inbox.',
            'cooldown_seconds' => 60,
        ]);

    $user = User::where('email', 'newplayer@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::Player)
        ->and($user->name)->toBe('newplayer');

    Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use ($user) {
        return $mail->hasTo('newplayer@example.com')
            && str_contains($mail->url, "/auth/magic-link/verify/{$user->id}")
            && str_contains($mail->url, 'token=')
            && str_contains($mail->url, 'signature=');
    });
});

test('submitting an existing email reuses existing user, sends link, and returns generic 200', function () {
    Mail::fake();

    $existingUser = User::factory()->create([
        'email' => 'operator@example.com',
        'role' => UserRole::VenueOperator,
        'name' => 'Existing Operator',
    ]);

    $response = $this->postJson('/auth/magic-link', [
        'email' => 'operator@example.com',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'message' => 'If this email address is valid, a login link has been sent to your inbox.',
            'cooldown_seconds' => 60,
        ]);

    expect(User::where('email', 'operator@example.com')->count())->toBe(1);

    $user = User::where('email', 'operator@example.com')->first();
    expect($user->role)->toBe(UserRole::VenueOperator);

    Mail::assertSent(MagicLinkMail::class, function (MagicLinkMail $mail) use ($existingUser) {
        return $mail->hasTo('operator@example.com')
            && str_contains($mail->url, "/auth/magic-link/verify/{$existingUser->id}");
    });
});

test('submitting an invalid email format fails validation with 422', function () {
    Mail::fake();

    $response = $this->postJson('/auth/magic-link', [
        'email' => 'not-a-valid-email',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    Mail::assertNothingSent();
});

test('rate limit is enforced at 5 attempts per 15 minutes per IP and email combination', function () {
    Mail::fake();

    $payload = ['email' => 'ratelimit@example.com'];

    // 5 allowed attempts
    for ($i = 0; $i < 5; $i++) {
        $response = $this->postJson('/auth/magic-link', $payload);
        $response->assertStatus(200);
    }

    // 6th attempt should be blocked with 429
    $blockedResponse = $this->postJson('/auth/magic-link', $payload);
    $blockedResponse->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJson([
            'status' => 'error',
            'message' => 'Too many login attempts. Please try again later.',
        ]);

    // Another email from the same IP should still be allowed
    $otherEmailResponse = $this->postJson('/auth/magic-link', [
        'email' => 'different@example.com',
    ]);
    $otherEmailResponse->assertStatus(200);
});

test('visiting a valid signed magic link authenticates user, consumes nonce atomically, and redirects to intended url', function () {
    $user = User::factory()->create([
        'email' => 'verify@example.com',
        'role' => UserRole::Player,
    ]);

    $token = Str::random(40);
    Cache::put(MagicLinkController::nonceCacheKey($user->id, $token), true, now()->addMinutes(15));

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
            'intended' => '/checkout/review',
        ]
    );

    // Initial session before verification
    $response = $this->withSession(['pre_auth_data' => 'active'])
        ->get($signedUrl);

    $response->assertRedirect('/checkout/review');
    $this->assertAuthenticatedAs($user);

    // Nonce must be consumed (Cache::pull) to prevent replay attack
    expect(Cache::get(MagicLinkController::nonceCacheKey($user->id, $token)))->toBeNull();
});

test('session ID is regenerated upon successful magic link verification to prevent fixation', function () {
    $user = User::factory()->create([
        'email' => 'sessionfixation@example.com',
        'role' => UserRole::Player,
    ]);

    $token = Str::random(40);
    Cache::put(MagicLinkController::nonceCacheKey($user->id, $token), true, now()->addMinutes(15));

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
        ]
    );

    $this->withSession(['fixture' => 'pre_session']);
    $initialSessionId = session()->getId();

    $this->get($signedUrl);

    $newSessionId = session()->getId();

    expect($newSessionId)->not->toBe($initialSessionId)
        ->and($newSessionId)->not->toBeEmpty();
    $this->assertAuthenticatedAs($user);
});

test('replaying an already consumed magic link fails and renders user-friendly error with redirect', function () {
    $user = User::factory()->create([
        'email' => 'replay@example.com',
        'role' => UserRole::Player,
    ]);

    $token = Str::random(40);
    // Nonce is NOT present in Cache (already consumed or expired)

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
            'intended' => '/dashboard',
        ]
    );

    $response = $this->get($signedUrl);

    $response->assertRedirect('/')
        ->assertSessionHas('error', 'This login link has already been used or expired. Please request a new one.');

    $this->assertGuest();
});

test('replaying already consumed magic link via json request returns 400 error', function () {
    $user = User::factory()->create([
        'email' => 'replayjson@example.com',
        'role' => UserRole::Player,
    ]);

    $token = Str::random(40);

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
        ]
    );

    $response = $this->getJson($signedUrl);

    $response->assertStatus(400)
        ->assertJson([
            'status' => 'error',
            'message' => 'This login link has already been used or expired. Please request a new one.',
        ]);

    $this->assertGuest();
});

test('tampered signature returns 403 with user-friendly error for json and redirect for web', function () {
    $user = User::factory()->create(['email' => 'tampered@example.com']);
    $token = Str::random(40);
    Cache::put(MagicLinkController::nonceCacheKey($user->id, $token), true, now()->addMinutes(15));

    $validSignedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
        ]
    );

    $tamperedUrl = $validSignedUrl.'&tampered=true';

    // JSON request
    $jsonResponse = $this->getJson($tamperedUrl);
    $jsonResponse->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'This login link is invalid or has expired. Please request a new one.',
        ]);

    // Browser request
    $webResponse = $this->get($tamperedUrl);
    $webResponse->assertRedirect('/')
        ->assertSessionHas('error', 'This login link is invalid or has expired. Please request a new one.');

    $this->assertGuest();
});

test('expired signed url returns 403 with user-friendly guidance', function () {
    $user = User::factory()->create(['email' => 'expired@example.com']);
    $token = Str::random(40);

    $expiredSignedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->subMinute(), // already expired
        [
            'user' => $user->id,
            'token' => $token,
        ]
    );

    $response = $this->getJson($expiredSignedUrl);
    $response->assertStatus(403)
        ->assertJson([
            'status' => 'error',
            'message' => 'This login link is invalid or has expired. Please request a new one.',
        ]);

    $this->assertGuest();
});

test('intended external url is sanitized to fallback dashboard to prevent open redirect', function () {
    $user = User::factory()->create(['email' => 'openredirect@example.com']);
    $token = Str::random(40);
    Cache::put(MagicLinkController::nonceCacheKey($user->id, $token), true, now()->addMinutes(15));

    $signedUrl = URL::temporarySignedRoute(
        'auth.magic-link.verify',
        now()->addMinutes(15),
        [
            'user' => $user->id,
            'token' => $token,
            'intended' => 'https://malicious-external-site.com',
        ]
    );

    $response = $this->get($signedUrl);
    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});
