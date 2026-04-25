<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ClipboardList, Package } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import BedsGrid from './partials/BedsGrid.vue';
import CasePanel from './partials/CasePanel.vue';
import ReportModal from './partials/ReportModal.vue';
import ScheduleModal from './partials/ScheduleModal.vue';
import SuppliesModal from './partials/SuppliesModal.vue';

interface SupplyUsedItem {
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
    total: number;
}

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
    supplies_used: SupplyUsedItem[] | null;
    op_report: string | null;
    post_op_notes: string | null;
    complications: string | null;
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
    inventoryItems: { id: string; name: string; code: string; sell_price: number; quantity: number }[];
    doctors: { id: string; name: string }[];
    bookings: { id: string; file_no: string; patient_name: string }[];
    dept: string;
    filters: { status?: string };
    revenue: number;
}>();

/* ── Stats ── */
const totalToday = computed(() => props.surgeries.total);
const completedToday = computed(() =>
    props.surgeries.data.filter((s) => s.status === 'completed').length,
);
const supplyTotal = computed(() =>
    props.surgeries.data.reduce((sum, s) => sum + Number(s.supply_total ?? 0), 0),
);

/* ── Filter ── */
const statusFilter = ref(props.filters.status ?? '');
let filterTimeout: ReturnType<typeof setTimeout> | null = null;
watch(statusFilter, () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = setTimeout(applyFilters, 300);
});
function applyFilters() {
    router.get('/lasik', { status: statusFilter.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get(
        '/lasik',
        { status: statusFilter.value || undefined, page },
        { preserveState: true },
    );
}

/* ── Active case ── */
const activeCase = ref<Surgery | null>(null);

function openCase(partial: { id: string }) {
    activeCase.value = props.surgeries.data.find((s) => s.id === partial.id) ?? null;
}

function closeCase() {
    activeCase.value = null;
}

function updateStatus(newStatus: string) {
    if (!activeCase.value) return;
    router.patch(
        `/lasik/${activeCase.value.id}/status`,
        { status: newStatus },
        {
            onSuccess: () => {
                if (activeCase.value) {
                    activeCase.value.status = newStatus as Surgery['status'];
                }
                toast.success('تم تحديث حالة الجلسة');
            },
        },
    );
}

/* ── Modals ── */
const showSchedule = ref(false);
const showReport = ref(false);
const reportSurgeryId = ref('');
const showSupplies = ref(false);
const suppliesSurgeryId = ref('');

function openReport(id: string) {
    reportSurgeryId.value = id;
    showReport.value = true;
}
function openSupplies(id: string) {
    suppliesSurgeryId.value = id;
    showSupplies.value = true;
}

/* ── Table ── */
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

const eyeLabel: Record<string, string> = { OD: 'يمنى', OS: 'يسرى', OU: 'كلاهما' };
</script>

<template>
    <Head title="قسم الليزك" />

    <!-- Stats Row -->
    <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="stat-card" style="border-right-color: #e07c10">
            <p class="stat-lbl">جلسات الليزك اليوم</p>
            <p class="stat-val">{{ totalToday }}</p>
        </div>
        <div class="stat-card" style="border-right-color: #1a8c5b">
            <p class="stat-lbl">مكتملة</p>
            <p class="stat-val">{{ completedToday }}</p>
        </div>
        <div class="stat-card" style="border-right-color: #0a4fa6">
            <p class="stat-lbl">إيراد الليزك اليوم</p>
            <p class="stat-val text-sm">{{ revenue.toLocaleString('ar-EG') }}</p>
            <p class="stat-sub">جنيه</p>
        </div>
        <div class="stat-card" style="border-right-color: #00b5a4">
            <p class="stat-lbl">مستلزمات مستخدمة</p>
            <p class="stat-val text-sm">{{ supplyTotal.toLocaleString('ar-EG') }}</p>
        </div>
    </div>

    <!-- Beds Grid -->
    <BedsGrid
        :or-rooms="orRooms"
        :surgeries="surgeries.data"
        @open-case="openCase"
        @schedule-new="showSchedule = true"
        @open-report="openReport"
        @open-supplies="openSupplies"
    />

    <!-- Table -->
    <div class="sect-card">
        <div class="sect-card-hd">
            <p class="sect-card-title">جدول جلسات الليزك</p>
            <select v-model="statusFilter" class="filter-select" @change="applyFilters">
                <option value="">جميع الحالات</option>
                <option value="scheduled">مجدولة</option>
                <option value="prep">تحضير</option>
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
                {{ value ? (value as string).replace('T', ' ').slice(0, 16) : '—' }}
            </template>
            <template #cell-file_no="{ row }">
                {{ (row as Surgery).booking?.file_no ?? '—' }}
            </template>
            <template #cell-patient="{ row }">
                {{ (row as Surgery).booking?.patient_name ?? '—' }}
            </template>
            <template #cell-eye="{ value }">
                {{ value ? (eyeLabel[value as string] ?? value) : '—' }}
            </template>
            <template #cell-surgeon="{ row }">
                {{ (row as Surgery).surgeon?.name ?? '—' }}
            </template>
            <template #cell-bed_no="{ value }">
                <span
                    v-if="value"
                    class="inline-flex h-6 w-6 items-center justify-center rounded bg-purple-50 text-xs font-bold text-[#7B2FA6]"
                >{{ value }}</span>
                <span v-else class="text-hospital-text-2">—</span>
            </template>
            <template #cell-status="{ value }">
                <Badge
                    :variant="
                        value as 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled'
                    "
                />
            </template>
            <template #cell-supply_total="{ value }">
                <span class="font-mono text-sm">{{ Number(value).toLocaleString('ar-EG') }} ج.م</span>
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

    <!-- Case Panel (centered popup) -->
    <CasePanel
        v-if="activeCase"
        :surgery="activeCase"
        @close="closeCase"
        @open-report="openReport"
        @open-supplies="openSupplies"
        @update-status="updateStatus"
    />

    <!-- Schedule Modal -->
    <ScheduleModal
        v-model="showSchedule"
        :or-rooms="orRooms"
        :doctors="doctors"
        :bookings="bookings"
        :dept="dept"
        @success="toast.success('تم جدولة جلسة الليزك بنجاح')"
    />

    <!-- Report Modal -->
    <ReportModal
        v-model="showReport"
        :surgery-id="reportSurgeryId"
        :dept="dept"
        @success="toast.success('تم حفظ التقرير بنجاح')"
    />

    <!-- Supplies Modal -->
    <SuppliesModal
        v-model="showSupplies"
        :surgery-id="suppliesSurgeryId"
        :inventory-items="inventoryItems"
        :dept="dept"
        @success="toast.success('تم حفظ المستلزمات بنجاح')"
    />
</template>

<style scoped>
/* ── Stat cards (right-border accent, matching reference design) ── */
.stat-card {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    border-right-width: 4px;
    padding: 14px 16px;
    box-shadow: 0 2px 8px rgba(10, 79, 166, 0.06);
    position: relative;
    overflow: hidden;
}
.stat-lbl {
    font-size: 10px;
    font-weight: 600;
    color: var(--color-hospital-text-3, #8a96ae);
    margin-bottom: 5px;
}
.stat-val {
    font-size: 24px;
    font-weight: 700;
    color: var(--color-hospital-text, #0d1f3c);
    line-height: 1;
    margin-bottom: 4px;
}
.stat-sub {
    font-size: 10px;
    color: var(--color-hospital-text-3, #8a96ae);
}

/* ── Section card (table wrapper) ── */
.sect-card {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(10, 79, 166, 0.06);
    overflow: hidden;
}
.sect-card-hd {
    padding: 12px 16px;
    border-bottom: 1px solid var(--color-hospital-border, #dde4ef);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--color-hospital-bg, #f3f6fa);
}
.sect-card-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-text, #0d1f3c);
}

/* ── Filter select ── */
.filter-select {
    padding: 5px 9px;
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 6px;
    font-size: 11px;
    font-family: inherit;
    color: var(--color-hospital-text, #0d1f3c);
    background: #fff;
    direction: rtl;
}
.filter-select:focus {
    outline: none;
    border-color: #7b2fa6;
    box-shadow: 0 0 0 2px rgba(123, 47, 166, 0.1);
}
</style>
