<?php

namespace Modules\Admin\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementService
{
    public function getUsers(int $perPage = 30): LengthAwarePaginator
    {
        return User::with('roles')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getRolesWithPermissions(): Collection
    {
        return Role::with('permissions')->orderBy('name')->get();
    }

    public function getAllPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    public function syncRolePermissions(string|int $roleId, array $permissions): void
    {
        $role = Role::findOrFail($roleId);
        $role->syncPermissions($permissions);
    }

    public function getRoles(): Collection
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }

    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return $user;
    }

    public function syncUserRole(string|int $userId, string $roleName): void
    {
        $user = User::findOrFail($userId);
        $user->syncRoles([$roleName]);
    }
}
