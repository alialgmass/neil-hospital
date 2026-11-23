import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Tooltip shown on actions the current user is not allowed to perform. */
export const NO_PERMISSION_TITLE = 'غير مصرح لك بهذه العملية';

/**
 * Permission checks against the shared `permissions` prop
 * (see HandleInertiaRequests::resolvePermissions). `['*']` = super-admin.
 *
 * The backend always enforces the same permission — these helpers only
 * drive the UI (disabling buttons / hiding navigation).
 */
export function usePermissions() {
    const page = usePage();

    const permissions = computed<string[]>(
        () => (page.props.permissions as string[] | undefined) ?? [],
    );

    function can(permission: string): boolean {
        return (
            permissions.value.includes('*') ||
            permissions.value.includes(permission)
        );
    }

    function canAny(...list: string[]): boolean {
        return list.some((permission) => can(permission));
    }

    return { permissions, can, canAny };
}
