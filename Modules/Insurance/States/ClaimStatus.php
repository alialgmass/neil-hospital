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
        'draft' => 'مسودة',
        'submitted' => 'مُرسلة',
        'approved' => 'معتمدة',
        'rejected' => 'مرفوضة',
        'paid' => 'مسددة',
    ];

    abstract public function label(): string;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, SubmittedState::class)
            ->allowTransition(SubmittedState::class, ApprovedState::class)
            ->allowTransition(SubmittedState::class, RejectedState::class)
            ->allowTransition(ApprovedState::class, PaidState::class);
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return self::STATUS_LABELS;
    }
}
