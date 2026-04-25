<?php

namespace Modules\Labs\Providers;

use Illuminate\Support\ServiceProvider;

class LabsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
    }
}
