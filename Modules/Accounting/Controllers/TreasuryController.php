<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Enums\TreasuryType;
use Modules\Accounting\Http\Requests\StoreTreasuryRequest;
use Modules\Accounting\Http\Requests\StoreTreasuryVoucherRequest;
use Modules\Accounting\Http\Requests\UpdateTreasuryRequest;
use Modules\Accounting\Services\TreasuryService;

class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryService $treasuryService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(): Response
    {
        $filters = request()->only(['type', 'source', 'from', 'to']);

        return Inertia::render('treasury/Index', [
            'entries' => $this->treasuryService->list($filters, 30),
            'balance' => $this->treasuryService->balance(),
            'todayNet' => $this->treasuryService->todayNet(),
            'filters' => $filters,
        ]);
    }

    public function store(StoreTreasuryRequest $request): RedirectResponse
    {
        $entry = $this->treasuryService->record($request->validated());

        $this->activityLog->log(
            action: 'treasury_entry',
            module: 'treasury',
            recordId: $entry->id,
            description: "{$entry->type->label()}: {$entry->description} — {$entry->amount} ج.م",
        );

        return back()->with('success', 'تم تسجيل حركة الخزنة بنجاح.');
    }

    /**
     * Issue a receipt voucher (إيصال استلام) or payment voucher (إيصال صرف):
     * records the treasury movement, then opens the printable voucher.
     */
    public function storeVoucher(StoreTreasuryVoucherRequest $request): RedirectResponse
    {
        $entry = $this->treasuryService->record([
            ...$request->validated(),
            'source' => 'manual',
        ]);

        $title = $entry->type === TreasuryType::In ? 'إيصال استلام' : 'إيصال صرف';

        $this->activityLog->log(
            action: 'treasury_voucher',
            module: 'treasury',
            recordId: $entry->id,
            description: "{$title} {$entry->voucherNumber()}: {$entry->beneficiary} — {$entry->amount} ج.م",
        );

        return redirect()
            ->route('treasury.voucher', $entry->id)
            ->with('success', "تم إصدار {$title} رقم {$entry->voucherNumber()}.");
    }

    public function update(UpdateTreasuryRequest $request, string $id): RedirectResponse
    {
        $entry = $this->treasuryService->update($id, $request->validated());

        $this->activityLog->log(
            action: 'treasury_entry_edited',
            module: 'treasury',
            recordId: $entry->id,
            description: "تعديل حركة خزنة: {$entry->description} — {$entry->amount} ج.م",
        );

        return back()->with('success', 'تم تعديل حركة الخزنة (تم عكس القيد الأصلي وتسجيل قيد جديد).');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->treasuryService->delete($id);

        $this->activityLog->log(
            action: 'treasury_entry_deleted',
            module: 'treasury',
            recordId: $id,
        );

        return back()->with('success', 'تم حذف حركة الخزنة (تم تسجيل قيد عكسي للحفاظ على الأرشيف).');
    }

    /**
     * Printable receipt voucher (إيصال استلام) for inflows or payment voucher (إيصال صرف) for outflows.
     */
    public function voucher(string $id): Response
    {
        $entry = $this->treasuryService->findOrFail($id)->load('creator:id,name');

        return Inertia::render('treasury/Voucher', [
            'voucher' => [
                'voucher_no' => $entry->voucherNumber(),
                'type' => $entry->type->value,
                'title' => $entry->type === TreasuryType::In ? 'إيصال استلام' : 'إيصال صرف',
                'party_label' => $entry->type === TreasuryType::In ? 'استلمنا من السيد/ة' : 'صرفنا إلى السيد/ة',
                'party' => $entry->beneficiary,
                'amount' => (float) $entry->amount,
                'date' => $entry->date->toDateString(),
                'description' => $entry->description,
                'reference_no' => $entry->reference_no,
                'created_by' => $entry->creator?->name,
                'is_reversed' => $entry->reversed_at !== null,
            ],
        ]);
    }

    public function statement(): Response
    {
        $filters = request()->only(['from', 'to']);

        return Inertia::render('treasury/Statement', [
            'statement' => $this->treasuryService->statement($filters['from'] ?? null, $filters['to'] ?? null),
            'filters' => $filters,
        ]);
    }
}
