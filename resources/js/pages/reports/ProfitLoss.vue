<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

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
                <p class="text-xl font-bold text-s">{{ fmt(data.totalRevenue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <div class="text-lg font-bold text-d">↓</div>
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المصروفات</p>
                <p class="text-xl font-bold text-d">{{ fmt(data.totalExpense) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                :class="data.netIncome >= 0 ? 'bg-pp' : 'bg-dp'"
            >
                <div class="text-lg font-bold" :class="data.netIncome >= 0 ? 'text-p' : 'text-d'">
                    {{ data.netIncome >= 0 ? '=' : '!' }}
                </div>
            </div>
            <div>
                <p class="text-xs text-t3">صافي الدخل</p>
                <p class="text-xl font-bold" :class="data.netIncome >= 0 ? 'text-p' : 'text-d'">{{ fmt(data.netIncome) }}</p>
                <p class="text-xs" :class="data.netIncome >= 0 ? 'text-s' : 'text-d'">{{ data.netIncome >= 0 ? 'ربح' : 'خسارة' }}</p>
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
            <table class="w-full text-sm">
                <tbody class="divide-y divide-br/50">
                    <tr v-for="(row, idx) in data.revenues" :key="idx" class="hover:bg-sf2">
                        <td class="px-4 py-2.5 text-t">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-s">{{ fmt(row.amount) }} ج.م</td>
                    </tr>
                    <tr v-if="data.revenues.length === 0">
                        <td colspan="2" class="px-4 py-4 text-center text-t3">لا توجد إيرادات</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-s/30 bg-sp/30 font-bold">
                        <td class="px-4 py-3 text-s">إجمالي الإيرادات</td>
                        <td class="px-4 py-3 text-left font-mono text-s">{{ fmt(data.totalRevenue) }} ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Expenses -->
        <div class="overflow-hidden rounded-[var(--rl)] border border-br shadow-[var(--sh)]">
            <div class="border-b border-br bg-dp/50 px-4 py-3">
                <p class="font-semibold text-d">المصروفات</p>
            </div>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-br/50">
                    <tr v-for="(row, idx) in data.expenses" :key="idx" class="hover:bg-sf2">
                        <td class="px-4 py-2.5 text-t">{{ row.name }}</td>
                        <td class="px-4 py-2.5 text-left font-mono font-medium text-d">{{ fmt(row.amount) }} ج.م</td>
                    </tr>
                    <tr v-if="data.expenses.length === 0">
                        <td colspan="2" class="px-4 py-4 text-center text-t3">لا توجد مصروفات</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-d/30 bg-dp/30 font-bold">
                        <td class="px-4 py-3 text-d">إجمالي المصروفات</td>
                        <td class="px-4 py-3 text-left font-mono text-d">{{ fmt(data.totalExpense) }} ج.م</td>
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
                :class="data.netIncome >= 0 ? 'text-s' : 'text-d'"
            >
                {{ fmt(data.netIncome) }} ج.م
            </p>
            <span
                class="mt-2 inline-block rounded-full px-4 py-1 text-sm font-semibold"
                :class="data.netIncome >= 0 ? 'bg-sp text-s' : 'bg-dp text-d'"
            >
                {{ data.netIncome >= 0 ? 'ربح' : 'خسارة' }}
            </span>
        </div>
    </div>
</template>
