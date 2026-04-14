<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            // All permission names assigned to the authenticated user.
            // The '*' sentinel is added for super-admin / admin role to grant all access.
            'permissions' => $user
                ? $this->resolvePermissions($user)
                : [],
            // Hospital-wide settings surfaced to every Vue page.
            'settings' => [
                'hospital_name'      => config('app.name', 'مستشفى النور'),
                'hospital_specialty' => 'طب وجراحة العيون',
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Return the flat permission name list for the given user.
     * Admins receive ['*'] so the frontend can skip per-permission checks.
     *
     * @param  \App\Models\User  $user
     * @return array<string>
     */
    private function resolvePermissions($user): array
    {
        if (! method_exists($user, 'getAllPermissions')) {
            return [];
        }

        if ($user->hasRole('admin')) {
            return ['*'];
        }

        return $user->getAllPermissions()->pluck('name')->all();
    }
}
