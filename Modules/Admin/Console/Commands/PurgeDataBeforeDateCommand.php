<?php

namespace Modules\Admin\Console\Commands;

use Carbon\Carbon;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Booking\Models\Booking;
use Modules\Clinic\Models\ClinicSheet;
use Modules\Clinic\Models\MedicalExamination;
use Modules\Labs\Models\DiagnosticResult;
use Modules\Surgery\Models\Surgery;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Permanently deletes bookings (with their clinical / insurance reflections),
 * journal + treasury entries and doctor dues dated strictly before a cutoff:
 *
 *   php artisan data:purge-before 2026-10-01 --dry-run
 *   php artisan data:purge-before 2026-10-01
 */
class PurgeDataBeforeDateCommand extends Command
{
    protected $signature = 'data:purge-before
        {date=2026-10-01 : Cutoff (YYYY-MM-DD) — records dated strictly before it are deleted}
        {--dry-run : Show what would be deleted without deleting}
        {--reset-doctor-debt : Also zero every doctor\'s running debt balance}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Delete bookings, journal/treasury entries and doctor dues dated before a cutoff';

    public function handle(): int
    {
        $cutoff = $this->parseCutoff((string) $this->argument('date'));

        if ($cutoff === null) {
            $this->error('Invalid date: expected YYYY-MM-DD.');

            return self::FAILURE;
        }

        $date = $cutoff->toDateString();
        $steps = $this->steps($date);

        $this->table(['البند', 'العدد'], array_map(
            fn (string $label, array $step) => [$label, number_format($step['query']()->count())],
            array_keys($steps),
            $steps,
        ));

        $debtors = DB::table('doctors')->where('doctor_debt_balance', '!=', 0)->count();

        if ($debtors > 0) {
            $this->warn($this->option('reset-doctor-debt')
                ? "سيتم تصفير رصيد مديونية {$debtors} طبيب."
                : "{$debtors} طبيب لديهم رصيد مديونية لن يتغير (استخدم --reset-doctor-debt لتصفيره).");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — nothing deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("حذف نهائي لكل ما سبق قبل {$date}؟ لا يمكن التراجع.")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($steps, $date) {
            $this->deleteBookingMedia($date);

            foreach ($steps as $label => $step) {
                $this->line($label.': '.number_format($step['query']()->delete()));
            }

            if ($this->option('reset-doctor-debt')) {
                DB::table('doctors')->update(['doctor_debt_balance' => 0]);
            }
        });

        $this->info("Purged all records dated before {$date}.");

        return self::SUCCESS;
    }

    private function parseCutoff(string $value): ?Carbon
    {
        try {
            $cutoff = Carbon::createFromFormat('!Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        return $cutoff !== false && $cutoff->toDateString() === $value ? $cutoff : null;
    }

    /**
     * Ordered delete steps; booking-dependent rows go first, bookings last.
     *
     * @return array<string, array{query: Closure(): Builder}>
     */
    private function steps(string $date): array
    {
        $bookingIds = fn () => DB::table('bookings')->where('visit_date', '<', $date)->select('id');
        $byBooking = fn (string $table) => fn () => DB::table($table)->whereIn('booking_id', $bookingIds());

        return [
            'تفويضات الأطباء' => ['query' => $byBooking('booking_doctor_delegations')],
            'مستحقات الأطباء (استحقاقات)' => ['query' => fn () => DB::table('doctor_entitlements')
                ->where(fn ($q) => $q->whereIn('booking_id', $bookingIds())
                    ->orWhere(fn ($q) => $q->whereNull('booking_id')->whereDate('created_at', '<', $date)))],
            'مدفوعات الأطباء' => ['query' => fn () => DB::table('dr_payments')->where('paid_at', '<', $date)],
            'تسويات مديونية الأطباء' => ['query' => fn () => DB::table('doctor_debt_settlements')
                ->where(fn ($q) => $q->whereIn('booking_id', $bookingIds())
                    ->orWhere(fn ($q) => $q->whereNull('booking_id')->whereDate('created_at', '<', $date)))],
            'مطالبات التأمين' => ['query' => $byBooking('insurance_claims')],
            'ودائع التأمين المعلقة' => ['query' => $byBooking('pending_deposits')],
            'العمليات الجراحية' => ['query' => $byBooking('surgeries')],
            'نتائج المختبر' => ['query' => $byBooking('diagnostic_results')],
            'الفحوصات الطبية' => ['query' => $byBooking('medical_examinations')],
            'كشوفات العيادة' => ['query' => $byBooking('clinic_sheets')],
            'خدمات الحجز' => ['query' => $byBooking('booking_services')],
            'سجل النشاط للحجوزات' => ['query' => fn () => DB::table('activity_logs')->whereIn('record_id', $bookingIds())],
            'قيود الخزينة' => ['query' => fn () => DB::table('treasury_entries')->where('date', '<', $date)],
            'القيود المحاسبية' => ['query' => fn () => DB::table('journal_entries')->where('date', '<', $date)],
            'الحجوزات' => ['query' => $bookingIds],
        ];
    }

    private function deleteBookingMedia(string $date): void
    {
        $bookingIds = fn () => DB::table('bookings')->where('visit_date', '<', $date)->select('id');

        $owners = [
            Booking::class => $bookingIds(),
            ClinicSheet::class => DB::table('clinic_sheets')->whereIn('booking_id', $bookingIds())->select('id'),
            MedicalExamination::class => DB::table('medical_examinations')->whereIn('booking_id', $bookingIds())->select('id'),
            DiagnosticResult::class => DB::table('diagnostic_results')->whereIn('booking_id', $bookingIds())->select('id'),
            Surgery::class => DB::table('surgeries')->whereIn('booking_id', $bookingIds())->select('id'),
        ];

        foreach ($owners as $modelType => $ids) {
            Media::query()
                ->where('model_type', $modelType)
                ->whereIn('model_id', $ids)
                ->get()
                ->each(function (Media $media) {
                    try {
                        $media->delete();
                    } catch (\Throwable $e) {
                        Log::warning("Failed to delete media ID {$media->id}: ".$e->getMessage());
                    }
                });
        }
    }
}
