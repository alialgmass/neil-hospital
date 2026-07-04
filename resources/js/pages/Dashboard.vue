<template>
    <Head title="لوحة التحكم" />

    <!-- Today Stats Grid -->
    <div class="stats-row grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <StatCard label="حجوزات اليوم" :value="todayStats.today_bookings" color="primary">
            <template #icon><Users class="h-4 w-4" /></template>
        </StatCard>
        <StatCard label="إيرادات اليوم" :value="fmt(todayStats.today_revenue)" color="accent">
            <template #icon><Banknote class="h-4 w-4" /></template>
        </StatCard>
        <StatCard label="مسدد اليوم" :value="fmt(todayStats.today_paid)" color="success">
            <template #icon><Activity class="h-4 w-4" /></template>
        </StatCard>
        <StatCard label="رصيد الخزنة" :value="fmt(treasury.balance)" :color="treasury.balance >= 0 ? 'primary' : 'danger'">
            <template #icon><Banknote class="h-4 w-4" /></template>
        </StatCard>
    </div>

    <!-- Alert for Low Stock -->
    <div v-if="lowStockCount > 0" class="alert al-warn flex gap-3 p-3 items-center mb-4 border rounded-[var(--r)] bg-hospital-warning-pale border-orange-200 text-hospital-warning">
        <AlertTriangle class="al-icon h-5 w-5" />
        <div class="flex-1 text-xs font-semibold">
            تنبيه المخزن: هناك {{ lowStockCount }} أصناف وصلت للحد الأدنى.
        </div>
        <Link href="/inventory?low_stock=1" class="btn btn-xs bg-white/50">عرض الكل</Link>
    </div>

    <!-- Dashboard Content Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Right Column: Queue / Main Ops (2/3) -->
        <div class="lg:col-span-2 space-y-4">
            <div class="card bg-white border border-hospital-border rounded-[var(--rl)] overflow-hidden [box-shadow:var(--sh)]">
                <div class="card-hd flex items-center justify-between px-4 py-3 border-b border-hospital-border bg-hospital-surface-2">
                    <div>
                        <h3 class="card-title text-[13px] font-bold text-hospital-text">قائمة انتظار اليوم</h3>
                        <p class="card-sub text-[10px] text-hospital-text-3">متابعة فورية للحالات الحالية بالمستشفى</p>
                    </div>
                    <Link href="/booking" class="btn btn-sm btn-icon border-hospital-border hover:bg-hospital-bg">
                        <Plus class="h-3.5 w-3.5" />
                    </Link>
                </div>
                <div class="card-bd p-0">
                    <div class="tbl-wrap overflow-x-auto">
                        <table class="w-full text-right">
                            <thead>
                                <tr class="bg-hospital-surface-2">
                                    <th class="px-3 py-2 text-[11px] font-bold text-hospital-text-3 border-b">الوقت</th>
                                    <th class="px-3 py-2 text-[11px] font-bold text-hospital-text-3 border-b">المريض</th>
                                    <th class="px-3 py-2 text-[11px] font-bold text-hospital-text-3 border-b">القسم</th>
                                    <th class="px-3 py-2 text-[11px] font-bold text-hospital-text-3 border-b">الحالة</th>
                                    <th class="px-3 py-2 text-[11px] font-bold text-hospital-text-3 border-b">الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in todayQueue" :key="entry.id" class="hover:bg-hospital-primary-pale transition-colors border-b border-hospital-border/50">
                                    <td class="px-3 py-2.5 text-[11px] font-mono text-hospital-text-2">{{ entry.time?.slice(0, 5) ?? '--:--' }}</td>
                                    <td class="px-3 py-2.5">
                                        <p class="text-[12px] font-bold text-hospital-text">{{ entry.patient_name }}</p>
                                        <p class="text-[10px] text-hospital-text-3">{{ entry.file_no }}</p>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <span :class="['dept-badge border px-2 py-0.5 rounded-full text-[10px] font-bold', getDeptClass(entry.dept)]">
                                            {{ deptLabels[entry.dept] ?? entry.dept }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <Badge :variant="entry.status" />
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <Link :href="`/booking/patient/${entry.file_no}`" class="btn btn-xs border-hospital-border">معاينة</Link>
                                    </td>
                                </tr>
                                <tr v-if="todayQueue.length === 0">
                                    <td colspan="5" class="py-12 text-center text-hospital-text-3 text-xs">لا توجد حالات حالياً</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Left Column: Reports / Summary (1/3) -->
        <div class="space-y-4">
            <!-- Monthly Progress -->
            <div class="card bg-white border border-hospital-border rounded-[var(--rl)] overflow-hidden [box-shadow:var(--sh)]">
                <div class="card-hd px-4 py-3 border-b border-hospital-border bg-hospital-surface-2">
                    <h3 class="card-title text-[13px] font-bold text-hospital-text">ملخص الإيرادات</h3>
                </div>
                <div class="card-bd p-4 space-y-4">
                    <div v-for="row in revenueByDept" :key="row.dept" class="space-y-1.5">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-bold text-hospital-text">{{ deptLabels[row.dept] ?? row.dept }}</span>
                            <span class="font-mono text-hospital-primary">{{ fmt(row.revenue) }}</span>
                        </div>
                        <div class="pw w-full bg-hospital-border h-[6px] rounded-full overflow-hidden">
                            <div 
                                class="pb pb-b h-full bg-hospital-primary rounded-full transition-all duration-500" 
                                :style="{ width: `${Math.min(100, (row.revenue / (Math.max(...revenueByDept.map(r => r.revenue)) || 1)) * 100)}%` }"
                            ></div>
                        </div>
                    </div>
                    <div v-if="revenueByDept.length === 0" class="text-center py-4 text-hospital-text-3 text-xs italic">
                        لا توجد بيانات متاحة حالياً
                    </div>
                </div>
            </div>

            <!-- Doctor Performance -->
            <div class="card bg-white border border-hospital-border rounded-[var(--rl)] overflow-hidden [box-shadow:var(--sh)]">
                <div class="card-hd px-4 py-3 border-b border-hospital-border bg-hospital-surface-2">
                    <h3 class="card-title text-[13px] font-bold text-hospital-text">أعلى الأطباء إيراداً</h3>
                </div>
                <div class="card-bd p-0">
                    <div class="item-list max-h-[300px] overflow-y-auto">
                        <div v-for="(row, idx) in revenueByDoc" :key="row.doctor_name" class="flex items-center justify-between px-4 py-2.5 border-b border-hospital-border last:border-0 hover:bg-hospital-surface-2 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-hospital-primary-pale text-[10px] font-bold text-hospital-primary">
                                    {{ idx + 1 }}
                                </span>
                                <span class="text-[12px] font-bold text-hospital-text">{{ row.doctor_name }}</span>
                            </div>
                            <span class="text-[11px] font-mono text-hospital-accent font-bold">{{ fmt(row.revenue) }}</span>
                        </div>
                        <div v-if="revenueByDoc.length === 0" class="text-center py-8 text-hospital-text-3 text-xs italic">
                            بانتظار تسجيل البيانات...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Activity, AlertTriangle, Banknote, Users, Plus } from 'lucide-vue-next';
