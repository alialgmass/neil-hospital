<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, EyeOff, Package, Printer, Search, ShoppingBag, TrendingDown, X } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

type Source = 'purchase' | 'stock_in' | 'issue' | 'bundle' | 'sale' | 'stock_take';
type Period = 'day' | 'month' | 'range';

interface Row {
    item_id?: string | null;
    item_name?: string;
    unit?: string;
    type: 'in' | 'out';
    source: Source;
    reference_no: string;
    party?: string;
    qty: number;
    unit_cost: number;
    total: number;
    movement_date: string;
}

interface SourceSummary {
    source: Source;
    direction: 'in' | 'out';
    count: number;
    qty: number;
    total: number;
}

interface ItemSummary {
    item_id: string | null;
    item_name: string | null;
    unit: string | null;
    in_qty: number;
    out_qty: number;
    in_value: number;
    out_value: number;
}

const props = defineProps<{
    data: { rows: Row[]; bySource: SourceSummary[]; byItem: ItemSummary[]; salesValue: number; from: string; to: string };
    filters: { period: Period; date: string; month: string; from: string; to: string; item_id: string | null; source: Source | null };
    items: { id: string; name: string }[];
}>();

const sourceLabels: Record<Source, string> = {
    purchase: 'فواتير المشتريات',
    stock_in: 'أذون الإضافة',
    issue: 'أذون الصرف',
    bundle: 'بنود العمليات',
    sale: 'فواتير المبيعات',
    stock_take: 'تسوية الجرد',
};

const periodLabels: Record<Period, string> = { day: 'اليوم', month: 'الشهر', range: 'مدة محددة' };

const form = reactive({
    period: props.filters.period,
    date: props.filters.date,
    month: props.filters.month,
    from: props.filters.from,
    to: props.filters.to,
    item_id: props.filters.item_id ?? '',
    source: props.filters.source ?? '',
});

const view = ref<'details' | 'items'>('details');

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['item_name', 'reference_no', 'party'],
    (r) => `${r.source}-${r.reference_no}-${r.item_name}-${r.movement_date}`,
);

const totalIn = computed(() => visibleRows.value.filter((r) => r.type === 'in').reduce((s, r) => s + Number(r.total), 0));
const totalOut = computed(() => visibleRows.value.filter((r) => r.type === 'out').reduce((s, r) => s + Number(r.total), 0));
const inCount = computed(() => visibleRows.value.filter((r) => r.type === 'in').length);
const outCount = computed(() => visibleRows.value.filter((r) => r.type === 'out').length);

const periodTitle = computed(() =>
    props.data.from === props.data.to ? props.data.from : `${props.data.from} — ${props.data.to}`,
);

function fmt(n: number | string) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function apply() {
    const query: Record<string, string> = { period: form.period };

    if (form.period === 'day') {
        query.date = form.date;
    } else if (form.period === 'month') {
        query.month = form.month;
    } else {
        query.from = form.from;
        query.to = form.to;
    }

    if (form.item_id) {
        query.item_id = form.item_id;
    }

    if (form.source) {
        query.source = form.source;
    }

    router.get('/reports/inventory-movement', query, { preserveState: true, preserveScroll: true });
}

function setPeriod(period: Period) {
    form.period = period;
    apply();
}

function print() {
    window.print();
}
</script>

