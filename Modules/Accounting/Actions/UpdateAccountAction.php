<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Models\Account;
use Modules\Admin\Services\ActivityLogService;

class UpdateAccountAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(Account $account, array $data): Account
    {
        $account->update($data);

        $this->activityLogService->log(
            action: 'update',
            module: 'accounting',
            recordId: $account->id,
            description: "تعديل حساب: {$account->code} — {$account->name}",
            newValues: $data,
        );

        return $account->fresh();
    }
}
