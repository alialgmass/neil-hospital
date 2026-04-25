<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Insurance\Actions\ManagePriceListAction;
use Modules\Insurance\Http\Requests\StorePriceListRequest;
use Modules\Insurance\Services\InsuranceService;

class PriceListController extends Controller
{
    public function __construct(
        private readonly InsuranceService $insuranceService,
        private readonly ManagePriceListAction $managePriceListAction,
    ) {}

    public function index(): Response
    {
        return Inertia::render('insurance/PriceLists', [
            'priceLists' => $this->insuranceService->allPriceLists()->through(
                fn ($pl) => tap($pl, fn ($p) => $p->load('items.service:id,name,dept'))
            ),
            'companies' => $this->insuranceService->allActive(),
            'services' => $this->insuranceService->getSelectableServices(),
        ]);
    }

    public function store(StorePriceListRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'] ?? [];
        unset($data['items']);

        $this->managePriceListAction->create($data, $items);

        return back()->with('success', 'تم إنشاء قائمة الأسعار بنجاح.');
    }
}
