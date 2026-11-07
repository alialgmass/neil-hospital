<?php

namespace Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Admin\Services\ModuleImportRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared controller backing the per-module "download template" and "import"
 * buttons. Each module registers its own routes to this controller so the
 * permission is enforced by the module's own route middleware.
 */
class ModuleImportActionController extends Controller
{
    public function template(string $module): BinaryFileResponse
    {
        if (! ModuleImportRegistry::supported($module)) {
            throw new NotFoundHttpException;
        }

        return Excel::download(
            ModuleImportRegistry::template($module),
            "{$module}_template.xlsx",
        );
    }

    public function import(string $module, Request $request): RedirectResponse
    {
        if (! ModuleImportRegistry::supported($module)) {
            throw new NotFoundHttpException;
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $importer = ModuleImportRegistry::importer($module);
        Excel::import($importer, $request->file('file'));

        return back()->with('importResult', [
            'module' => ModuleImportRegistry::title($module),
            'created' => $importer->created,
            'updated' => $importer->updated,
            'skipped' => $importer->skipped,
        ]);
    }
}
