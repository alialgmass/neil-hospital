<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { ref } from 'vue';

interface AccountRow {
    code: string;
    name: string;
    balance: number;
}

interface Statement {
    revenues: AccountRow[];
    costOfServices: AccountRow[];
    doctorFees: AccountRow[];
    operatingExpenses: AccountRow[];
    totalRevenue: number;
    totalCost: number;
    grossProfit: number;
    totalDoctorFees: number;
    totalOperating: number;
    netBeforeTax: number;
    tax: number;
    netIncome: number;
}

const props = defineProps<{
    statement: Statement;
    filters: { from?: string; to?: string };
}>();

const fromFilter = ref(props.filters.from ?? '');
const toFilter   = ref(props.filters.to   ?? '');

function applyFilters() {
    router.get('/ledger/income-statement', {
        from: fromFilter.value || undefined,
        to:   toFilter.value   || undefined,
    }, { preserveState: true });
}

function fmt(n: number) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
function printPage() {
    window.print();
}
</script>

<template>
    <Head title="قائمة الدخل" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3 print:hidden">
        <h2 class="text-lg font-bold text-hospital-text">قائمة الدخل التفصيلية</h2>
        <div class="flex items-center gap-2">
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <input v-model="toFilter"   type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <button class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm hover:bg-hospital-bg print:hidden" @click="printPage">
                <Printer class="h-4 w-4" /> طباعة
            </button>
        </div>
    </div>

    <div class="mx-auto max-w-2xl space-y-4">

        <!-- ① Revenues -->
        <div class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
            <div class="border-b border-hospital-border bg-green-50 px-4 py-3">
                <h3 class="font-semibold text-green-800">💰 إيرادات التشغيل</h3>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="row in statement.revenues" :key="row.code"
                        class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                        <td class="px-4 py-2.5 font-mono text-hospital-muted">{{ row.code }}</td>
                        <td class="px-4 py-2.5 text-hospital-text">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-green-700">{{ fmt(row.balance) }}</td>
                    </tr>
                    <tr v-if="statement.revenues.length === 0">
                        <td colspan="3" class="px-4 py-3 text-center text-hospital-muted">لا توجد إيرادات</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-green-200 bg-green-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-green-800">إجمالي الإيرادات</td>
                        <td class="px-4 py-3 text-left font-mono text-green-800">{{ fmt(statement.totalRevenue) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- ② Cost of Medical Services -->
        <div class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
            <div class="border-b border-hospital-border bg-red-50 px-4 py-3">
                <h3 class="font-semibold text-red-800">🔧 تكلفة الخدمات الطبية المباشرة</h3>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="row in statement.costOfServices" :key="row.code"
                        class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                        <td class="px-4 py-2.5 font-mono text-hospital-muted">{{ row.code }}</td>
                        <td class="px-4 py-2.5 text-hospital-text">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-red-700">{{ fmt(row.balance) }}</td>
                    </tr>
                    <tr v-if="statement.costOfServices.length === 0">
                        <td colspan="3" class="px-4 py-3 text-center text-hospital-muted">—</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-red-200 bg-red-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-red-800">إجمالي تكلفة الخدمات</td>
                        <td class="px-4 py-3 text-left font-mono text-red-800">{{ fmt(statement.totalCost) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Gross Profit -->
        <div class="overflow-hidden rounded-xl border-2 border-blue-300 bg-blue-50 shadow-sm">
            <div class="flex items-center justify-between px-5 py-3">
                <span class="font-bold text-blue-900">🏆 مجمل الربح التشغيلي</span>
                <span class="font-mono text-lg font-bold text-blue-700">{{ fmt(statement.grossProfit) }} ج</span>
            </div>
        </div>

        <!-- ③ Doctor Fees -->
        <div class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
            <div class="border-b border-hospital-border bg-orange-50 px-4 py-3">
                <h3 class="font-semibold text-orange-800">👨‍⚕️ مستحقات وأتعاب الأطباء</h3>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="row in statement.doctorFees" :key="row.code"
                        class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                        <td class="px-4 py-2.5 font-mono text-hospital-muted">{{ row.code }}</td>
                        <td class="px-4 py-2.5 text-hospital-text">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-orange-700">{{ fmt(row.balance) }}</td>
                    </tr>
                    <tr v-if="statement.doctorFees.length === 0">
                        <td colspan="3" class="px-4 py-3 text-center text-hospital-muted">—</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-orange-200 bg-orange-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-orange-800">إجمالي مستحقات الأطباء</td>
                        <td class="px-4 py-3 text-left font-mono text-orange-800">{{ fmt(statement.totalDoctorFees) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- ④ Operating Expenses -->
        <div class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
            <div class="border-b border-hospital-border bg-red-50 px-4 py-3">
                <h3 class="font-semibold text-red-800">📋 المصروفات التشغيلية العامة</h3>
            </div>
            <table class="w-full text-sm">
                <tbody>
                    <tr v-for="row in statement.operatingExpenses" :key="row.code"
                        class="border-b border-hospital-border/50 hover:bg-hospital-bg/40">
                        <td class="px-4 py-2.5 font-mono text-hospital-muted">{{ row.code }}</td>
                        <td class="px-4 py-2.5 text-hospital-text">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-red-700">{{ fmt(row.balance) }}</td>
                    </tr>
                    <tr v-if="statement.operatingExpenses.length === 0">
                        <td colspan="3" class="px-4 py-3 text-center text-hospital-muted">—</td>
                    </tr>
                </tbody>
                <tfoot class="border-t border-red-200 bg-red-50 font-semibold">
                    <tr>
                        <td colspan="2" class="px-4 py-3 text-red-800">إجمالي المصروفات التشغيلية</td>
                        <td class="px-4 py-3 text-left font-mono text-red-800">{{ fmt(statement.totalOperating) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Net Profit before Tax -->
        <div class="overflow-hidden rounded-xl border-2 border-hospital-primary bg-hospital-primary/5 shadow-sm">
            <div class="flex items-center justify-between px-5 py-3">
                <span class="font-bold text-hospital-primary">💎 صافي الربح التشغيلي قبل الضريبة</span>
                <span class="font-mono text-lg font-bold"
                    :class="statement.netBeforeTax >= 0 ? 'text-green-700' : 'text-red-700'">
                    {{ fmt(Math.abs(statement.netBeforeTax)) }} ج
                </span>
            </div>
        </div>

        <!-- Tax -->
        <div class="overflow-hidden rounded-xl border border-red-200 bg-red-50 shadow-sm">
            <div class="flex items-center justify-between px-5 py-3">
                <span class="text-sm font-medium text-red-800">ضريبة الدخل (15%)</span>
                <span class="font-mono font-medium text-red-700">{{ fmt(statement.tax) }} ج</span>
            </div>
        </div>

        <!-- Net Income after Tax -->
        <div class="overflow-hidden rounded-xl border-2 shadow-sm"
            :class="statement.netIncome >= 0 ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50'">
            <div class="flex items-center justify-between px-6 py-4">
                <span class="text-lg font-bold"
                    :class="statement.netIncome >= 0 ? 'text-green-800' : 'text-red-800'">
                    ✅ {{ statement.netIncome >= 0 ? 'صافي الربح بعد الضريبة' : 'صافي الخسارة' }}
                </span>
                <span class="font-mono text-xl font-bold"
                    :class="statement.netIncome >= 0 ? 'text-green-700' : 'text-red-700'">
                    {{ fmt(Math.abs(statement.netIncome)) }} ج
                </span>
            </div>
        </div>

    </div>
</template>
