<?php

namespace Tests\Feature\Permissions;

use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Guards against permission drift: every data-changing module endpoint is
 * protected, and every permission referenced by routes, FormRequests,
 * controllers and Vue pages actually exists in RolesPermissionsSeeder.
 */
class PermissionConsistencyTest extends TestCase
{
    public function test_every_mutating_module_route_requires_a_permission(): void
    {
        $unprotected = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->getActionName(), 'Modules\\'))
            ->filter(fn (Route $route) => array_diff($route->methods(), ['GET', 'HEAD']) !== [])
            ->reject(fn (Route $route) => collect($route->gatherMiddleware())
                ->contains(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:')))
            ->map(fn (Route $route) => implode('|', $route->methods()).' '.$route->uri())
            ->values()
            ->all();

        $this->assertSame([], $unprotected, 'Mutating routes without a can: middleware');
    }

    public function test_every_permission_referenced_in_routes_exists_in_the_seeder(): void
    {
        $referenced = collect(RouteFacade::getRoutes()->getRoutes())
            ->flatMap(fn (Route $route) => $route->gatherMiddleware())
            ->filter(fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'can:'))
            ->map(fn (string $middleware) => explode(',', substr($middleware, 4))[0])
            ->unique()
            ->values();

        $this->assertSame([], $referenced->diff(RolesPermissionsSeeder::PERMISSIONS)->values()->all());
    }

    public function test_every_permission_checked_in_php_code_exists_in_the_seeder(): void
    {
        $referenced = [];

        foreach ((new Finder)->files()->in(base_path('Modules'))->name('*.php') as $file) {
            preg_match_all("/->can\\('([a-z_.]+)'\\)/", $file->getContents(), $matches);
            array_push($referenced, ...$matches[1]);
        }

        $missing = array_values(array_diff(array_unique($referenced), RolesPermissionsSeeder::PERMISSIONS));

        $this->assertSame([], $missing);
    }

    public function test_every_permission_checked_in_vue_code_exists_in_the_seeder(): void
    {
        $referenced = [];

        foreach ((new Finder)->files()->in(resource_path('js'))->name(['*.vue', '*.ts']) as $file) {
            preg_match_all("/\\bcan(?:Any)?\\(\\s*'([a-z_.]+)'/", $file->getContents(), $matches);
            array_push($referenced, ...$matches[1]);
            preg_match_all("/permission:\\s*'([a-z_.]+)'/", $file->getContents(), $matches);
            array_push($referenced, ...$matches[1]);
        }

        $missing = array_values(array_diff(array_unique($referenced), RolesPermissionsSeeder::PERMISSIONS));

        $this->assertSame([], $missing);
    }
}
