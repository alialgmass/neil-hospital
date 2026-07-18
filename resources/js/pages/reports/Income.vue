<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Clock, TrendingUp, Users } from 'lucide-vue-next';
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
const toFilter = ref(props.to);
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
const totalCases = props.revenueByDept.reduce((s, r) => s + Number(r.cases), 0);

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

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الإيرادات</h1>
        <p class="mt-0.5 text-sm text-t3">إيرادات الفترة مقسمةً بالقسم والطبيب</p>
    </div>

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <TrendingUp class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الإيرادات</p>
                <p class="text-xl font-bold text-t">{{ fmt(stats.totalRevenue) }}</p>
                <p class="text-xs text-t3">ج.م للفترة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Users class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">فواتير مسددة</p>
                <p class="text-xl font-bold text-t">{{ stats.paidCount }}</p>
                <p class="text-xs text-t3">هذه الفترة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <Clock class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">فواتير معلقة</p>
                <p class="text-xl font-bold text-t">{{ fmt(stats.pendingAmount) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <BarChart3 class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">إيراد اليوم</p>
                <p class="text-xl font-bold text-t">{{ fmt(stats.todayRevenue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
    </div>

    <!-- Card: Revenue by Dept -->
    <div class="mb-6 overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-br px-5 py-4">
            <div>
                <h3 class="font-semibold text-t">سجل الإيرادات</h3>
                <p class="text-xs text-t3">مرتبطة بالحجوزات والأقسام</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <select v-model="deptFilter" class="input-field w-auto">
                    <option value="">كل الأقسام</option>
                    <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <input v-model="fromFilter" type="date" class="input-field w-auto" />
                <input v-model="toFilter" type="date" class="input-field w-auto" />
                <button class="btn-primary" @click="applyFilters">عرض</button>
            </div>
        </div>
        <div class="p-5">
            <div v-if="filteredDept().length === 0" class="py-10 text-center text-sm text-t3">
                لا توجد إيرادات في هذه الفترة
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br text-right text-xs text-t3">
                        <th class="pb-2 font-semibold">القسم</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in filteredDept()" :key="row.dept" class="hover:bg-sf2">
                        <td class="py-2.5 font-medium text-t">{{ deptLabels[row.dept!] ?? row.dept }}</td>
                        <td class="py-2.5 text-center text-t3">{{ row.cases }}</td>
                        <td class="py-2.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-br">
                                    <div class="h-full rounded-full bg-p" :style="{ width: pct(row.revenue) + '%' }" />
                                </div>
                                <span class="text-xs text-t3">{{ pct(row.revenue) }}%</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-left font-mono font-semibold text-s">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-br font-bold">
                        <td class="pt-2 text-t">الإجمالي</td>
                        <td class="pt-2 text-center text-t2">{{ totalCases }}</td>
                        <td class="pt-2 text-center text-t3">100%</td>
                        <td class="pt-2 text-left font-mono text-p">{{ fmt(totalRevenue) }} ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Revenue by Doctor -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="border-b border-br px-5 py-4">
            <h3 class="font-semibold text-t">الإيرادات حسب الطبيب</h3>
        </div>
        <div class="p-5">
            <div v-if="revenueByDoc.length === 0" class="py-8 text-center text-sm text-t3">
                لا توجد إيرادات في هذه الفترة
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br text-right text-xs text-t3">
                        <th class="pb-2 font-semibold">الطبيب</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in revenueByDoc" :key="row.doctor_name" class="hover:bg-sf2">
                        <td class="py-2.5 font-medium text-t">{{ row.doctor_name }}</td>
                        <td class="py-2.5 text-center text-t3">{{ row.cases }}</td>
                        <td class="py-2.5 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-br">
                                    <div class="h-full rounded-full bg-a" :style="{ width: pct(row.revenue) + '%' }" />
                                </div>
                                <span class="text-xs text-t3">{{ pct(row.revenue) }}%</span>
                            </div>
                        </td>
                        <td class="py-2.5 text-left font-mono font-semibold text-s">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
