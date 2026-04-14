<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,

    // Hospital modules
    Modules\Booking\Providers\BookingServiceProvider::class,
    Modules\Clinic\Providers\ClinicServiceProvider::class,
];
