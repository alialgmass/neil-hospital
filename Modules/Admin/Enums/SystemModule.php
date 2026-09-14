<?php

namespace Modules\Admin\Enums;

use Modules\Admin\Models\Setting;

enum SystemModule: string
{
    case Booking = 'booking';
    case Clinic = 'clinic';
    case Labs = 'labs';
    case Surgery = 'surgery';
    case Lasik = 'lasik';
    case Laser = 'laser';
    case Pentacam = 'pentacam';
    case Doctors = 'doctors';
    case Accounting = 'accounting';
    case Inventory = 'inventory';
    case Hr = 'hr';
    case Reports = 'reports';

    const Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::Booking => 'الحجز',
            self::Clinic => 'العيادة',
            self::Labs => 'الفحوصات',
            self::Surgery => 'العمليات',
            self::Lasik => 'الليزك',
            self::Laser => 'الليزر',
            self::Pentacam => 'البنتكام',
            self::Doctors => 'الأطباء',
            self::Accounting => 'المالية والمحاسبة',
            self::Inventory => 'المخزن والخدمات',
            self::Hr => 'الموارد البشرية',
            self::Reports => 'التقارير والأرشيف',
        };
    }

    /**
     * URL path prefixes (first segment) that belong to this module.
     *
     * @return array<int, string>
     */
    public function routePrefixes(): array
    {
        return match ($this) {
            self::Booking => ['booking'],
            self::Clinic => ['clinic'],
            self::Labs => ['labs'],
            self::Surgery => ['surgery', 'or-rooms'],
            self::Lasik => ['lasik'],
            self::Laser => ['laser'],
            self::Pentacam => ['pentacam'],
            self::Doctors => ['doctors', 'dr-claims', 'dr-payments', 'doctor-shifts'],
            self::Accounting => ['treasury', 'journal', 'daily-journal', 'accounts', 'ledger', 'cost-centers', 'sales-invoices'],
            self::Inventory => ['services', 'inventory', 'suppliers', 'purchases', 'stock-issue', 'stock-permits', 'supply-bundles', 'stock-take', 'insurance'],
            self::Hr => ['employees', 'attendance', 'shifts', 'shift-handovers', 'leaves', 'payroll'],
            self::Reports => ['reports', 'archive'],
        };
    }

    /**
     * The `bookings.dept` column value this module corresponds to, if any.
     * Used to strip disabled clinical departments out of cross-cutting
     * report/dashboard breakdowns (not just the module's own menu/routes).
     */
    public function deptValue(): ?string
    {
        return match ($this) {
            self::Clinic => 'clinic',
            self::Labs => 'labs',
            self::Surgery => 'surgery',
            self::Lasik => 'lasik',
            self::Laser => 'laser',
            self::Pentacam => 'pentacam',
            default => null,
        };
    }

    public function settingKey(): string
    {
        return "module_enabled_{$this->value}";
    }

    public function isEnabled(): bool
    {
        return Setting::getValue($this->settingKey(), 'true') !== 'false';
    }

    /**
     * Resolve the module (if any) that owns the given request path.
     */
    public static function fromPath(string $path): ?self
    {
        $firstSegment = strtolower(trim(explode('/', ltrim($path, '/'))[0] ?? ''));

        foreach (self::cases() as $module) {
            if (in_array($firstSegment, $module->routePrefixes(), true)) {
                return $module;
            }
        }

        return null;
    }

    /**
     * @return array<string, bool>
     */
    public static function statuses(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $module) => [$module->value => $module->isEnabled()])
            ->all();
    }

    /**
     * `bookings.dept` values whose owning module is currently enabled.
     * Pass to a `whereIn('dept', ...)` clause to strip disabled departments
     * out of report/dashboard breakdowns while leaving system-wide totals alone.
     *
     * @return array<int, string>
     */
    public static function enabledDeptValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $module) => $module->deptValue() !== null && $module->isEnabled())
            ->map(fn (self $module) => $module->deptValue())
            ->values()
            ->all();
    }
}
