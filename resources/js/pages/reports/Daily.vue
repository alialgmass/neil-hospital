<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { TrendingUp, Users, Wallet, BarChart3 } from 'lucide-vue-next';
import { ref } from 'vue';
import StatCard from '@/components/shared/StatCard.vue';

interface RevenueRow {
    dept?: string;
    doctor_name?: string;
    cases: number;
    revenue: number;
}

interface Treasury {
    total_in: number;
    total_out: number;
    balance: number;
}

const props = defineProps<{
    date: string;
    revenueByDept: RevenueRow[];
    revenueByDoc: RevenueRow[];
    treasury: Treasury;
}>();

const dateFilter = ref(props.date);

function applyDate() {
    router.get('/reports/daily', { date: dateFilter.value }, { preserveState: true });
}

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const totalRevenue = props.revenueByDept.reduce((s, r) => s + Number(r.revenue), 0);
const totalCases   = props.revenueByDept.reduce((s, r) => s + Number(r.cases), 0);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG');
}
</script>

<template>
    <Head title="التقرير اليومي" />

    <!-- Date Picker -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-xl font-bold text-hospital-text">التقرير اليومي</h2>
        <div class="flex items-center gap-2">
            <input
                v-model="dateFilter"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyDate"
            />
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label="إجمالي الإيرادات" :value="`${fmt(totalRevenue)} ج.م`" color="primary">
            <template #icon><TrendingUp class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="عدد الحالات" :value="totalCases.toString()" color="success">
            <template #icon><Users class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="إجمالي الوارد" :value="`${fmt(treasury.total_in)} ج.م`" color="success">
            <template #icon><Wallet class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="صافي حركة اليوم" :value="`${fmt(treasury.balance)} ج.م`" :color="treasury.balance >= 0 ? 'primary' : 'danger'">
            <template #icon><BarChart3 class="h-5 w-5" /></template>
        </StatCard>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <!-- Revenue by Department -->
        <div class="rounded-xl border border-hospital-border bg-white p-5 shadow-sm">
            <h3 class="mb-4 font-semibold text-hospital-text">الإيرادات حسب القسم</h3>
            <div v-if="revenueByDept.length === 0" class="py-8 text-center text-sm text-hospital-muted">
                لا توجد إيرادات في هذا اليوم
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-hospital-border text-right text-xs text-hospital-muted">
                        <th class="pb-2">القسم</th>
                        <th class="pb-2 text-center">الحالات</th>
                        <th class="pb-2 text-left">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border/50">
                    <tr v-for="row in revenueByDept" :key="row.dept" class="hover:bg-hospital-bg/50">
                        <td class="py-2 font-medium">{{ deptLabels[row.dept!] ?? row.dept }}</td>
                        <td class="py-2 text-center text-hospital-muted">{{ row.cases }}</td>
                        <td class="py-2 text-left font-mono text-hospital-success">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-hospital-border font-bold">
                        <td class="pt-2">الإجمالي</td>
                        <td class="pt-2 text-center">{{ totalCases }}</td>
                        <td class="pt-2 text-left font-mono text-hospital-primary">{{ fmt(totalRevenue) }} ج.م</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Revenue by Doctor -->
        <div class="rounded-xl border border-hospital-border bg-white p-5 shadow-sm">
            <h3 class="mb-4 font-semibold text-hospital-text">الإيرادات حسب الطبيب</h3>
            <div v-if="revenueByDoc.length === 0" class="py-8 text-center text-sm text-hospital-muted">
                لا توجد إيرادات في هذا اليوم
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-hospital-border text-right text-xs text-hospital-muted">
                        <th class="pb-2">الطبيب</th>
                        <th class="pb-2 text-center">الحالات</th>
                        <th class="pb-2 text-left">الإيراد</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border/50">
                    <tr v-for="row in revenueByDoc" :key="row.doctor_name" class="hover:bg-hospital-bg/50">
                        <td class="py-2 font-medium">{{ row.doctor_name }}</td>
                        <td class="py-2 text-center text-hospital-muted">{{ row.cases }}</td>
                        <td class="py-2 text-left font-mono text-hospital-success">{{ fmt(row.revenue) }} ج.م</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Treasury Summary -->
    <div class="mt-6 rounded-xl border border-hospital-border bg-white p-5 shadow-sm">
        <h3 class="mb-4 font-semibold text-hospital-text">ملخص حركة الخزنة لهذا اليوم</h3>
        <div class="grid grid-cols-3 gap-4 text-center">
            <div class="rounded-lg bg-hospital-success/10 p-4">
                <p class="text-xs text-hospital-muted">وارد اليوم</p>
                <p class="mt-1 text-lg font-bold text-hospital-success">{{ fmt(treasury.total_in) }} ج.م</p>
            </div>
            <div class="rounded-lg bg-hospital-danger/10 p-4">
                <p class="text-xs text-hospital-muted">صادر اليوم</p>
                <p class="mt-1 text-lg font-bold text-hospital-danger">{{ fmt(treasury.total_out) }} ج.م</p>
            </div>
            <div class="rounded-lg bg-hospital-primary/10 p-4">
                <p class="text-xs text-hospital-muted">صافي اليوم</p>
                <p class="mt-1 text-lg font-bold" :class="treasury.balance >= 0 ? 'text-hospital-primary' : 'text-hospital-danger'">
                    {{ fmt(treasury.balance) }} ج.م
                </p>
            </div>
        </div>
    </div>
</template>
