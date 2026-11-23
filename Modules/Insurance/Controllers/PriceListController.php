<?php

namespace Modules\Insurance\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Insurance\Actions\ManagePriceListAction;
use Modules\Insurance\Http\Requests\StorePriceListRequest;
use Modules\Insurance\Http\Requests\UpdatePriceListItemRequest;
use Modules\Insurance\Http\Requests\UpdatePriceListRequest;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Models\PriceListItem;
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
            'priceLists' => $this->insuranceService->allPriceLists(),
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

    /**
     * Edit a price list's header and its service prices in one atomic save.
     */
    public function update(UpdatePriceListRequest $request, PriceList $priceList): RedirectResponse
    {
        $data = $request->validated();
        $items = $data['items'];
        unset($data['items']);

        $this->managePriceListAction->update($priceList, $data, $items);

        return back()->with('success', 'تم تعديل قائمة الأسعار بنجاح.');
    }

    /**
     * Update a single price-list item's price. This only affects the live
     * price used by future bookings/claims — existing bookings/claims
     * already snapshot the price they were created with and are unaffected.
     */
    public function updateItem(UpdatePriceListItemRequest $request, PriceListItem $item): RedirectResponse
    {
        $item->update($request->validated());

        return back()->with('success', 'تم تحديث سعر الخدمة بنجاح.');
    }
}
