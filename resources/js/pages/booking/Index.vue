<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarPlus,
    Edit3,
    Trash2,
    Printer,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import DateFilter from '@/components/shared/DateFilter.vue';
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
        dept?: string;
        status?: string;
        pay_status?: string;
        search?: string;
    };
    todayStats: Record<string, number>;
    services?: unknown[];
    doctors?: unknown[];
    insuranceCompanies?: unknown[];
}

const props = defineProps<Props>();

const showCreateModal = ref(false);
const editBooking = ref<Booking | null>(null);
const cancelTarget = ref<Booking | null>(null);
const cancelReason = ref('');
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

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <SearchBar
            v-model="search"
            placeholder="بحث باسم المريض أو رقم الملف..."
            class="min-w-[220px] flex-1"
            @keyup.enter="applySearch"
        />

        <select
            v-model="selectedStatus"
            class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
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

        <button
            type="button"
            class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light"
            @click="showCreateModal = true"
        >
            <CalendarPlus class="h-4 w-4" />
            حجز جديد
        </button>
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

    <!-- Create Modal -->
    <Modal v-model="showCreateModal" title="حجز جديد" size="xl">
        <BookingForm
            :services="(services as any) ?? []"
            :doctors="(doctors as any) ?? []"
            :insurance-companies="(insuranceCompanies as any) ?? []"
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
        title="تعديل الحجز"
        @close="editBooking = null"
    >
        <BookingForm
            v-if="editBooking"
            :services="(services as any) ?? []"
            :doctors="(doctors as any) ?? []"
            :insurance-companies="(insuranceCompanies as any) ?? []"
            :booking="editBooking as Record<string, unknown>"
            :submit-url="`/booking/${editBooking.id}`"
            submit-method="put"
            @success="editBooking = null"
            @cancel="editBooking = null"
        />
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
