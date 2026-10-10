<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface StatementRow {
    date: string;
    description: string;
    in: number;
    out: number;
    balance: number;
    reference?: string;
}

interface Statement {
    opening_balance: number;
    statement: StatementRow[];
}

const props = defineProps<{
    statement: Statement;
    filters: { from?: string; to?: string };
}>();

const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

function applyFilters() {
    router.get(
        '/treasury/statement',
        { from: fromFilter.value || undefined, to: toFilter.value || undefined },
        { preserveState: true },
    );
}

const totalIn = computed(() => props.statement.statement.reduce((sum, row) => sum + row.in, 0));
const totalOut = computed(() => props.statement.statement.reduce((sum, row) => sum + row.out, 0));
const closingBalance = computed(() => props.statement.opening_balance + totalIn.value - totalOut.value);

function fmt(n: number) {
    return n > 0 ? n.toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—';
}
function fmtBal(n: number) {
    return n.toLocaleString('en-US', { minimumFractionDigits: 2 });
}
function printPage() {
    window.print();
}
</script>

<template>
    <Head title="كشف حركة الخزنة الرئيسية" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <h2 class="text-lg font-bold text-hospital-text">كشف حركة الخزنة الرئيسية</h2>
        <div class="flex flex-wrap items-center gap-2">
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <button class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm hover:bg-hospital-bg" @click="printPage">
                <Printer class="h-4 w-4" /> طباعة
            </button>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
        <div class="border-b border-hospital-border px-4 py-3 font-semibold text-hospital-text">
            الخزنة الرئيسية — الرصيد الافتتاحي:
            <span class="font-mono">{{ fmtBal(statement.opening_balance) }} ج.م</span>
        </div>
        <table class="w-full text-sm">
            <thead class="border-b border-hospital-border bg-hospital-bg">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                    <th class="px-4 py-3 text-right font-semibold">البيان</th>
                    <th class="px-4 py-3 text-left font-semibold">وارد</th>
                    <th class="px-4 py-3 text-left font-semibold">صادر</th>
                    <th class="px-4 py-3 text-left font-semibold">الرصيد</th>
                    <th class="px-4 py-3 text-right font-semibold">المرجع</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, i) in statement.statement" :key="i" class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                    <td class="px-4 py-3">{{ row.date }}</td>
                    <td class="px-4 py-3">{{ row.description }}</td>
                    <td class="px-4 py-3 text-left font-mono text-hospital-success">{{ fmt(row.in) }}</td>
                    <td class="px-4 py-3 text-left font-mono text-hospital-danger">{{ fmt(row.out) }}</td>
                    <td class="px-4 py-3 text-left font-mono font-semibold" :class="row.balance < 0 ? 'text-hospital-danger' : 'text-hospital-success'">
                        {{ fmtBal(row.balance) }}
                    </td>
                    <td class="px-4 py-3 text-hospital-text-2">{{ row.reference ?? '—' }}</td>
                </tr>
                <tr v-if="statement.statement.length === 0">
                    <td class="px-4 py-10 text-center text-hospital-text-3" colspan="6">لا توجد حركات في هذه الفترة</td>
                </tr>
            </tbody>
            <tfoot v-if="statement.statement.length > 0" class="border-t-2 border-hospital-border bg-hospital-bg font-bold">
                <tr>
                    <td class="px-4 py-3" colspan="2">الإجمالي ({{ statement.statement.length }} حركة)</td>
                    <td class="px-4 py-3 text-left font-mono text-hospital-success">{{ fmtBal(totalIn) }}</td>
                    <td class="px-4 py-3 text-left font-mono text-hospital-danger">{{ fmtBal(totalOut) }}</td>
                    <td class="px-4 py-3 text-left font-mono" :class="closingBalance < 0 ? 'text-hospital-danger' : 'text-hospital-success'">
                        {{ fmtBal(closingBalance) }}
                    </td>
                    <td class="px-4 py-3 text-hospital-text-2">الرصيد الختامي</td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
