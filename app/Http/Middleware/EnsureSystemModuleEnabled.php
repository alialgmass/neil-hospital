<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Admin\Enums\SystemModule;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemModuleEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $module = SystemModule::fromPath($request->path());

        if ($module && ! $module->isEnabled()) {
            abort(403, 'هذه الميزة معطّلة حالياً من إعدادات النظام.');
        }

        return $next($request);
    }
}
