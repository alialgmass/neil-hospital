<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
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

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">قائمة مرضى العيادة</h2>
        <div class="flex items-center gap-2">
            <input
                v-model="selectedDate"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                @change="changeDate"
            />
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
            <Badge :variant="value as 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled'" />
        </template>
        <template #cell-pay_status="{ value }">
            <Badge :variant="value as 'paid' | 'partial' | 'unpaid'" />
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
