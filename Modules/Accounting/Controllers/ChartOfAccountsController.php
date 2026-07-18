<?php

namespace Modules\Accounting\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Actions\CreateAccountAction;
use Modules\Accounting\Actions\UpdateAccountAction;
use Modules\Accounting\Http\Requests\StoreAccountRequest;
use Modules\Accounting\Http\Requests\UpdateAccountRequest;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Services\AccountService;

class ChartOfAccountsController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly CreateAccountAction $createAction,
        private readonly UpdateAccountAction $updateAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('accounting/ChartOfAccounts', [
            'accounts' => $this->accountService->all(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $this->createAction->execute($request->validated());

        return back()->with('success', 'تم إضافة الحساب بنجاح.');
    }

    public function update(UpdateAccountRequest $request, string $id): RedirectResponse
    {
        $account = Account::findOrFail($id);
        $this->updateAction->execute($account, $request->validated());

        return back()->with('success', 'تم تعديل الحساب بنجاح.');
    }
}
