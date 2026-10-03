<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Resolves technical permission / role keys (e.g. "booking.create") into
 * human-readable labels from lang/{locale}/permissions.php.
 *
 * The UI is Arabic-only, so labels default to the "ar" locale regardless of
 * APP_LOCALE. Missing translations fall back to the raw key.
 */
class PermissionLabelService
{
    public const DEFAULT_LOCALE = 'ar';

    public function permission(string $name, string $locale = self::DEFAULT_LOCALE): string
    {
        return $this->section('labels', $locale)[$name] ?? $name;
    }

    public function group(string $group, string $locale = self::DEFAULT_LOCALE): string
    {
        return $this->section('groups', $locale)[$group] ?? $group;
    }

    public function role(string $name, string $locale = self::DEFAULT_LOCALE): string
    {
        return $this->section('roles', $locale)[$name] ?? $name;
    }

    public static function groupOf(string $permission): string
    {
        return explode('.', $permission)[0];
    }

    /**
     * @return array{name: string, label: string, group: string, group_label: string}
     */
    public function describePermission(string $name): array
    {
        $group = self::groupOf($name);

        return [
            'name' => $name,
            'label' => $this->permission($name),
            'group' => $group,
            'group_label' => $this->group($group),
        ];
    }

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return Collection<int, array{name: string, label: string, group: string, group_label: string}>
     */
    public function describePermissions(Collection $permissions): Collection
    {
        return $permissions->map(fn (Permission $permission) => $this->describePermission($permission->name))->values();
    }

    /**
     * Role with a translated label and (when loaded) labelled permissions.
     *
     * @return array<string, mixed>
     */
    public function describeRole(Role $role): array
    {
        $data = [
            'id' => $role->id,
            'name' => $role->name,
            'label' => $this->role($role->name),
        ];

        if ($role->relationLoaded('permissions')) {
            $data['permissions'] = $this->describePermissions($role->permissions)->all();
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function section(string $section, string $locale): array
    {
        $values = trans("permissions.{$section}", [], $locale);

        return is_array($values) ? $values : [];
    }
}
