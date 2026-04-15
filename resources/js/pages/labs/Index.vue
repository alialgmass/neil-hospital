<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';

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
}

const props = defineProps<{
    queue: { data: Booking[]; current_page: number; last_page: number; total: number };
    date: string;
    filters: { search?: string };
}>();

const columns = [
    { key: 'time',    label: 'الوقت' },
    { key: 'file_no', label: 'رقم الملف',  sortable: true },
    { key: 'patient', label: 'المريض',     sortable: true },
    { key: 'doctor',  label: 'الطبيب' },
    { key: 'results', label: 'الفحوصات' },
    { key: 'status',  label: 'الحالة' },
    { key: 'pay_status', label: 'السداد' },
];

const selectedDate = ref(props.date);
const search       = ref(props.filters.search ?? '');

function applyFilters() {
    router.get('/labs', { date: selectedDate.value, search: search.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/labs', { date: selectedDate.value, search: search.value || undefined, page }, { preserveState: true });
}

const showResult    = ref(false);
const resultBooking = ref<string>('');

const form = useForm({
    test_name:    '',
    eye:          '',
    result_text:  '',
    doctor_notes: '',
});

function openResult(bookingId: string) {
    resultBooking.value = bookingId;
    form.reset();
    showResult.value = true;
}

function submitResult() {
    form.post(`/labs/${resultBooking.value}/results`, {
        onSuccess: () => {
 showResult.value = false; 
},
    });
}

const labTests = [
    'OCT (مقطعية)', 'OCT عصب بصري', 'توبوغرافيا', 'أنجيوغرافيا',
    'سونار', 'مجال بصري', 'مقاس عدسة (A-Scan)', 'تصوير ملون', 'مقاس نظر أطفال',
];

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
            <p class="text-2xl font-bold text-orange-700">{{ revenueToday.toLocaleString('ar-EG') }}</p>
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
        <template #cell-time="{ value }">{{ (value as string)?.slice(0, 5) ?? '—' }}</template>
        <template #cell-patient="{ row }">{{ (row as Booking).patient_name }}</template>
        <template #cell-doctor="{ row }">{{ (row as Booking).doctor?.name ?? '—' }}</template>
        <template #cell-results="{ row }">
            <span class="text-xs text-hospital-text-2">
                {{ (row as Booking).diagnostic_results?.length ?? 0 }} فحص
            </span>
        </template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'confirmed' | 'in_progress' | 'completed' | 'waiting')" />
        </template>
        <template #cell-pay_status="{ value }">
            <Badge :variant="(value as 'paid' | 'partial' | 'unpaid')" />
        </template>
        <template #actions="{ row }">
            <button
                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary hover:bg-hospital-primary-pale"
                @click="openResult((row as Booking).id)"
            >
                <FlaskConical class="h-3.5 w-3.5" />
                تسجيل نتيجة
            </button>
        </template>
    </DataTable>

    <!-- Record Result Modal -->
    <Modal v-model="showResult" title="تسجيل نتيجة فحص" size="md">
        <form class="space-y-4" @submit.prevent="submitResult">
            <div>
                <label class="mb-1 block text-sm font-medium">نوع الفحص</label>
                <select v-model="form.test_name" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">— اختر الفحص —</option>
                    <option v-for="t in labTests" :key="t" :value="t">{{ t }}</option>
                </select>
                <p v-if="form.errors.test_name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.test_name }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">العين</label>
                <select v-model="form.eye" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">—</option>
                    <option value="OD">عين يمنى (OD)</option>
                    <option value="OS">عين يسرى (OS)</option>
                    <option value="OU">كلاهما (OU)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">نتيجة الفحص</label>
                <textarea v-model="form.result_text" rows="4" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">ملاحظات الطبيب</label>
                <textarea v-model="form.doctor_notes" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showResult = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">تسجيل النتيجة</button>
            </div>
        </form>
    </Modal>
</template>
