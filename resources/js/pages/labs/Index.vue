<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import SearchBar from '@/components/shared/SearchBar.vue';
import { usePermissions } from '@/composables/usePermissions';
import examinations from '@/routes/examinations';
import { weekdayDoctorFallback } from '@/utils/weekdayDoctor';

interface DiagnosticResult {
    id: string;
    test_name: string;
    eye?: string;
    result_text?: string;
    recorded_at: string;
}

interface Booking {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    time?: string;
    status: string;
    pay_status: string;
    doctor?: { name: string };
    diagnostic_results: DiagnosticResult[];
    medical_examination?: { id: string; status: 'draft' | 'finalized' } | null;
}

const props = defineProps<{
    queue: { data: Booking[]; current_page: number; last_page: number; total: number };
    date: string;
    filters: { search?: string };
}>();

const columns = [
    { key: 'file_no', label: 'رقم الملف',  sortable: true },
    { key: 'patient', label: 'المريض',     sortable: true },
    { key: 'doctor',  label: 'الطبيب' },
    { key: 'results', label: 'النتيجة' },
    { key: 'status',  label: 'الحالة' },
    { key: 'pay_status', label: 'السداد' },
];

const selectedDate = ref(props.date);
const search       = ref(props.filters.search ?? '');
const fallbackDoctor = computed(() => weekdayDoctorFallback(props.date));

function applyFilters() {
    router.get('/labs', { date: selectedDate.value, search: search.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/labs', { date: selectedDate.value, search: search.value || undefined, page }, { preserveState: true });
}

const { can } = usePermissions();
const canViewExamination = computed(() => can('examinations.view'));
const canPrintExamination = computed(() => can('examinations.print'));

function examinationLabel(booking: Booking): string {
    if (!booking.medical_examination) {
        return 'الفحص الطبي';
    }

    return booking.medical_examination.status === 'finalized' ? 'الفحص الطبي (معتمد)' : 'الفحص الطبي (مسودة)';
}

const totalToday     = computed(() => props.queue.total);
const completedToday = computed(() => props.queue.data.filter((b) => b.status === 'completed').length);
const revenueToday   = computed(() =>
    props.queue.data.filter((b) => b.pay_status === 'paid' || b.pay_status === 'partial')
        .reduce((s, b) => s + Number((b as { price?: number }).price ?? 0), 0),
);
</script>

<template>
    <Head title="قسم الفحوصات" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-teal-100 bg-teal-50 p-4">
            <p class="text-xs font-medium text-teal-600">حجوزات الفحوصات</p>
            <p class="text-2xl font-bold text-teal-700">{{ totalToday }}</p>
            <p class="text-xs text-teal-500">اليوم</p>
        </div>
        <div class="rounded-xl border border-green-100 bg-green-50 p-4">
            <p class="text-xs font-medium text-green-600">مكتمل</p>
            <p class="text-2xl font-bold text-green-700">{{ completedToday }}</p>
            <p class="text-xs text-green-500">{{ totalToday ? Math.round(completedToday / totalToday * 100) : 0 }}%</p>
        </div>
        <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
            <p class="text-xs font-medium text-orange-600">إيراد الفحوصات (ج)</p>
            <p class="text-2xl font-bold text-orange-700">{{ revenueToday.toLocaleString('en-US') }}</p>
            <p class="text-xs text-orange-500">↑ اليوم</p>
        </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">قسم الفحوصات التشخيصية</h2>
        <div class="flex flex-wrap items-center gap-2">
            <SearchBar v-model="search" placeholder="بحث بالاسم أو الملف..." @update:model-value="applyFilters" />
            <input
                v-model="selectedDate"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            />
        </div>
    </div>

    <DataTable :columns="columns" :rows="queue.data" :current-page="queue.current_page" :last-page="queue.last_page" :total="queue.total" empty-text="لا توجد حجوزات فحوصات لهذا اليوم" @page="goToPage">
        <template #cell-patient="{ row }">{{ (row as Booking).patient_name }}</template>
        <template #cell-doctor="{ row }">{{ (row as Booking).doctor?.name ?? fallbackDoctor ?? '—' }}</template>
        <template #cell-results="{ row }">
            <div
                v-if="(row as Booking).medical_examination || (row as Booking).diagnostic_results?.length"
                class="flex flex-wrap items-center gap-1"
            >
                <!-- The medical examination is the result of a labs visit -->
                <a
                    v-if="(row as Booking).medical_examination && canPrintExamination"
                    :href="examinations.print((row as Booking).medical_examination!.id).url"
                    target="_blank"
                    class="flex items-center gap-1 rounded bg-hospital-primary-pale px-1.5 py-0.5 text-[11px] font-semibold text-hospital-primary hover:bg-hospital-primary hover:text-white"
                    title="طباعة نتيجة الفحص"
                >
                    <Printer class="h-3 w-3" />
                    طباعة النتيجة
                </a>
                <!-- Results recorded before the medical examination replaced them -->
                <a
                    v-for="result in (row as Booking).diagnostic_results"
                    :key="result.id"
                    :href="`/labs/results/${result.id}/letter`"
                    target="_blank"
                    class="flex items-center gap-1 rounded bg-hospital-bg px-1.5 py-0.5 text-[11px] text-hospital-text-2 hover:bg-hospital-primary-pale hover:text-hospital-primary"
                    :title="`طباعة خطاب ${result.test_name}`"
                >
                    <Printer class="h-3 w-3" />
                    {{ result.test_name }}
                </a>
            </div>
            <span v-else class="text-xs text-hospital-text-3">—</span>
        </template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'confirmed' | 'in_progress' | 'completed' | 'waiting')" />
        </template>
        <template #cell-pay_status="{ value }">
            <Badge :variant="(value as 'paid' | 'partial' | 'unpaid')" />
        </template>
        <template #actions="{ row }">
            <a
                v-if="canViewExamination"
                :href="examinations.booking((row as Booking).id).url"
                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium hover:bg-hospital-primary-pale"
                :class="(row as Booking).medical_examination?.status === 'finalized' ? 'text-hospital-success' : 'text-hospital-primary'"
            >
                <ClipboardList class="h-3.5 w-3.5" />
                {{ examinationLabel(row as Booking) }}
            </a>
        </template>
    </DataTable>
</template>
