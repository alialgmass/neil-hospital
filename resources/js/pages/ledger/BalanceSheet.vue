<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Printer } from 'lucide-vue-next';

interface Row {
    code: string;
    name: string;
    balance: number;
}

interface Sheet {
    asOf: string;
    assets: Row[];
    liabilities: Row[];
    equity: Row[];
    totalAssets: number;
    totalLiabilities: number;
    totalEquity: number;
    totalLiabilitiesAndEquity: number;
    isBalanced: boolean;
}

const props = defineProps<{
    sheet: Sheet;
    filters: { asOf?: string };
}>();

function applyFilter(value: string) {
    router.get('/ledger/balance-sheet', { as_of: value || undefined }, { preserveState: true });
}

function fmt(n: number) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="الميزانية العمومية" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <h2 class="text-lg font-bold text-hospital-text">الميزانية العمومية</h2>
                <p class="text-xs text-hospital-text-2">الأصول مقابل الخصوم وحقوق الملكية كما في تاريخ محدد</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="date"
                    :value="filters.asOf ?? ''"
                    class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    @change="applyFilter(($event.target as HTMLInputElement).value)"
                />
                <button class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary-light" @click="printPage">
                    <Printer class="h-4 w-4" /> طباعة
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between rounded-xl border p-4 shadow-sm print:hidden"
            :class="sheet.isBalanced ? 'border-hospital-success/30 bg-hospital-success/5' : 'border-hospital-danger/30 bg-hospital-danger/5'">
            <div>
                <p class="text-xs font-medium" :class="sheet.isBalanced ? 'text-hospital-success' : 'text-hospital-danger'">حالة الميزانية كما في {{ sheet.asOf }}</p>
                <p class="mt-1 text-sm font-bold" :class="sheet.isBalanced ? 'text-hospital-success' : 'text-hospital-danger'">
                    {{ sheet.isBalanced ? 'متوازنة' : 'غير متوازنة' }}
                </p>
            </div>
            <CheckCircle2 v-if="sheet.isBalanced" class="h-8 w-8 text-hospital-success" />
            <AlertTriangle v-else class="h-8 w-8 text-hospital-danger" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
                <div class="border-b border-hospital-border bg-hospital-bg px-4 py-3 font-semibold text-hospital-text">الأصول</div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="row in sheet.assets" :key="row.code" class="border-b border-hospital-border/50 even:bg-hospital-bg/30">
                            <td class="px-4 py-2 font-mono text-hospital-text-2">{{ row.code }}</td>
                            <td class="px-4 py-2 text-hospital-text">{{ row.name }}</td>
                            <td class="px-4 py-2 text-left font-mono">{{ fmt(row.balance) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-hospital-primary bg-hospital-bg font-semibold">
                        <tr>
                            <td colspan="2" class="px-4 py-3 text-hospital-text">إجمالي الأصول</td>
                            <td class="px-4 py-3 text-left font-mono">{{ fmt(sheet.totalAssets) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="space-y-4">
                <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
                    <div class="border-b border-hospital-border bg-hospital-bg px-4 py-3 font-semibold text-hospital-text">الخصوم</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="row in sheet.liabilities" :key="row.code" class="border-b border-hospital-border/50 even:bg-hospital-bg/30">
                                <td class="px-4 py-2 font-mono text-hospital-text-2">{{ row.code }}</td>
                                <td class="px-4 py-2 text-hospital-text">{{ row.name }}</td>
                                <td class="px-4 py-2 text-left font-mono">{{ fmt(row.balance) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-hospital-primary bg-hospital-bg font-semibold">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-hospital-text">إجمالي الخصوم</td>
                                <td class="px-4 py-3 text-left font-mono">{{ fmt(sheet.totalLiabilities) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
                    <div class="border-b border-hospital-border bg-hospital-bg px-4 py-3 font-semibold text-hospital-text">حقوق الملكية</div>
                    <table class="w-full text-sm">
                        <tbody>
                            <tr v-for="row in sheet.equity" :key="row.code" class="border-b border-hospital-border/50 even:bg-hospital-bg/30">
                                <td class="px-4 py-2 font-mono text-hospital-text-2">{{ row.code }}</td>
                                <td class="px-4 py-2 text-hospital-text">{{ row.name }}</td>
                                <td class="px-4 py-2 text-left font-mono">{{ fmt(row.balance) }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="border-t-2 border-hospital-primary bg-hospital-bg font-semibold">
                            <tr>
                                <td colspan="2" class="px-4 py-3 text-hospital-text">إجمالي حقوق الملكية</td>
                                <td class="px-4 py-3 text-left font-mono">{{ fmt(sheet.totalEquity) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
