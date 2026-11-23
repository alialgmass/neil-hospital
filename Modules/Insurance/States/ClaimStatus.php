<?php

namespace Modules\Insurance\States;

use Modules\Insurance\Models\InsuranceClaim;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<InsuranceClaim>
 */
abstract class ClaimStatus extends State
{
    /**
     * Arabic labels indexed by status name, used for report/settings UI
     * without needing to instantiate a State (which requires a model).
     *
     * @var array<string, string>
     */
    private const STATUS_LABELS = [
        'draft' => 'غير مسددة',
        'submitted' => 'مُرسلة',
        'approved' => 'معتمدة',
        'under_review' => 'تحت المراجعة',
        'rejected' => 'مرفوضة',
        'paid' => 'مسددة',
        'closed' => 'مقفولة',
    ];

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, SubmittedState::class)
            ->allowTransition(SubmittedState::class, ApprovedState::class)
            ->allowTransition(SubmittedState::class, RejectedState::class)
            ->allowTransition(SubmittedState::class, UnderReviewState::class)
            ->allowTransition(UnderReviewState::class, ApprovedState::class)
            ->allowTransition(UnderReviewState::class, RejectedState::class)
            ->allowTransition(RejectedState::class, SubmittedState::class)
            ->allowTransition(ApprovedState::class, PaidState::class)
            ->allowTransition(PaidState::class, ClosedState::class);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::STATUS_LABELS;
    }
}
