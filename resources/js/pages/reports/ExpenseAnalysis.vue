<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, EyeOff, FileText, Search, TrendingDown, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    code: string;
    name: string;
    entries: number;
    total: number;
}

const props = defineProps<{
    data: { rows: Row[]; total: number; from: string; to: string };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['name', 'code'],
    (r) => r.code,
);

const visibleTotal = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.total), 0));
const visibleEntries = computed(() => visibleRows.value.reduce((s, r) => s + r.entries, 0));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function pct(amount: number): string {
    return visibleTotal.value > 0 ? ((amount / visibleTotal.value) * 100).toFixed(1) + '%' : '0%';
}

function search() {
    router.get('/reports/expense-analysis', { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head title="تحليل المصروفات" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تحليل المصروفات</h1>
        <p class="mt-0.5 text-sm text-t3">تفصيل المصروفات حسب البند والفئة</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <TrendingDown class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المصروفات</p>
                <p class="text-xl font-bold text-t">{{ fmt(visibleTotal) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <FileText class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد البنود</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <BarChart3 class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد القيود</p>
                <p class="text-xl font-bold text-t">{{ visibleEntries }}</p>
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
            <input v-model="rowSearch" type="text" placeholder="ابحث بالبند أو الكود..." class="input-field h-9 w-64 pr-9" />
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
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكود</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">البند</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">عدد القيود</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الإجمالي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">النسبة</th>
                    <th class="w-8 px-2 py-3 print:hidden" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="row.code" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-mono text-xs text-t3">{{ row.code }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ row.name }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.entries }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-d">{{ Number(row.total).toFixed(2) }} ج</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-24 overflow-hidden rounded-full bg-br">
                                <div class="h-full rounded-full bg-d" :style="{ width: pct(Number(row.total)) }" />
                            </div>
                            <span class="text-xs text-t3">{{ pct(Number(row.total)) }}</span>
                        </div>
                    </td>
                    <td class="px-2 py-3 print:hidden">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="6">
                        {{ data.rows.length === 0 ? 'لا توجد مصروفات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-3 font-bold text-t" colspan="3">الإجمالي</td>
                    <td class="px-4 py-3 font-mono font-bold text-d">{{ Number(visibleTotal).toFixed(2) }} ج</td>
                    <td colspan="2" />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
