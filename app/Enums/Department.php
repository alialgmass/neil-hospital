<?php

namespace App\Enums;

use Modules\Admin\Enums\SystemModule;

enum Department: string
{
    case Clinic = 'clinic';
    case Labs = 'labs';
    case Surgery = 'surgery';
    case Lasik = 'lasik';
    case Laser = 'laser';
    case Pentacam = 'pentacam';

    public function label(): string
    {
        return match ($this) {
            self::Clinic => 'العيادة',
            self::Labs => 'الفحوصات',
            self::Surgery => 'العمليات',
            self::Lasik => 'الليزك',
            self::Laser => 'الليزر',
            self::Pentacam => 'البنتكام',
        };
    }

    /**
     * The system module that owns this department. Every department value is
     * also a {@see SystemModule} value, so the mapping is a direct lookup.
     */
    public function module(): SystemModule
    {
        return SystemModule::from($this->value);
    }

    /**
     * Selectable department options whose owning module is currently enabled,
     * shaped for the frontend department pickers (booking + doctors screens).
     * This is the single source of truth the Vue components read from — they
     * must not maintain their own department lists.
     *
     * @return array<int, array{value: string, label: string, module: string}>
     */
    public static function optionsForEnabledModules(): array
    {
        return array_values(array_map(
            fn (self $dept): array => [
                'value' => $dept->value,
                'label' => $dept->label(),
                'module' => $dept->module()->value,
            ],
            array_filter(
                self::cases(),
                fn (self $dept): bool => $dept->module()->isEnabled(),
            ),
        ));
    }
}
