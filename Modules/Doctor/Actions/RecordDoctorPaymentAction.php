<?php

namespace Modules\Doctor\Actions;

use Modules\Accounting\Actions\AutoPostDoctorPaymentAction;
use Modules\Admin\Services\ActivityLogService;
use Modules\Doctor\Models\Doctor;
use Modules\Doctor\Models\DoctorPayment;

class RecordDoctorPaymentAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly AutoPostDoctorPaymentAction $autoPost,
    ) {}

    public function execute(array $data): DoctorPayment
    {
        $payment = DoctorPayment::create([
            ...$data,
            'paid_by' => auth()->id(),
        ]);

        $doctor = Doctor::findOrFail($data['doctor_id']);

        $this->autoPost->execute($payment, $doctor->name);

        $this->activityLogService->log(
            action: 'payment',
            module: 'doctor',
            recordId: $payment->id,
            description: "صرف مستحقات للدكتور {$doctor->name}: {$payment->amount} ج",
        );

        return $payment;
    }
}
