<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Stethoscope, CheckCircle, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';

interface Booking {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    visit_time?: string;
    status: 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled';
    pay_status: 'unpaid' | 'partial' | 'paid';
    price: number;
    doctor?: { name: string };
    clinic_sheet?: { diagnosis?: string } | null;
}

const props = defineProps<{
    queue: {
        data: Booking[];
        current_page: number;
        last_page: number;
        total: number;
    };
    date: string;
}>();

const selectedDate = ref(props.date);

const totalToday    = computed(() => props.queue.total);
const completedToday = computed(() => props.queue.data.filter((b) => b.status === 'completed').length);
const revenueToday  = computed(() =>
    props.queue.data
        .filter((b) => b.pay_status === 'paid' || b.pay_status === 'partial')
        .reduce((sum, b) => sum + Number(b.price), 0),
);

const columns = [
    { key: 'visit_time',    label: 'الوقت' },
    { key: 'file_no',       label: 'رقم الملف',   sortable: true },
    { key: 'patient_name',  label: 'المريض',       sortable: true },
    { key: 'patient_phone', label: 'الهاتف' },
    { key: 'doctor',        label: 'الطبيب' },
    { key: 'status',        label: 'الحالة' },
    { key: 'pay_status',    label: 'السداد' },
    { key: 'diagnosis',     label: 'التشخيص' },
];

function changeDate() {
    router.get('/clinic', { date: selectedDate.value }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/clinic', { date: selectedDate.value, page }, { preserveState: true });
}
</script>

<template>
    <Head title="العيادة" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-white">
                <Stethoscope class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-blue-600">حجوزات العيادة اليوم</p>
                <p class="text-2xl font-bold text-blue-700">{{ totalToday }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-600 text-white">
                <CheckCircle class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-green-600">مكتمل</p>
                <p class="text-2xl font-bold text-green-700">{{ completedToday }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-teal-100 bg-teal-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-600 text-white">
                <TrendingUp class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-teal-600">إيراد العيادة (ج)</p>
                <p class="text-2xl font-bold text-teal-700">{{ revenueToday.toLocaleString('ar-EG') }}</p>
            </div>
        </div>
    </div>

    <!-- Header with date filter -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">حجوزات قسم العيادة</h2>
        <div class="flex items-center gap-2">
            <input
                v-model="selectedDate"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                @change="changeDate"
            />
            <Link
                href="/booking?dept=clinic"
                class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90"
            >
                + حجز عيادة
            </Link>
        </div>
    </div>

    <DataTable
        :columns="columns"
        :rows="queue.data"
        :current-page="queue.current_page"
        :last-page="queue.last_page"
        :total="queue.total"
        empty-text="لا يوجد مرضى في قائمة اليوم"
        @page="goToPage"
    >
        <template #cell-visit_time="{ value }">
            {{ (value as string)?.slice(0, 5) ?? '—' }}
        </template>
        <template #cell-doctor="{ row }">
            {{ (row as Booking).doctor?.name ?? '—' }}
        </template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled')" />
        </template>
        <template #cell-pay_status="{ value }">
            <Badge :variant="(value as 'paid' | 'partial' | 'unpaid')" />
        </template>
        <template #cell-diagnosis="{ row }">
            <span class="line-clamp-1 max-w-xs text-xs text-hospital-text-2">
                {{ (row as Booking).clinic_sheet?.diagnosis ?? '—' }}
            </span>
        </template>
        <template #actions="{ row }">
            <Link
                :href="`/clinic/${(row as Booking).id}`"
                class="flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-hospital-primary hover:bg-hospital-primary-pale transition-colors"
            >
                <Eye class="h-3.5 w-3.5" />
                فتح الملف
            </Link>
        </template>
    </DataTable>
</template>
