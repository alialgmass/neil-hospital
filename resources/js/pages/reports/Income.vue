<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { TrendingUp, Users, Clock, DollarSign } from 'lucide-vue-next';
import { ref } from 'vue';

interface RevenueRow {
    dept?: string;
    doctor_name?: string;
    cases: number;
    revenue: number;
}

interface Stats {
    totalRevenue: number;
    paidCount: number;
    pendingAmount: number;
    todayRevenue: number;
}

const props = defineProps<{
    from: string;
    to: string;
    stats: Stats;
    revenueByDept: RevenueRow[];
    revenueByDoc: RevenueRow[];
}>();

const fromFilter = ref(props.from);
const toFilter   = ref(props.to);
const deptFilter = ref('');

function applyFilters() {
    router.get('/reports/income', { from: fromFilter.value, to: toFilter.value }, { preserveState: true });
}

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const totalRevenue = props.revenueByDept.reduce((s, r) => s + Number(r.revenue), 0);
const totalCases   = props.revenueByDept.reduce((s, r) => s + Number(r.cases), 0);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG');
}

function pct(revenue: number) {
    if (totalRevenue === 0) {
        return '0';
    }

    return ((revenue / totalRevenue) * 100).toFixed(1);
}

const filteredDept = () => {
    if (!deptFilter.value) {
        return props.revenueByDept;
    }

    return props.revenueByDept.filter((r) => r.dept === deptFilter.value);
};
</script>

<template>
    <Head title="تقرير الإيرادات" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-white">
                <TrendingUp class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-blue-600">إجمالي الإيرادات</p>
                <p class="text-xl font-bold text-blue-700">{{ fmt(stats.totalRevenue) }}</p>
                <p class="text-xs text-blue-500">ج.م للفترة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-600 text-white">
                <Users class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-green-600">فواتير مسددة</p>
                <p class="text-xl font-bold text-green-700">{{ stats.paidCount }}</p>
                <p class="text-xs text-green-500">هذه الفترة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-orange-100 bg-orange-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-600 text-white">
                <Clock class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-orange-600">فواتير معلقة (ج)</p>
                <p class="text-xl font-bold text-orange-700">{{ fmt(stats.pendingAmount) }}</p>
                <p class="text-xs text-orange-500">تحتاج متابعة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-teal-100 bg-teal-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-600 text-white">
                <DollarSign class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-teal-600">اليوم (ج)</p>
                <p class="text-xl font-bold text-teal-700">{{ fmt(stats.todayRevenue) }}</p>
                <p class="text-xs text-teal-500">↑ إيراد اليوم</p>
            </div>
        </div>
    </div>

    <!-- Card: Revenue Registry -->
    <div class="mb-6 overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-hospital-border px-5 py-4">
            <div>
                <h3 class="font-semibold text-hospital-text">سجل الإيرادات</h3>
                <p class="text-xs text-hospital-muted">مرتبطة بالحجوزات والأقسام</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select
                    v-model="deptFilter"
                    class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-xs focus:border-hospital-primary focus:outline-none"
                >
                    <option value="">كل الأقسام</option>
                    <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <span class="text-sm text-hospital-muted">من</span>
                <input
                    v-model="fromFilter"
                    type="date"
                    class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
                <span class="text-sm text-hospital-muted">إلى</span>
                <input
                    v-model="toFilter"
                    type="date"
                    class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
                <button
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
                    @click="applyFilters"
                >
                    عرض
                </button>
            </div>
        </div>
        <div class="p-5">
            <div v-if="filteredDept().length === 0" class="py-10 text-center text-sm text-hospital-muted">
                لا توجد إيرادات في هذه الفترة
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-hospital-border text-right text-xs text-hospital-muted">
                        <th class="pb-2 font-semibold">القسم</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border/50">
                    <tr v-for="row in filteredDept()" :key="row.dept" class="hover:bg-hospital-bg/50">
                        <td class="py-2.5 font-medium">{{ deptLabels[row.dept!] ?? row.dept }}</td>
                        <td class="py-2.5 text-center text-hospital-muted">{{ row.cases }}</td>
                        <td class="py-2.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-hospital-border">
                                    <div class="h-full rounded-full bg-hospital-primary" :style="{ width: pct(row.revenue) + '%' }" />
                                </div>
                                <span class="text-xs text-hospital-muted">{{ pct(row.revenue) }}%</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-left font-mono font-semibold text-hospital-success">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-hospital-border font-bold">
                        <td class="pt-2">الإجمالي</td>
                        <td class="pt-2 text-center">{{ totalCases }}</td>
                        <td class="pt-2 text-center text-hospital-muted">100%</td>
                        <td class="pt-2 text-left font-mono text-hospital-primary">{{ fmt(totalRevenue) }} ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Revenue by Doctor -->
    <div class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
        <div class="border-b border-hospital-border px-5 py-4">
            <h3 class="font-semibold text-hospital-text">الإيرادات حسب الطبيب</h3>
        </div>
        <div class="p-5">
            <div v-if="revenueByDoc.length === 0" class="py-8 text-center text-sm text-hospital-muted">
                لا توجد إيرادات في هذه الفترة
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-hospital-border text-right text-xs text-hospital-muted">
                        <th class="pb-2 font-semibold">الطبيب</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border/50">
                    <tr v-for="row in revenueByDoc" :key="row.doctor_name" class="hover:bg-hospital-bg/50">
                        <td class="py-2.5 font-medium">{{ row.doctor_name }}</td>
                        <td class="py-2.5 text-center text-hospital-muted">{{ row.cases }}</td>
                        <td class="py-2.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-hospital-border">
                                    <div class="h-full rounded-full bg-hospital-accent" :style="{ width: pct(row.revenue) + '%' }" />
                                </div>
                                <span class="text-xs text-hospital-muted">{{ pct(row.revenue) }}%</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-left font-mono font-semibold text-hospital-success">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
