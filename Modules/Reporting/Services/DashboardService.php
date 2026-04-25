<?php

namespace Modules\Reporting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function todayStats(): array
    {
        $today = today()->toDateString();

        $bookings = DB::table('bookings')->whereDate('visit_date', $today);

        return [
            'today_bookings' => (clone $bookings)->count(),
            'today_revenue' => (float) (clone $bookings)->where('pay_status', '!=', 'unpaid')->sum('price'),
            'today_paid' => (clone $bookings)->where('pay_status', 'paid')->count(),
            'today_pending' => (clone $bookings)->where('pay_status', 'unpaid')->count(),
        ];
    }

    public function revenueByDept(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? today()->startOfMonth()->toDateString();
        $to = $to ?? today()->toDateString();

        return DB::table('bookings')
            ->select('dept', DB::raw('COUNT(*) as cases'), DB::raw('SUM(price) as revenue'))
            ->where('pay_status', '!=', 'unpaid')
            ->whereBetween('visit_date', [$from, $to])
            ->groupBy('dept')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    public function revenueByDoctor(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? today()->startOfMonth()->toDateString();
        $to = $to ?? today()->toDateString();

        return DB::table('bookings')
            ->join('doctors', 'bookings.doctor_id', '=', 'doctors.id')
            ->select('doctors.name as doctor_name', DB::raw('COUNT(*) as cases'), DB::raw('SUM(bookings.price) as revenue'))
            ->where('bookings.pay_status', '!=', 'unpaid')
            ->whereBetween('bookings.visit_date', [$from, $to])
            ->groupBy('doctors.id', 'doctors.name')
            ->orderByDesc('revenue')
            ->get()
            ->toArray();
    }

    public function treasuryBalance(): array
    {
        $totalIn = DB::table('treasury_entries')->where('type', 'in')->sum('amount');
        $totalOut = DB::table('treasury_entries')->where('type', 'out')->sum('amount');

        return [
            'total_in' => (float) $totalIn,
            'total_out' => (float) $totalOut,
            'balance' => (float) ($totalIn - $totalOut),
        ];
    }

    public function lowStockCount(): int
    {
        return DB::table('inventory')
            ->whereRaw('quantity <= min_quantity AND min_quantity > 0')
            ->count();
    }

    public function todayQueue(): Collection
    {
        return DB::table('bookings')
            ->leftJoin('doctors', 'bookings.doctor_id', '=', 'doctors.id')
            ->select(
                'bookings.id',
                'bookings.file_no',
                'bookings.patient_name',
                'bookings.dept',
                'bookings.status',
                'bookings.pay_status',
                // 'bookings.time',
                'doctors.name as doctor_name',
            )
           // ->whereDate('bookings.date', today())
            ->whereIn('bookings.status', ['confirmed', 'waiting', 'in_progress'])
          //  ->orderBy('bookings.time')
            ->limit(20)
            ->get();
    }

    public function getIncomeStats(string $from, string $to): array
    {
        $bookings = DB::table('bookings')
            ->whereBetween('visit_date', [$from, $to]);

        $totalRevenue = (float) (clone $bookings)->where('pay_status', '!=', 'unpaid')->sum('price');
        $paidCount = (clone $bookings)->where('pay_status', 'paid')->count();
        $pendingAmount = (float) (clone $bookings)->where('pay_status', 'unpaid')->sum('price');
        $todayRevenue = (float) DB::table('bookings')
            ->whereDate('visit_date', today())
            ->where('pay_status', '!=', 'unpaid')
            ->sum('price');

        return compact('totalRevenue', 'paidCount', 'pendingAmount', 'todayRevenue');
    }
}
