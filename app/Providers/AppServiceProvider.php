<?php

namespace App\Providers;

use App\Repositories\BookingRepository;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\EventRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\EventRepository;
use App\Repositories\PaymentRepository;
use App\Services\Contracts\EventSlotCounterInterface;
use App\Services\Contracts\FakePaymentGatewayInterface;
use App\Services\FakePaymentGateway;
use App\Services\InMemoryEventSlotCounter;
use App\Services\RedisEventSlotCounter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(FakePaymentGatewayInterface::class, FakePaymentGateway::class);

        $this->app->bind(EventSlotCounterInterface::class, function () {
            if ($this->app->environment('testing')) {
                return new InMemoryEventSlotCounter;
            }

            return new RedisEventSlotCounter;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
