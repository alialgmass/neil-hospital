<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarPlus,
    Edit3,
    Trash2,
    Printer,
    X,
    CreditCard,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import DateFilter from '@/components/shared/DateFilter.vue';
import ExportBar from '@/components/shared/ExportBar.vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';
import StatCard from '@/components/shared/StatCard.vue';
import BookingForm from './Partials/BookingForm.vue';

interface Booking {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    dept: string;
    visit_date: string;
    price: number;
    paid_amount: number;
    pay_status: 'unpaid' | 'partial' | 'paid';
    status: 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled';
    doctor?: { name: string };
}

interface Props {
    bookings: {
        data: Booking[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    filters: {
        date?: string;
        date_from?: string;
        date_to?: string;
        dept?: string;
        status?: string;
        pay_status?: string;
        search?: string;
    };
    todayStats: Record<string, number>;
    services?: { id: string; name: string; dept: string; price: number; ins_price: number }[];
    doctors?: { id: string; name: string; is_active: boolean }[];
    insuranceCompanies?: { id: string; name: string }[];
    orRooms?: { id: number; name: string; beds: { id: number; bed_number: number }[] }[];
}

const props = defineProps<Props>();

// ── Permissions ──
const page = usePage<{ permissions?: string[] }>();
const permissions = computed<string[]>(() => (page.props.permissions as string[]) ?? []);
function can(permission: string): boolean {
    return permissions.value.includes('*') || permissions.value.includes(permission);
}
const canPay = computed(() => can('booking.pay'));

// ── State ──
const showCreateModal = ref(false);
const editBooking = ref<Booking | null>(null);
const cancelTarget = ref<Booking | null>(null);
const cancelReason = ref('');

// ── Pay modal ──
const payTarget = ref<Booking | null>(null);
const payForm = useForm({ paid_amount: '', pay_method: 'cash' });

const isPayModalOpen = computed({
    get: () => !!payTarget.value,
    set: (val) => {
 if (!val) {
 payTarget.value = null; payForm.reset(); 
} 
},
});

const payRemaining = computed(() => {
    if (!payTarget.value) {
return 0;
}

    const net = Math.max(0, Number(payTarget.value.price) - (Number((payTarget.value as any).discount) || 0) - (Number((payTarget.value as any).ins_amount) || 0));

    return Math.max(0, net - Number(payTarget.value.paid_amount));
});

function openPay(booking: Booking) {
    payTarget.value = booking;
    payForm.paid_amount = String(payRemaining.value || '');
}

function submitPay() {
    if (!payTarget.value) {
return;
}

    payForm.patch(`/booking/${payTarget.value.id}/pay`, {
        onSuccess: () => {
 payTarget.value = null; payForm.reset(); 
},
    });
}
const search = ref(props.filters.search ?? '');
const selectedDept = ref(props.filters.dept ?? '');
const selectedStatus = ref(props.filters.status ?? '');

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const columns = [
    { key: 'file_no', label: 'رقم الملف', sortable: true },
    { key: 'patient_name', label: 'المريض', sortable: true },
    { key: 'dept', label: 'القسم' },
    { key: 'visit_date', label: 'التاريخ', sortable: true },
    { key: 'doctor', label: 'الطبيب' },
    { key: 'price', label: 'السعر' },
    { key: 'pay_status', label: 'السداد' },
    { key: 'status', label: 'الحالة' },
];

const statCards = computed(() => [
    {
        label: 'العيادة',
        value: props.todayStats.clinic ?? 0,
        color: 'primary' as const,
    },
    {
        label: 'الفحوصات',
        value: props.todayStats.labs ?? 0,
        color: 'accent' as const,
    },
    {
        label: 'العمليات',
        value: props.todayStats.surgery ?? 0,
        color: 'warning' as const,
    },
    {
        label: 'الليزك',
        value: props.todayStats.lasik ?? 0,
        color: 'success' as const,
    },
    {
        label: 'الليزر',
        value: props.todayStats.laser ?? 0,
        color: 'danger' as const,
    },
]);

const currentDeptLabel = computed(() =>
    selectedDept.value ? (deptLabels[selectedDept.value] ?? selectedDept.value) : 'كل الحجوزات',
);

function applyFilter(from: string, to: string) {
    router.get(
        '/booking',
        {
            date_from: from,
            date_to: to,
            dept: selectedDept.value,
            status: selectedStatus.value,
            search: search.value,
        },
        { preserveState: true, replace: true },
    );
}

function applySearch() {
    router.get(
        '/booking',
        {
            dept: selectedDept.value,
            status: selectedStatus.value,
            search: search.value,
        },
        { preserveState: true, replace: true },
    );
}

function goToPage(page: number) {
    router.get('/booking', { ...props.filters, page }, { preserveState: true });
}

function confirmCancel(booking: Booking) {
    cancelTarget.value = booking;
    cancelReason.value = '';
}

function doCancel() {
    if (!cancelTarget.value) {
return;
}

    router.delete(`/booking/${cancelTarget.value.id}`, {
        data: { cancel_reason: cancelReason.value },
        onSuccess: () => {
            cancelTarget.value = null;
        },
    });
}

function printReceipt(id: string) {
    window.open(`/booking/${id}/receipt`, '_blank');
}
const isEditModalOpen = computed({
    get: () => !!editBooking.value,
    set: (val) => {
        if (!val) {
            editBooking.value = null;
        }
    },
});
const isCloseModalOpen = computed({
    get: () => !!cancelTarget.value,
    set: (val) => {
        if (!val) {
            cancelTarget.value = null;
        }
    },
});
</script>

<template>
    <Head title="الحجوزات" />

    <!-- Stats row -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <StatCard
            v-for="stat in statCards"
            :key="stat.label"
            :label="stat.label"
            :value="stat.value"
            :color="stat.color"
        />
    </div>

    <!-- Dept Tabs -->
    <div class="mb-4 flex gap-1 border-b border-hospital-border">
        <button
            class="px-4 py-2 text-sm font-medium transition-colors"
            :class="selectedDept === ''
                ? 'border-b-2 border-hospital-primary text-hospital-primary'
                : 'text-hospital-muted hover:text-hospital-text'"
            @click="selectedDept = ''; applySearch()"
        >
            كل الحجوزات
        </button>
        <button
            v-for="(label, key) in deptLabels"
            :key="key"
            class="px-4 py-2 text-sm font-medium transition-colors"
            :class="selectedDept === key
                ? 'border-b-2 border-hospital-primary text-hospital-primary'
                : 'text-hospital-muted hover:text-hospital-text'"
            @click="selectedDept = key; applySearch()"
        >
            {{ label }}
        </button>
    </div>

    <!-- Toolbar: filters on left, action on right -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="selectedStatus"
                class="rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                @change="applySearch"
            >
                <option value="">كل الحالات</option>
                <option value="waiting">انتظار</option>
                <option value="confirmed">مؤكد</option>
                <option value="in_progress">جارٍ</option>
                <option value="completed">مكتمل</option>
                <option value="cancelled">ملغي</option>
            </select>

            <DateFilter
                @apply="applyFilter"
                @clear="() => router.get('/booking')"
            />

            <SearchBar
                v-model="search"
                placeholder="بحث باسم المريض أو رقم الملف..."
                class="min-w-[200px]"
                @keyup.enter="applySearch"
            />
        </div>

        <button
            type="button"
            class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light"
            @click="showCreateModal = true"
        >
            <CalendarPlus class="h-4 w-4" />
            حجز جديد
        </button>
    </div>

    <!-- Table card -->
    <div class="booking-table-card overflow-hidden rounded-xl border border-hospital-border shadow-sm">
        <!-- Card header -->
        <div class="flex items-center justify-between border-b border-hospital-border bg-hospital-bg px-4 py-3">
            <div>
                <p class="text-sm font-bold text-hospital-text">{{ currentDeptLabel }}</p>
                <p class="text-xs text-hospital-text-3">{{ bookings.total }} سجل</p>
            </div>
            <ExportBar @print="() => window.print()" />
        </div>

        <!-- Table -->
        <DataTable
            :columns="columns"
            :rows="bookings.data"
            :current-page="bookings.current_page"
            :last-page="bookings.last_page"
            :total="bookings.total"
            @page="goToPage"
        >
            <template #cell-dept="{ value }">
                {{ deptLabels[value as string] ?? value }}
            </template>
            <template #cell-doctor="{ row }">
                {{ (row as Booking).doctor?.name ?? '—' }}
            </template>
            <template #cell-price="{ value }">
                {{ Number(value).toLocaleString('ar-EG') }} ج.م
            </template>
            <template #cell-pay_status="{ value }">
                <Badge :variant="(value as 'paid' | 'partial' | 'unpaid')" />
            </template>
            <template #cell-status="{ value }">
                <Badge :variant="(value as 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled')" />
            </template>
            <template #actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <!-- Pay button — only for users with booking.pay and not fully paid -->
                    <button
                        v-if="canPay && (row as Booking).pay_status !== 'paid'"
                        type="button"
                        title="تسجيل دفعة"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-success-pale hover:text-hospital-success"
                        @click="openPay(row as Booking)"
                    >
                        <CreditCard class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        title="طباعة إيصال"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                        @click="printReceipt((row as Booking).id)"
                    >
                        <Printer class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        title="تعديل"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-warning-pale hover:text-hospital-warning"
                        @click="editBooking = row as Booking"
                    >
                        <Edit3 class="h-4 w-4" />
                    </button>
                    <button
                        type="button"
                        title="إلغاء"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-danger-pale hover:text-hospital-danger"
                        @click="confirmCancel(row as Booking)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>
    </div>

    <!-- Create Modal -->
    <Modal v-model="showCreateModal" size="xl">
        <template #header="{ close }">
            <div class="flex items-center justify-between rounded-t-2xl px-6 py-4" style="background: linear-gradient(135deg, #072E63, #0A4FA6)">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15">
                        <CalendarPlus class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-base font-bold text-white">شاشة الحجز الداخلي</p>
                        <p class="text-xs text-white/60">رقم الحجز: سيتم توليده تلقائياً</p>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-white/70 transition-colors hover:bg-white/20 hover:text-white" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>
        </template>
        <BookingForm
            :services="(services as any) ?? []"
            :doctors="(doctors as any) ?? []"
            :insurance-companies="(insuranceCompanies as any) ?? []"
            :or-rooms="(orRooms as any) ?? []"
            submit-url="/booking"
            submit-method="post"
            @success="showCreateModal = false"
            @cancel="showCreateModal = false"
        />
    </Modal>

    <!-- Edit Modal -->
    <Modal
        v-model="isEditModalOpen"
        size="xl"
        @close="editBooking = null"
    >
        <template #header="{ close }">
            <div class="flex items-center justify-between rounded-t-2xl px-6 py-4" style="background: linear-gradient(135deg, #072E63, #0A4FA6)">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/15">
                        <Edit3 class="h-5 w-5 text-white" />
                    </div>
                    <div>
                        <p class="text-base font-bold text-white">تعديل الحجز</p>
                        <p class="text-xs text-white/60">{{ editBooking?.file_no ?? '' }}</p>
                    </div>
                </div>
                <button type="button" class="rounded-lg p-1.5 text-white/70 transition-colors hover:bg-white/20 hover:text-white" @click="close">
                    <X class="h-5 w-5" />
                </button>
            </div>
        </template>
        <BookingForm
            v-if="editBooking"
            :services="(services as any) ?? []"
            :doctors="(doctors as any) ?? []"
            :insurance-companies="(insuranceCompanies as any) ?? []"
            :or-rooms="(orRooms as any) ?? []"
            :booking="editBooking as Record<string, unknown>"
            :submit-url="`/booking/${editBooking.id}`"
            submit-method="put"
            @success="editBooking = null"
            @cancel="editBooking = null"
        />
    </Modal>

    <!-- Pay Modal -->
    <Modal v-model="isPayModalOpen" size="sm" title="تسجيل دفعة">
        <div v-if="payTarget" class="space-y-4">
            <!-- Booking summary -->
            <div class="rounded-lg bg-hospital-bg px-4 py-3 text-sm">
                <p class="font-semibold text-hospital-text">{{ payTarget.patient_name }}</p>
                <p class="text-xs text-hospital-text-3">{{ payTarget.file_no }} — {{ payTarget.dept }}</p>
                <div class="mt-2 flex items-center justify-between text-xs">
                    <span class="text-hospital-text-3">المبلغ المتبقي</span>
                    <span class="font-bold text-hospital-danger">{{ payRemaining.toLocaleString('ar-EG') }} ج</span>
                </div>
            </div>

            <!-- Amount -->
            <div>
                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">المبلغ المدفوع (ج) *</label>
                <input
                    v-model="payForm.paid_amount"
                    type="number"
                    step="0.01"
                    min="0.01"
                    :max="payRemaining"
                    class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                    :class="{ 'border-hospital-danger': payForm.errors.paid_amount }"
                />
                <p v-if="payForm.errors.paid_amount" class="mt-1 text-xs text-hospital-danger">{{ payForm.errors.paid_amount }}</p>
            </div>

            <!-- Pay method -->
            <div>
                <label class="mb-1 block text-xs font-semibold text-hospital-text-2">طريقة الدفع</label>
                <select
                    v-model="payForm.pay_method"
                    class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                >
                    <option value="cash">كاش</option>
                    <option value="card">شبكة</option>
                    <option value="transfer">تحويل</option>
                    <option value="insurance">تأمين</option>
                </select>
            </div>
        </div>
        <template #footer>
            <button
                type="button"
                class="rounded-lg border border-hospital-border px-4 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg"
                @click="isPayModalOpen = false"
            >
                إلغاء
            </button>
            <button
                type="button"
                :disabled="payForm.processing || !payForm.paid_amount"
                class="flex items-center gap-2 rounded-lg bg-hospital-success px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-green-700 disabled:opacity-50"
                @click="submitPay"
            >
                <CreditCard class="h-4 w-4" />
                تأكيد الدفع
            </button>
        </template>
    </Modal>

    <!-- Cancel Confirm Modal -->
    <Modal
        v-model="isCloseModalOpen"
        title="تأكيد الإلغاء"
        size="sm"
        @close="cancelTarget = null"
    >
        <p class="text-sm text-hospital-text">
            هل تريد إلغاء حجز
            <strong>{{ cancelTarget?.patient_name }}</strong> —
            {{ cancelTarget?.file_no }}؟
        </p>
        <div class="mt-4">
            <label class="mb-1 block text-xs font-medium text-hospital-text-2"
                >سبب الإلغاء *</label
            >
            <textarea
                v-model="cancelReason"
                rows="2"
                class="w-full resize-none rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                placeholder="اذكر سبب الإلغاء..."
            />
        </div>
        <template #footer>
            <button
                type="button"
                class="rounded-lg border border-hospital-border px-4 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg"
                @click="cancelTarget = null"
            >
                تراجع
            </button>
            <button
                type="button"
                :disabled="!cancelReason.trim()"
                class="rounded-lg bg-hospital-danger px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-red-700 disabled:opacity-50"
                @click="doCancel"
            >
                تأكيد الإلغاء
            </button>
        </template>
    </Modal>
</template>

<style scoped>
/* Remove DataTable's own outer border/rounding/shadow when nested inside the card */
.booking-table-card :deep(> div) {
    border: none;
    border-radius: 0;
    box-shadow: none;
}
</style>
