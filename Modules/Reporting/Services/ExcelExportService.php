<?php

namespace Modules\Reporting\Services;

use Maatwebsite\Excel\Facades\Excel;
use Modules\Reporting\Exports\DeptRevenueExport;
use Modules\Reporting\Exports\DoctorClaimsExport;
use Modules\Reporting\Exports\DoctorPaymentsExport;
use Modules\Reporting\Exports\ProfitLossExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    public function export(string $type, array $data): BinaryFileResponse
    {
        return match ($type) {
            'dept-revenue' => Excel::download(new DeptRevenueExport($data), 'dept-revenue.xlsx'),
            'doctor-claims' => Excel::download(new DoctorClaimsExport($data), 'doctor-claims.xlsx'),
            'doctor-payments' => Excel::download(new DoctorPaymentsExport($data), 'doctor-payments.xlsx'),
            'profit-loss' => Excel::download(new ProfitLossExport($data), 'profit-loss.xlsx'),
            default => abort(404, 'نوع التقرير غير مدعوم'),
        };
    }
}
