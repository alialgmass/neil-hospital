<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, EyeOff, Package, Search, TrendingDown, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    item_name?: string;
    unit?: string;
    type: 'in' | 'out';
    reference_no: string;
    party?: string;
    qty: number;
    unit_cost: number;
    total: number;
    movement_date: string;
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['item_name', 'reference_no', 'party'],
    (r) => `${r.reference_no}-${r.item_name}-${r.movement_date}`,
);

const totalIn = computed(() => visibleRows.value.filter((r) => r.type === 'in').reduce((s, r) => s + Number(r.total), 0));
const totalOut = computed(() => visibleRows.value.filter((r) => r.type === 'out').reduce((s, r) => s + Number(r.total), 0));
const inCount = computed(() => visibleRows.value.filter((r) => r.type === 'in').length);
const outCount = computed(() => visibleRows.value.filter((r) => r.type === 'out').length);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/inventory-movement', { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head title="حركة المخزون" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">حركة المخزون</h1>
        <p class="mt-0.5 text-sm text-t3">الوارد والصادر من المواد والمستلزمات</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <ArrowDownCircle class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">وارد (ج.م)</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalIn) }}</p>
                <p class="text-xs text-t3">{{ inCount }} حركة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <ArrowUpCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">صادر (ج.م)</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalOut) }}</p>
                <p class="text-xs text-t3">{{ outCount }} حركة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Package class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحركات</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <TrendingDown class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">صافي الحركة</p>
                <p class="text-xl font-bold" :class="totalIn - totalOut >= 0 ? 'text-s' : 'text-d'">{{ fmt(totalIn - totalOut) }}</p>
                <p class="text-xs text-t3">ج.م</p>
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
            <input v-model="rowSearch" type="text" placeholder="ابحث بالصنف أو المرجع أو الجهة..." class="input-field h-9 w-64 pr-9" />
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
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المرجع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">النوع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الجهة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القيمة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="w-8 px-2 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="`${row.reference_no}-${row.item_name}-${row.movement_date}`" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ row.item_name || '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-t2">{{ row.reference_no }}</td>
                    <td class="px-4 py-3">
                        <Badge :variant="row.type === 'in' ? 'active' : 'cancelled'">
                            {{ row.type === 'in' ? 'وارد' : 'صادر' }}
                        </Badge>
                    </td>
                    <td class="px-4 py-3 text-t3">{{ row.party || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.qty }} {{ row.unit || '' }}</td>
                    <td class="px-4 py-3 font-mono" :class="row.type === 'in' ? 'text-s' : 'text-d'">{{ Number(row.total).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 text-t3">{{ row.movement_date }}</td>
                    <td class="px-2 py-3">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="8">
                        {{ data.rows.length === 0 ? 'لا توجد حركات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
