<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { EyeOff, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface AccountRow {
    name: string;
    amount: number;
}

const props = defineProps<{
    data: {
        revenues: AccountRow[];
        expenses: AccountRow[];
        totalRevenue: number;
        totalExpense: number;
        netIncome: number;
        from: string;
        to: string;
    };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function search() {
    router.get('/reports/profit-loss', { from: from.value, to: to.value }, { preserveState: true });
}

function exportExcel() {
    window.location.href = `/reports/profit-loss/export?from=${from.value}&to=${to.value}`;
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

const revFilter = useReportRowFilter(() => props.data.revenues, ['name'], (r) => r.name);
const expFilter = useReportRowFilter(() => props.data.expenses, ['name'], (r) => r.name);

const visibleTotalRevenue = computed(() => revFilter.visibleRows.value.reduce((s, r) => s + Number(r.amount), 0));
const visibleTotalExpense = computed(() => expFilter.visibleRows.value.reduce((s, r) => s + Number(r.amount), 0));
const visibleNetIncome = computed(() => visibleTotalRevenue.value - visibleTotalExpense.value);
</script>

<template>
    <Head title="الأرباح والخسائر" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">قائمة الدخل والخسارة</h1>
        <p class="mt-0.5 text-sm text-t3">الربح والخسارة والنتيجة المالية للفترة</p>
    </div>

    <!-- Filter row -->
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">من</label>
            <input v-model="from" type="date" class="input-field" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى</label>
            <input v-model="to" type="date" class="input-field" />
        </div>
        <button class="btn-primary self-end" @click="search">حساب</button>
        <button class="btn-secondary self-end" @click="exportExcel">Excel</button>
        <button class="btn-secondary self-end" @click="() => window.print()">طباعة</button>
    </div>

    <!-- Stats row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <div class="text-lg font-bold text-s">↑</div>
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الإيرادات</p>
                <p class="text-xl font-bold text-s">{{ fmt(visibleTotalRevenue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <div class="text-lg font-bold text-d">↓</div>
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المصروفات</p>
                <p class="text-xl font-bold text-d">{{ fmt(visibleTotalExpense) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                :class="visibleNetIncome >= 0 ? 'bg-pp' : 'bg-dp'"
            >
                <div class="text-lg font-bold" :class="visibleNetIncome >= 0 ? 'text-p' : 'text-d'">
                    {{ visibleNetIncome >= 0 ? '=' : '!' }}
                </div>
            </div>
            <div>
                <p class="text-xs text-t3">صافي الدخل</p>
                <p class="text-xl font-bold" :class="visibleNetIncome >= 0 ? 'text-p' : 'text-d'">{{ fmt(visibleNetIncome) }}</p>
                <p class="text-xs" :class="visibleNetIncome >= 0 ? 'text-s' : 'text-d'">{{ visibleNetIncome >= 0 ? 'ربح' : 'خسارة' }}</p>
            </div>
        </div>
    </div>

    <!-- Revenues & Expenses 2-col -->
    <div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
        <!-- Revenues -->
        <div class="overflow-hidden rounded-[var(--rl)] border border-br shadow-[var(--sh)]">
            <div class="border-b border-br bg-sp/50 px-4 py-3">
                <p class="font-semibold text-s">الإيرادات</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-b border-br px-4 py-2.5 print:hidden">
                <div class="relative">
                    <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                    <input v-model="revFilter.search.value" type="text" placeholder="ابحث في بنود الإيرادات..." class="input-field h-8 w-56 pr-9 text-xs" />
                </div>
                <button v-if="revFilter.excludedCount.value > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-2.5 py-1 text-xs text-t2 hover:bg-sf2" @click="revFilter.restoreAll()">
                    <EyeOff class="h-3.5 w-3.5" />
                    {{ revFilter.excludedCount.value }} مستبعد — إظهار الكل
                </button>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in revFilter.visibleRows.value" :key="row.name" class="hover:bg-sf2">
                        <td class="px-4 py-2.5 text-t">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-s">{{ fmt(row.amount) }} ج.م</td>
                        <td class="w-6 px-2 py-2.5 print:hidden">
                            <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="revFilter.exclude(row)">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="revFilter.visibleRows.value.length === 0">
                        <td colspan="3" class="px-4 py-4 text-center text-t3">
                            {{ data.revenues.length === 0 ? 'لا توجد إيرادات' : 'لا توجد نتائج مطابقة' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-s/30 bg-sp/30 font-bold">
                        <td class="px-4 py-3 text-s">إجمالي الإيرادات</td>
                        <td class="px-4 py-3 text-left font-mono text-s">{{ fmt(visibleTotalRevenue) }} ج.م</td>
                        <td class="print:hidden" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Expenses -->
        <div class="overflow-hidden rounded-[var(--rl)] border border-br shadow-[var(--sh)]">
            <div class="border-b border-br bg-dp/50 px-4 py-3">
                <p class="font-semibold text-d">المصروفات</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 border-b border-br px-4 py-2.5 print:hidden">
                <div class="relative">
                    <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                    <input v-model="expFilter.search.value" type="text" placeholder="ابحث في بنود المصروفات..." class="input-field h-8 w-56 pr-9 text-xs" />
                </div>
                <button v-if="expFilter.excludedCount.value > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-2.5 py-1 text-xs text-t2 hover:bg-sf2" @click="expFilter.restoreAll()">
                    <EyeOff class="h-3.5 w-3.5" />
                    {{ expFilter.excludedCount.value }} مستبعد — إظهار الكل
                </button>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in expFilter.visibleRows.value" :key="row.name" class="hover:bg-sf2">
                        <td class="px-4 py-2.5 text-t">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-d">{{ fmt(row.amount) }} ج.م</td>
                        <td class="w-6 px-2 py-2.5 print:hidden">
                            <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="expFilter.exclude(row)">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expFilter.visibleRows.value.length === 0">
                        <td colspan="3" class="px-4 py-4 text-center text-t3">
                            {{ data.expenses.length === 0 ? 'لا توجد مصروفات' : 'لا توجد نتائج مطابقة' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-d/30 bg-dp/30 font-bold">
                        <td class="px-4 py-3 text-d">إجمالي المصروفات</td>
                        <td class="px-4 py-3 text-left font-mono text-d">{{ fmt(visibleTotalExpense) }} ج.م</td>
                        <td class="print:hidden" />
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Net Result Card -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br shadow-[var(--sh)]">
        <div class="bg-pd px-4 py-3">
            <p class="text-sm font-bold text-white">نتيجة الفترة المالية</p>
        </div>
        <div class="bg-sf p-6 text-center">
            <p class="text-sm text-t2">الفترة من {{ data.from }} إلى {{ data.to }}</p>
            <p
                class="mt-3 text-4xl font-bold"
                :class="visibleNetIncome >= 0 ? 'text-s' : 'text-d'"
            >
                {{ fmt(visibleNetIncome) }} ج.م
            </p>
            <span
                class="mt-2 inline-block rounded-full px-4 py-1 text-sm font-semibold"
                :class="visibleNetIncome >= 0 ? 'bg-sp text-s' : 'bg-dp text-d'"
            >
                {{ visibleNetIncome >= 0 ? 'ربح' : 'خسارة' }}
            </span>
        </div>
    </div>
</template>
