<?php

namespace App\Providers;

use App\Contracts\Notifications\NotificationDeliveryContract;
use App\Contracts\Payments\PaymentGatewayContract;
use App\Contracts\Payments\PaymentWebhookContract;
use App\Models\Business;
use App\Models\Venue;
use App\Policies\BusinessPolicy;
use App\Policies\VenuePolicy;
use App\Services\Notifications\Delivery\MockNotificationDelivery;
use App\Services\Payments\Gateways\MockPaymentGateway;
use RuntimeException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NotificationDeliveryContract::class, function (): NotificationDeliveryContract {
            return new MockNotificationDelivery();
        });

        $this->app->bind(PaymentGatewayContract::class, function (): PaymentGatewayContract {
            $driver = (string) config('services.payment_gateway.driver', 'mock');

            if ($driver === 'mock' && ! app()->environment('production')) {
                return new MockPaymentGateway();
            }

            throw new RuntimeException('No safe payment gateway adapter is configured for driver [' . $driver . '].');
        });

        $this->app->bind(PaymentWebhookContract::class, function (): PaymentWebhookContract {
            $driver = (string) config('services.payment_gateway.driver', 'mock');

            if ($driver === 'mock' && ! app()->environment('production')) {
                return new MockPaymentGateway();
            }

            throw new RuntimeException('No safe webhook adapter is configured for driver [' . $driver . '].');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(Venue::class, VenuePolicy::class);
    }
}
