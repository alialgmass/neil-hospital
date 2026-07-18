<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ClipboardList } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
    bookings: { id: string; file_no: string; patient_name: string }[];
    doctors: { id: string; name: string }[];
    dept: string;
    filters: { status?: string };
}>();

const columns = [
    { key: 'scheduled_at', label: 'الموعد',     sortable: true },
    { key: 'file_no',      label: 'رقم الملف' },
    { key: 'patient',      label: 'المريض' },
    { key: 'procedure',    label: 'نوع الليزر' },
    { key: 'eye',          label: 'العين' },
    { key: 'surgeon',      label: 'الطبيب' },
    { key: 'status',       label: 'الحالة' },
];

const statusFilter = ref(props.filters.status ?? '');

let filterTimeout: ReturnType<typeof setTimeout> | null = null;
watch(statusFilter, () => {
    if (filterTimeout) {
clearTimeout(filterTimeout);
}

    filterTimeout = setTimeout(() => {
        applyFilters();
    }, 300);
});

function applyFilters() {
    router.get('/laser', { status: statusFilter.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/laser', { status: statusFilter.value || undefined, page }, { preserveState: true });
}

const totalToday     = computed(() => props.surgeries.total);
const completedToday = computed(() => props.surgeries.data.filter((s) => s.status === 'completed').length);
const completedPct   = computed(() => totalToday.value ? Math.round(completedToday.value / totalToday.value * 100) : 0);

const eyeLabel: Record<string, string> = { OD: 'عين يمنى', OS: 'عين يسرى', OU: 'كلاهما' };

/* ── Schedule ── */
const showSchedule = ref(false);
const scheduleForm = useForm({
    booking_id:   '',
    dept:         'laser',
    surgeon_id:   '',
    eye:          '' as string,
    procedure:    '',
    scheduled_at: '',
    pre_op_notes: '',
});
function submitSchedule() {
    scheduleForm.post('/laser', {
        onSuccess: () => {
            showSchedule.value = false;
            scheduleForm.reset();
            toast.success('تم جدولة جلسة الليزر بنجاح');
        },
    });
}

/* ── Report ── */
const showReport   = ref(false);
const reportTarget = ref('');
const reportForm   = useForm({ op_report: '', post_op_notes: '', complications: '' });
function openReport(id: string) {
    reportTarget.value = id;
    reportForm.reset();
    showReport.value = true;
}
function submitReport() {
    reportForm.post(`/laser/${reportTarget.value}/report`, {
        onSuccess: () => {
            showReport.value = false;
            toast.success('تم حفظ التقرير بنجاح');
        },
    });
}

const laserProcedures = ['YAG Laser', 'ليزر شبكية', 'ليزر جلوكوما (SLT)', 'ليزر جلوكوما (ALT)', 'ليزر ملتحمة'];
</script>

<template>
    <Head title="قسم الليزر" />

    <!-- ── Stats row ── -->
    <div class="mb-4 grid grid-cols-3 gap-3">
        <div class="stat-card border-r-[#1A8C5B]">
            <p class="stat-lbl">جلسات الليزر اليوم</p>
            <p class="stat-val">{{ totalToday }}</p>
            <p class="stat-sub">اليوم</p>
        </div>
        <div class="stat-card border-r-hospital-accent">
            <p class="stat-lbl">مكتمل</p>
            <p class="stat-val">{{ completedToday }}</p>
            <p class="stat-sub">{{ completedPct }}%</p>
        </div>
        <div class="stat-card border-r-[#E07C10]">
            <p class="stat-lbl">إيراد الليزر (ج)</p>
            <p class="stat-val">—</p>
            <p class="stat-sub">اليوم</p>
        </div>
    </div>

    <!-- ── Main card ── -->
    <div class="dept-card">
        <div class="dept-card-hd">
            <div>
                <p class="dept-card-title">حجوزات قسم الليزر التشخيصي / العلاجي</p>
            </div>
            <div class="flex items-center gap-2">
                <select
                    v-model="statusFilter"
                    class="rounded-lg border border-hospital-border bg-white px-2 py-1.5 text-xs text-hospital-text focus:border-[#1A8C5B] focus:outline-none"
                    @change="applyFilters"
                >
                    <option value="">جميع الحالات</option>
                    <option value="scheduled">مجدولة</option>
                    <option value="in_progress">جارية</option>
                    <option value="completed">مكتملة</option>
                    <option value="cancelled">ملغاة</option>
                </select>
                <button
                    class="flex items-center gap-1.5 rounded-lg bg-[#1A8C5B] px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-[#167a4e]"
                    @click="showSchedule = true"
                >
                    <CalendarPlus class="h-3.5 w-3.5" />
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
                    class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-[#1A8C5B] transition-colors hover:bg-green-50"
                    @click="openReport((row as Surgery).id)"
                >
                    <ClipboardList class="h-3.5 w-3.5" />
                    تقرير
                </button>
            </template>
        </DataTable>
    </div>

    <!-- ── Schedule Modal ── -->
    <Modal v-model="showSchedule" title="جدولة جلسة ليزر" size="md">
        <form class="space-y-4" @submit.prevent="submitSchedule">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">رقم الحجز</label>
                    <select v-model="scheduleForm.booking_id" class="dept-input">
                        <option value="">-- اختر الحجز --</option>
                        <option v-for="b in props.bookings" :key="b.id" :value="b.id">
                            {{ b.file_no }} — {{ b.patient_name }}
                        </option>
                    </select>
                    <p v-if="scheduleForm.errors.booking_id" class="mt-1 text-xs text-hospital-danger">{{ scheduleForm.errors.booking_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الطبيب</label>
                    <select v-model="scheduleForm.surgeon_id" class="dept-input">
                        <option value="">— اختر الطبيب —</option>
                        <option v-for="doc in doctors" :key="doc.id" :value="doc.id">{{ doc.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نوع الليزر</label>
                    <select v-model="scheduleForm.procedure" class="dept-input">
                        <option value="">— اختر —</option>
                        <option v-for="p in laserProcedures" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">العين</label>
                    <select v-model="scheduleForm.eye" class="dept-input">
                        <option value="">—</option>
                        <option value="OD">عين يمنى (OD)</option>
                        <option value="OS">عين يسرى (OS)</option>
                        <option value="OU">كلاهما (OU)</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">موعد الجلسة</label>
                    <input v-model="scheduleForm.scheduled_at" type="datetime-local" class="dept-input" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">ملاحظات ما قبل الجلسة</label>
                    <textarea v-model="scheduleForm.pre_op_notes" rows="3" class="dept-input" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showSchedule = false">إلغاء</button>
                <button type="submit" :disabled="scheduleForm.processing" class="rounded-lg bg-[#1A8C5B] px-4 py-2 text-sm font-medium text-white disabled:opacity-60">جدولة</button>
            </div>
        </form>
    </Modal>

    <!-- ── Report Modal ── -->
    <Modal v-model="showReport" title="تقرير جلسة الليزر" size="md">
        <form class="space-y-4" @submit.prevent="submitReport">
            <div>
                <label class="mb-1 block text-sm font-medium">تقرير الجلسة</label>
                <textarea v-model="reportForm.op_report" rows="4" class="dept-input" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">ملاحظات ما بعد الجلسة</label>
                <textarea v-model="reportForm.post_op_notes" rows="3" class="dept-input" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">مضاعفات</label>
                <textarea v-model="reportForm.complications" rows="2" class="dept-input" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showReport = false">إلغاء</button>
                <button type="submit" :disabled="reportForm.processing" class="rounded-lg bg-[#1A8C5B] px-4 py-2 text-sm font-medium text-white disabled:opacity-60">حفظ</button>
            </div>
        </form>
    </Modal>
</template>

<style scoped>
.stat-card {
    background: var(--color-hospital-surface, #fff);
    border: 1px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 10px;
    border-right-width: 4px;
    padding: 12px 14px;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.stat-lbl { font-size: 10px; font-weight: 600; color: var(--color-hospital-text-3, #8A96AE); margin-bottom: 4px; }
.stat-val { font-size: 22px; font-weight: 800; color: var(--color-hospital-text, #0D1F3C); line-height: 1; margin-bottom: 2px; }
.stat-sub { font-size: 10px; color: var(--color-hospital-text-3, #8A96AE); }

.dept-card {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 10px;
    box-shadow: 0 1px 6px rgba(0,0,0,.06);
    overflow: hidden;
}
.dept-card-hd {
    padding: 11px 15px;
    border-bottom: 1px solid var(--color-hospital-border, #DDE4EF);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--color-hospital-bg, #F3F6FA);
}
.dept-card-title { font-size: 13px; font-weight: 700; color: var(--color-hospital-text, #0D1F3C); }

.dept-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid var(--color-hospital-border, #DDE4EF);
    border-radius: 7px;
    font-size: 13px;
    font-family: inherit;
    color: var(--color-hospital-text, #0D1F3C);
    background: #fff;
    direction: rtl;
}
.dept-input:focus {
    outline: none;
    border-color: #1A8C5B;
    box-shadow: 0 0 0 3px rgba(26,140,91,.1);
}

:deep(.dept-card > div > table),
:deep(.dept-card > div > div > table) { border-radius: 0; border: none; box-shadow: none; }
</style>