import Badge from '@/components/shared/Badge.vue';
import StatCard from '@/components/shared/StatCard.vue';

interface TodayStats {
    today_bookings: number;
    today_revenue:  number;
    today_paid:     number;
    today_pending:  number;
}
interface DeptRow  { dept: string; cases: number; revenue: number }
interface DocRow   { doctor_name: string; cases: number; revenue: number }
interface Treasury { total_in: number; total_out: number; balance: number }
interface QueueEntry { id: string; file_no: string; patient_name: string; dept: string; status: string; pay_status: string; time?: string; doctor_name?: string }

defineProps<{
    todayStats:    TodayStats;
    revenueByDept: DeptRow[];
    revenueByDoc:  DocRow[];
    treasury:      Treasury;
    lowStockCount: number;
    todayQueue:    QueueEntry[];
    filters:       { from?: string; to?: string };
}>();

const deptLabels: Record<string, string> = {
    clinic: 'عيادة', labs: 'فحوصات', surgery: 'عمليات', lasik: 'ليزك', laser: 'ليزر',
};

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { maximumFractionDigits: 0 }); 
}

function getDeptClass(dept: string) {
    const map: Record<string, string> = {
        clinic: 'bg-[#E8F1FB] border-[#BDD5F2] text-[#0A4FA6]',
        labs: 'bg-[#E0F7F5] border-[#B2EBE5] text-[#007A6E]',
        surgery: 'bg-[#F3E8FD] border-[#DCC3F9] text-[#7B2FA6]',
        lasik: 'bg-[#FEF0E0] border-[#FCD8B0] text-[#7A3E00]',
        laser: 'bg-[#E2F5EC] border-[#B8E6CF] text-[#1A8C5B]',
    };
    return map[dept] || 'bg-gray-100 border-gray-200 text-gray-600';
}
</script>

