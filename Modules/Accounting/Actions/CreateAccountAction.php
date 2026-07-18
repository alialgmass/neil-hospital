<?php

namespace Modules\Accounting\Actions;

use Modules\Accounting\Models\Account;
use Modules\Admin\Services\ActivityLogService;

class CreateAccountAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(array $data): Account
    {
        $account = Account::create($data);

        $this->activityLogService->log(
            action: 'create',
            module: 'accounting',
            recordId: $account->id,
            description: "إضافة حساب: {$account->code} — {$account->name}",
            newValues: $data,
        );

        return $account;
    }
}
