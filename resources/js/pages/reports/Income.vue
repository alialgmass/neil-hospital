<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { BarChart3, Clock, EyeOff, Search, TrendingUp, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

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
    pentacam: 'البنتكام',
};

const page = usePage<{ moduleStatus?: Record<string, boolean> }>();
const availableDeptLabels = computed(() => {
    const moduleStatus = (page.props.moduleStatus as Record<string, boolean>) ?? {};

    return Object.fromEntries(Object.entries(deptLabels).filter(([key]) => moduleStatus[key] !== false));
});

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG');
}

const deptFiltered = computed(() => {
    if (!deptFilter.value) {
        return props.revenueByDept;
    }

    return props.revenueByDept.filter((r) => r.dept === deptFilter.value);
});

const deptRows = useReportRowFilter(() => deptFiltered.value, ['dept'], (r) => r.dept ?? '');
const docRows = useReportRowFilter(() => props.revenueByDoc, ['doctor_name'], (r) => r.doctor_name ?? '');

const totalRevenue = computed(() => deptRows.visibleRows.value.reduce((s, r) => s + Number(r.revenue), 0));
const totalCases = computed(() => deptRows.visibleRows.value.reduce((s, r) => s + Number(r.cases), 0));

function pct(revenue: number) {
    if (totalRevenue.value === 0) {
        return '0';
    }

    return ((revenue / totalRevenue.value) * 100).toFixed(1);
}
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
                    <option v-for="(label, key) in availableDeptLabels" :key="key" :value="key">{{ label }}</option>
                </select>
                <input v-model="fromFilter" type="date" class="input-field w-auto" />
                <input v-model="toFilter" type="date" class="input-field w-auto" />
                <button class="btn-primary" @click="applyFilters">عرض</button>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 border-b border-br px-5 py-2.5 print:hidden">
            <div class="relative">
                <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                <input v-model="deptRows.search.value" type="text" placeholder="ابحث بالقسم..." class="input-field h-8 w-56 pr-9 text-xs" />
            </div>
            <button v-if="deptRows.excludedCount.value > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-2.5 py-1 text-xs text-t2 hover:bg-sf2" @click="deptRows.restoreAll()">
                <EyeOff class="h-3.5 w-3.5" />
                {{ deptRows.excludedCount.value }} مستبعد — إظهار الكل
            </button>
        </div>
        <div class="p-5">
            <div v-if="deptRows.visibleRows.value.length === 0" class="py-10 text-center text-sm text-t3">
                {{ revenueByDept.length === 0 ? 'لا توجد إيرادات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br text-right text-xs text-t3">
                        <th class="pb-2 font-semibold">القسم</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                        <th class="w-6 pb-2 print:hidden" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in deptRows.visibleRows.value" :key="row.dept" class="hover:bg-sf2">
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
                        <td class="w-6 py-2.5 print:hidden">
                            <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="deptRows.exclude(row)">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-br font-bold">
                        <td class="pt-2 text-t">الإجمالي</td>
                        <td class="pt-2 text-center text-t2">{{ totalCases }}</td>
                        <td class="pt-2 text-center text-t3">100%</td>
                        <td class="pt-2 text-left font-mono text-p">{{ fmt(totalRevenue) }} ج.م</td>
                        <td class="print:hidden" />
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
        <div class="flex flex-wrap items-center gap-2 border-b border-br px-5 py-2.5 print:hidden">
            <div class="relative">
                <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                <input v-model="docRows.search.value" type="text" placeholder="ابحث باسم الطبيب..." class="input-field h-8 w-56 pr-9 text-xs" />
            </div>
            <button v-if="docRows.excludedCount.value > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-2.5 py-1 text-xs text-t2 hover:bg-sf2" @click="docRows.restoreAll()">
                <EyeOff class="h-3.5 w-3.5" />
                {{ docRows.excludedCount.value }} مستبعد — إظهار الكل
            </button>
        </div>
        <div class="p-5">
            <div v-if="docRows.visibleRows.value.length === 0" class="py-8 text-center text-sm text-t3">
                {{ revenueByDoc.length === 0 ? 'لا توجد إيرادات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br text-right text-xs text-t3">
                        <th class="pb-2 font-semibold">الطبيب</th>
                        <th class="pb-2 text-center font-semibold">الحالات</th>
                        <th class="pb-2 text-center font-semibold">النسبة</th>
                        <th class="pb-2 text-left font-semibold">الإيراد</th>
                        <th class="w-6 pb-2 print:hidden" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in docRows.visibleRows.value" :key="row.doctor_name" class="hover:bg-sf2">
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
                        <td class="w-6 py-2.5 print:hidden">
                            <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="docRows.exclude(row)">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
