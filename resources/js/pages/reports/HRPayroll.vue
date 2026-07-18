<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Banknote, TrendingDown, TrendingUp, Users, CheckCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface PayrollRow {
    employee_name: string;
    employee_no: string;
    dept: string;
    position: string;
    month: number;
    year: number;
    base_salary: number;
    allowances: number;
    overtime_pay: number;
    deductions: number;
    net_salary: number;
    status: string;
    paid_at: string | null;
}

interface Totals {
    base_salary: number;
    allowances: number;
    overtime_pay: number;
    deductions: number;
    net_salary: number;
    paid_count: number;
    draft_count: number;
}

const props = defineProps<{
    data: { rows: PayrollRow[]; totals: Totals; month: number; year: number };
    filters: { month: number; year: number };
}>();

const monthFilter = ref(props.filters.month);
const yearFilter = ref(props.filters.year);

const months = [
    { v: 1, l: 'يناير' }, { v: 2, l: 'فبراير' }, { v: 3, l: 'مارس' },
    { v: 4, l: 'أبريل' }, { v: 5, l: 'مايو' }, { v: 6, l: 'يونيو' },
    { v: 7, l: 'يوليو' }, { v: 8, l: 'أغسطس' }, { v: 9, l: 'سبتمبر' },
    { v: 10, l: 'أكتوبر' }, { v: 11, l: 'نوفمبر' }, { v: 12, l: 'ديسمبر' },
];

function apply() {
    router.get('/reports/hr-payroll', { month: monthFilter.value, year: yearFilter.value }, { preserveState: true });
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

const statusClass: Record<string, string> = {
    draft:    'bg-sf2 text-t3',
    approved: 'bg-pp text-p',
    paid:     'bg-sp/10 text-s',
};

const statusLabel: Record<string, string> = {
    draft:    'مسودة',
    approved: 'معتمد',
    paid:     'مصروف',
};

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات',
    lasik: 'الليزك', laser: 'الليزر', reception: 'الاستقبال',
    admin: 'الإدارة', pharmacy: 'الصيدلية', hr: 'الموارد البشرية',
};
</script>

<template>
    <Head title="تقرير الرواتب" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الرواتب</h1>
        <p class="mt-0.5 text-sm text-t3">ملخص رواتب الموظفين للشهر المحدد</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Banknote class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الرواتب</p>
                <p class="text-lg font-bold text-t">{{ fmt(data.totals.net_salary) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <TrendingUp class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الوقت الإضافي</p>
                <p class="text-lg font-bold text-s">{{ fmt(data.totals.overtime_pay) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <TrendingDown class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الخصومات</p>
                <p class="text-lg font-bold text-d">{{ fmt(data.totals.deductions) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <CheckCircle class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">مصروف</p>
                <p class="text-lg font-bold text-s">{{ data.totals.paid_count }}</p>
                <p class="text-xs text-t3">موظف</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sf2">
                <Users class="h-5 w-5 text-t3" />
            </div>
            <div>
                <p class="text-xs text-t3">مسودة</p>
                <p class="text-lg font-bold text-t2">{{ data.totals.draft_count }}</p>
                <p class="text-xs text-t3">موظف</p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-br px-5 py-4">
            <div>
                <h3 class="font-semibold text-t">كشف الرواتب</h3>
                <p class="text-xs text-t3">{{ data.rows.length }} موظف</p>
            </div>
            <div class="flex items-end gap-2">
                <select v-model="monthFilter" class="input-field w-auto">
                    <option v-for="m in months" :key="m.v" :value="m.v">{{ m.l }}</option>
                </select>
                <input v-model="yearFilter" type="number" min="2020" max="2100" class="input-field w-24" />
                <button class="btn-primary" @click="apply">عرض</button>
            </div>
        </div>

        <div v-if="data.rows.length === 0" class="py-16 text-center text-sm text-t3">
            لا توجد رواتب لهذا الشهر — قم بإنشاء كشف الرواتب من صفحة الرواتب
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br bg-sf2 text-right text-xs text-t3">
                        <th class="px-4 py-3 font-semibold">الموظف</th>
                        <th class="px-4 py-3 font-semibold">القسم / الوظيفة</th>
                        <th class="px-4 py-3 text-left font-semibold">الراتب الأساسي</th>
                        <th class="px-4 py-3 text-left font-semibold">البدلات</th>
                        <th class="px-4 py-3 text-left font-semibold">إضافي</th>
                        <th class="px-4 py-3 text-left font-semibold">خصومات</th>
                        <th class="px-4 py-3 text-left font-semibold">الصافي</th>
                        <th class="px-4 py-3 text-center font-semibold">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="(row, i) in data.rows" :key="i" class="hover:bg-sf2">
                        <td class="px-4 py-3">
                            <p class="font-medium text-t">{{ row.employee_name }}</p>
                            <p class="text-xs text-t3">{{ row.employee_no }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <p class="text-t2">{{ deptLabels[row.dept] ?? row.dept }}</p>
                            <p class="text-xs text-t3">{{ row.position }}</p>
                        </td>
                        <td class="px-4 py-3 text-left font-mono text-t2">{{ fmt(row.base_salary) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-t2">{{ fmt(row.allowances) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-s">
                            <span v-if="row.overtime_pay > 0">+{{ fmt(row.overtime_pay) }}</span>
                            <span v-else class="text-t3">—</span>
                        </td>
                        <td class="px-4 py-3 text-left font-mono text-d">
                            <span v-if="row.deductions > 0">-{{ fmt(row.deductions) }}</span>
                            <span v-else class="text-t3">—</span>
                        </td>
                        <td class="px-4 py-3 text-left font-mono font-bold text-p">{{ fmt(row.net_salary) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass[row.status]">
                                {{ statusLabel[row.status] ?? row.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-br">
                    <tr class="bg-sf2 font-bold">
                        <td colspan="2" class="px-4 py-3 text-t">الإجمالي</td>
                        <td class="px-4 py-3 text-left font-mono text-t2">{{ fmt(data.totals.base_salary) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-t2">{{ fmt(data.totals.allowances) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-s">+{{ fmt(data.totals.overtime_pay) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-d">-{{ fmt(data.totals.deductions) }}</td>
                        <td class="px-4 py-3 text-left font-mono text-p">{{ fmt(data.totals.net_salary) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
