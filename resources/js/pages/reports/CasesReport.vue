<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Clock, EyeOff, FileText, Search, Users, Wallet, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    file_no: string;
    patient_name: string;
    dept: string;
    service: string;
    doctor_name: string;
    price: number;
    pay_status: string;
    status: string;
    visit_date: string;
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string; dept?: string };
    filters: { from: string; to: string; dept?: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const dept = ref(props.filters.dept ?? '');

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const page = usePage<{ moduleStatus?: Record<string, boolean> }>();
const visibleDeptLabels = computed(() => {
    const moduleStatus = (page.props.moduleStatus as Record<string, boolean>) ?? {};

    return Object.fromEntries(
        Object.entries(deptLabels).filter(([key]) => moduleStatus[key] !== false),
    );
});

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['patient_name', 'file_no'],
    (r) => r.file_no,
);

const totalRevenue = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.price), 0));
const paidCount = computed(() => visibleRows.value.filter((r) => r.pay_status === 'paid').length);
const pendingCount = computed(() => visibleRows.value.filter((r) => r.pay_status !== 'paid').length);

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/cases', { from: from.value, to: to.value, dept: dept.value || undefined }, { preserveState: true });
}
</script>

<template>
    <Head title="تقرير الحالات" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الحالات</h1>
        <p class="mt-0.5 text-sm text-t3">قائمة كل الحالات خلال الفترة المحددة</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <FileText class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الحالات</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Wallet class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الإيرادات</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalRevenue) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Users class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">مسدد</p>
                <p class="text-xl font-bold text-s">{{ paidCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <Clock class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">غير مسدد</p>
                <p class="text-xl font-bold text-w">{{ pendingCount }}</p>
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
        <div class="flex flex-col gap-1">
            <label class="form-label">القسم</label>
            <select v-model="dept" class="input-field">
                <option value="">كل الأقسام</option>
                <option v-for="(label, key) in visibleDeptLabels" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>
        <button class="btn-primary self-end" @click="search">بحث</button>
    </div>

    <!-- Row search + exclusion status -->
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <div class="relative">
            <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
            <input
                v-model="rowSearch"
                type="text"
                placeholder="ابحث بالاسم أو رقم الملف..."
                class="input-field h-9 w-64 pr-9"
            />
        </div>
        <button v-if="excludedCount > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-3 py-1.5 text-xs text-t2 hover:bg-sf2" @click="restoreAll">
            <EyeOff class="h-3.5 w-3.5" />
            {{ excludedCount }} صف مستبعد من العرض — إظهار الكل
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">رقم الملف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المريض</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القسم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الخدمة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الطبيب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">السعر</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الدفع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="w-8 px-2 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="row.file_no" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-mono text-xs text-t2">{{ row.file_no }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ row.patient_name }}</td>
                    <td class="px-4 py-3 text-t2">{{ deptLabels[row.dept] || row.dept }}</td>
                    <td class="px-4 py-3 text-t3">{{ row.service }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.doctor_name || '—' }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-t">{{ Number(row.price).toFixed(2) }} ج</td>
                    <td class="px-4 py-3">
                        <Badge :variant="row.pay_status === 'paid' ? 'active' : row.pay_status === 'partial' ? 'pending' : 'cancelled'">
                            {{ row.pay_status === 'paid' ? 'مسدد' : row.pay_status === 'partial' ? 'جزئي' : 'غير مسدد' }}
                        </Badge>
                    </td>
                    <td class="px-4 py-3 text-t3">{{ row.visit_date }}</td>
                    <td class="px-2 py-3">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="9">
                        {{ data.rows.length === 0 ? 'لا توجد حالات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
