<?php

namespace Modules\Insurance\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;

interface InsuranceRepositoryInterface
{
    public function paginate(?string $search = null, int $perPage = 20): LengthAwarePaginator;

    public function allActive(): Collection;

    public function findById(string $id): InsuranceCompany;

    public function create(array $data): InsuranceCompany;

    public function update(string $id, array $data): InsuranceCompany;

    public function priceLists(string $companyId): Collection;

    public function createPriceList(array $data, array $items): PriceList;

    public function updatePriceList(PriceList $priceList, array $data, array $items): PriceList;
}
