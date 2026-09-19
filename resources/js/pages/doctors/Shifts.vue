<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PlusCircle, CheckCircle, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface Doctor {
    id: string;
    name: string;
}

interface DoctorShift {
    id: string;
    doctor?: { name: string };
    shift_date: string;
    started_at?: string;
    ended_at?: string;
    status: string;
    notes?: string;
    bookings_count?: number;
    revenue?: number;
}

const props = defineProps<{
    shifts: {
        data: DoctorShift[];
        current_page: number;
        last_page: number;
        total: number;
    };
    doctors: Doctor[];
    filters: { doctor_id?: string; date?: string };
}>();

const columns = [
    { key: 'shift_date',  label: 'التاريخ', sortable: true },
    { key: 'doctor',      label: 'الطبيب' },
    { key: 'started_at',  label: 'بدأ الوردية' },
    { key: 'ended_at',    label: 'انتهى الوردية' },
    { key: 'status',      label: 'الحالة' },
    { key: 'bookings_count', label: 'الحالات' },
    { key: 'revenue',     label: 'الإيراد' },
];

const doctorFilter = ref(props.filters.doctor_id ?? '');
const dateFilter   = ref(props.filters.date ?? '');

function applyFilters() {
    router.get('/doctor-shifts', {
        doctor_id: doctorFilter.value || undefined,
        date: dateFilter.value || undefined,
    }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/doctor-shifts', {
        doctor_id: doctorFilter.value || undefined,
        date: dateFilter.value || undefined,
        page,
    }, { preserveState: true });
}

const showOpen = ref(false);
const openForm = useForm({
    doctor_id:  '',
    shift_date: new Date().toISOString().slice(0, 10),
    notes:      '',
});
function submitOpen() {
    openForm.post('/doctor-shifts', {
        onSuccess: () => {
 showOpen.value = false; openForm.reset(); 
},
    });
}

const statusColors: Record<string, string> = {
    open:         'bg-hospital-success/10 text-hospital-success',
    closed:       'bg-hospital-muted/20 text-hospital-text-3',
    handed_over:  'bg-hospital-primary/10 text-hospital-primary',
};
const statusLabels: Record<string, string> = {
    open: 'مفتوحة', closed: 'مغلقة', handed_over: 'مسلمة',
};

function fmt(n: number) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

function closeShift(id: string) {
    router.patch(`/doctor-shifts/${id}/close`, {}, { preserveState: false });
}
</script>

<template>
    <Head title="ورديات الأطباء" />

    <!-- Filters -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">ورديات الأطباء</h2>
        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="doctorFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">كل الأطباء</option>
                <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <input
                v-model="dateFilter"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            />
            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
                @click="showOpen = true"
            >
                <PlusCircle class="h-4 w-4" /> فتح وردية
            </button>
        </div>
    </div>

    <DataTable
        :columns="columns"
        :rows="shifts.data"
        :current-page="shifts.current_page"
        :last-page="shifts.last_page"
        :total="shifts.total"
        empty-text="لا توجد ورديات"
        @page="goToPage"
    >
        <template #cell-doctor="{ row }">
            {{ (row as DoctorShift).doctor?.name ?? '—' }}
        </template>
        <template #cell-status="{ value }">
            <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="statusColors[value as string] ?? 'bg-hospital-muted/20 text-hospital-text-3'"
            >
                {{ statusLabels[value as string] ?? value }}
            </span>
        </template>
        <template #cell-revenue="{ value }">
            <span class="font-mono text-hospital-success">{{ value ? fmt(value as number) : '—' }} ج.م</span>
        </template>
        <template #cell-bookings_count="{ value }">
            {{ value ?? '—' }}
        </template>
        <template #cell-actions="{ row }">
            <div class="flex items-center gap-1.5">
                <button
                    v-if="(row as DoctorShift).status === 'open'"
                    class="flex items-center gap-1 rounded px-2 py-1 text-xs text-hospital-danger hover:bg-hospital-danger/10 transition-colors"
                    @click="closeShift((row as DoctorShift).id)"
                >
                    <XCircle class="h-3.5 w-3.5" /> إغلاق
                </button>
                <span v-else class="text-xs text-hospital-text-3">
                    <CheckCircle class="inline h-3.5 w-3.5" />
                </span>
            </div>
        </template>
    </DataTable>

    <!-- Open Shift Modal -->
    <Modal v-model="showOpen" title="فتح وردية جديدة" size="sm">
        <form class="space-y-4" @submit.prevent="submitOpen">
            <div>
                <label class="mb-1 block text-sm font-medium">الطبيب <span class="text-hospital-danger">*</span></label>
                <select v-model="openForm.doctor_id" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">اختر الطبيب</option>
                    <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <p v-if="openForm.errors.doctor_id" class="mt-1 text-xs text-hospital-danger">{{ openForm.errors.doctor_id }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">تاريخ الوردية</label>
                <input v-model="openForm.shift_date" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                <textarea v-model="openForm.notes" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showOpen = false">إلغاء</button>
                <button type="submit" :disabled="openForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">فتح</button>
            </div>
        </form>
    </Modal>
</template>
