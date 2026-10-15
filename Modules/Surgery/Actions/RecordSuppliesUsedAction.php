<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Modules\Surgery\DTOs\SuppliesUsedData;
use Modules\Surgery\Models\Surgery;
use Modules\Surgery\Services\SurgeryService;

class RecordSuppliesUsedAction
{
    public function __construct(
        private readonly SurgeryService $surgeryService,
        private readonly ActivityLogService $activityLog,
        private readonly IssueCaseSuppliesPermitAction $issuePermit,
    ) {}

    /**
     * Individual items (not bundles, which issue their own permit) are issued
     * through one stock permit for the whole submission, so they show up in
     * the stock-issue vouchers and move inventory.
     */
    public function execute(SuppliesUsedData $data): Surgery
    {
        $surgery = $this->surgeryService->recordSupplies($this->withIssuedPermit($data));

        $this->activityLog->log(
            action: 'supplies_recorded',
            module: $surgery->dept->value,
            recordId: $data->surgeryId,
            description: "تسجيل مستلزمات: إجمالي = {$surgery->supply_total} ج.م",
        );

        return $surgery;
    }

    private function withIssuedPermit(SuppliesUsedData $data): SuppliesUsedData
    {
        $issuable = array_keys(array_filter(
            $data->items,
            fn (array $line) => ! $line['is_bundle'] && $line['inventory_item_id'] !== '' && empty($line['permit_id']),
        ));

        if ($issuable === []) {
            return $data;
        }

        $permit = $this->issuePermit->execute(
            Surgery::findOrFail($data->surgeryId),
            array_map(fn (int $index) => $data->items[$index], $issuable),
        );

        $items = $data->items;

        foreach ($issuable as $index) {
            $items[$index]['permit_id'] = $permit->id;
        }

        return new SuppliesUsedData($data->surgeryId, $items);
    }
}
