<?php

namespace Modules\Insurance\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Admin\Services\ActivityLogService;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Services\InsuranceService;

class ManagePriceListAction
{
    public function __construct(
        private readonly InsuranceService $insuranceService,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function create(array $data, array $items): PriceList
    {
        $priceList = DB::transaction(fn () => $this->insuranceService->createPriceList($data, $items));

        $this->activityLogService->log(
            action: 'create',
            module: 'insurance',
            recordId: $priceList->id,
            description: "إنشاء قائمة أسعار: {$priceList->name}",
        );

        return $priceList;
    }

    /**
     * Price lists are read live only when a new booking is priced — bookings
     * and claims snapshot their amounts — so edits only affect future bookings.
     */
    public function update(PriceList $priceList, array $data, array $items): PriceList
    {
        $priceList = DB::transaction(fn () => $this->insuranceService->updatePriceList($priceList, $data, $items));

        $this->activityLogService->log(
            action: 'update',
            module: 'insurance',
            recordId: $priceList->id,
            description: "تعديل قائمة أسعار: {$priceList->name} ({$priceList->items->count()} خدمة)",
        );

        return $priceList;
    }
}
