<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ClipboardList, List, Package } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface Surgery {
    id: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    anaesthesia: string | null;
    surgeon: { id: string; name: string } | null;
    or_bed_id: number | null;
    bed_no: number | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
    scheduled_at: string | null;
    supply_total: number;
}

interface OrBed {
    id: number;
    bed_number: number;
    status: string;
    surgery?: Surgery | null;
}

interface OrRoom {
    id: number;
    name: string;
    beds: OrBed[];
}

interface Paginator {
    data: Surgery[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    surgeries: Paginator;
    orRooms: OrRoom[];
    inventoryItems: {
        id: string;
        name: string;
        code: string;
        sell_price: number;
        quantity: number;
    }[];
    doctors: { id: string; name: string }[];
    bookings: { id: string; file_no: string; patient_name: string }[];
    dept: string;
    filters: { status?: string };
}>();

const columns = [
    { key: 'scheduled_at', label: 'الموعد', sortable: true },
    { key: 'file_no', label: 'رقم الملف' },
    { key: 'patient', label: 'المريض' },
    { key: 'procedure', label: 'الإجراء' },
    { key: 'eye', label: 'العين' },
    { key: 'surgeon', label: 'الطبيب' },
    { key: 'bed_no', label: 'السرير' },
    { key: 'status', label: 'الحالة' },
    { key: 'supply_total', label: 'تكلفة المستلزمات' },
];

const viewMode = ref<'grid' | 'table'>('grid');

const statusFilter = ref(props.filters.status ?? '');
function applyFilters() {
    router.get(
        '/surgery',
        { status: statusFilter.value || undefined },
        { preserveState: true },
    );
}
function goToPage(page: number) {
    router.get(
        '/surgery',
        { status: statusFilter.value || undefined, page },
        { preserveState: true },
    );
}

/* ── Beds grid from OR Rooms ── */
const bedMap = computed(() => {
    const map: Record<
        number,
        { room: OrRoom; bed: OrBed; surgery: Surgery | null }
    > = {};
    let bedCounter = 1;

    props.orRooms.forEach((room) => {
        room.beds.forEach((bed) => {
            const surgery =
                props.surgeries.data.find((s) => s.or_bed_id === bed.id) ??
                null;
            map[bedCounter++] = { room, bed, surgery };
        });
    });

    return map;
});

const totalBeds = computed(() => Object.keys(bedMap.value).length);

const bedBg: Record<string, string> = {
    scheduled: '#27AE60',
    prep: '#2980B9',
    in_progress: '#E74C3C',
    completed: '#1A8C5B',
    cancelled: '#95A5A6',
};

const statusAr: Record<string, string> = {
    scheduled: 'مجدولة',
    prep: 'تحضير',
    in_progress: 'جارية',
    completed: 'مكتملة',
    cancelled: 'ملغاة',
};

const eyeLabel: Record<string, string> = {
    OD: 'عين يمنى',
    OS: 'عين يسرى',
    OU: 'كلاهما',
};

/* ── Stats ── */
const scheduledCount = computed(
    () => props.surgeries.data.filter((s) => s.status === 'scheduled').length,
);
const inProgressCount = computed(
    () =>
        props.surgeries.data.filter(
            (s) => s.status === 'in_progress' || s.status === 'prep',
        ).length,
);
const completedCount = computed(
    () => props.surgeries.data.filter((s) => s.status === 'completed').length,
);
const supplyTotal = computed(() =>
    props.surgeries.data.reduce((s, b) => s + Number(b.supply_total ?? 0), 0),
);

/* ── Schedule Modal ── */
const showSchedule = ref(false);
const scheduleForm = useForm({
    booking_id: '',
    dept: 'surgery',
    or_bed_id: null as number | null,
    surgeon_id: '',
    eye: '',
    procedure: '',
    anaesthesia: '',
    pre_op_notes: '',
    scheduled_at: '',
});

function selectOrBed(bedId: number) {
    if (occupiedBedIds.value.includes(bedId)) {
return;
}

    scheduleForm.or_bed_id = scheduleForm.or_bed_id === bedId ? null : bedId;
}

const occupiedBedIds = computed(() => {
    const ids: number[] = [];
    props.orRooms.forEach((room) => {
        room.beds.forEach((bed) => {
            if (
                bed.status !== 'available' &&
                bed.surgery &&
                ['scheduled', 'prep', 'in_progress'].includes(
                    bed.surgery.status,
                )
            ) {
                ids.push(bed.id);
            }
        });
    });

    return ids;
});

function submitSchedule() {
    scheduleForm.post('/surgery', {
        onSuccess: () => {
            showSchedule.value = false;
            scheduleForm.reset();
        },
    });
}

function getBedLabel(bedId: number): string {
    for (const room of props.orRooms) {
        const bed = room.beds.find((b) => b.id === bedId);

        if (bed) {
return `${room.name} - سرير ${bed.bed_number}`;
}
    }

    return '';
}

/* ── Report Modal ── */
const showReport = ref(false);
const reportTarget = ref<string>('');
const reportForm = useForm({
    op_report: '',
    post_op_notes: '',
    complications: '',
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
const showSupplies = ref(false);
const suppliesTarget = ref<string>('');
interface SupplyItem {
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
}
const supplyItems = ref<SupplyItem[]>([
    { inventory_item_id: '', name: '', qty: 1, unit_cost: 0 },
]);
const suppliesTotal = computed(() =>
    supplyItems.value.reduce((s, i) => s + i.qty * i.unit_cost, 0),
);

function selectInventoryItem(item: SupplyItem, inventoryId: string) {
    const inv = props.inventoryItems.find((i) => i.id === inventoryId);

    if (inv) {
        item.inventory_item_id = inventoryId;
        item.name = inv.name;
        item.unit_cost = inv.sell_price;
    }
}

function addSupplyRow() {
    supplyItems.value.push({
        inventory_item_id: '',
        name: '',
        qty: 1,
        unit_cost: 0,
    });
}
function removeSupplyRow(i: number) {
    supplyItems.value.splice(i, 1);
}
function openSupplies(id: string) {
    suppliesTarget.value = id;
    supplyItems.value = [
        { inventory_item_id: '', name: '', qty: 1, unit_cost: 0 },
    ];
    showSupplies.value = true;
}
function submitSupplies() {
    router.post(
        `/surgery/${suppliesTarget.value}/supplies`,
        {
            surgery_id: suppliesTarget.value,
            items: supplyItems.value,
        },
        {
            onSuccess: () => {
                showSupplies.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="قسم العمليات" />

    <!-- ── Header ── -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-hospital-text">
                غرف الإقامة — قسم العمليات
            </h2>
            <div
                class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-hospital-text-2"
            >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-[#27AE60]"></span
                    >مجدولة</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-[#E74C3C]"></span
                    >جارية</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-[#2980B9]"></span
                    >تحضير</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-[#1A8C5B]"></span
                    >مكتملة</span
                >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-gray-300"></span
                    >فارغة</span
                >
            </div>
        </div>
        <div class="flex items-center gap-2">
            <div
                class="flex items-center gap-0.5 rounded-lg border border-hospital-border bg-white p-1"
            >
                <button
                    class="rounded p-1.5 transition-colors"
                    :class="
                        viewMode === 'grid'
                            ? 'bg-hospital-primary text-white'
                            : 'text-gray-400 hover:text-gray-600'
                    "
                    title="عرض الغرف"
                    @click="viewMode = 'grid'"
                >
                    <svg
                        class="h-4 w-4"
                        fill="currentColor"
                        viewBox="0 0 16 16"
                    >
                        <path
                            d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5v-3zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5v-3zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5v-3zm8 0A1.5 1.5 0 0 1 10.5 9h3a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5h-3A1.5 1.5 0 0 1 9 13.5v-3z"
                        />
                    </svg>
                </button>
                <button
                    class="rounded p-1.5 transition-colors"
                    :class="
                        viewMode === 'table'
                            ? 'bg-hospital-primary text-white'
                            : 'text-gray-400 hover:text-gray-600'
                    "
                    title="عرض جدول"
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
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-hospital-primary/90"
                @click="showSchedule = true"
            >
                <CalendarPlus class="h-4 w-4" />
                جدولة عملية
            </button>
        </div>
    </div>

    <!-- ── Stats strip ── -->
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="stat-card border-r-[#27AE60]">
            <p class="stat-lbl">مجدولة</p>
            <p class="stat-val">{{ scheduledCount }}</p>
        </div>
        <div class="stat-card border-r-[#E74C3C]">
            <p class="stat-lbl">جارية / تحضير</p>
            <p class="stat-val">{{ inProgressCount }}</p>
        </div>
        <div class="stat-card border-r-[#1A8C5B]">
            <p class="stat-lbl">مكتملة اليوم</p>
            <p class="stat-val">{{ completedCount }}</p>
        </div>
        <div class="stat-card border-r-hospital-accent">
            <p class="stat-lbl">تكلفة المستلزمات</p>
            <p class="stat-val text-sm">
                {{ supplyTotal.toLocaleString('ar-EG') }}
                <span class="text-xs text-hospital-text-2">ج</span>
            </p>
        </div>
    </div>

    <!-- ── GRID VIEW ── -->
    <div
        v-if="viewMode === 'grid'"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5"
    >
        <div
            v-for="(item, idx) in bedMap"
            :key="idx"
            class="bed-card group"
            :style="
                item.surgery
                    ? { background: bedBg[item.surgery.status] ?? '#27AE60' }
                    : { background: '#BDC3C7', opacity: '0.75' }
            "
            @click="
                item.surgery
                    ? openReport(item.surgery.id)
                    : (showSchedule = true)
            "
        >
            <div class="bed-card-hd">
                <span class="text-[13px] font-black"
                    >{{ item.room.name }} - {{ item.bed.bed_number }}</span
                >
                <span v-if="item.surgery" class="bed-status-badge">{{
                    statusAr[item.surgery.status]
                }}</span>
            </div>
            <div v-if="item.surgery" class="bed-card-body">
                <p class="mb-1 text-[13px] leading-tight font-extrabold">
                    {{ item.surgery.booking?.patient_name ?? '—' }}
                </p>
                <p class="bed-info-row">
                    <span>العملية:</span
                    ><strong>{{ item.surgery.procedure }}</strong>
                </p>
                <p class="bed-info-row">
                    <span>الطبيب:</span
                    ><strong>{{ item.surgery.surgeon?.name ?? '—' }}</strong>
                </p>
                <p v-if="item.surgery.eye" class="bed-info-row">
                    <span>العين:</span
                    ><strong>{{
                        eyeLabel[item.surgery.eye!] ?? item.surgery.eye
                    }}</strong>
                </p>
                <div class="mt-2 flex gap-1.5" @click.stop>
                    <button
                        class="bed-action-btn"
                        @click="openReport(item.surgery!.id)"
                    >
                        ▶ تقرير
                    </button>
                    <button
                        class="bed-action-btn"
                        @click="openSupplies(item.surgery!.id)"
                    >
                        💊 مستلزمات
                    </button>
                </div>
            </div>
            <div v-else class="bed-card-empty">
                <div class="text-2xl">🛏️</div>
                <p class="mt-1 text-[11px] opacity-80">غرفة فارغة</p>
                <div class="mt-2 rounded bg-white/30 px-2 py-1 text-[10px]">
                    + جدولة عملية
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE VIEW ── -->
    <DataTable
        v-else
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
        <template #cell-file_no="{ row }">{{
            (row as Surgery).booking?.file_no ?? '—'
        }}</template>
        <template #cell-patient="{ row }">{{
            (row as Surgery).booking?.patient_name ?? '—'
        }}</template>
        <template #cell-eye="{ value }">{{
            value ? (eyeLabel[value as string] ?? value) : '—'
        }}</template>
        <template #cell-surgeon="{ row }">{{
            (row as Surgery).surgeon?.name ?? '—'
        }}</template>
        <template #cell-bed_no="{ value }">
            <span
                v-if="value"
                class="inline-flex h-6 w-6 items-center justify-center rounded bg-hospital-primary-pale text-xs font-bold text-hospital-primary"
                >{{ value }}</span
            >
            <span v-else class="text-hospital-text-2">—</span>
        </template>
        <template #cell-status="{ value }">
            <Badge
                :variant="
                    value as
                        | 'scheduled'
                        | 'prep'
                        | 'in_progress'
                        | 'completed'
                        | 'cancelled'
                "
            />
        </template>
        <template #cell-supply_total="{ value }">
            <span class="font-mono text-sm"
                >{{ Number(value).toLocaleString('ar-EG') }} ج.م</span
            >
        </template>
        <template #actions="{ row }">
            <div class="flex items-center gap-1">
                <button
                    class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary transition-colors hover:bg-hospital-primary-pale"
                    @click="openReport((row as Surgery).id)"
                >
                    <ClipboardList class="h-3.5 w-3.5" /> تقرير
                </button>
                <button
                    class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-accent transition-colors hover:bg-hospital-accent/10"
                    @click="openSupplies((row as Surgery).id)"
                >
                    <Package class="h-3.5 w-3.5" /> مستلزمات
                </button>
            </div>
        </template>
    </DataTable>

    <!-- ── Schedule Modal ── -->
    <Modal v-model="showSchedule" title="جدولة عملية جراحية" size="lg">
        <form class="space-y-4" @submit.prevent="submitSchedule">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >المريض / الحجز</label
                    >
                    <select
                        v-model="scheduleForm.booking_id"
                        class="dept-input"
                    >
                        <option value="">— اختر المريض —</option>
                        <option v-for="b in bookings" :key="b.id" :value="b.id">
                            {{ b.file_no }} — {{ b.patient_name }}
                        </option>
                    </select>
                    <p
                        v-if="scheduleForm.errors.booking_id"
                        class="mt-1 text-xs text-hospital-danger"
                    >
                        {{ scheduleForm.errors.booking_id }}
                    </p>
                </div>
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >الطبيب الجراح</label
                    >
                    <select
                        v-model="scheduleForm.surgeon_id"
                        class="dept-input"
                    >
                        <option value="">— اختر الطبيب —</option>
                        <option
                            v-for="doc in doctors"
                            :key="doc.id"
                            :value="doc.id"
                        >
                            {{ doc.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >العين</label
                    >
                    <select v-model="scheduleForm.eye" class="dept-input">
                        <option value="">—</option>
                        <option value="OD">عين يمنى (OD)</option>
                        <option value="OS">عين يسرى (OS)</option>
                        <option value="OU">كلاهما (OU)</option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >التخدير</label
                    >
                    <select
                        v-model="scheduleForm.anaesthesia"
                        class="dept-input"
                    >
                        <option value="">—</option>
                        <option value="local">موضعي (Local)</option>
                        <option value="topical">سطحي (Topical)</option>
                        <option value="sedation">مهدئ (Sedation)</option>
                        <option value="general">عام (General)</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >الإجراء</label
                    >
                    <input
                        v-model="scheduleForm.procedure"
                        type="text"
                        placeholder="اسم الإجراء الجراحي"
                        class="dept-input"
                    />
                </div>
                <div class="col-span-2">
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >موعد العملية</label
                    >
                    <input
                        v-model="scheduleForm.scheduled_at"
                        type="datetime-local"
                        class="dept-input"
                    />
                </div>
            </div>

            <!-- ── OR Bed picker ── -->
            <div class="beds-panel">
                <div class="beds-panel-legend">
                    <span class="beds-legend-dot beds-legend-free" /> فارغ
                    <span class="beds-legend-dot beds-legend-busy" /> مشغول
                    <span class="beds-legend-dot beds-legend-selected" /> محدد
                    <span v-if="scheduleForm.or_bed_id" class="beds-panel-selected ms-auto">
                        ✓ {{ getBedLabel(scheduleForm.or_bed_id) }}
                    </span>
                </div>
                <div v-for="room in orRooms" :key="room.id" class="beds-room">
                    <p class="beds-room-label">{{ room.name }}</p>
                    <div class="beds-row">
                        <button
                            v-for="bed in room.beds"
                            :key="bed.id"
                            type="button"
                            :class="[
                                'or-bed',
                                occupiedBedIds.includes(bed.id) ? 'or-bed-busy' : 'or-bed-free',
                                scheduleForm.or_bed_id === bed.id ? 'or-bed-selected' : '',
                            ]"
                            :title="
                                occupiedBedIds.includes(bed.id)
                                    ? `${room.name} - سرير ${bed.bed_number} مشغول`
                                    : `${room.name} - سرير ${bed.bed_number}`
                            "
                            @click="selectOrBed(bed.id)"
                        >
                            <span class="or-bed-num">{{ bed.bed_number }}</span>
                            <span v-if="occupiedBedIds.includes(bed.id)" class="or-bed-busy-dot" />
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text"
                    >ملاحظات ما قبل العملية</label
                >
                <textarea
                    v-model="scheduleForm.pre_op_notes"
                    rows="3"
                    class="dept-input"
                />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="showSchedule = false"
                >
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

    <!-- ── Report Modal ── -->
    <Modal v-model="showReport" title="تقرير العملية" size="md">
        <form class="space-y-4" @submit.prevent="submitReport">
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text"
                    >تقرير العملية</label
                >
                <textarea
                    v-model="reportForm.op_report"
                    rows="4"
                    class="dept-input"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text"
                    >ملاحظات ما بعد العملية</label
                >
                <textarea
                    v-model="reportForm.post_op_notes"
                    rows="3"
                    class="dept-input"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text"
                    >المضاعفات</label
                >
                <textarea
                    v-model="reportForm.complications"
                    rows="2"
                    class="dept-input"
                />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="showReport = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="reportForm.processing"
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    حفظ التقرير
                </button>
            </div>
        </form>
    </Modal>

    <!-- ── Supplies Modal ── -->
    <Modal v-model="showSupplies" title="تسجيل المستلزمات المستخدمة" size="lg">
        <div class="space-y-3">
            <div
                v-for="(item, idx) in supplyItems"
                :key="idx"
                class="grid grid-cols-12 items-center gap-2"
            >
                <select
                    :value="item.inventory_item_id"
                    class="dept-input col-span-5"
                    @change="
                        selectInventoryItem(
                            item,
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">— اختر صنف —</option>
                    <option
                        v-for="inv in inventoryItems"
                        :key="inv.id"
                        :value="inv.id"
                    >
                        {{ inv.name }} ({{ inv.code }}) - متوفر:
                        {{ inv.quantity }}
                    </option>
                </select>
                <input
                    v-model.number="item.qty"
                    type="number"
                    min="1"
                    placeholder="الكمية"
                    class="dept-input col-span-2"
                />
                <input
                    v-model.number="item.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="السعر"
                    class="dept-input col-span-3"
                />
                <button
                    class="col-span-1 flex h-9 w-9 items-center justify-center rounded-lg text-hospital-danger hover:bg-hospital-danger/10"
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
            <div
                class="border-t border-hospital-border pt-3 text-left font-mono text-sm font-semibold"
            >
                الإجمالي: {{ suppliesTotal.toLocaleString('ar-EG') }} ج.م
            </div>
            <div class="flex justify-end gap-2">
                <button
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="showSupplies = false"
                >
                    إلغاء
                </button>
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

<style scoped>
/* ── Stat cards ── */
.stat-card {
    background: var(--color-hospital-surface, #fff);
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    border-right-width: 4px;
    padding: 12px 14px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
}
.stat-lbl {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-hospital-text-3, #8a96ae);
    margin-bottom: 4px;
}
.stat-val {
    font-size: 22px;
    font-weight: 800;
    color: var(--color-hospital-text, #0d1f3c);
    line-height: 1;
}

/* ── Bed cards (grid view) ── */
.bed-card {
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    color: #fff;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.18);
    transition:
        transform 0.18s,
        box-shadow 0.18s;
}
.bed-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.28);
}
.bed-card-hd {
    background: rgba(0, 0, 0, 0.18);
    padding: 7px 11px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bed-status-badge {
    font-size: 9px;
    background: rgba(255, 255, 255, 0.25);
    padding: 2px 8px;
    border-radius: 12px;
}
.bed-card-body {
    padding: 9px 11px;
    font-size: 11px;
    line-height: 1.85;
}
.bed-info-row {
    display: flex;
    gap: 4px;
}
.bed-info-row span {
    opacity: 0.75;
}
.bed-action-btn {
    flex: 1;
    padding: 4px;
    background: rgba(255, 255, 255, 0.22);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 5px;
    cursor: pointer;
    font-size: 10px;
    font-family: inherit;
    transition: background 0.15s;
}
.bed-action-btn:hover {
    background: rgba(255, 255, 255, 0.35);
}
.bed-card-empty {
    padding: 16px 11px;
    text-align: center;
}

/* ── Bed picker (modal) ── */
.beds-panel {
    background: #f3f6fa;
    border: 1.5px solid #dde4ef;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.beds-panel-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    font-weight: 600;
    color: #4a5878;
    border-bottom: 1px solid #dde4ef;
    padding-bottom: 8px;
    flex-wrap: wrap;
}
.beds-legend-dot {
    display: inline-block;
    width: 11px;
    height: 11px;
    border-radius: 3px;
    border: 1.5px solid transparent;
}
.beds-legend-free  { background: #fff; border-color: #dde4ef; }
.beds-legend-busy  { background: #fff0ee; border-color: #e74c3c; }
.beds-legend-selected { background: #27ae60; border-color: #27ae60; }
.beds-panel-selected {
    font-size: 10px;
    font-weight: 700;
    background: #27ae60;
    color: #fff;
    border-radius: 12px;
    padding: 2px 10px;
}
.beds-room {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.beds-room-label {
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.beds-row {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.or-bed {
    width: 48px;
    height: 42px;
    border-radius: 8px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    padding: 0;
    position: relative;
    gap: 3px;
}
.or-bed-num {
    font-size: 14px;
    font-weight: 800;
    color: #0d1f3c;
    line-height: 1;
}
.or-bed-free:hover {
    border-color: #27ae60;
    background: #edfaf3;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(39,174,96,0.18);
}
.or-bed-free:hover .or-bed-num { color: #27ae60; }
.or-bed-busy {
    background: #fff0ee;
    border-color: #e74c3c;
    cursor: not-allowed;
    opacity: 0.8;
}
.or-bed-busy .or-bed-num { color: #e74c3c; }
.or-bed-busy-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #e74c3c;
}
.or-bed-selected {
    background: #27ae60 !important;
    border-color: #27ae60 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(39,174,96,0.35);
}
.or-bed-selected .or-bed-num { color: #fff !important; }

/* ── Form inputs ── */
.dept-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 7px;
    font-size: 13px;
    font-family: inherit;
    color: var(--color-hospital-text, #0d1f3c);
    background: #fff;
    direction: rtl;
}
.dept-input:focus {
    outline: none;
    border-color: var(--color-hospital-primary, #0a4fa6);
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.1);
}
</style>
