<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Insurance\Models\InsuranceClaim;

class InsuranceClaimController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'insurance_company_id' => ['required', 'exists:insurance_companies,id'],
            'booking_id' => ['nullable', 'exists:bookings,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'patient_name' => ['required', 'string', 'max:150'],
            'file_no' => ['nullable', 'string', 'max:50'],
            'service_name' => ['required', 'string', 'max:200'],
            'invoice_amount' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'patient_share' => ['nullable', 'numeric', 'min:0'],
            'insurance_share' => ['nullable', 'numeric', 'min:0'],
            'service_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['status'] = 'draft';
        $data['claim_date'] = now()->toDateString();
        $data['created_by'] = $request->user()->id;
        $data['discount'] = $data['discount'] ?? 0;
        $data['patient_share'] = $data['patient_share'] ?? 0;
        $data['insurance_share'] = $data['insurance_share'] ?? $data['invoice_amount'];

        InsuranceClaim::create($data);

        return back()->with('success', 'تم إنشاء المطالبة بنجاح.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $claim = InsuranceClaim::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:draft,submitted,approved,rejected,paid'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'rejection_reason' => ['nullable', 'string'],
            'submission_date' => ['nullable', 'date'],
            'approval_date' => ['nullable', 'date'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $claim->update($data);

        return back()->with('success', 'تم تحديث المطالبة بنجاح.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $claim = InsuranceClaim::findOrFail($id);

        if ($claim->status !== 'draft') {
            return back()->with('error', 'لا يمكن حذف مطالبة بعد إرسالها.');
        }

        $claim->delete();

        return back()->with('success', 'تم حذف المطالبة.');
    }
}
