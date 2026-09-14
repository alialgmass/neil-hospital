<?php

namespace Modules\Inventory\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Actions\AutoPostSupplierPaymentAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Models\SupplierPayment;

class RecordSupplierPaymentAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostSupplierPaymentAction $autoPost,
    ) {}

    public function execute(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::findOrFail($data['supplier_id']);

            $payment = SupplierPayment::create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

            $this->autoPost->execute($payment, $supplier->name);

            $supplier->decrement('balance', (float) $payment->amount);

            $this->activityLogService->log(
                action: 'payment',
                module: 'supplier',
                recordId: $payment->id,
                description: "سداد مستحقات للمورد {$supplier->name}: {$payment->amount} ج",
            );

            return $payment;
        });
    }
}
