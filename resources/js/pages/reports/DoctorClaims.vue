<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download, Users, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';

interface Row {
    doctor_name: string;
    fee_type: string;
    cases: number;
    total_billed: number;
    ins_amount: number;
    net_billed: number;
    doctor_claim: number;
    center_share: number;
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string };
    filters: { from: string; to: string; doctorId?: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const totalClaim = props.data.rows.reduce((s, r) => s + Number(r.doctor_claim), 0);
const totalCases = props.data.rows.reduce((s, r) => s + Number(r.cases), 0);
const totalBilled = props.data.rows.reduce((s, r) => s + Number(r.total_billed), 0);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/doctor-claims', { from: from.value, to: to.value }, { preserveState: true });
}

function exportExcel() {
    window.location.href = `/reports/doctor-claims/export?from=${from.value}&to=${to.value}`;
}
</script>

<template>
    <Head title="مستحقات الأطباء" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">مستحقات الأطباء</h1>
        <p class="mt-0.5 text-sm text-t3">احتساب مستحقات كل طبيب بالتفصيل</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Users class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحالات</p>
                <p class="text-xl font-bold text-t">{{ totalCases }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <Wallet class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الفواتير</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalBilled) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Wallet class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المستحقات</p>
                <p class="text-xl font-bold text-s">{{ fmt(totalClaim) }}</p>
                <p class="text-xs text-t3">ج.م</p>
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
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجمالي الفواتير</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تأمين</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصافي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">مستحق الطبيب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">حصة المركز</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="(row, idx) in data.rows" :key="idx" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ row.doctor_name }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.cases }}</td>
                    <td class="px-4 py-3 font-mono text-t">{{ Number(row.total_billed).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-p">{{ Number(row.ins_amount).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-t2">{{ Number(row.net_billed).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono font-bold text-s">{{ Number(row.doctor_claim).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-t2">{{ Number(row.center_share).toFixed(2) }} ج</td>
                </tr>
                <tr v-if="data.rows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="7">لا توجد بيانات في هذه الفترة</td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-3 font-bold text-t" colspan="5">إجمالي المستحقات</td>
                    <td class="px-4 py-3 font-mono font-bold text-s">{{ fmt(totalClaim) }} ج</td>
                    <td />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
