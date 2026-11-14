<?php

namespace Modules\Doctor\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Actions\AutoPostDoctorPaymentAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class RecordDoctorPaymentAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostDoctorPaymentAction $autoPost,
        private readonly SettleDoctorEntitlementsAction $settleDoctorEntitlements,
    ) {}

    public function execute(array $data): DoctorPayment
    {
        return DB::transaction(function () use ($data): DoctorPayment {
            $payment = DoctorPayment::create([
                ...$data,
                'created_by' => auth()->id(),
            ]);

            $doctor = Doctor::findOrFail($data['doctor_id']);

            $this->autoPost->execute($payment, $doctor->name);
            $this->settleDoctorEntitlements->execute($payment);

            $this->activityLogService->log(
                action: 'payment',
                module: 'doctor',
                recordId: $payment->id,
                description: "صرف مستحقات للدكتور {$doctor->name}: {$payment->amount} ج",
            );

            return $payment;
        });
    }
}
