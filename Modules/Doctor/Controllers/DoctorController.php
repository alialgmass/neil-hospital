<?php

namespace Modules\Doctor\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Doctor\Actions\CreateDoctorAction;
use Modules\Doctor\Actions\UpdateDoctorAction;
use Modules\Doctor\Http\Requests\StoreDoctorRequest;
use Modules\Doctor\Http\Requests\UpdateDoctorRequest;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Services\DoctorService;

class DoctorController extends Controller
{
    public function __construct(
        private readonly DoctorService $doctorService,
        private readonly CreateDoctorAction $createAction,
        private readonly UpdateDoctorAction $updateAction,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['search']);

        return Inertia::render('doctors/Index', [
            'doctors' => $this->doctorService->list($filters),
            'filters' => $filters,
        ]);
    }

    public function store(StoreDoctorRequest $request): RedirectResponse
    {
        $this->createAction->execute($request->validated());

        return back()->with('success', 'تم إضافة الطبيب بنجاح.');
    }

    public function update(UpdateDoctorRequest $request, string $id): RedirectResponse
    {
        $doctor = Doctor::findOrFail($id);
        $this->updateAction->execute($doctor, $request->validated());

        return back()->with('success', 'تم تعديل بيانات الطبيب بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $doctor = Doctor::findOrFail($id);

        if ($doctor->payments()->exists() || $doctor->shifts()->exists()) {
            return back()->with('error', 'لا يمكن حذف الطبيب لوجود مدفوعات أو شِفتات مرتبطة به.');
        }

        $doctor->delete();

        return back()->with('success', 'تم حذف الطبيب بنجاح.');
    }
}
