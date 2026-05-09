<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { UmbrellaOff, CheckCircle, Clock, XCircle, CalendarDays } from 'lucide-vue-next';
import { ref } from 'vue';

interface LeaveRow {
    employee_name: string;
    employee_no: string;
    dept: string;
    type: string;
    from_date: string;
    to_date: string;
    days: number;
    status: string;
    reason: string | null;
}

interface Summary {
    total_days: number;
    approved: number;
    pending: number;
    rejected: number;
}

interface Employee {
    id: string;
    name: string;
    employee_no: string;
}

const props = defineProps<{
    data: { rows: LeaveRow[]; summary: Summary; from: string; to: string };
    filters: { from: string; to: string; employeeId: string | null; type: string | null };
    employees: Employee[];
}>();

const fromFilter = ref(props.filters.from);
const toFilter = ref(props.filters.to);
const employeeFilter = ref(props.filters.employeeId ?? '');
const typeFilter = ref(props.filters.type ?? '');

function apply() {
    router.get('/reports/hr-leaves', {
        from: fromFilter.value,
        to: toFilter.value,
        employee_id: employeeFilter.value || undefined,
        type: typeFilter.value || undefined,
    }, { preserveState: true });
}

const leaveTypeLabel: Record<string, string> = {
    annual:    'سنوية',
    sick:      'مرضية',
    unpaid:    'بدون راتب',
    emergency: 'طارئة',
    maternity: 'أمومة',
};

const leaveTypeClass: Record<string, string> = {
    annual:    'bg-pp text-p',
    sick:      'bg-dp/10 text-d',
    unpaid:    'bg-sf2 text-t3',
    emergency: 'bg-wp/10 text-w',
    maternity: 'bg-ap/10 text-a',
};

const statusLabel: Record<string, string> = {
    pending:  'قيد المراجعة',
    approved: 'معتمدة',
    rejected: 'مرفوضة',
};

const statusClass: Record<string, string> = {
    pending:  'bg-wp/10 text-w',
    approved: 'bg-sp/10 text-s',
    rejected: 'bg-dp/10 text-d',
};

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات',
    lasik: 'الليزك', laser: 'الليزر', reception: 'الاستقبال',
    admin: 'الإدارة', pharmacy: 'الصيدلية', hr: 'الموارد البشرية',
};
</script>

<template>
    <Head title="تقرير الإجازات" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الإجازات</h1>
        <p class="mt-0.5 text-sm text-t3">سجل إجازات الموظفين للفترة المحددة</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <CalendarDays class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الأيام</p>
                <p class="text-xl font-bold text-t">{{ data.summary.total_days }}</p>
                <p class="text-xs text-t3">يوم</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <CheckCircle class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">معتمدة</p>
                <p class="text-xl font-bold text-s">{{ data.summary.approved }}</p>
                <p class="text-xs text-t3">طلب</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <Clock class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">قيد المراجعة</p>
                <p class="text-xl font-bold text-w">{{ data.summary.pending }}</p>
                <p class="text-xs text-t3">طلب</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <XCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">مرفوضة</p>
                <p class="text-xl font-bold text-d">{{ data.summary.rejected }}</p>
                <p class="text-xs text-t3">طلب</p>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-br px-5 py-4">
            <div>
                <h3 class="font-semibold text-t">سجل الإجازات</h3>
                <p class="text-xs text-t3">{{ data.rows.length }} طلب إجازة</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <select v-model="employeeFilter" class="input-field w-auto">
                    <option value="">كل الموظفين</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }}
                    </option>
                </select>
                <select v-model="typeFilter" class="input-field w-auto">
                    <option value="">كل الأنواع</option>
                    <option v-for="(label, key) in leaveTypeLabel" :key="key" :value="key">{{ label }}</option>
                </select>
                <input v-model="fromFilter" type="date" class="input-field w-auto" />
                <input v-model="toFilter" type="date" class="input-field w-auto" />
                <button class="btn-primary" @click="apply">عرض</button>
            </div>
        </div>

        <div v-if="data.rows.length === 0" class="py-16 text-center text-sm text-t3">
            لا توجد إجازات في هذه الفترة
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br bg-sf2 text-right text-xs text-t3">
                        <th class="px-4 py-3 font-semibold">الموظف</th>
                        <th class="px-4 py-3 font-semibold">القسم</th>
                        <th class="px-4 py-3 text-center font-semibold">النوع</th>
                        <th class="px-4 py-3 font-semibold">من</th>
                        <th class="px-4 py-3 font-semibold">إلى</th>
                        <th class="px-4 py-3 text-center font-semibold">الأيام</th>
                        <th class="px-4 py-3 font-semibold">السبب</th>
                        <th class="px-4 py-3 text-center font-semibold">الحالة</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="(row, i) in data.rows" :key="i" class="hover:bg-sf2">
                        <td class="px-4 py-3">
                            <p class="font-medium text-t">{{ row.employee_name }}</p>
                            <p class="text-xs text-t3">{{ row.employee_no }}</p>
                        </td>
                        <td class="px-4 py-3 text-t2">{{ deptLabels[row.dept] ?? row.dept }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="leaveTypeClass[row.type]">
                                {{ leaveTypeLabel[row.type] ?? row.type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-t2">{{ row.from_date }}</td>
                        <td class="px-4 py-3 font-mono text-t2">{{ row.to_date }}</td>
                        <td class="px-4 py-3 text-center font-semibold text-t">{{ row.days }}</td>
                        <td class="px-4 py-3 text-t3">{{ row.reason ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[row.status]">
                                {{ statusLabel[row.status] ?? row.status }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
