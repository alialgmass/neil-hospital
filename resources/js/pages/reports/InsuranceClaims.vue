<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Building2, TrendingUp, Users, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Row {
    company_name?: string;
    cases: number;
    total_billed: number;
    ins_amount: number;
    patient_amount: number;
}

interface StatusBreakdownRow {
    status: string;
    label: string;
    count: number;
    total: number;
}

const props = defineProps<{
    data: { rows: Row[]; statusBreakdown: StatusBreakdownRow[]; from: string; to: string };
    filters: { from: string; to: string };
}>();

const statusClasses: Record<string, string> = {
    draft: 'bg-sf2 text-t2',
    submitted: 'bg-pp text-p',
    approved: 'bg-sp/10 text-s',
    rejected: 'bg-dp/10 text-d',
    paid: 'bg-ap/10 text-a',
};

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const totalIns = computed(() => props.data.rows.reduce((s, r) => s + Number(r.ins_amount), 0));
const totalPatient = computed(() => props.data.rows.reduce((s, r) => s + Number(r.patient_amount), 0));
const totalCases = computed(() => props.data.rows.reduce((s, r) => s + Number(r.cases), 0));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/insurance', { from: from.value, to: to.value }, { preserveState: true });
}
</script>

<template>
    <Head title="تقرير التأمين" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير مطالبات التأمين</h1>
        <p class="mt-0.5 text-sm text-t3">مستحقات ومطالبات شركات التأمين</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Building2 class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">شركات التأمين</p>
                <p class="text-xl font-bold text-t">{{ data.rows.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Users class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحالات</p>
                <p class="text-xl font-bold text-t">{{ totalCases }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Wallet class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">مطالبات التأمين</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalIns) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <TrendingUp class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">تحمّل المرضى</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalPatient) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
    </div>

    <!-- Claim Status Breakdown -->
    <div class="mb-5 overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="border-b border-br px-5 py-3">
            <h3 class="font-semibold text-t">حالة المطالبات</h3>
            <p class="text-xs text-t3">عدد وقيمة المطالبات في نظام تتبع المطالبات، حسب الحالة</p>
        </div>
        <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-5">
            <div v-for="s in data.statusBreakdown" :key="s.status" class="rounded-lg p-3" :class="statusClasses[s.status]">
                <p class="text-xs font-medium opacity-80">{{ s.label }}</p>
                <p class="mt-1 text-lg font-bold">{{ s.count }}</p>
                <p class="font-mono text-xs opacity-80">{{ fmt(s.total) }} ج</p>
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
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">شركة التأمين</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجمالي الفواتير</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">مطالبة التأمين</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تحمّل المريض</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="(row, idx) in data.rows" :key="idx" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-medium text-t">{{ row.company_name || 'غير محدد' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.cases }}</td>
                    <td class="px-4 py-3 font-mono text-t">{{ Number(row.total_billed).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono font-medium text-p">{{ Number(row.ins_amount).toFixed(2) }} ج</td>
                    <td class="px-4 py-3 font-mono text-w">{{ Number(row.patient_amount).toFixed(2) }} ج</td>
                </tr>
                <tr v-if="data.rows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="5">لا توجد بيانات في هذه الفترة</td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-3 font-bold text-t" colspan="3">إجمالي مطالبات التأمين</td>
                    <td class="px-4 py-3 font-mono font-bold text-p">{{ fmt(totalIns) }} ج</td>
                    <td />
                </tr>
            </tfoot>
        </table>
    </div>
</template>
