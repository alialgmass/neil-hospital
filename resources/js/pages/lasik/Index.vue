<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarPlus, ClipboardList, Package, X } from 'lucide-vue-next';
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
    or_bed_id: number | null;
    bed_no: number | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
    scheduled_at: string | null;
    supply_total: number;
    anaesthesia: string | null;
}

interface Paginator {
    data: Surgery[];
    current_page: number;
    last_page: number;
    total: number;
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
    { key: 'supply_total', label: 'المستلزمات' },
];

const statusFilter = ref(props.filters.status ?? '');
function applyFilters() {
    router.get(
        '/lasik',
        { status: statusFilter.value || undefined },
        { preserveState: true },
    );
}
function goToPage(page: number) {
    router.get(
        '/lasik',
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

const occupiedBedNos = computed(() => occupiedBedIds.value); // For compatibility

const bedStatusColor: Record<string, string> = {
    scheduled: '#7B2FA6',
    prep: '#2980B9',
    in_progress: '#E74C3C',
    completed: '#1A8C5B',
    cancelled: '#95A5A6',
};

/* ── Active case panel ── */
const activeCase = ref<Surgery | null>(null);
function openCase(s: Surgery) {
    activeCase.value = s;
}
function closeCase() {
    activeCase.value = null;
}

const eyeLabel: Record<string, string> = {
    OD: 'عين يمنى',
    OS: 'عين يسرى',
    OU: 'كلاهما',
};
const statusAr: Record<string, string> = {
    scheduled: 'مجدولة',
    prep: 'تحضير',
    in_progress: 'جارية',
    completed: 'مكتملة',
    cancelled: 'ملغاة',
};

/* ── Stats ── */
const totalToday = computed(() => props.surgeries.total);
const completedToday = computed(
    () => props.surgeries.data.filter((s) => s.status === 'completed').length,
);
const supplyTotal = computed(() =>
    props.surgeries.data.reduce((s, b) => s + Number(b.supply_total ?? 0), 0),
);

/* ── Schedule ── */
const showSchedule = ref(false);
const scheduleForm = useForm({
    booking_id: '',
    dept: 'lasik',
    or_bed_id: null as number | null,
    surgeon_id: '',
    eye: '' as string,
    procedure: '',
    anaesthesia: 'topical',
    pre_op_notes: '',
    scheduled_at: '',
});

function selectOrBed(bedId: number) {
    if (occupiedBedIds.value.includes(bedId)) return;
    scheduleForm.or_bed_id = scheduleForm.or_bed_id === bedId ? null : bedId;
}

function submitSchedule() {
    scheduleForm.post('/lasik', {
        onSuccess: () => {
            showSchedule.value = false;
            scheduleForm.reset();
        },
    });
}

function getBedLabel(bedId: number): string {
    for (const room of props.orRooms) {
        const bed = room.beds.find((b) => b.id === bedId);
        if (bed) return `${room.name} - سرير ${bed.bed_number}`;
    }
    return '';
}

/* ── Report ── */
const showReport = ref(false);
const reportTarget = ref('');
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
    reportForm.post(`/lasik/${reportTarget.value}/report`, {
        onSuccess: () => {
            showReport.value = false;
        },
    });
}

/* ── Supplies ── */
const showSupplies = ref(false);
const suppliesTarget = ref('');
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
function addSupplyRow() {
    supplyItems.value.push({
        inventory_item_id: '',
        name: '',
        qty: 1,
        unit_cost: 0,
    });
}
function removeSupplyRow(idx: number) {
    supplyItems.value.splice(idx, 1);
}
function openSupplies(id: string) {
    suppliesTarget.value = id;
    supplyItems.value = [
        { inventory_item_id: '', name: '', qty: 1, unit_cost: 0 },
    ];
    showSupplies.value = true;
}
function selectInventoryItem(item: SupplyItem, inventoryId: string) {
    const inv = props.inventoryItems.find((i) => i.id === inventoryId);
    if (inv) {
        item.inventory_item_id = inventoryId;
        item.name = inv.name;
        item.unit_cost = inv.sell_price;
    }
}
function submitSupplies() {
    router.post(
        `/lasik/${suppliesTarget.value}/supplies`,
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

const procedures = [
    'LASIK',
    'SMILE',
    'PRK',
    'LASEK',
    'Femto-LASIK',
    'Trans PRK',
];
</script>

<template>
    <Head title="قسم الليزك" />

    <!-- ── Stats row ── -->
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="stat-card border-r-[#E07C10]">
            <p class="stat-lbl">جلسات الليزك اليوم</p>
            <p class="stat-val">{{ totalToday }}</p>
        </div>
        <div class="stat-card border-r-[#1A8C5B]">
            <p class="stat-lbl">مكتملة</p>
            <p class="stat-val">{{ completedToday }}</p>
        </div>
        <div class="stat-card border-r-hospital-primary">
            <p class="stat-lbl">إيراد الليزك</p>
            <p class="stat-val">—</p>
            <p class="stat-sub">جنيه</p>
        </div>
        <div class="stat-card border-r-hospital-accent">
            <p class="stat-lbl">مستلزمات مستخدمة</p>
            <p class="stat-val text-sm">
                {{ supplyTotal.toLocaleString('ar-EG') }}
            </p>
        </div>
    </div>

    <!-- ── Beds grid (same card style as surgery) ── -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-bold text-hospital-text">
                غرف الإقامة — قسم الليزك ({{ totalBeds }} سرير)
            </h2>
            <div
                class="mt-1.5 flex flex-wrap items-center gap-3 text-xs text-hospital-text-2"
            >
                <span class="flex items-center gap-1.5"
                    ><span class="h-3 w-3 rounded-sm bg-[#7B2FA6]"></span
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
        <button
            class="flex items-center gap-1.5 rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#6A2890]"
            @click="showSchedule = true"
        >
            <CalendarPlus class="h-4 w-4" />
            جدولة ليزك
        </button>
    </div>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div
            v-for="(item, idx) in bedMap"
            :key="idx"
            class="bed-card group"
            :style="
                item.surgery
                    ? {
                          background:
                              bedStatusColor[item.surgery.status] ?? '#7B2FA6',
                      }
                    : { background: '#BDC3C7', opacity: '0.75' }
            "
            @click="
                item.surgery ? openCase(item.surgery) : (showSchedule = true)
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
                    <span>الإجراء:</span
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
                <p class="mt-1 text-[11px] opacity-80">سرير فارغ</p>
                <div class="mt-2 rounded bg-white/30 px-2 py-1 text-[10px]">
                    + جدولة ليزك
                </div>
            </div>
        </div>
    </div>

    <!-- ── Table + Case panel ── -->
    <div
        class="grid gap-4"
        :class="activeCase ? 'grid-cols-1 lg:grid-cols-3' : 'grid-cols-1'"
    >
        <div :class="activeCase ? 'lg:col-span-2' : ''">
            <div class="dept-card">
                <div class="dept-card-hd">
                    <p class="dept-card-title">جدول جلسات الليزك</p>
                    <select
                        v-model="statusFilter"
                        class="rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1.5 text-xs text-hospital-text focus:border-[#7B2FA6] focus:outline-none"
                        @change="applyFilters"
                    >
                        <option value="">جميع الحالات</option>
                        <option value="scheduled">مجدولة</option>
                        <option value="in_progress">جارية</option>
                        <option value="completed">مكتملة</option>
                        <option value="cancelled">ملغاة</option>
                    </select>
                </div>
                <DataTable
                    :columns="columns"
                    :rows="surgeries.data"
                    :current-page="surgeries.current_page"
                    :last-page="surgeries.last_page"
                    :total="surgeries.total"
                    empty-text="لا توجد إجراءات ليزك مسجلة"
                    @page="goToPage"
                >
                    <template #cell-scheduled_at="{ value }">
                        {{
                            value
                                ? (value as string)
                                      .replace('T', ' ')
                                      .slice(0, 16)
                                : '—'
                        }}
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
                            class="inline-flex h-6 w-6 items-center justify-center rounded bg-purple-50 text-xs font-bold text-[#7B2FA6]"
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
                            >{{
                                Number(value).toLocaleString('ar-EG')
                            }}
                            ج.م</span
                        >
                    </template>
                    <template #actions="{ row }">
                        <div class="flex gap-1">
                            <button
                                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary hover:bg-hospital-primary-pale"
                                @click="openCase(row as Surgery)"
                            >
                                <ClipboardList class="h-3.5 w-3.5" /> عرض
                            </button>
                            <button
                                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-[#7B2FA6] hover:bg-purple-50"
                                @click="openSupplies((row as Surgery).id)"
                            >
                                <Package class="h-3.5 w-3.5" /> مستلزمات
                            </button>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- Case panel -->
        <div v-if="activeCase" class="lg:col-span-1">
            <div class="dept-card overflow-hidden">
                <div class="case-panel-hd">
                    <div>
                        <p class="text-[13px] font-bold text-white">
                            {{ activeCase.booking?.patient_name ?? '—' }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-white/60">
                            {{ activeCase.procedure }} —
                            {{
                                activeCase.bed_no
                                    ? `سرير ${activeCase.bed_no}`
                                    : 'بدون سرير'
                            }}
                        </p>
                    </div>
                    <button
                        class="flex h-7 w-7 items-center justify-center rounded-full bg-white/15 text-white hover:bg-white/25"
                        @click="closeCase"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <div class="p-3">
                    <div class="mb-3 grid grid-cols-2 gap-2">
                        <div class="case-info-cell">
                            <p class="case-info-lbl">الحالة</p>
                            <p class="case-info-val">
                                {{ statusAr[activeCase.status] }}
                            </p>
                        </div>
                        <div class="case-info-cell">
                            <p class="case-info-lbl">العين</p>
                            <p class="case-info-val">
                                {{
                                    activeCase.eye
                                        ? eyeLabel[activeCase.eye]
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div class="case-info-cell">
                            <p class="case-info-lbl">الطبيب</p>
                            <p class="case-info-val">
                                {{ activeCase.surgeon?.name ?? '—' }}
                            </p>
                        </div>
                        <div class="case-info-cell">
                            <p class="case-info-lbl">الموعد</p>
                            <p class="case-info-val">
                                {{
                                    activeCase.scheduled_at
                                        ? activeCase.scheduled_at.slice(0, 10)
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div class="case-info-cell">
                            <p class="case-info-lbl">رقم الملف</p>
                            <p class="case-info-val">
                                {{ activeCase.booking?.file_no ?? '—' }}
                            </p>
                        </div>
                        <div class="case-info-cell">
                            <p class="case-info-lbl">المستلزمات</p>
                            <p class="case-info-val">
                                {{
                                    Number(
                                        activeCase.supply_total,
                                    ).toLocaleString('ar-EG')
                                }}
                                ج
                            </p>
                        </div>
                    </div>
                    <p
                        class="mb-2 border-b-2 border-purple-100 pb-1 text-[11px] font-bold text-[#7B2FA6]"
                    >
                        المستلزمات المستخدمة في الليزك
                    </p>
                    <div
                        class="mb-3 min-h-[40px] rounded-lg bg-gray-50 px-3 py-2 text-xs text-hospital-text-2"
                    >
                        <span v-if="!activeCase.supply_total"
                            >لا توجد مستلزمات مسجلة بعد</span
                        >
                        <span v-else
                            >إجمالي المستلزمات:
                            {{
                                Number(activeCase.supply_total).toLocaleString(
                                    'ar-EG',
                                )
                            }}
                            ج</span
                        >
                    </div>
                    <button
                        class="mb-3 w-full rounded-lg bg-[#7B2FA6] py-2 text-xs font-medium text-white transition-colors hover:bg-[#6A2890]"
                        @click="openSupplies(activeCase.id)"
                    >
                        + إضافة مستلزم
                    </button>
                    <div class="case-totals-bar">
                        <div class="flex justify-between text-xs">
                            <span class="text-white/70">إجمالي المستلزمات</span>
                            <span class="font-bold text-white"
                                >{{
                                    Number(
                                        activeCase.supply_total,
                                    ).toLocaleString('ar-EG')
                                }}
                                ج</span
                            >
                        </div>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button
                            class="flex flex-1 items-center justify-center gap-1 rounded-lg border border-[#7B2FA6] px-3 py-1.5 text-xs font-medium text-[#7B2FA6] hover:bg-purple-50"
                            @click="openReport(activeCase.id)"
                        >
                            <ClipboardList class="h-3.5 w-3.5" /> تقرير
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Schedule Modal ── -->
    <Modal v-model="showSchedule" title="جدولة إجراء ليزك" size="lg">
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
                        >الإجراء</label
                    >
                    <select v-model="scheduleForm.procedure" class="dept-input">
                        <option value="">— اختر —</option>
                        <option v-for="p in procedures" :key="p" :value="p">
                            {{ p }}
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
                <div>
                    <label
                        class="mb-1 block text-sm font-medium text-hospital-text"
                        >موعد الإجراء</label
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
                <div class="beds-panel-lbl">
                    اختر السرير:
                    <span
                        v-if="scheduleForm.or_bed_id"
                        class="beds-panel-selected"
                        >{{ getBedLabel(scheduleForm.or_bed_id) }}</span
                    >
                </div>
                <div class="beds-row">
                    <template v-for="(item, idx) in bedMap" :key="idx">
                        <button
                            type="button"
                            class="bed"
                            :class="{
                                'bed-occupied-picker': occupiedBedIds.includes(
                                    item.bed.id,
                                ),
                                'bed-selected':
                                    scheduleForm.or_bed_id === item.bed.id,
                            }"
                            :title="
                                occupiedBedIds.includes(item.bed.id)
                                    ? `${item.room.name} - سرير ${item.bed.bed_number} مشغول`
                                    : `${item.room.name} - سرير ${item.bed.bed_number}`
                            "
                            @click="selectOrBed(item.bed.id)"
                        >
                            {{ item.room.name }}:{{ item.bed.bed_number }}
                        </button>
                    </template>
                </div>
                <div class="beds-legend">
                    <span class="legend-item"
                        ><span class="bed" style="cursor: default">غ:س</span>
                        فارغ</span
                    >
                    <span class="legend-item"
                        ><span
                            class="bed bed-occupied-picker"
                            style="cursor: default"
                            >غ:س</span
                        >
                        مشغول</span
                    >
                    <span class="legend-item"
                        ><span class="bed bed-selected" style="cursor: default"
                            >غ:س</span
                        >
                        محجوز</span
                    >
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-hospital-text"
                    >ملاحظات ما قبل الإجراء</label
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
                    class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    جدولة
                </button>
            </div>
        </form>
    </Modal>

    <!-- ── Report Modal ── -->
    <Modal v-model="showReport" title="تقرير الليزك" size="md">
        <form class="space-y-4" @submit.prevent="submitReport">
            <div>
                <label class="mb-1 block text-sm font-medium"
                    >تقرير الإجراء</label
                ><textarea
                    v-model="reportForm.op_report"
                    rows="4"
                    class="dept-input"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium"
                    >ملاحظات ما بعد الإجراء</label
                ><textarea
                    v-model="reportForm.post_op_notes"
                    rows="3"
                    class="dept-input"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">المضاعفات</label
                ><textarea
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
                    class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    حفظ التقرير
                </button>
            </div>
        </form>
    </Modal>

    <!-- ── Supplies Modal ── -->
    <Modal v-model="showSupplies" title="تسجيل المستلزمات" size="lg">
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
                    class="col-span-1 h-9 w-9 rounded-lg text-hospital-danger hover:bg-hospital-danger/10"
                    @click="removeSupplyRow(idx)"
                >
                    ×
                </button>
            </div>
            <button
                class="text-sm text-[#7B2FA6] hover:underline"
                @click="addSupplyRow"
            >
                + إضافة صنف
            </button>
            <div
                class="flex justify-end gap-2 border-t border-hospital-border pt-3"
            >
                <button
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="showSupplies = false"
                >
                    إلغاء
                </button>
                <button
                    class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white hover:bg-[#6A2890]"
                    @click="submitSupplies"
                >
                    تسجيل
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
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
    margin-bottom: 2px;
}
.stat-sub {
    font-size: 10px;
    color: var(--color-hospital-text-3, #8a96ae);
}

/* ── Card (table section) ── */
.dept-card {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}
.dept-card-hd {
    padding: 11px 15px;
    border-bottom: 1px solid var(--color-hospital-border, #dde4ef);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--color-hospital-bg, #f3f6fa);
}
.dept-card-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-text, #0d1f3c);
}
.dept-card-sub {
    font-size: 11px;
    color: var(--color-hospital-text-3, #8a96ae);
    margin-top: 1px;
}

/* ── Bed cards (grid view) — same as surgery ── */
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

/* ── Bed picker in modal ── */
.beds-panel {
    background: var(--color-hospital-bg, #f3f6fa);
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 8px;
    padding: 10px 12px;
}
.beds-panel-lbl {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-hospital-text-2, #4a5878);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.beds-panel-selected {
    font-size: 10px;
    font-weight: 700;
    background: #7b2fa6;
    color: #fff;
    border-radius: 12px;
    padding: 2px 8px;
}
.bed-occupied-picker {
    background: var(--color-hospital-primary-pale, #e8f1fb) !important;
    border-color: var(--color-hospital-primary, #0a4fa6) !important;
    color: var(--color-hospital-primary, #0a4fa6) !important;
    cursor: not-allowed !important;
    opacity: 0.7;
}
.bed-selected {
    background: #7b2fa6 !important;
    border-color: #7b2fa6 !important;
    color: #fff !important;
    opacity: 1 !important;
}
.beds-legend {
    display: flex;
    gap: 12px;
    margin-top: 8px;
    font-size: 10px;
    color: var(--color-hospital-text-2, #4a5878);
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ── Case panel ── */
.case-panel-hd {
    background: linear-gradient(135deg, #7b2fa6, #9b4fc6);
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.case-info-cell {
    background: var(--color-hospital-bg, #f3f6fa);
    border-radius: 6px;
    padding: 7px 10px;
}
.case-info-lbl {
    font-size: 10px;
    color: var(--color-hospital-text-3, #8a96ae);
    margin-bottom: 2px;
}
.case-info-val {
    font-size: 12px;
    font-weight: 600;
    color: var(--color-hospital-text, #0d1f3c);
}
.case-totals-bar {
    background: linear-gradient(135deg, #5b2080, #7b2fa6);
    border-radius: 8px;
    padding: 10px 12px;
}

/* ── Form input ── */
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
    border-color: #7b2fa6;
    box-shadow: 0 0 0 3px rgba(123, 47, 166, 0.1);
}
</style>
