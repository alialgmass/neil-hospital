<?php

namespace Modules\Insurance\Services;

use Illuminate\Support\Collection;
use Modules\Admin\Models\ActivityLog;
use Modules\Booking\Enums\PayMethod;
use Modules\Booking\Models\Booking;
use Modules\Insurance\Enums\DepositDecision;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PendingDeposit;
use Modules\Insurance\States\ApprovedState;
use Modules\Insurance\States\ClaimStatus;
use Modules\Insurance\States\ClosedState;
use Modules\Insurance\States\DraftState;
use Modules\Insurance\States\PaidState;

/**
 * Read model behind the collections console: counts, money, per-stage and
 * per-company aggregates with ageing, reception queue, deposits and audit
 * flags — all derived from current claim/booking data, nothing is stored.
 */
class CollectionConsoleService
{
    /** Audit thresholds (guide §collection control), kept in one place. */
    public const RECEPTION_LATE_DAYS = 3;

    public const APPROVED_UNCOLLECTED_DAYS = 15;

    public const OVERDUE_DAYS = 90;

    public const REJECTION_RATIO = 0.15;

    public const DEDUCTION_RATIO = 0.10;

    public const GAP_TOLERANCE = 1;

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $claims = InsuranceClaim::with(['company', 'booking', 'service'])->latest('created_at')->get();
        $reception = $this->reception();
        $deposits = PendingDeposit::with(['booking', 'company'])->latest('created_at')->get();

