<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, Download, EyeOff, Search, TrendingUp, Users, Wallet, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    dept: string;
    doctor_name: string;
    cases: number;
    revenue: number;
    ins_amount: number;
    patient_amount: number;
}

const props = defineProps<{
    data: { rows: Row[]; total: number; from: string; to: string };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
    pentacam: 'البنتكام',
};

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['doctor_name', 'dept'],
    (r) => `${r.dept}-${r.doctor_name}`,
);

const totalRevenue = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.revenue), 0));
const totalCases = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.cases), 0));
const totalIns = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.ins_amount), 0));
const deptCount = computed(() => new Set(visibleRows.value.map((r) => r.dept)).size);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/dept-revenue', { from: from.value, to: to.value }, { preserveState: true });
}

function exportExcel() {
    window.location.href = `/reports/dept-revenue/export?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head title="إيرادات الأقسام" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">إيرادات الأقسام</h1>
        <p class="mt-0.5 text-sm text-t3">مقارنة إيرادات كل قسم بالتفصيل</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Wallet class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الإيرادات</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalRevenue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Users class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحالات</p>
                <p class="text-xl font-bold text-t">{{ totalCases }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <BarChart3 class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">مطالبات التأمين</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalIns) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <TrendingUp class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد الأقسام</p>
                <p class="text-xl font-bold text-t">{{ deptCount }}</p>
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
        <button class="btn-secondary self-end flex items-center gap-2" @click="exportExcel">
            <Download class="h-4 w-4" />
            Excel
        </button>
        <button class="btn-secondary self-end" @click="() => window.print()">طباعة</button>
    </div>

    <!-- Row search + exclusion status -->
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <div class="relative">
            <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
            <input
                v-model="rowSearch"
                type="text"
                placeholder="ابحث بالطبيب أو القسم..."
                class="input-field h-9 w-64 pr-9"
            />
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
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القسم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الطبيب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الإيراد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تأمين</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">نقدي</th>
                    <th class="w-8 px-2 py-3 print:hidden" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="`${row.dept}-${row.doctor_name}`" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ deptLabels[row.dept] || row.dept }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.doctor_name || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.cases }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-t">{{ Number(row.revenue).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-p">{{ Number(row.ins_amount).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-s">{{ Number(row.patient_amount).toFixed(2) }} ج</td>
                    <td class="px-2 py-3 print:hidden">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="7">
                        {{ data.rows.length === 0 ? 'لا توجد بيانات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-3 font-bold text-t" colspan="3">الإجمالي</td>
                    <td class="px-4 py-3 font-mono font-bold text-p">{{ Number(totalRevenue).toFixed(2) }} ج</td>
                    <td colspan="3" />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
