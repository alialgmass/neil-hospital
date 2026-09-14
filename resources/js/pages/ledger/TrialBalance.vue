<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Printer, Scale, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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

function clearFilters() {
    fromFilter.value = '';
    toFilter.value = '';

    router.get('/ledger/trial-balance', {}, { preserveState: true });
}

const totalDebits  = computed(() => props.rows.reduce((s, r) => s + r.debits, 0));
const totalCredits = computed(() => props.rows.reduce((s, r) => s + r.credits, 0));
const isBalanced   = computed(() => Math.abs(totalDebits.value - totalCredits.value) < 0.01);

const groupLabels: Record<string, string> = {
    assets: 'أصول', liabilities: 'خصوم', equity: 'حقوق ملكية', revenues: 'إيرادات', expenses: 'مصروفات',
};

const groupBadgeClass: Record<string, string> = {
    assets:      'bg-blue-50 text-blue-700 border-blue-200',
    liabilities: 'bg-orange-50 text-orange-700 border-orange-200',
    equity:      'bg-purple-50 text-purple-700 border-purple-200',
    revenues:    'bg-green-50 text-green-700 border-green-200',
    expenses:    'bg-red-50 text-red-700 border-red-200',
};

function groupClass(group: string): string {
    return groupBadgeClass[group] ?? 'bg-hospital-bg text-hospital-text-2 border-hospital-border';
}

function fmt(n: number) {
    return n.toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
function printPage() {
    window.print();
}

const printedAt = new Date().toLocaleDateString('ar-EG', { year: 'numeric', month: 'long', day: 'numeric' });

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

    <div class="space-y-5">
        <!-- Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
            <div>
                <h2 class="text-lg font-bold text-hospital-text">ميزان المراجعة</h2>
                <p class="text-xs text-hospital-text-2">أرصدة جميع الحسابات المدينة والدائنة خلال الفترة المحددة</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5">
                    <label class="text-xs font-medium text-hospital-text-2">من</label>
                    <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
                </div>
                <div class="flex items-center gap-1.5">
                    <label class="text-xs font-medium text-hospital-text-2">إلى</label>
                    <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
                </div>
                <button v-if="fromFilter || toFilter" class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg" @click="clearFilters">
                    <X class="h-3.5 w-3.5" /> مسح
                </button>
                <button class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary-light" @click="printPage">
                    <Printer class="h-4 w-4" /> طباعة
                </button>
            </div>
        </div>

        <!-- Print-only letterhead -->
        <div class="hidden border-b-2 border-hospital-primary pb-3 text-center print:block">
            <h1 class="text-xl font-bold text-hospital-primary">مستشفى النور</h1>
            <p class="text-xs text-hospital-text-2">طب وجراحة العيون — المنيا، مصر</p>
            <p class="mt-2 text-sm font-semibold text-hospital-text">ميزان المراجعة</p>
            <p class="text-xs text-hospital-text-2">
                <span v-if="fromFilter || toFilter">الفترة من {{ fromFilter || '—' }} إلى {{ toFilter || '—' }}</span>
                <span v-else>كافة الفترات</span>
                — تاريخ الطباعة: {{ printedAt }}
            </p>
        </div>

        <!-- Summary cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 print:hidden">
            <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-hospital-text-2">إجمالي المدين</p>
                <p class="mt-1 font-mono text-lg font-bold text-hospital-primary">{{ fmt(totalDebits) }}</p>
            </div>
            <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
                <p class="text-xs font-medium text-hospital-text-2">إجمالي الدائن</p>
                <p class="mt-1 font-mono text-lg font-bold text-hospital-success">{{ fmt(totalCredits) }}</p>
            </div>
            <div class="flex items-center justify-between rounded-xl border p-4 shadow-sm"
                :class="isBalanced ? 'border-hospital-success/30 bg-hospital-success/5' : 'border-hospital-danger/30 bg-hospital-danger/5'">
                <div>
                    <p class="text-xs font-medium" :class="isBalanced ? 'text-hospital-success' : 'text-hospital-danger'">حالة الميزان</p>
                    <p class="mt-1 text-sm font-bold" :class="isBalanced ? 'text-hospital-success' : 'text-hospital-danger'">
                        {{ isBalanced ? 'متوازن' : 'غير متوازن' }}
                    </p>
                </div>
                <CheckCircle2 v-if="isBalanced" class="h-8 w-8 text-hospital-success" />
                <AlertTriangle v-else class="h-8 w-8 text-hospital-danger" />
            </div>
        </div>

        <div v-if="rows.length" class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
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
                    <tr v-for="row in rows" :key="row.code" class="border-b border-hospital-border/50 transition-colors even:bg-hospital-bg/30 hover:bg-hospital-primary-pale/40">
                        <td class="px-4 py-3 font-mono text-hospital-text-2">{{ row.code }}</td>
                        <td class="px-4 py-3 font-medium text-hospital-text">{{ row.name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium" :class="groupClass(row.group)">
                                {{ groupLabels[row.group] ?? row.group }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-left font-mono">{{ row.debits ? fmt(row.debits) : '—' }}</td>
                        <td class="px-4 py-3 text-left font-mono">{{ row.credits ? fmt(row.credits) : '—' }}</td>
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
                        <td class="px-4 py-3 text-left">
                            <span class="inline-flex items-center gap-1 text-xs" :class="isBalanced ? 'text-hospital-success' : 'text-hospital-danger'">
                                <Scale class="h-3.5 w-3.5" /> {{ isBalanced ? 'متوازن' : 'غير متوازن' }}
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div v-else class="rounded-xl border border-hospital-border bg-white p-12 text-center text-hospital-text-2">
            لا توجد بيانات لعرضها ضمن الفترة المحددة
        </div>
    </div>
</template>
