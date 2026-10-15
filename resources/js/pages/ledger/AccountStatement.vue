<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { ref } from 'vue';

interface Account {
    id: string;
    code: string;
    name: string;
}

interface StatementRow {
    date: string;
    description: string;
    debit: number;
    credit: number;
    balance: number;
    reference?: string;
}

interface Statement {
    account: { code: string; name: string };
    statement: StatementRow[];
}

const props = defineProps<{
    statement: Statement | null;
    accounts: Account[];
    filters: { account_id?: string; from?: string; to?: string };
}>();

const accountFilter = ref(props.filters.account_id ?? '');
const fromFilter    = ref(props.filters.from       ?? '');
const toFilter      = ref(props.filters.to         ?? '');

function applyFilters() {
    router.get('/ledger/account-statement', {
        account_id: accountFilter.value || undefined,
        from:       fromFilter.value    || undefined,
        to:         toFilter.value      || undefined,
    }, { preserveState: true });
}

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
    <Head title="كشف الحساب" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <h2 class="text-lg font-bold text-hospital-text">كشف الحساب</h2>
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="accountFilter" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters">
                <option value="">— اختر الحساب —</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
            </select>
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <input v-model="toFilter"   type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <button class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm hover:bg-hospital-bg" @click="printPage">
                <Printer class="h-4 w-4" /> طباعة
            </button>
        </div>
    </div>

    <div v-if="statement" class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
        <div class="border-b border-hospital-border px-4 py-3 font-semibold text-hospital-text">
            {{ statement.account.code }} — {{ statement.account.name }}
        </div>
        <table class="w-full text-sm">
            <thead class="border-b border-hospital-border bg-hospital-bg">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                    <th class="px-4 py-3 text-right font-semibold">البيان</th>
                    <th class="px-4 py-3 text-left font-semibold">مدين</th>
                    <th class="px-4 py-3 text-left font-semibold">دائن</th>
                    <th class="px-4 py-3 text-left font-semibold">الرصيد</th>
                    <th class="px-4 py-3 text-right font-semibold">المرجع</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, i) in statement.statement" :key="i" class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                    <td class="px-4 py-3">{{ row.date }}</td>
                    <td class="px-4 py-3">{{ row.description }}</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(row.debit) }}</td>
                    <td class="px-4 py-3 text-left font-mono">{{ fmt(row.credit) }}</td>
                    <td class="px-4 py-3 text-left font-mono font-semibold" :class="row.balance < 0 ? 'text-hospital-danger' : 'text-hospital-success'">
                        {{ fmtBal(Math.abs(row.balance)) }}
                    </td>
                    <td class="px-4 py-3 text-hospital-text-2">{{ row.reference ?? '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div v-else class="rounded-xl border border-hospital-border bg-white p-12 text-center text-hospital-text-2">
        اختر حساباً لعرض كشف الحساب
    </div>
</template>
