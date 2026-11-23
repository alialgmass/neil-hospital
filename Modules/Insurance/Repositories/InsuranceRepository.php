<?php

namespace Modules\Insurance\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Repositories\Contracts\InsuranceRepositoryInterface;

class InsuranceRepository implements InsuranceRepositoryInterface
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return InsuranceCompany::query()
            ->when($search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function allActive(): Collection
    {
        return InsuranceCompany::where('status', 'active')->orderBy('name')->get();
    }

    public function findById(string $id): InsuranceCompany
    {
        return InsuranceCompany::findOrFail($id);
    }

    public function create(array $data): InsuranceCompany
    {
        return InsuranceCompany::create($data);
    }

    public function update(string $id, array $data): InsuranceCompany
    {
        $company = $this->findById($id);
        $company->update($data);

        return $company->fresh();
    }

    public function priceLists(string $companyId): Collection
    {
        return PriceList::with('items.service')
            ->where('ins_company_id', $companyId)
            ->get();
    }

    public function createPriceList(array $data, array $items): PriceList
    {
        $priceList = PriceList::create($data);

        foreach ($items as $item) {
            $priceList->items()->create($item);
        }

        return $priceList->load('items.service');
    }

    /**
     * Update header fields and sync items by service_id: existing services get
     * their price updated, new services are added, omitted services are removed.
     *
     * @param  array<int, array{service_id: string, price: float|int|string}>  $items
     */
    public function updatePriceList(PriceList $priceList, array $data, array $items): PriceList
    {
        $priceList->update($data);

        $pricesByService = collect($items)->mapWithKeys(fn (array $item) => [$item['service_id'] => $item['price']]);

        $priceList->items()->whereNotIn('service_id', $pricesByService->keys())->delete();

        $existing = $priceList->items()->get()->keyBy('service_id');

        foreach ($pricesByService as $serviceId => $price) {
            $item = $existing->get($serviceId);

            if ($item) {
                $item->update(['price' => $price]);
            } else {
                $priceList->items()->create(['service_id' => $serviceId, 'price' => $price]);
            }
        }

        return $priceList->load('items.service');
    }
}
