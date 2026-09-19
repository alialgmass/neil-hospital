<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PlusCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface Doctor {
    id: string;
    name: string;
}

interface DoctorPayment {
    id: string;
    doctor?: { name: string };
    amount: number;
    method: string;
    period_from: string;
    period_to: string;
    paid_at: string;
    notes?: string;
}

const props = defineProps<{
    payments: {
        data: DoctorPayment[];
        current_page: number;
        last_page: number;
        total: number;
    };
    doctors: Doctor[];
    filters: { doctor_id?: string; from?: string; to?: string };
}>();

const columns = [
    { key: 'paid_at',     label: 'تاريخ الدفع', sortable: true },
    { key: 'doctor',      label: 'الطبيب' },
    { key: 'period',      label: 'الفترة' },
    { key: 'amount',      label: 'المبلغ' },
    { key: 'method',      label: 'الطريقة' },
    { key: 'notes',       label: 'ملاحظات' },
];

const doctorFilter = ref(props.filters.doctor_id ?? '');
const fromFilter   = ref(props.filters.from ?? '');
const toFilter     = ref(props.filters.to ?? '');

function applyFilters() {
    router.get('/dr-payments', {
        doctor_id: doctorFilter.value || undefined,
        from: fromFilter.value || undefined,
        to:   toFilter.value || undefined,
    }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/dr-payments', {
        doctor_id: doctorFilter.value || undefined,
        from: fromFilter.value || undefined,
        to:   toFilter.value || undefined,
        page,
    }, { preserveState: true });
}

const showAdd = ref(false);
const addForm = useForm({
    doctor_id:   '',
    amount:      '' as number | string,
    method:      'cash' as 'cash' | 'transfer',
    period_from: '',
    period_to:   '',
    paid_at:     new Date().toISOString().slice(0, 10),
    notes:       '',
});
function submitAdd() {
    addForm.post('/dr-claims/pay', {
        onSuccess: () => {
 showAdd.value = false; addForm.reset(); 
},
    });
}

const methodLabels: Record<string, string> = { cash: 'نقدي', transfer: 'تحويل' };

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="مدفوعات الأطباء" />

    <!-- Filters -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">سجل مدفوعات الأطباء</h2>
        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="doctorFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">كل الأطباء</option>
                <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
                @click="showAdd = true"
            >
                <PlusCircle class="h-4 w-4" /> تسجيل دفعة
            </button>
        </div>
    </div>

    <DataTable
        :columns="columns"
        :rows="payments.data"
        :current-page="payments.current_page"
        :last-page="payments.last_page"
        :total="payments.total"
        empty-text="لا توجد مدفوعات"
        @page="goToPage"
    >
        <template #cell-doctor="{ row }">
            {{ (row as DoctorPayment).doctor?.name ?? '—' }}
        </template>
        <template #cell-period="{ row }">
            <span class="text-xs text-hospital-text-3">
                {{ (row as DoctorPayment).period_from }} — {{ (row as DoctorPayment).period_to }}
            </span>
        </template>
        <template #cell-amount="{ value }">
            <span class="font-mono text-hospital-success">{{ fmt(value as number) }} ج.م</span>
        </template>
        <template #cell-method="{ value }">
            {{ methodLabels[value as string] ?? value }}
        </template>
    </DataTable>

    <!-- Add Payment Modal -->
    <Modal v-model="showAdd" title="تسجيل دفعة لطبيب" size="md">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">الطبيب <span class="text-hospital-danger">*</span></label>
                    <select v-model="addForm.doctor_id" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="">اختر الطبيب</option>
                        <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <p v-if="addForm.errors.doctor_id" class="mt-1 text-xs text-hospital-danger">{{ addForm.errors.doctor_id }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">من تاريخ</label>
                    <input v-model="addForm.period_from" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">إلى تاريخ</label>
                    <input v-model="addForm.period_to" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">المبلغ (ج.م) <span class="text-hospital-danger">*</span></label>
                    <input v-model.number="addForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="addForm.errors.amount" class="mt-1 text-xs text-hospital-danger">{{ addForm.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">طريقة الدفع</label>
                    <select v-model="addForm.method" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="cash">نقدي</option>
                        <option value="transfer">تحويل بنكي</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">تاريخ الدفع</label>
                    <input v-model="addForm.paid_at" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                    <textarea v-model="addForm.notes" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">تسجيل</button>
            </div>
        </form>
    </Modal>
</template>
