<?php

namespace Modules\Surgery\Actions;

use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Enums\JournalSource;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Services\JournalService;
use Modules\Doctor\Actions\SyncBookingDoctorDuesAction;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\StockPermit;
use Modules\Surgery\Models\Surgery;

/**
 * Edits or removes a single line of a case's supplies_used.
 *
 * - An individual item added with a stock-issue permit moved stock and posted
 *   its consumption entry; editing it re-issues the permit for the new
 *   quantity and removing it returns the stock, reverses the entries and
 *   deletes the permit. Lines saved before permits existed (no permit_id)
 *   only live on the case — editing/removing them rewrites the line and the
 *   total.
 * - A bundle (بند) line moved stock and posted journal entries through its
 *   own stock permit; removing it does the same. Bundles can't be edited in
 *   place — remove and re-add them instead.
 *
 * Lines are addressed by their position; the caller also sends the line's
 * item/bundle id so a stale screen can't hit a different line.
 */
class ModifySurgerySupplyLineAction
{
    public function __construct(
        private readonly JournalService $journalService,
        private readonly SyncBookingDoctorDuesAction $syncBookingDoctorDues,
        private readonly ActivityLogService $activityLog,
        private readonly IssueCaseSuppliesPermitAction $issuePermit,
    ) {}

    public function update(string $surgeryId, int $index, string $lineRef, float $qty, float $unitCost): Surgery
    {
        return DB::transaction(function () use ($surgeryId, $index, $lineRef, $qty, $unitCost) {
            [$surgery, $lines, $line] = $this->lockLine($surgeryId, $index, $lineRef);

            if (! empty($line['is_bundle'])) {
                throw ValidationException::withMessages([
                    'line' => 'لا يمكن تعديل البند مباشرة — احذفه ثم أضفه من جديد بالأصناف المطلوبة.',
                ]);
            }

            $lines[$index] = [
                ...$line,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'total' => round($qty * $unitCost, 2),
            ];

            // A line backed by an issue permit re-issues it for the new quantity.
            if (! empty($line['permit_id'])) {
                $this->releasePermit($surgery, $line, $lines);
                $lines[$index]['permit_id'] = $this->issuePermit->execute($surgery, [$lines[$index]])->id;
            }

            $surgery = $this->saveLines($surgery, $lines);

            $this->activityLog->log(
                action: 'supplies_updated',
                module: $surgery->dept->value,
                recordId: $surgery->id,
                description: "تعديل مستلزم: {$line['name']} — الكمية {$qty} × {$unitCost}",
            );

            return $surgery;
        });
    }

    public function delete(string $surgeryId, int $index, string $lineRef): Surgery
    {
        return DB::transaction(function () use ($surgeryId, $index, $lineRef) {
            [$surgery, $lines, $line] = $this->lockLine($surgeryId, $index, $lineRef);

            if (! empty($line['is_bundle']) || ! empty($line['permit_id'])) {
                $this->releasePermit($surgery, $line, $lines);
            }

            array_splice($lines, $index, 1);

            $surgery = $this->saveLines($surgery, $lines);

            $this->activityLog->log(
                action: 'supplies_deleted',
                module: $surgery->dept->value,
                recordId: $surgery->id,
                description: "حذف مستلزم: {$line['name']} ({$line['total']} ج.م)",
            );

            return $surgery;
        });
    }

    /**
     * @return array{0: Surgery, 1: array<int, array<string, mixed>>, 2: array<string, mixed>}
     */
    private function lockLine(string $surgeryId, int $index, string $lineRef): array
    {
        $surgery = Surgery::whereKey($surgeryId)->lockForUpdate()->firstOrFail();
        $lines = array_values($surgery->supplies_used ?? []);
        $line = $lines[$index] ?? null;

        if ($line === null || $this->lineRef($line) !== $lineRef) {
            throw ValidationException::withMessages([
                'line' => 'تغيرت قائمة المستلزمات — أعد فتح الحالة وحاول مرة أخرى.',
            ]);
        }

        return [$surgery, $lines, $line];
    }

