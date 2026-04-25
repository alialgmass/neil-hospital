<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
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

        return Inertia::render('insurance/Companies', [
            'companies' => $this->insuranceService->list($search, 20),
            'filters' => ['search' => $search, 'company_id' => $companyFilter],
            'claims' => $this->insuranceService->getClaimsList($companyFilter, 30)->withQueryString(),
            'stats' => $this->insuranceService->getMonthlyClaimsStats(),
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
