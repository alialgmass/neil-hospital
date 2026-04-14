<?php

namespace Modules\Booking\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Booking\Repositories\BookingRepository;
use Modules\Booking\Repositories\Contracts\BookingRepositoryInterface;

class BookingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface → concrete implementation (DIP)
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    }
}
