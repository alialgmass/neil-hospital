<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, EyeOff, Package, Search, TrendingDown, TrendingUp, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    item_name: string;
    supplier_name?: string;
    avg_cost: number;
    min_cost: number;
    max_cost: number;
    total_qty: number;
    total_value: number;
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['item_name', 'supplier_name'],
    (r) => `${r.item_name}-${r.supplier_name ?? ''}`,
);

const totalValue = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.total_value), 0));
const avgPrice = computed(() =>
    visibleRows.value.length > 0 ? visibleRows.value.reduce((s, r) => s + Number(r.avg_cost), 0) / visibleRows.value.length : 0,
);
const totalQty = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.total_qty), 0));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/purchase-prices', { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head title="أسعار الشراء" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">أسعار الشراء</h1>
        <p class="mt-0.5 text-sm text-t3">متوسطات ونطاق أسعار المشتريات</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Package class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد الأصناف</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <TrendingDown class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي قيمة المشتريات</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalValue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <BarChart3 class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">متوسط سعر الوحدة</p>
                <p class="text-xl font-bold text-t">{{ fmt(avgPrice) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <TrendingUp class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الكميات</p>
                <p class="text-xl font-bold text-t">{{ totalQty }}</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">من</label>
            <input v-model="from" class="input-field" type="date" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى</label>
            <input v-model="to" class="input-field" type="date" />
        </div>
        <button class="btn-primary self-end" @click="search">بحث</button>
    </div>

    <!-- Row search + exclusion status -->
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <div class="relative">
            <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
            <input v-model="rowSearch" type="text" placeholder="ابحث بالصنف أو المورد..." class="input-field h-9 w-64 pr-9" />
        </div>
        <button v-if="excludedCount > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-3 py-1.5 text-xs text-t2 hover:bg-sf2" @click="restoreAll">
            <EyeOff class="h-3.5 w-3.5" />
            {{ excludedCount }} صف مستبعد من العرض — إظهار الكل
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصنف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المورد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">متوسط السعر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">أقل سعر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">أعلى سعر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجمالي</th>
                    <th class="w-8 px-2 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="`${row.item_name}-${row.supplier_name ?? ''}`" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ row.item_name }}</td>
                    <td class="px-4 py-3 text-t3">{{ row.supplier_name || '—' }}</td>
                    <td class="px-4 py-3 font-mono text-t2">{{ Number(row.avg_cost).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-s">{{ Number(row.min_cost).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-d">{{ Number(row.max_cost).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 text-t2">{{ row.total_qty }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-t">{{ Number(row.total_value).toFixed(2) }} ج</td>
                    <td class="px-2 py-3">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="8">
                        {{ data.rows.length === 0 ? 'لا توجد بيانات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
