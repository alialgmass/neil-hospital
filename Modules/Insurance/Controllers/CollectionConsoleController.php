<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Actions\AdvanceClaimStageAction;
use Modules\Insurance\Actions\ApproveInsuranceCollectionAction;
use Modules\Insurance\Actions\CloseInsuranceCollectionDayAction;
use Modules\Insurance\Actions\CollectPatientCashEntryAction;
use Modules\Insurance\Actions\ConvertPendingDepositAction;
use Modules\Insurance\Actions\DecidePendingDepositAction;
use Modules\Insurance\Actions\RecordClaimCollectionAction;
use Modules\Insurance\Actions\RecordPendingDepositAction;
use Modules\Insurance\Actions\SetInsuranceDecisionAction;
use Modules\Insurance\Enums\DepositDecision;
use Modules\Insurance\Enums\InsuranceDecision;
use Modules\Insurance\Http\Requests\DecidePendingDepositRequest;
use Modules\Insurance\Http\Requests\RecordClaimCollectionRequest;
use Modules\Insurance\Http\Requests\SetInsuranceDecisionRequest;
use Modules\Insurance\Http\Requests\StorePendingDepositRequest;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCollectionApproval;
use Modules\Insurance\Models\PatientCashEntry;
use Modules\Insurance\Models\PendingDeposit;
use Modules\Insurance\Services\CollectionConsoleService;

class CollectionConsoleController extends Controller
{
    public function index(CollectionConsoleService $console): Response
    {
        return Inertia::render('insurance/Collection', $console->build());
    }

    public function advance(AdvanceClaimStageAction $action, string $id): RedirectResponse
    {
        $action->execute(InsuranceClaim::findOrFail($id));

        return back()->with('success', 'تم نقل المطالبة للمرحلة التالية.');
    }

    public function collect(RecordClaimCollectionRequest $request, RecordClaimCollectionAction $action, string $id): RedirectResponse
    {
        $action->execute(
            InsuranceClaim::findOrFail($id),
            (float) $request->validated('amount'),
            (float) $request->validated('discount'),
            $request->validated('discount_reason') ?: $request->validated('reason'),
            $request->user()->id,
        );

        return back()->with('success', 'تم تسجيل التحصيل.');
    }

    public function approveCollection(ApproveInsuranceCollectionAction $action, string $id): RedirectResponse
    {
        $action->approve(InsuranceCollectionApproval::findOrFail($id), request()->user());

        return back()->with('success', 'تم اعتماد التحصيل وترحيله.');
    }

    public function rejectCollection(Request $request, ApproveInsuranceCollectionAction $action, string $id): RedirectResponse
    {
        $reason = trim((string) $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason']);
        $action->reject(InsuranceCollectionApproval::findOrFail($id), $request->user(), $reason);

        return back()->with('success', 'تم رفض التحصيل المعلّق.');
    }

    public function closeDay(CloseInsuranceCollectionDayAction $action): RedirectResponse
    {
        $action->execute(request()->user());

        return back()->with('success', 'تم إغلاق يوم التحصيل.');
    }

    public function collectCash(CollectPatientCashEntryAction $action, string $id): RedirectResponse
    {
        $action->execute(PatientCashEntry::findOrFail($id), request()->user());

        return back()->with('success', 'تم تحصيل المبلغ النقدي.');
    }

    public function setDecision(SetInsuranceDecisionRequest $request, SetInsuranceDecisionAction $action, string $id): RedirectResponse
    {
        $action->execute(Booking::findOrFail($id), InsuranceDecision::from($request->validated('decision')), $request->user());

        return back()->with('success', 'تم تحديث قرار التأمين.');
    }

    public function storeDeposit(StorePendingDepositRequest $request, RecordPendingDepositAction $action): RedirectResponse
    {
        $action->execute(
            Booking::findOrFail($request->validated('booking_id')),
            (float) $request->validated('amount'),
            $request->user()->id,
        );

        return back()->with('success', 'تم تسجيل الأمانة.');
    }

    public function decideDeposit(DecidePendingDepositRequest $request, DecidePendingDepositAction $action, string $id): RedirectResponse
    {
        $action->execute(
            PendingDeposit::findOrFail($id),
            DepositDecision::from($request->validated('decision')),
        );

        return back()->with('success', 'تم تسجيل قرار الشركة.');
    }

    public function convertDeposit(Request $request, ConvertPendingDepositAction $action, string $id): RedirectResponse
    {
        $action->execute(PendingDeposit::findOrFail($id), $request->user()->id);

        return back()->with('success', 'تم تحويل الأمانة لمطالبة واسترداد المبلغ للمريض.');
    }
}
