export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppVariant = 'header' | 'sidebar';

export type FlashToast = {
    type: 'success' | 'info' | 'warning' | 'error';
    message: string;
};

/**
 * A selectable department, as delivered by the shared `departments` Inertia
 * prop (App\Enums\Department::optionsForEnabledModules). Already filtered to
 * departments whose owning system module is enabled.
 */
export type DepartmentOption = {
    value: string;
    label: string;
    module: string;
};
