<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Admin\Enums\SystemModule;
use Modules\Admin\Http\Requests\ModuleImportRequest;
use Modules\Admin\Services\ModuleImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleImportController extends Controller
{
    public function __construct(
        private readonly ModuleImportService $importService,
    ) {}

    public function index(): Response
    {
        $modules = collect(SystemModule::cases())
            ->map(fn (SystemModule $module) => [
                'value' => $module->value,
                'label' => $module->label(),
                'hasImport' => $this->importService->hasImport($module),
                'enabled' => $module->isEnabled(),
            ])
            ->all();

        return Inertia::render('admin/ModuleImports', [
            'modules' => $modules,
            'importResult' => session('importResult'),
        ]);
    }

    public function template(SystemModule $module): BinaryFileResponse
    {
        return Excel::download(
            $this->importService->template($module),
            "{$module->value}_template.xlsx",
        );
    }

    public function import(SystemModule $module, ModuleImportRequest $request): RedirectResponse
    {
        $importer = $this->importService->resolveImport($module);
        Excel::import($importer, $request->file('file'));

        return back()->with('importResult', [
            'module' => $module->label(),
            'created' => $importer->created,
            'updated' => $importer->updated,
            'skipped' => $importer->skipped,
        ]);
    }
}
