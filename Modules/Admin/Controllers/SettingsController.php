<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Models\Setting;
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
            'hospitalLogoUrl' => Setting::logoUrl(),
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

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,svg,webp'],
        ]);

        $setting = Setting::firstOrCreate(['key' => 'hospital_logo'], ['group' => 'hospital']);
        $setting->addMediaFromRequest('logo')->toMediaCollection('logo');

        return back()->with('success', 'تم تحديث شعار المستشفى بنجاح.');
    }
}