        return [
            'counts' => $this->counts($claims),
            'money' => $this->money($claims, $deposits),
            'stages' => $this->stages($claims),
            'companies' => $this->companies($claims),
            'claims' => $claims->map(fn (InsuranceClaim $c) => $this->claimRow($c))->values()->all(),
            'reception' => $reception->map(fn (Booking $b) => $this->receptionRow($b))->values()->all(),
            'deposits' => $deposits->map(fn (PendingDeposit $d) => $this->depositRow($d))->values()->all(),
            'flags' => $this->flags($claims, $reception),
            'log' => $this->log(),
            'stageLabels' => ClaimStatus::labels(),
        ];
    }

    /**
     * Outstanding = what the company still owes on a submitted claim:
     * share − rejected − received − deduction. Drafts, paid and closed claims
     * owe nothing (drafts haven't been claimed yet).
     */
    public function outstanding(InsuranceClaim $claim): float
    {
        if ($claim->status instanceof DraftState || $claim->status instanceof PaidState || $claim->status instanceof ClosedState) {
            return 0.0;
        }

        return max(0.0, $claim->insurance_share - $claim->rejected_amount - (float) $claim->paid_amount - $claim->deduction);
    }

    public function ageInDays(InsuranceClaim $claim): ?int
    {
        return $claim->submission_date ? (int) $claim->submission_date->diffInDays(now()->startOfDay(), true) : null;
    }

    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @return array<string, int>
     */
    private function counts(Collection $claims): array
    {
        $made = $claims->reject(fn (InsuranceClaim $c) => $c->status instanceof DraftState);

        return [
            'total' => $claims->count(),
            'made' => $made->count(),
            'not_made' => $claims->count() - $made->count(),
            'open' => $claims->filter(fn (InsuranceClaim $c) => $this->outstanding($c) > 0)->count(),
        ];
    }

    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @param  Collection<int, PendingDeposit>  $deposits
     * @return array<string, int|float>
     */
    private function money(Collection $claims, Collection $deposits): array
    {
        return [
            'due' => $this->num($claims->sum('insurance_share')),
            'claimed' => $this->num($claims->reject(fn ($c) => $c->status instanceof DraftState)->sum('insurance_share')),
            'collected' => $this->num($claims->sum('paid_amount')),
            'rejected' => $this->num($claims->sum('rejected_amount') + $claims->sum('deduction')),
            'outstanding' => $this->num($claims->sum(fn (InsuranceClaim $c) => $this->outstanding($c))),
            'held_deposits' => $this->num($deposits
                ->reject(fn (PendingDeposit $d) => $d->refunded || $d->decision === DepositDecision::Rejected)
                ->sum('amount')),
        ];
    }

    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @return array<string, array{count: int, amount: int|float}>
     */
    private function stages(Collection $claims): array
    {
        return collect(ClaimStatus::labels())->mapWithKeys(function (string $label, string $key) use ($claims) {
            $inStage = $claims->filter(fn (InsuranceClaim $c) => (string) $c->status === $key);

            return [$key => ['count' => $inStage->count(), 'amount' => $this->num($inStage->sum('insurance_share'))]];
        })->all();
    }

    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @return array<int, array<string, mixed>>
     */
    private function companies(Collection $claims): array
    {
        return $claims->groupBy('insurance_company_id')->map(function (Collection $group, string $id) {
            /** @var InsuranceCompany|null $company */
            $company = $group->first()->company;
            $ageing = ['within_30' => 0.0, 'from_30_to_60' => 0.0, 'from_60_to_90' => 0.0, 'over_90' => 0.0];

            foreach ($group as $claim) {
                $owed = $this->outstanding($claim);
                $age = $this->ageInDays($claim);

                if ($owed > 0 && $age !== null) {
                    $ageing[match (true) {
                        $age <= 30 => 'within_30',
                        $age <= 60 => 'from_30_to_60',
                        $age <= 90 => 'from_60_to_90',
                        default => 'over_90',
                    }] += $owed;
                }
            }

            $made = $group->reject(fn ($c) => $c->status instanceof DraftState);

            return [
                'id' => $id,
                'name' => $company?->name,
                'claims' => $made->count(),
                'claimed' => $this->num($made->sum('insurance_share')),
                'collected' => $this->num($group->sum('paid_amount')),
                'rejected' => $this->num($group->sum('rejected_amount')),
                'deduction' => $this->num($group->sum('deduction')),
                'outstanding' => $this->num($group->sum(fn (InsuranceClaim $c) => $this->outstanding($c))),
                'ageing' => array_map(fn (float $v) => $this->num($v), $ageing),
            ];
        })->values()->all();
    }

    /**
     * Contract bookings that never became a claim.
     *
     * @return Collection<int, Booking>
     */
    private function reception(): Collection
    {
        return Booking::query()
            ->whereIn('pay_method', [PayMethod::Insurance->value, PayMethod::Contract->value])
            ->whereNotNull('ins_company_id')
            ->whereDoesntHave('insuranceClaim')
            ->with('insuranceCompany')
            ->latest('visit_date')
            ->limit(200)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function receptionRow(Booking $booking): array
    {
        $price = (float) $booking->price;

        return [
            'id' => $booking->id,
            'patient' => $booking->patient_name,
            'file_no' => $booking->file_no,
            'company' => $booking->insuranceCompany?->name,
            'service' => $booking->service_name,
            'dept' => $booking->dept?->value,
            'date' => $booking->visit_date?->toDateString(),
            'price' => $this->num($price),
            'status' => $price > 0 ? 'ready' : 'waiting_pricing',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function claimRow(InsuranceClaim $claim): array
    {
        return [
            'id' => $claim->id,
            'reference' => $claim->claim_reference,
            'company_id' => $claim->insurance_company_id,
            'company' => $claim->company?->name,
            'patient' => $claim->patient_name,
            'file_no' => $claim->file_no,
            'service' => $claim->service_name,
            'dept' => $claim->booking?->dept?->value,
            'stage' => (string) $claim->status,
            'stage_label' => $claim->status->label(),
            'share' => $this->num($claim->insurance_share),
            'approved' => $claim->approved_amount === null ? null : $this->num($claim->approved_amount),
            'paid' => $this->num((float) $claim->paid_amount),
            'deduction' => $this->num($claim->deduction),
            'outstanding' => $this->num($this->outstanding($claim)),
            'age' => $this->ageInDays($claim),
            'submission_date' => $claim->submission_date?->toDateString(),
            'collected_at' => $claim->collected_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function depositRow(PendingDeposit $deposit): array
    {
        return [
            'id' => $deposit->id,
            'patient' => $deposit->booking?->patient_name,
            'company' => $deposit->company?->name,
            'service' => $deposit->booking?->service_name,
            'amount' => $this->num($deposit->amount),
            'paid_on' => $deposit->paid_on?->toDateString(),
            'decision' => $deposit->decision->value,
            'decision_label' => $deposit->decision->label(),
            'refunded' => $deposit->refunded,
            'converted' => $deposit->converted,
        ];
    }

    /**
     * @param  Collection<int, InsuranceClaim>  $claims
     * @param  Collection<int, Booking>  $reception
     * @return array<int, array{kind: string, severity: string, title: string, detail: string}>
     */
    private function flags(Collection $claims, Collection $reception): array
    {
        $flags = [];
        $add = function (string $kind, string $severity, string $title, string $detail) use (&$flags): void {
            $flags[] = compact('kind', 'severity', 'title', 'detail');
        };

        foreach ($reception as $booking) {
            $days = $booking->visit_date ? (int) $booking->visit_date->diffInDays(now()->startOfDay(), true) : 0;
            if ($days > self::RECEPTION_LATE_DAYS) {
                $add('reception_late', 'high', 'حالة استقبال متأخرة التحويل',
                    "{$booking->patient_name} ({$booking->insuranceCompany?->name}) مسجّلة من {$days} يوم ولسه ما اتحوّلتش لمطالبة — خطر ضياع إيراد.");
            }
        }

        foreach ($claims as $claim) {
            $ref = $claim->claim_reference ?: $claim->id;
            $company = $claim->company?->name;
            $age = $this->ageInDays($claim);
            $share = $claim->insurance_share;
            $outstanding = $this->outstanding($claim);

            if ($claim->status instanceof ApprovedState) {
                $since = $claim->approval_date ?? $claim->submission_date;
                $days = $since ? (int) $since->diffInDays(now()->startOfDay(), true) : 0;
                if ($days > self::APPROVED_UNCOLLECTED_DAYS) {
                    $add('approved_uncollected', 'high', 'معتمدة وما اتحصّلتش',
                        "{$ref} ({$company}) معتمدة من {$days} يوم و ".number_format($share - $claim->rejected_amount).' ج.م لسه ما دخلش الخزينة.');
                }
            }

            if (! ($claim->status instanceof DraftState) && $share > 0 && $claim->rejected_amount / $share > self::REJECTION_RATIO) {
                $add('rejection_high', 'medium', 'نسبة رفض عالية',
                    "{$ref}: المرفوض ".number_format($claim->rejected_amount).' من '.number_format($share).' — راجع سبب الرفض ووثّقه.');
            }

            if ($claim->status instanceof PaidState || $claim->status instanceof ClosedState) {
                $due = ($claim->approved_amount ?? $share) - $claim->rejected_amount;

                if ($due > 0 && $claim->deduction / $due > self::DEDUCTION_RATIO) {
                    $add('deduction_high', 'high', 'خصم/ضريبة أعلى من المعتاد',
                        "{$ref}: الخصم ".number_format($claim->deduction).' = '.round($claim->deduction / $due * 100).'% من المستحق. لازم مستند رسمي يثبت الخصم.');
                }

                $gap = $due - ((float) $claim->paid_amount + $claim->deduction);
                if (abs($gap) > self::GAP_TOLERANCE) {
                    $add('collection_gap', 'high', 'فرق غير مُبرّر في التحصيل',
                        "{$ref}: المستحق ".number_format($due).' والمسجّل (محصّل + خصم) '.number_format((float) $claim->paid_amount + $claim->deduction).' — فرق '.number_format(abs($gap)).' مش مُفسَّر.');
                }
            }

            $catalog = $claim->service;
            if ($catalog && ! in_array(round($claim->invoice_amount, 2), [round((float) $catalog->price, 2), round((float) $catalog->ins_price, 2)], true)) {
                $add('price_mismatch', 'medium', 'سعر مختلف عن الكتالوج',
                    "{$ref}: {$claim->service_name} مسجّلة بـ ".number_format($claim->invoice_amount).' والسعر المعتمد '.number_format((float) $catalog->price).'.');
            }

            if ($outstanding > 0 && $age !== null && $age > self::OVERDUE_DAYS) {
                $add('overdue_90', 'medium', 'دين متأخر أكتر من ٣ شهور',
                    "{$ref} ({$company}): ".number_format($outstanding)." ج.م عدّى عليه {$age} يوم من غير تحصيل.");
            }
        }

        $seen = [];
        foreach ($claims as $claim) {
            $file = $claim->booking?->file_no ?: $claim->file_no;
            if (! $file) {
                continue;
            }
            $key = $file.'|'.$claim->service_name;
            $ref = $claim->claim_reference ?: $claim->id;
            if (isset($seen[$key])) {
                $add('duplicate', 'medium', 'حالة مكرّرة محتملة',
                    "{$claim->patient_name} — {$claim->service_name} مسجّلة في {$seen[$key]} و {$ref}. اتأكد إنها مش مزدوجة.");
            } else {
                $seen[$key] = $ref;
            }
        }

        usort($flags, fn (array $a, array $b) => ($a['severity'] === 'high' ? 0 : 1) <=> ($b['severity'] === 'high' ? 0 : 1));

        return $flags;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function log(): array
    {
        return ActivityLog::where('module', 'insurance_collection')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->map(fn (ActivityLog $l) => [
                'time' => $l->created_at?->format('Y-m-d H:i'),
                'user' => $l->user_name,
                'action' => $l->action,
                'detail' => $l->description,
                'record_id' => $l->record_id,
            ])->all();
    }

    private function num(float|int $value): int|float
    {
        $rounded = round((float) $value, 2);

        return floor($rounded) === $rounded ? (int) $rounded : $rounded;
    }
}
