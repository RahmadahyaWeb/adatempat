<?php

namespace App\Providers;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Listeners\AuditTwoFactorAuthentication;
use App\Models\Business;
use App\Models\Space;
use App\Models\Venue;
use App\Policies\BusinessPolicy;
use App\Policies\SpacePolicy;
use App\Policies\VenuePolicy;
use App\Services\Payments\FakePaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PaymentGatewayInterface::class,
            fn () => match (config('payment.default', 'fake')) {
                default => new FakePaymentGateway,
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
        Gate::policy(Space::class, SpacePolicy::class);

        Event::listen([
            TwoFactorAuthenticationConfirmed::class,
            TwoFactorAuthenticationDisabled::class,
        ], AuditTwoFactorAuthentication::class);

        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
