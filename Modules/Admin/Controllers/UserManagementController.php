<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Services\UserManagementService;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userService
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/Users', [
            'users' => $this->userService->getUsers(30),
            'roles' => $this->userService->getRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'exists:roles,name'],
        ]);

        $this->userService->createUser($data);

        return back()->with('success', 'تم إنشاء المستخدم بنجاح.');
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'exists:roles,name'],
        ]);

        $this->userService->syncUserRole($id, $data['role']);

        return back()->with('success', 'تم تحديث دور المستخدم بنجاح.');
    }
}
