<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ClipboardList, Grid, List, Package } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface OrBed {
    id: number;
    label: string;
}

interface Doctor {
    id: string;
    name: string;
}

interface Surgery {
    id: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    anaesthesia: string | null;
    surgeon: Doctor | null;
    or_bed: { id: number; bed_number: string; room: { name: string } } | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
    scheduled_at: string | null;
    supply_total: number;
}

interface Paginator {
    data: Surgery[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    surgeries: Paginator;
    availableBeds: OrBed[];
    dept: string;
    filters: { status?: string };
}>();

const columns = [
    { key: 'scheduled_at', label: 'الموعد',      sortable: true },
    { key: 'file_no',      label: 'رقم الملف' },
    { key: 'patient',      label: 'المريض' },
    { key: 'procedure',    label: 'الإجراء' },
    { key: 'eye',          label: 'العين' },
    { key: 'surgeon',      label: 'الطبيب' },
    { key: 'or_bed',       label: 'السرير' },
    { key: 'status',       label: 'الحالة' },
    { key: 'supply_total', label: 'تكلفة المستلزمات' },
];

/* ── Filters ── */
const statusFilter = ref(props.filters.status ?? '');
function applyFilters() {
    router.get('/surgery', { status: statusFilter.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/surgery', { status: statusFilter.value || undefined, page }, { preserveState: true });
}

/* ── Schedule Modal ── */
const showSchedule = ref(false);
const scheduleForm = useForm({
    booking_id:   '',
    dept:         'surgery',
    or_bed_id:    '' as string | number,
    surgeon_id:   '',
    eye:          '',
    procedure:    '',
    anaesthesia:  '',
    pre_op_notes: '',
    scheduled_at: '',
});

function submitSchedule() {
    scheduleForm.post('/surgery', {
        onSuccess: () => {
            showSchedule.value = false;
            scheduleForm.reset();
        },
    });
}

/* ── Report Modal ── */
const showReport   = ref(false);
const reportTarget = ref<string>('');
const reportForm   = useForm({
    op_report:      '',
    post_op_notes:  '',
    complications:  '',
});

function openReport(id: string) {
    reportTarget.value = id;
    reportForm.reset();
    showReport.value = true;
}

function submitReport() {
    reportForm.post(`/surgery/${reportTarget.value}/report`, {
        onSuccess: () => {
            showReport.value = false;
        },
    });
}

/* ── Supplies Modal ── */
const showSupplies   = ref(false);
const suppliesTarget = ref<string>('');

interface SupplyItem { name: string; qty: number; unit_cost: number }
const supplyItems  = ref<SupplyItem[]>([{ name: '', qty: 1, unit_cost: 0 }]);

const suppliesTotal = computed(() =>
    supplyItems.value.reduce((sum, i) => sum + i.qty * i.unit_cost, 0),
);

function addSupplyRow() {
    supplyItems.value.push({ name: '', qty: 1, unit_cost: 0 });
}
function removeSupplyRow(idx: number) {
    supplyItems.value.splice(idx, 1);
}

function openSupplies(id: string) {
    suppliesTarget.value = id;
    supplyItems.value    = [{ name: '', qty: 1, unit_cost: 0 }];
    showSupplies.value   = true;
}

function submitSupplies() {
    router.post(`/surgery/${suppliesTarget.value}/supplies`, {
        surgery_id: suppliesTarget.value,
        items:      supplyItems.value,
    }, {
        onSuccess: () => {
 showSupplies.value = false; 
},
    });
}

const eyeLabel: Record<string, string> = { OD: 'عين يمنى', OS: 'عين يسرى', OU: 'كلاهما' };

const viewMode = ref<'table' | 'grid'>('table');

const statusColors: Record<string, string> = {
    scheduled:   'border-l-4 border-l-green-500 bg-green-50',
    prep:        'border-l-4 border-l-yellow-500 bg-yellow-50',
    in_progress: 'border-l-4 border-l-red-500 bg-red-50',
    completed:   'border-l-4 border-l-gray-400 bg-gray-50',
    cancelled:   'border-l-4 border-l-gray-300 bg-gray-50 opacity-60',
};

const statusAr: Record<string, string> = {
    scheduled: 'مجدولة', prep: 'تحضير', in_progress: 'جارية', completed: 'مكتملة', cancelled: 'ملغاة',
};
</script>

<template>
    <Head title="قسم العمليات" />

    <!-- Header -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">غرف الإقامة — قسم العمليات</h2>
            <!-- Status legend -->
            <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-hospital-text-2">
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-green-500"></span>مجدولة</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-red-500"></span>جارية</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-yellow-500"></span>تحضير</span>
                <span class="flex items-center gap-1"><span class="inline-block h-3 w-3 rounded bg-gray-400"></span>فارغ</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- View toggle -->
            <div class="flex items-center gap-1 rounded-lg border border-hospital-border bg-white p-1">
                <button
                    class="rounded p-1.5 transition-colors"
                    :class="viewMode === 'grid' ? 'bg-hospital-primary text-white' : 'text-gray-400 hover:text-gray-600'"
                    @click="viewMode = 'grid'"
                >
                    <Grid class="h-4 w-4" />
                </button>
                <button
                    class="rounded p-1.5 transition-colors"
                    :class="viewMode === 'table' ? 'bg-hospital-primary text-white' : 'text-gray-400 hover:text-gray-600'"
                    @click="viewMode = 'table'"
                >
                    <List class="h-4 w-4" />
                </button>
            </div>
            <select
                v-model="statusFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">جميع الحالات</option>
                <option value="scheduled">مجدولة</option>
                <option value="prep">تحضير</option>
                <option value="in_progress">جارية</option>
                <option value="completed">مكتملة</option>
                <option value="cancelled">ملغاة</option>
            </select>
            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
                @click="showSchedule = true"
            >
                <CalendarPlus class="h-4 w-4" />
                جدولة عملية
            </button>
        </div>
    </div>

    <!-- Grid View -->
    <div v-if="viewMode === 'grid'" class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <div
            v-for="surgery in surgeries.data"
            :key="surgery.id"
            class="cursor-pointer rounded-xl border bg-white p-3 shadow-sm transition-shadow hover:shadow-md"
            :class="statusColors[surgery.status] ?? 'border-gray-200'"
        >
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wide text-gray-500">
                    {{ surgery.or_bed ? `${surgery.or_bed.room.name} — ${surgery.or_bed.bed_number}` : 'بدون سرير' }}
                </span>
                <span class="rounded-full px-1.5 py-0.5 text-xs font-medium" :class="{
                    'bg-green-100 text-green-700': surgery.status === 'scheduled',
                    'bg-red-100 text-red-700': surgery.status === 'in_progress',
                    'bg-yellow-100 text-yellow-700': surgery.status === 'prep',
                    'bg-gray-100 text-gray-600': surgery.status === 'completed' || surgery.status === 'cancelled',
                }">{{ statusAr[surgery.status] }}</span>
            </div>
            <p class="truncate text-sm font-semibold text-hospital-text">{{ surgery.booking?.patient_name ?? '—' }}</p>
            <p class="mt-0.5 truncate text-xs text-hospital-text-2">{{ surgery.procedure }}</p>
            <p class="mt-1 text-xs text-hospital-muted">{{ surgery.surgeon?.name ?? 'طبيب غير محدد' }}</p>
            <div class="mt-2 flex gap-1">
                <button class="flex-1 rounded bg-hospital-primary/10 py-1 text-xs font-medium text-hospital-primary hover:bg-hospital-primary/20" @click.stop="openReport(surgery.id)">تقرير</button>
                <button class="flex-1 rounded bg-hospital-accent/10 py-1 text-xs font-medium text-hospital-accent hover:bg-hospital-accent/20" @click.stop="openSupplies(surgery.id)">مستلزمات</button>
            </div>
        </div>
        <div v-if="surgeries.data.length === 0" class="col-span-full py-12 text-center text-hospital-muted">
            لا توجد عمليات مجدولة
        </div>
    </div>

    <!-- Table View -->
    <DataTable v-else
        :columns="columns"
        :rows="surgeries.data"
        :current-page="surgeries.current_page"
        :last-page="surgeries.last_page"
        :total="surgeries.total"
        empty-text="لا توجد عمليات مسجلة"
        @page="goToPage"
    >
        <template #cell-scheduled_at="{ value }">
            {{ value ? (value as string).replace('T', ' ').slice(0, 16) : '—' }}
        </template>
        <template #cell-file_no="{ row }">
            {{ (row as Surgery).booking?.file_no ?? '—' }}
        </template>
        <template #cell-patient="{ row }">
            {{ (row as Surgery).booking?.patient_name ?? '—' }}
        </template>
        <template #cell-eye="{ value }">
            {{ value ? eyeLabel[value as string] ?? value : '—' }}
        </template>
        <template #cell-surgeon="{ row }">
            {{ (row as Surgery).surgeon?.name ?? '—' }}
        </template>
        <template #cell-or_bed="{ row }">
            <span v-if="(row as Surgery).or_bed">
                {{ (row as Surgery).or_bed!.room.name }} — {{ (row as Surgery).or_bed!.bed_number }}
            </span>
            <span v-else class="text-hospital-text-2">—</span>
        </template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled')" />
        </template>
        <template #cell-supply_total="{ value }">
            <span class="font-mono text-sm">{{ Number(value).toLocaleString('ar-EG') }} ج.م</span>
        </template>
        <template #actions="{ row }">
            <div class="flex items-center gap-1">
                <button
                    class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary hover:bg-hospital-primary-pale transition-colors"
                    @click="openReport((row as Surgery).id)"
                >
                    <ClipboardList class="h-3.5 w-3.5" />
                    تقرير
                </button>
                <button
                    class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-accent hover:bg-hospital-accent/10 transition-colors"
                    @click="openSupplies((row as Surgery).id)"
                >
                    <Package class="h-3.5 w-3.5" />
                    مستلزمات
                </button>
            </div>
        </template>
    </DataTable>

    <!-- Schedule Modal -->
    <Modal v-model="showSchedule" title="جدولة عملية جراحية" size="lg">
        <form class="space-y-4" @submit.prevent="submitSchedule">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-hospital-text">رقم الحجز</label>
                    <input
                        v-model="scheduleForm.booking_id"
                        type="text"
                        placeholder="معرّف الحجز"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                    <p v-if="scheduleForm.errors.booking_id" class="mt-1 text-xs text-hospital-danger">{{ scheduleForm.errors.booking_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hospital-text">السرير</label>
                    <select
                        v-model="scheduleForm.or_bed_id"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    >
                        <option value="">— اختر السرير —</option>
                        <option v-for="bed in availableBeds" :key="bed.id" :value="bed.id">
                            {{ bed.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hospital-text">العين</label>
                    <select
                        v-model="scheduleForm.eye"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    >
                        <option value="">—</option>
                        <option value="OD">عين يمنى (OD)</option>
                        <option value="OS">عين يسرى (OS)</option>
                        <option value="OU">كلاهما (OU)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-hospital-text">التخدير</label>
                    <select
                        v-model="scheduleForm.anaesthesia"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    >
                        <option value="">—</option>
                        <option value="local">موضعي (Local)</option>
                        <option value="topical">سطحي (Topical)</option>
                        <option value="sedation">مهدئ (Sedation)</option>
                        <option value="general">عام (General)</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium text-hospital-text">الإجراء</label>
                    <input
                        v-model="scheduleForm.procedure"
                        type="text"
                        placeholder="اسم الإجراء الجراحي"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium text-hospital-text">موعد العملية</label>
                    <input
                        v-model="scheduleForm.scheduled_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium text-hospital-text">ملاحظات ما قبل العملية</label>
                    <textarea
                        v-model="scheduleForm.pre_op_notes"
                        rows="3"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showSchedule = false">
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="scheduleForm.processing"
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 disabled:opacity-60"
                >
                    جدولة
                </button>
            </div>
        </form>
    </Modal>

    <!-- Report Modal -->
    <Modal v-model="showReport" title="تقرير العملية" size="md">
        <form class="space-y-4" @submit.prevent="submitReport">
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text">تقرير العملية</label>
                <textarea v-model="reportForm.op_report" rows="4" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text">ملاحظات ما بعد العملية</label>
                <textarea v-model="reportForm.post_op_notes" rows="3" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text">المضاعفات</label>
                <textarea v-model="reportForm.complications" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showReport = false">إلغاء</button>
                <button type="submit" :disabled="reportForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 disabled:opacity-60">
                    حفظ التقرير
                </button>
            </div>
        </form>
    </Modal>

    <!-- Supplies Modal -->
    <Modal v-model="showSupplies" title="تسجيل المستلزمات المستخدمة" size="lg">
        <div class="space-y-3">
            <div
                v-for="(item, idx) in supplyItems"
                :key="idx"
                class="grid grid-cols-12 items-center gap-2"
            >
                <input
                    v-model="item.name"
                    type="text"
                    placeholder="اسم الصنف"
                    class="col-span-5 rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
                <input
                    v-model.number="item.qty"
                    type="number"
                    min="1"
                    placeholder="الكمية"
                    class="col-span-3 rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
                <input
                    v-model.number="item.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="السعر"
                    class="col-span-3 rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
                <button
                    class="col-span-1 flex h-9 w-9 items-center justify-center rounded-lg text-hospital-danger hover:bg-hospital-danger/10 transition-colors"
                    @click="removeSupplyRow(idx)"
                >
                    ×
                </button>
            </div>

            <button
                class="text-sm text-hospital-primary hover:underline"
                @click="addSupplyRow"
            >
                + إضافة صنف
            </button>

            <div class="border-t border-hospital-border pt-3 text-left font-mono text-sm font-semibold">
                الإجمالي: {{ suppliesTotal.toLocaleString('ar-EG') }} ج.م
            </div>

            <div class="flex justify-end gap-2">
                <button class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showSupplies = false">إلغاء</button>
                <button
                    class="rounded-lg bg-hospital-accent px-4 py-2 text-sm font-medium text-white hover:bg-hospital-accent/90"
                    @click="submitSupplies"
                >
                    تسجيل المستلزمات
                </button>
            </div>
        </div>
    </Modal>
</template>
