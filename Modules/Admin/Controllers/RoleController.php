<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\UserManagementService;

class RoleController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userService
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Roles', [
            'roles' => $this->userService->getRolesWithPermissions(),
            'allPermissions' => $this->userService->getAllPermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->userService->createRole(trim($data['name']), $data['permissions'] ?? []);

        return back()->with('success', 'تم إضافة الدور بنجاح.');
    }

    public function updatePermissions(Request $request, string $roleId): RedirectResponse
    {
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->userService->syncRolePermissions($roleId, $data['permissions'] ?? []);

        return back()->with('success', 'تم تحديث صلاحيات الدور بنجاح.');
    }
}
