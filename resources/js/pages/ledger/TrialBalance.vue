<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { ref } from 'vue';

interface TrialRow {
    code: string;
    name: string;
    group: string;
    nature: string;
    debits: number;
    credits: number;
    balance: number;
}

const props = defineProps<{
    rows: TrialRow[];
    filters: { from?: string; to?: string };
}>();

const fromFilter = ref(props.filters.from ?? '');
const toFilter   = ref(props.filters.to   ?? '');

function applyFilters() {
    router.get('/ledger/trial-balance', { from: fromFilter.value || undefined, to: toFilter.value || undefined }, { preserveState: true });
}

const totalDebits  = props.rows.reduce((s, r) => s + r.debits, 0);
const totalCredits = props.rows.reduce((s, r) => s + r.credits, 0);

const groupLabels: Record<string, string> = {
    assets: 'أصول', liabilities: 'خصوم', equity: 'حقوق ملكية', revenues: 'إيرادات', expenses: 'مصروفات',
};

function fmt(n: number) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
function printPage() {
    window.print();
}

function balanceSideLabel(row: TrialRow): string {
    const isNaturalPositive = row.balance >= 0;
    const naturalSideIsDebit = row.nature === 'debit';
    const isDebitBalance = isNaturalPositive ? naturalSideIsDebit : !naturalSideIsDebit;
    return isDebitBalance ? 'مدين' : 'دائن';
}

function balanceSideClass(row: TrialRow): string {
    const isNaturalPositive = row.balance >= 0;
    const naturalSideIsDebit = row.nature === 'debit';
    const isDebitBalance = isNaturalPositive ? naturalSideIsDebit : !naturalSideIsDebit;
    return isDebitBalance ? 'text-hospital-primary' : 'text-hospital-success';
}
</script>

<template>
    <Head title="ميزان المراجعة" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <h2 class="text-lg font-bold text-hospital-text">ميزان المراجعة</h2>
        <div class="flex items-center gap-2">
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <input v-model="toFilter"   type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <button class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm hover:bg-hospital-bg" @click="printPage">
                <Printer class="h-4 w-4" /> طباعة
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="border-b border-hospital-border bg-hospital-bg text-hospital-text">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">الكود</th>
                    <th class="px-4 py-3 text-right font-semibold">الحساب</th>
                    <th class="px-4 py-3 text-right font-semibold">المجموعة</th>
                    <th class="px-4 py-3 text-left font-semibold">مجموع المدين</th>
                    <th class="px-4 py-3 text-left font-semibold">مجموع الدائن</th>
                    <th class="px-4 py-3 text-left font-semibold">الرصيد</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.code" class="border-b border-hospital-border/50 hover:bg-hospital-bg/40 transition-colors">
                    <td class="px-4 py-3 font-mono text-hospital-text-2">{{ row.code }}</td>
                    <td class="px-4 py-3 text-hospital-text">{{ row.name }}</td>
                    <td class="px-4 py-3 text-hospital-text-2">{{ groupLabels[row.group] ?? row.group }}</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(row.debits) }}</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(row.credits) }}</td>
                    <td class="px-4 py-3 text-left font-mono font-semibold" :class="balanceSideClass(row)">
                        {{ fmt(Math.abs(row.balance)) }}
                        <span class="text-xs opacity-70">({{ balanceSideLabel(row) }})</span>
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-hospital-primary bg-hospital-bg font-semibold">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-hospital-text">الإجمالي</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(totalDebits) }}</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(totalCredits) }}</td>
                    <td class="px-4 py-3" />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
