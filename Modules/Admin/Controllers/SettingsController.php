<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Services\SettingsService;
use Modules\Booking\States\BookingStatus;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $service
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => $this->service->all(),
            'systemModules' => collect(SystemModule::cases())->map(fn (SystemModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
                'enabled' => $module->isEnabled(),
            ])->all(),
            'bookingStatuses' => BookingStatus::options(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable', 'string'],
        ]);

        $this->service->updateBulk($data['settings']);

        return back()->with('success', 'تم حفظ الإعدادات بنجاح.');
    }
}