<template>
    <Head title="حركة المخزون" />

    <!-- Page Header -->
    <div class="mb-6 flex items-start justify-between">
        <div>
            <h1 class="text-xl font-bold text-t">حركة المخزون</h1>
            <p class="mt-0.5 text-sm text-t3">
                المشتريات وأذون الإضافة والصرف وبنود العمليات وفواتير المبيعات وتسويات الجرد — {{ periodTitle }}
            </p>
        </div>
        <button type="button" class="btn-secondary flex items-center gap-1.5 print:hidden" @click="print">
            <Printer class="h-4 w-4" />
            طباعة
        </button>
    </div>

    <!-- Filters -->
    <div class="mb-5 space-y-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)] print:hidden">
        <div class="flex gap-2">
            <button
                v-for="(label, key) in periodLabels"
                :key="key"
                type="button"
                class="rounded-lg px-4 py-1.5 text-sm font-medium transition-colors"
                :class="form.period === key ? 'bg-p text-white' : 'border border-br text-t2 hover:bg-sf2'"
                @click="setPeriod(key)"
            >
                {{ label }}
            </button>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div v-if="form.period === 'day'" class="flex flex-col gap-1">
                <label class="form-label">اليوم</label>
                <input v-model="form.date" class="input-field" type="date" />
            </div>
            <div v-else-if="form.period === 'month'" class="flex flex-col gap-1">
                <label class="form-label">الشهر</label>
                <input v-model="form.month" class="input-field" type="month" />
            </div>
            <template v-else>
                <div class="flex flex-col gap-1">
                    <label class="form-label">من</label>
                    <input v-model="form.from" class="input-field" type="date" />
                </div>
                <div class="flex flex-col gap-1">
                    <label class="form-label">إلى</label>
                    <input v-model="form.to" class="input-field" type="date" />
                </div>
            </template>
            <div class="flex flex-col gap-1">
                <label class="form-label">الصنف</label>
                <select v-model="form.item_id" class="input-field min-w-48">
                    <option value="">كل الأصناف</option>
                    <option v-for="item in items" :key="item.id" :value="item.id">{{ item.name }}</option>
                </select>
            </div>
            <div class="flex flex-col gap-1">
                <label class="form-label">نوع الحركة</label>
                <select v-model="form.source" class="input-field">
                    <option value="">الكل</option>
                    <option v-for="(label, key) in sourceLabels" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <button class="btn-primary self-end" @click="apply">عرض</button>
        </div>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <ArrowDownCircle class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">وارد بالتكلفة (ج.م)</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalIn) }}</p>
                <p class="text-xs text-t3">{{ inCount }} حركة</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <ArrowUpCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">صادر بالتكلفة (ج.م)</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalOut) }}</p>
                <p class="text-xs text-t3">{{ outCount }} حركة</p>
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
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <ShoppingBag class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">قيمة المبيعات (سعر البيع)</p>
                <p class="text-xl font-bold text-t">{{ fmt(data.salesValue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Package class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحركات</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
                <p class="text-xs text-t3">{{ data.byItem.length }} صنف</p>
            </div>
        </div>
    </div>

    <!-- By source -->
    <div class="mb-5 overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="border-b border-br bg-sf2 px-4 py-2.5 text-sm font-semibold text-t">ملخص حسب نوع الحركة</div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-t2">
                    <th class="px-4 py-2 text-right font-semibold">النوع</th>
                    <th class="px-4 py-2 text-right font-semibold">الاتجاه</th>
                    <th class="px-4 py-2 text-right font-semibold">عدد المستندات</th>
                    <th class="px-4 py-2 text-right font-semibold">إجمالي الكمية</th>
                    <th class="px-4 py-2 text-right font-semibold">القيمة بالتكلفة (ج.م)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="s in data.bySource" :key="`${s.source}-${s.direction}`">
                    <td class="px-4 py-2 font-medium text-t">{{ sourceLabels[s.source] ?? s.source }}</td>
                    <td class="px-4 py-2">
                        <Badge :variant="s.direction === 'in' ? 'active' : 'cancelled'">{{ s.direction === 'in' ? 'وارد' : 'صادر' }}</Badge>
                    </td>
                    <td class="px-4 py-2 text-t2">{{ s.count }}</td>
                    <td class="px-4 py-2 text-t2">{{ s.qty }}</td>
                    <td class="px-4 py-2 font-mono" :class="s.direction === 'in' ? 'text-s' : 'text-d'">{{ fmt(s.total) }}</td>
                </tr>
                <tr v-if="data.bySource.length === 0">
                    <td class="px-4 py-6 text-center text-t3" colspan="5">لا توجد حركات في هذه الفترة</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- View switch + row search -->
    <div class="mb-3 flex flex-wrap items-center gap-3 print:hidden">
        <div class="flex gap-1 rounded-lg border border-br p-0.5">
            <button
                type="button"
                class="rounded-md px-3 py-1 text-xs font-medium"
                :class="view === 'details' ? 'bg-p text-white' : 'text-t2'"
                @click="view = 'details'"
            >
                الحركات التفصيلية
            </button>
            <button
                type="button"
                class="rounded-md px-3 py-1 text-xs font-medium"
                :class="view === 'items' ? 'bg-p text-white' : 'text-t2'"
                @click="view = 'items'"
            >
                ملخص الأصناف
            </button>
        </div>
        <template v-if="view === 'details'">
            <div class="relative">
                <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                <input v-model="rowSearch" type="text" placeholder="ابحث بالصنف أو المرجع أو الجهة..." class="input-field h-9 w-64 pr-9" />
            </div>
            <button v-if="excludedCount > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-3 py-1.5 text-xs text-t2 hover:bg-sf2" @click="restoreAll">
                <EyeOff class="h-3.5 w-3.5" />
                {{ excludedCount }} صف مستبعد من العرض — إظهار الكل
            </button>
        </template>
    </div>

    <!-- Item summary -->
    <div v-if="view === 'items'" class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصنف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">كمية الوارد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">كمية الصادر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">صافي الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">قيمة الوارد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">قيمة الصادر</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="item in data.byItem" :key="item.item_id ?? item.item_name ?? ''" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ item.item_name || '—' }}</td>
                    <td class="px-4 py-3 text-s">{{ item.in_qty }} {{ item.unit || '' }}</td>
                    <td class="px-4 py-3 text-d">{{ item.out_qty }} {{ item.unit || '' }}</td>
                    <td class="px-4 py-3 font-semibold" :class="item.in_qty - item.out_qty >= 0 ? 'text-s' : 'text-d'">
                        {{ Math.round((item.in_qty - item.out_qty) * 100) / 100 }}
                    </td>
                    <td class="px-4 py-3 font-mono text-t2">{{ fmt(item.in_value) }}</td>
                    <td class="px-4 py-3 font-mono text-t2">{{ fmt(item.out_value) }}</td>
                </tr>
                <tr v-if="data.byItem.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="6">لا توجد حركات في هذه الفترة</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Detailed movements -->
    <div v-else class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">نوع المستند</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المرجع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصنف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الاتجاه</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الجهة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تكلفة الوحدة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القيمة</th>
                    <th class="w-8 px-2 py-3 print:hidden" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="`${row.source}-${row.reference_no}-${row.item_name}-${row.movement_date}`" class="hover:bg-sf2">
                    <td class="whitespace-nowrap px-4 py-3 text-t3">{{ String(row.movement_date).slice(0, 16) }}</td>
                    <td class="px-4 py-3 text-t2">{{ sourceLabels[row.source] ?? row.source }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-t2">{{ row.reference_no }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ row.item_name || '—' }}</td>
                    <td class="px-4 py-3">
                        <Badge :variant="row.type === 'in' ? 'active' : 'cancelled'">
                            {{ row.type === 'in' ? 'وارد' : 'صادر' }}
                        </Badge>
                    </td>
                    <td class="px-4 py-3 text-t3">{{ row.party || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.qty }} {{ row.unit || '' }}</td>
                    <td class="px-4 py-3 font-mono text-t3">{{ fmt(row.unit_cost) }}</td>
                    <td class="px-4 py-3 font-mono" :class="row.type === 'in' ? 'text-s' : 'text-d'">{{ fmt(row.total) }} ج</td>
                    <td class="px-2 py-3 print:hidden">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="10">
                        {{ data.rows.length === 0 ? 'لا توجد حركات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
