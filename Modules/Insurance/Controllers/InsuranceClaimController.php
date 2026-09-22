<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Insurance\Actions\DeleteInsuranceClaimAction;
use Modules\Insurance\Actions\UpdateInsuranceClaimAction;
use Modules\Insurance\Exports\InsuranceClaimsExport;
use Modules\Insurance\Http\Requests\StoreInsuranceClaimRequest;
use Modules\Insurance\Http\Requests\UpdateInsuranceClaimRequest;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Services\InsuranceService;
use Modules\Insurance\States\DraftState;

class InsuranceClaimController extends Controller
{
    public function __construct(
        private readonly UpdateInsuranceClaimAction $updateAction,
        private readonly DeleteInsuranceClaimAction $deleteAction,
        private readonly InsuranceService $insuranceService,
    ) {}

    public function export(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $from = Carbon::parse($month.'-01')->startOfMonth()->toDateString();
        $to = Carbon::parse($month.'-01')->endOfMonth()->toDateString();

        $claims = $this->insuranceService->getClaimsForExport([
            'company_id' => $request->input('company_id'),
            'from' => $from,
            'to' => $to,
        ]);

        return Excel::download(new InsuranceClaimsExport($claims), "insurance-claims-{$month}.xlsx");
    }

    public function store(StoreInsuranceClaimRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['status'] = DraftState::$name;
        $data['claim_date'] = now()->toDateString();
        $data['created_by'] = $request->user()->id;
        $data['discount'] = $data['discount'] ?? 0;
        $data['patient_share'] = $data['patient_share'] ?? 0;
        $data['insurance_share'] = $data['insurance_share'] ?? $data['invoice_amount'];

        InsuranceClaim::create($data);

        return back()->with('success', 'تم إنشاء المطالبة بنجاح.');
    }

    public function update(UpdateInsuranceClaimRequest $request, string $id): RedirectResponse
    {
        $claim = InsuranceClaim::findOrFail($id);
        $this->updateAction->execute($claim, $request->validated());

        return back()->with('success', 'تم تحديث المطالبة بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $claim = InsuranceClaim::findOrFail($id);
        $this->deleteAction->execute($claim);

        return back()->with('success', 'تم حذف المطالبة.');
    }
}
