<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Services\ModuleExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleExportController extends Controller
{
    public function __construct(
        private readonly ModuleExportService $moduleExportService,
    ) {}

    public function index(): Response
    {
        $modules = collect(SystemModule::cases())
            ->map(fn (SystemModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
                'enabled' => $module->isEnabled(),
            ])
            ->all();

        return Inertia::render('admin/ModuleExports', [
            'modules' => $modules,
        ]);
    }

    public function export(SystemModule $module): BinaryFileResponse
    {
        return Excel::download(
            $this->moduleExportService->export($module),
            "{$module->value}.xlsx",
        );
    }
}