    /** @param  array<string, mixed>  $line */
    private function lineRef(array $line): string
    {
        return (string) (! empty($line['is_bundle']) ? ($line['bundle_id'] ?? '') : ($line['inventory_item_id'] ?? ''));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function saveLines(Surgery $surgery, array $lines): Surgery
    {
        $lines = array_values($lines);

        $surgery->update([
            'supplies_used' => $lines,
            'supply_total' => round(array_sum(array_map(fn (array $line) => (float) ($line['total'] ?? 0), $lines)), 2),
        ]);

        $this->resyncDoctorDues($surgery);

        return $surgery;
    }

    /**
     * The doctor's surgery share is (paid − supply_total). Re-align it only
     * when dues were already posted for this booking, so a pre-payment edit
     * stays a no-op exactly like adding supplies before payment.
     */
    private function resyncDoctorDues(Surgery $surgery): void
    {
        $booking = $surgery->booking;

        if (! $booking) {
            return;
        }

        $hasPostedDues = JournalEntry::where('reference', $booking->file_no)
            ->where('source', JournalSource::DOCTOR_SHIFT->value)
            ->where('idempotency_key', 'like', "doctor_dues:{$booking->file_no}:%")
            ->whereNull('reversed_at')
            ->whereNull('reversal_of_id')
            ->exists();

        if ($hasPostedDues) {
            $this->syncBookingDoctorDues->execute($booking);
        }
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function releasePermit(Surgery $surgery, array $line, array $lines): void
    {
        $permit = $this->bundlePermit($surgery, $line, $lines);

        foreach ($permit->items as $item) {
            if ($item->item_id) {
                InventoryItem::whereKey($item->item_id)->increment('quantity', abs((float) $item->qty));
            }
        }

        JournalEntry::where('source', JournalSource::SUPPLIES_USED->value)
            ->where(function ($query) use ($permit) {
                $query->where('idempotency_key', 'like', "bundle_supply_item:{$permit->id}:%")
                    ->orWhere('idempotency_key', "bundle_supply_charge:{$permit->id}")
                    ->orWhere('idempotency_key', 'like', "stock_issue:{$permit->id}:%");
            })
            ->whereNull('reversed_at')
            ->get()
            ->each(fn (JournalEntry $entry) => $this->journalService->reverse(
                $entry,
                JournalSource::REVERSAL,
                $entry->reference ?? $permit->permit_no,
                "عكس قيد — حذف بند من الحالة: {$entry->description}",
            ));

        $permit->delete();
    }

    /**
     * Lines saved before permit_id was recorded are matched to the oldest
     * unclaimed permit for the same bundle whose journal entries reference
     * this case.
     *
     * @param  array<string, mixed>  $line
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function bundlePermit(Surgery $surgery, array $line, array $lines): StockPermit
    {
        if (! empty($line['permit_id'])) {
            $permit = StockPermit::with('items')->find($line['permit_id']);

            if ($permit) {
                return $permit;
            }
        }

        $reference = $surgery->booking?->file_no;
        $claimedPermitIds = array_filter(array_column($lines, 'permit_id'));

        $candidatePermitIds = $reference === null ? collect() : JournalEntry::where('reference', $reference)
            ->where('source', JournalSource::SUPPLIES_USED->value)
            ->whereNull('reversed_at')
            ->where(function ($query) {
                $query->where('idempotency_key', 'like', 'bundle_supply_item:%')
                    ->orWhere('idempotency_key', 'like', 'bundle_supply_charge:%');
            })
            ->pluck('idempotency_key')
            ->map(fn (string $key) => explode(':', $key)[1] ?? null)
            ->filter()
            ->unique()
            ->diff($claimedPermitIds);

        $permit = StockPermit::with('items')
            ->whereIn('id', $candidatePermitIds)
            ->where('reason', "استخدام بند: {$line['name']}")
            ->orderBy('created_at')
            ->first();

        if (! $permit) {
            throw ValidationException::withMessages([
                'line' => 'تعذر تحديد إذن الصرف الخاص بهذا البند — لا يمكن حذفه تلقائياً.',
            ]);
        }

        return $permit;
    }
}
