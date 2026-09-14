<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Activity, EyeOff, Package, Scissors, Search, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    file_no: string;
    patient_name: string;
    paid_amount: number;
    dept: string;
    procedure: string | null;
    eye: string | null;
    status: string;
    scheduled_at: string;
    supply_total: number;
    complications: string | null;
    surgeon_name: string | null;
}

const props = defineProps<{
    data: {
        rows: Row[];
        total_count: number;
        surgery_count: number;
        lasik_count: number;
        total_supplies: number;
        from: string;
        to: string;
    };
    filters: { from: string; to: string; dept?: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const dept = ref(props.filters.dept ?? '');

const page = usePage<{ moduleStatus?: Record<string, boolean> }>();
const moduleStatus = computed(() => (page.props.moduleStatus as Record<string, boolean>) ?? {});
const surgeryEnabled = computed(() => moduleStatus.value.surgery !== false);
const lasikEnabled = computed(() => moduleStatus.value.lasik !== false);

const statusLabels: Record<string, string> = {
    scheduled: 'مجدولة',
    in_progress: 'جارية',
    completed: 'مكتملة',
    cancelled: 'ملغاة',
};

const statusVariants: Record<string, 'active' | 'pending' | 'cancelled'> = {
    scheduled: 'pending',
    in_progress: 'pending',
    completed: 'active',
    cancelled: 'cancelled',
};

const eyeLabels: Record<string, string> = { right: 'يمين', left: 'يسار', both: 'كلتيهما' };

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['patient_name', 'file_no'],
    (r) => `${r.file_no}-${r.scheduled_at}`,
);

const totalCount = computed(() => visibleRows.value.length);
const surgeryCount = computed(() => visibleRows.value.filter((r) => r.dept === 'surgery').length);
const lasikCount = computed(() => visibleRows.value.filter((r) => r.dept === 'lasik').length);
const totalSupplies = computed(() => visibleRows.value.reduce((s, r) => s + Number(r.supply_total), 0));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

function search() {
    router.get('/reports/surgeries', { from: from.value, to: to.value, dept: dept.value || undefined }, { preserveState: true });
}
</script>

<template>
    <Head title="تقرير العمليات والجراحات" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير العمليات والجراحات</h1>
        <p class="mt-0.5 text-sm text-t3">
            {{ lasikEnabled && surgeryEnabled
                ? 'تفاصيل العمليات والليزك خلال الفترة'
                : (lasikEnabled ? 'تفاصيل الليزك خلال الفترة' : 'تفاصيل العمليات خلال الفترة') }}
        </p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Scissors class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي العمليات</p>
                <p class="text-xl font-bold text-t">{{ totalCount }}</p>
            </div>
        </div>
        <div v-if="surgeryEnabled" class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Activity class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">عمليات جراحية</p>
                <p class="text-xl font-bold text-t">{{ surgeryCount }}</p>
            </div>
        </div>
        <div v-if="lasikEnabled" class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <Scissors class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">ليزك</p>
                <p class="text-xl font-bold text-t">{{ lasikCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <Package class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">تكلفة المستلزمات</p>
                <p class="text-xl font-bold text-t">{{ fmt(totalSupplies) }}</p>
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
        <div class="flex flex-col gap-1">
            <label class="form-label">القسم</label>
            <select v-model="dept" class="input-field">
                <option value="">الكل (عمليات + ليزك)</option>
                <option v-if="surgeryEnabled" value="surgery">عمليات جراحية</option>
                <option v-if="lasikEnabled" value="lasik">ليزك</option>
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
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الإجراء</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">العين</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الجراح</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المستلزمات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="w-8 px-2 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="row in visibleRows" :key="`${row.file_no}-${row.scheduled_at}`" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-mono text-xs text-t2">{{ row.file_no }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ row.patient_name }}</td>
                    <td class="px-4 py-3">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="row.dept === 'lasik' ? 'bg-ap text-a' : 'bg-pp text-p'"
                        >
                            {{ row.dept === 'lasik' ? 'ليزك' : 'جراحة' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-t2">{{ row.procedure || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.eye ? (eyeLabels[row.eye] ?? row.eye) : '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.surgeon_name || '—' }}</td>
                    <td class="px-4 py-3">
                        <Badge :variant="statusVariants[row.status] ?? 'pending'">
                            {{ statusLabels[row.status] ?? row.status }}
                        </Badge>
                    </td>
                    <td class="px-4 py-3 font-mono" :class="row.supply_total > 0 ? 'text-w' : 'text-t3'">
                        {{ row.supply_total > 0 ? fmt(row.supply_total) + ' ج' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-t3">{{ row.scheduled_at?.split('T')[0] ?? '—' }}</td>
                    <td class="px-2 py-3">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="10">
                        {{ data.rows.length === 0 ? 'لا توجد عمليات في هذه الفترة' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
