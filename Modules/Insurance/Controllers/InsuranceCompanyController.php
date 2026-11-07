<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Doctor\Models\Doctor;
use Modules\Insurance\Actions\CreateInsuranceCompanyAction;
use Modules\Insurance\Actions\UpdateInsuranceCompanyAction;
use Modules\Insurance\Http\Requests\StoreInsuranceCompanyRequest;
use Modules\Insurance\Http\Requests\UpdateInsuranceCompanyRequest;
use Modules\Insurance\Services\InsuranceService;

class InsuranceCompanyController extends Controller
{
    public function __construct(
        private readonly InsuranceService $insuranceService,
        private readonly CreateInsuranceCompanyAction $createAction,
        private readonly UpdateInsuranceCompanyAction $updateAction,
    ) {}

    public function index(): Response
    {
        $search = request('search');
        $companyFilter = request('company_id');

        $claimFilters = request()->only([
            'company_id', 'from', 'to', 'service_id', 'status', 'dept', 'doctor_id', 'patient',
        ]);

        return Inertia::render('insurance/Companies', [
            'companies' => $this->insuranceService->list($search, 20),
            'filters' => ['search' => $search, 'company_id' => $companyFilter],
            'claimFilters' => $claimFilters,
            'claims' => $this->insuranceService->getClaimsList($claimFilters, 30),
            'stats' => $this->insuranceService->getMonthlyClaimsStats(),
            'doctors' => Doctor::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'claimFilterServices' => $this->insuranceService->getSelectableServices(),
        ]);
    }

    public function store(StoreInsuranceCompanyRequest $request): RedirectResponse
    {
        $this->createAction->execute($request->validated());

        return back()->with('success', 'تم إضافة شركة التأمين بنجاح.');
    }

    public function update(UpdateInsuranceCompanyRequest $request, string $id): RedirectResponse
    {
        $this->updateAction->execute($id, $request->validated());

        return back()->with('success', 'تم تعديل شركة التأمين بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $company = $this->insuranceService->findById($id);
        $company->delete();

        return back()->with('success', 'تم حذف شركة التأمين.');
    }
}
