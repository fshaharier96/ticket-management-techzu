<?php

namespace App\Providers;

use App\Services\Sla\BusinessHoursCalculator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
         $this->app->bind(BusinessHoursCalculator::class, fn () => BusinessHoursCalculator::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
         Event::subscribe(TicketNotificationSubscriber::class);
    }
}
