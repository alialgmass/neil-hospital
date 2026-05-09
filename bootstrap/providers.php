<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Modules\Accounting\Providers\AccountingServiceProvider;
use Modules\Admin\Providers\AdminServiceProvider;
use Modules\Booking\Providers\BookingServiceProvider;
use Modules\Clinic\Providers\ClinicServiceProvider;
use Modules\Doctor\Providers\DoctorServiceProvider;
use Modules\HR\Providers\HRServiceProvider;
use Modules\Insurance\Providers\InsuranceServiceProvider;
use Modules\Inventory\Providers\InventoryServiceProvider;
use Modules\Labs\Providers\LabsServiceProvider;
use Modules\Reporting\Providers\ReportingServiceProvider;
use Modules\Surgery\Providers\SurgeryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TelescopeServiceProvider::class,

    // Hospital modules
    BookingServiceProvider::class,
    ClinicServiceProvider::class,
    SurgeryServiceProvider::class,
    AccountingServiceProvider::class,
    InventoryServiceProvider::class,
    DoctorServiceProvider::class,
    AdminServiceProvider::class,
    ReportingServiceProvider::class,
    LabsServiceProvider::class,
    InsuranceServiceProvider::class,
    HRServiceProvider::class,
];
