<?php

namespace Modules\Insurance\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Insurance\Models\InsuranceClaim;
use Modules\Insurance\Models\InsuranceCompany;
use Modules\Insurance\Models\PriceList;
use Modules\Insurance\Models\PriceListItem;
use Modules\Insurance\Repositories\Contracts\InsuranceRepositoryInterface;
use Modules\Inventory\Models\Service;

class InsuranceService
{
    public function __construct(
        private readonly InsuranceRepositoryInterface $repository,
    ) {}

    public function list(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository->paginate($search, $perPage);
    }

    public function allActive(): Collection
    {
        return $this->repository->allActive();
    }

    public function create(array $data): InsuranceCompany
    {
        return $this->repository->create($data);
    }

    public function findById(string $id): InsuranceCompany
    {
        return $this->repository->findById($id);
    }

    public function update(string $id, array $data): InsuranceCompany
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Calculate how much insurance covers and patient share for a given service + company.
     *
     * @return array{insurance_pays: float, patient_pays: float, base_price: float}
     */
    public function calculateInsuranceCoverage(string $serviceId, string $companyId): array
    {
        $service = Service::findOrFail($serviceId);
        $company = InsuranceCompany::findOrFail($companyId);

        // Check if there's a price list item override for this service
        $override = PriceListItem::query()
            ->whereHas('priceList', fn ($q) => $q->where('ins_company_id', $companyId)->where('is_active', true))
            ->where('service_id', $serviceId)
            ->first();

        $basePrice = $override ? $override->price : ($service->ins_price ?: $service->price);
        $coveragePercent = $company->coverage_pct;
        $insurancePays = round($basePrice * $coveragePercent / 100, 2);
        $patientPays = round($basePrice - $insurancePays, 2);

        return [
            'base_price' => $basePrice,
            'insurance_pays' => $insurancePays,
            'patient_pays' => $patientPays,
            'coverage_pct' => $coveragePercent,
        ];
    }

    public function priceLists(?string $companyId = null): Collection|array
    {
        return PriceList::with(['company', 'items.service'])
            ->when($companyId, fn ($q, $v) => $q->where('ins_company_id', $v))
            ->where('is_active', true)
            ->get();
    }

    public function createPriceList(array $data, array $items): PriceList
    {
        return $this->repository->createPriceList($data, $items);
    }

    public function allPriceLists(int $perPage = 20): LengthAwarePaginator
    {
        return PriceList::with('company')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getClaimsList(?string $companyId = null, int $perPage = 30): LengthAwarePaginator
    {
        return InsuranceClaim::with(['company:id,name', 'service:id,name'])
            ->when($companyId, fn ($q, $v) => $q->where('insurance_company_id', $v))
            ->orderByDesc('claim_date')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function getMonthlyClaimsStats(): array
    {
        $thisMonth = now()->startOfMonth();
        $monthlyClaimsCount = InsuranceClaim::where('claim_date', '>=', $thisMonth)->count();
        $monthlyClaimsTotal = InsuranceClaim::where('claim_date', '>=', $thisMonth)->sum('insurance_share');

        return [
            'monthly_claims_count' => $monthlyClaimsCount,
            'monthly_claims_total' => round((float) $monthlyClaimsTotal, 2),
        ];
    }

    public function getSelectableServices(): Collection
    {
        return Service::active()
            ->orderBy('dept')
            ->orderBy('name')
            ->get(['id', 'name', 'dept', 'price', 'ins_price']);
    }
}
