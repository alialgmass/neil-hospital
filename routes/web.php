<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Modules\Reporting\Controllers\DashboardController;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class.'@index')->name('dashboard');
});

require __DIR__.'/settings.php';

$directories = File::directories(base_path('Modules'));
foreach ($directories as $directory) {
    if (file_exists($directory.'/Routes/web.php')) {
        require $directory.'/Routes/web.php';
    }
}
