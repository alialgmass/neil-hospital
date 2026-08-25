<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarCheck, Clock, UserCheck, UserX, AlertTriangle, Timer, EyeOff, Search, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface AttendanceRow {
    employee_name: string;
    employee_no: string;
    dept: string;
    date: string;
    status: string;
    check_in: string | null;
    check_out: string | null;
    overtime_hours: number;
    shift_name: string | null;
}

interface Summary {
    present: number;
    absent: number;
    late: number;
    half_day: number;
    on_leave: number;
    overtime_hours: number;
}

interface Employee {
    id: string;
    name: string;
    employee_no: string;
}

const props = defineProps<{
    data: { rows: AttendanceRow[]; summary: Summary; from: string; to: string };
    filters: { from: string; to: string; employeeId: string | null };
    employees: Employee[];
}>();

const fromFilter = ref(props.filters.from);
const toFilter = ref(props.filters.to);
const employeeFilter = ref(props.filters.employeeId ?? '');

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['employee_name', 'employee_no'],
    (r) => `${r.employee_no}-${r.date}`,
);

function apply() {
    router.get('/reports/hr-attendance', {
        from: fromFilter.value,
        to: toFilter.value,
        employee_id: employeeFilter.value || undefined,
    }, { preserveState: true });
}

const statusLabel: Record<string, string> = {
    present:  'حاضر',
    absent:   'غائب',
    late:     'متأخر',
    half_day: 'نصف يوم',
    on_leave: 'إجازة',
};

const statusClass: Record<string, string> = {
    present:  'bg-sp/10 text-s',
    absent:   'bg-dp/10 text-d',
    late:     'bg-wp/10 text-w',
    half_day: 'bg-ap/10 text-a',
    on_leave: 'bg-pp text-p',
};

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات',
    lasik: 'الليزك', laser: 'الليزر', reception: 'الاستقبال',
    admin: 'الإدارة', pharmacy: 'الصيدلية', hr: 'الموارد البشرية',
};
</script>

<template>
    <Head title="تقرير الحضور والانصراف" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الحضور والانصراف</h1>
        <p class="mt-0.5 text-sm text-t3">سجل حضور وانصراف الموظفين للفترة المحددة</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sp">
                <UserCheck class="h-4 w-4 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">حاضر</p>
                <p class="text-lg font-bold text-s">{{ data.summary.present }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-dp">
                <UserX class="h-4 w-4 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">غائب</p>
                <p class="text-lg font-bold text-d">{{ data.summary.absent }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-wp">
                <AlertTriangle class="h-4 w-4 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">متأخر</p>
                <p class="text-lg font-bold text-w">{{ data.summary.late }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-ap">
                <Clock class="h-4 w-4 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">نصف يوم</p>
                <p class="text-lg font-bold text-a">{{ data.summary.half_day }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-pp">
                <CalendarCheck class="h-4 w-4 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجازة</p>
                <p class="text-lg font-bold text-p">{{ data.summary.on_leave }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Timer class="h-4 w-4 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">ساعات إضافية</p>
                <p class="text-lg font-bold text-s">{{ data.summary.overtime_hours }}</p>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-br px-5 py-4">
            <div>
                <h3 class="font-semibold text-t">سجل الحضور</h3>
                <p class="text-xs text-t3">{{ visibleRows.length }} سجل في الفترة المحددة</p>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <select v-model="employeeFilter" class="input-field w-auto">
                    <option value="">كل الموظفين</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }} ({{ emp.employee_no }})
                    </option>
                </select>
                <input v-model="fromFilter" type="date" class="input-field w-auto" />
                <input v-model="toFilter" type="date" class="input-field w-auto" />
                <button class="btn-primary" @click="apply">عرض</button>
            </div>
        </div>

        <!-- Row search + exclusion status -->
        <div class="flex flex-wrap items-center gap-3 border-b border-br px-5 py-3">
            <div class="relative">
                <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
                <input v-model="rowSearch" type="text" placeholder="ابحث باسم الموظف أو الرقم..." class="input-field h-9 w-64 pr-9" />
            </div>
            <button v-if="excludedCount > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-3 py-1.5 text-xs text-t2 hover:bg-sf2" @click="restoreAll">
                <EyeOff class="h-3.5 w-3.5" />
                {{ excludedCount }} صف مستبعد من العرض — إظهار الكل
            </button>
        </div>

        <div v-if="visibleRows.length === 0" class="py-16 text-center text-sm text-t3">
            {{ data.rows.length === 0 ? 'لا توجد سجلات حضور في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-br bg-sf2 text-right text-xs text-t3">
                        <th class="px-4 py-3 font-semibold">الموظف</th>
                        <th class="px-4 py-3 font-semibold">القسم</th>
                        <th class="px-4 py-3 font-semibold">التاريخ</th>
                        <th class="px-4 py-3 text-center font-semibold">الوردية</th>
                        <th class="px-4 py-3 text-center font-semibold">الحالة</th>
                        <th class="px-4 py-3 text-center font-semibold">الدخول</th>
                        <th class="px-4 py-3 text-center font-semibold">الخروج</th>
                        <th class="px-4 py-3 text-center font-semibold">إضافي</th>
                        <th class="w-8 px-2 py-3 print:hidden" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in visibleRows" :key="`${row.employee_no}-${row.date}`" class="hover:bg-sf2">
                        <td class="px-4 py-3">
                            <p class="font-medium text-t">{{ row.employee_name }}</p>
                            <p class="text-xs text-t3">{{ row.employee_no }}</p>
                        </td>
                        <td class="px-4 py-3 text-t2">{{ deptLabels[row.dept] ?? row.dept }}</td>
                        <td class="px-4 py-3 font-mono text-t2">{{ row.date }}</td>
                        <td class="px-4 py-3 text-center text-t3">{{ row.shift_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass[row.status]">
                                {{ statusLabel[row.status] ?? row.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center font-mono text-t2">{{ row.check_in ?? '—' }}</td>
                        <td class="px-4 py-3 text-center font-mono text-t2">{{ row.check_out ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            <span v-if="row.overtime_hours > 0" class="font-semibold text-s">{{ row.overtime_hours }}س</span>
                            <span v-else class="text-t3">—</span>
                        </td>
                        <td class="px-2 py-3 print:hidden">
                            <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
