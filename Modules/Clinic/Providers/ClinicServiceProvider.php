<?php

namespace Modules\Clinic\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Clinic\Policies\MedicalExaminationPolicy;
use Modules\Clinic\Repositories\ClinicSheetRepository;
use Modules\Clinic\Repositories\Contracts\ClinicSheetRepositoryInterface;

class ClinicServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ClinicSheetRepositoryInterface::class, ClinicSheetRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(MedicalExamination::class, MedicalExaminationPolicy::class);

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
