<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ClipboardList } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface Surgery {
    id: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    surgeon: { id: string; name: string } | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
    scheduled_at: string | null;
}

interface Paginator {
    data: Surgery[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    surgeries: Paginator;
    availableBeds: never[];
    dept: string;
    filters: { status?: string };
}>();

const columns = [
    { key: 'scheduled_at', label: 'الموعد',   sortable: true },
    { key: 'file_no',      label: 'رقم الملف' },
    { key: 'patient',      label: 'المريض' },
    { key: 'procedure',    label: 'نوع الليزر' },
    { key: 'eye',          label: 'العين' },
    { key: 'surgeon',      label: 'الطبيب' },
    { key: 'status',       label: 'الحالة' },
];

const statusFilter = ref(props.filters.status ?? '');
function applyFilters() {
    router.get('/laser', { status: statusFilter.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/laser', { status: statusFilter.value || undefined, page }, { preserveState: true });
}

/* ── Schedule ── */
const showSchedule = ref(false);
const scheduleForm = useForm({
    booking_id:   '',
    dept:         'laser',
    surgeon_id:   '',
    eye:          '',
    procedure:    '',
    scheduled_at: '',
    pre_op_notes: '',
});
function submitSchedule() {
    scheduleForm.post('/laser', {
        onSuccess: () => {
 showSchedule.value = false; scheduleForm.reset(); 
},
    });
}

/* ── Report ── */
const showReport   = ref(false);
const reportTarget = ref('');
const reportForm   = useForm({ op_report: '', post_op_notes: '', complications: '' });
function openReport(id: string) {
 reportTarget.value = id; reportForm.reset(); showReport.value = true; 
}
function submitReport() {
    reportForm.post(`/laser/${reportTarget.value}/report`, { onSuccess: () => {
 showReport.value = false; 
} });
}

const laserProcedures = ['YAG Laser', 'ليزر شبكية', 'ليزر جلوكوما (SLT)', 'ليزر جلوكوما (ALT)', 'ليزر ملتحمة'];
const eyeLabel: Record<string, string> = { OD: 'عين يمنى', OS: 'عين يسرى', OU: 'كلاهما' };

const totalToday     = computed(() => props.surgeries.total);
const completedToday = computed(() => props.surgeries.data.filter((s) => s.status === 'completed').length);
</script>

<template>
    <Head title="قسم الليزر" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-green-100 bg-green-50 p-4">
            <p class="text-xs font-medium text-green-600">جلسات الليزر اليوم</p>
            <p class="text-2xl font-bold text-green-700">{{ totalToday }}</p>
            <p class="text-xs text-green-500">اليوم</p>
        </div>
        <div class="rounded-xl border border-teal-100 bg-teal-50 p-4">
            <p class="text-xs font-medium text-teal-600">مكتمل</p>
            <p class="text-2xl font-bold text-teal-700">{{ completedToday }}</p>
            <p class="text-xs text-teal-500">{{ totalToday ? Math.round(completedToday / totalToday * 100) : 0 }}%</p>
        </div>
        <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
            <p class="text-xs font-medium text-orange-600">إيراد الليزر (ج)</p>
            <p class="text-2xl font-bold text-orange-700">—</p>
            <p class="text-xs text-orange-500">↑ اليوم</p>
        </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">قسم الليزر العلاجي</h2>
        <div class="flex items-center gap-2">
            <select
                v-model="statusFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">جميع الحالات</option>
                <option value="scheduled">مجدولة</option>
                <option value="in_progress">جارية</option>
                <option value="completed">مكتملة</option>
                <option value="cancelled">ملغاة</option>
            </select>
            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
                @click="showSchedule = true"
            >
                <CalendarPlus class="h-4 w-4" />
                جدولة ليزر
            </button>
        </div>
    </div>

    <DataTable
        :columns="columns"
        :rows="surgeries.data"
        :current-page="surgeries.current_page"
        :last-page="surgeries.last_page"
        :total="surgeries.total"
        empty-text="لا توجد جلسات ليزر مسجلة"
        @page="goToPage"
    >
        <template #cell-scheduled_at="{ value }">
            {{ value ? (value as string).replace('T', ' ').slice(0, 16) : '—' }}
        </template>
        <template #cell-file_no="{ row }">{{ (row as Surgery).booking?.file_no ?? '—' }}</template>
        <template #cell-patient="{ row }">{{ (row as Surgery).booking?.patient_name ?? '—' }}</template>
        <template #cell-eye="{ value }">{{ value ? eyeLabel[value as string] ?? value : '—' }}</template>
        <template #cell-surgeon="{ row }">{{ (row as Surgery).surgeon?.name ?? '—' }}</template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'scheduled' | 'in_progress' | 'completed' | 'cancelled')" />
        </template>
        <template #actions="{ row }">
            <button
                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary hover:bg-hospital-primary-pale"
                @click="openReport((row as Surgery).id)"
            >
                <ClipboardList class="h-3.5 w-3.5" />
                تقرير
            </button>
        </template>
    </DataTable>

    <!-- Schedule Modal -->
    <Modal v-model="showSchedule" title="جدولة جلسة ليزر" size="md">
        <form class="space-y-4" @submit.prevent="submitSchedule">
            <div>
                <label class="mb-1 block text-sm font-medium">رقم الحجز</label>
                <input v-model="scheduleForm.booking_id" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="scheduleForm.errors.booking_id" class="mt-1 text-xs text-hospital-danger">{{ scheduleForm.errors.booking_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">نوع الليزر</label>
                    <select v-model="scheduleForm.procedure" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="">— اختر —</option>
                        <option v-for="p in laserProcedures" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">العين</label>
                    <select v-model="scheduleForm.eye" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="">—</option>
                        <option value="OD">عين يمنى (OD)</option>
                        <option value="OS">عين يسرى (OS)</option>
                        <option value="OU">كلاهما (OU)</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">موعد الجلسة</label>
                <input v-model="scheduleForm.scheduled_at" type="datetime-local" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showSchedule = false">إلغاء</button>
                <button type="submit" :disabled="scheduleForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">جدولة</button>
            </div>
        </form>
    </Modal>

    <!-- Report Modal -->
    <Modal v-model="showReport" title="تقرير جلسة الليزر" size="md">
        <form class="space-y-4" @submit.prevent="submitReport">
            <div>
                <label class="mb-1 block text-sm font-medium">تقرير الجلسة</label>
                <textarea v-model="reportForm.op_report" rows="4" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">ملاحظات ما بعد الجلسة</label>
                <textarea v-model="reportForm.post_op_notes" rows="3" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">مضاعفات</label>
                <textarea v-model="reportForm.complications" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showReport = false">إلغاء</button>
                <button type="submit" :disabled="reportForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">حفظ</button>
            </div>
        </form>
    </Modal>
</template>
