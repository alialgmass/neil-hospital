<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';

interface Row {
    doctor_id: string;
    doctor_name: string;
    amount: number;
    method: string;
    period_from: string;
    period_to: string;
    paid_at: string;
    paid_by_name: string;
    notes?: string;
}

const props = defineProps<{
    data: { rows: Row[]; total: number; from: string; to: string };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const methodLabels: Record<string, string> = {
    cash: 'كاش',
    transfer: 'تحويل بنكي',
};

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/doctor-payments', { from: from.value, to: to.value }, { preserveState: true });
}

function exportExcel() {
    window.location.href = `/reports/doctor-payments/export?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head title="مدفوعات الأطباء" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">مدفوعات الأطباء</h1>
        <p class="mt-0.5 text-sm text-t3">سجل الدفعات المصروفة للأطباء</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Wallet class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المدفوعات</p>
                <p class="text-xl font-bold text-s">{{ fmt(data.total) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Wallet class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد الدفعات</p>
                <p class="text-xl font-bold text-t">{{ data.rows.length }}</p>
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

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الطبيب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المبلغ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">طريقة الدفع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">فترة الاستحقاق</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تاريخ الدفع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">بواسطة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">ملاحظات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="(row, idx) in data.rows" :key="row.doctor_id + idx" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ row.doctor_name }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-s">{{ Number(row.amount).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 text-t2">{{ methodLabels[row.method] ?? row.method }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.period_from }} — {{ row.period_to }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.paid_at }}</td>
                    <td class="px-4 py-3 text-t3">{{ row.paid_by_name || '—' }}</td>
                    <td class="px-4 py-3 text-t3">{{ row.notes || '—' }}</td>
                </tr>
                <tr v-if="data.rows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="7">لا توجد مدفوعات في هذه الفترة</td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-3 font-bold text-t">الإجمالي</td>
                    <td class="px-4 py-3 font-mono font-bold text-s">{{ fmt(data.total) }} ج</td>
                    <td colspan="5" />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
