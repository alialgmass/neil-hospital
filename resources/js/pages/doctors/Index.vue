<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PlusCircle, UserCheck, Percent, DollarSign } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';

interface Doctor {
    id: string;
    name: string;
    specialty?: string;
    phone?: string;
    fee_type: 'percentage' | 'fixed' | 'insurance';
    fee_value: number;
    is_active: boolean;
}

const props = defineProps<{
    doctors: { data: Doctor[]; current_page: number; last_page: number; total: number };
    filters: { search?: string };
}>();

const columns = [
    { key: 'name',      label: 'الاسم',      sortable: true },
    { key: 'specialty', label: 'التخصص' },
    { key: 'phone',     label: 'الهاتف' },
    { key: 'fee_type',  label: 'نوع الحساب' },
    { key: 'fee_value', label: 'القيمة' },
    { key: 'is_active', label: 'الحالة' },
];

const activeCount   = computed(() => props.doctors.data.filter((d) => d.is_active).length);
const pctCount      = computed(() => props.doctors.data.filter((d) => d.fee_type === 'percentage').length);
const fixedCount    = computed(() => props.doctors.data.filter((d) => d.fee_type === 'fixed').length);

const search = ref(props.filters.search ?? '');
function applySearch() {
 router.get('/doctors', { search: search.value || undefined }, { preserveState: true }); 
}
function goToPage(page: number) {
 router.get('/doctors', { search: search.value || undefined, page }, { preserveState: true }); 
}

const showAdd = ref(false);
const form = useForm({
    name:      '',
    specialty: '',
    phone:     '',
    fee_type:  'percentage' as 'percentage' | 'fixed' | 'insurance',
    fee_value: 40,
    is_active: true,
});
function submit() {
 form.post('/doctors', { onSuccess: () => {
 showAdd.value = false; form.reset(); form.fee_type = 'percentage'; form.fee_value = 40; form.is_active = true; 
} }); 
}

const feeTypeLabels: Record<string, string> = {
    percentage: 'نسبة مئوية %',
    fixed:      'مبلغ ثابت',
    insurance:  'تأمين صحي (صفر)',
};
</script>

<template>
    <Head title="إدارة الأطباء" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-white">
                <UserCheck class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-blue-600">أطباء نشطون</p>
                <p class="text-2xl font-bold text-blue-700">{{ activeCount }}</p>
                <p class="text-xs text-blue-500">من أصل {{ doctors.total }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-purple-100 bg-purple-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-600 text-white">
                <Percent class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-purple-600">حساب بالنسبة</p>
                <p class="text-2xl font-bold text-purple-700">{{ pctCount }}</p>
                <p class="text-xs text-purple-500">طبيب</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-600 text-white">
                <DollarSign class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-green-600">حساب ثابت</p>
                <p class="text-2xl font-bold text-green-700">{{ fixedCount }}</p>
                <p class="text-xs text-green-500">طبيب</p>
            </div>
        </div>
    </div>

    <div class="mb-5 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">إدارة الأطباء وصلاحياتهم</h2>
            <p class="text-xs text-hospital-muted">تحديد نسبة أو قيمة حصة كل طبيب من الإيرادات</p>
        </div>
        <div class="flex items-center gap-2">
            <SearchBar v-model="search" placeholder="بحث بالاسم..." @update:model-value="applySearch" />
            <button class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="showAdd = true">
                <PlusCircle class="h-4 w-4" /> طبيب جديد
            </button>
        </div>
    </div>

    <DataTable :columns="columns" :rows="doctors.data" :current-page="doctors.current_page" :last-page="doctors.last_page" :total="doctors.total" empty-text="لا يوجد أطباء" @page="goToPage">
        <template #cell-fee_type="{ value }">{{ feeTypeLabels[value as string] ?? value }}</template>
        <template #cell-fee_value="{ value, row }">
            <span v-if="(row as Doctor).fee_type === 'percentage'">{{ value }}%</span>
            <span v-else-if="(row as Doctor).fee_type === 'fixed'" class="font-mono">{{ Number(value).toLocaleString('ar-EG') }} ج.م</span>
            <span v-else class="text-hospital-text-2">—</span>
        </template>
        <template #cell-is_active="{ value }">
            <Badge :variant="value ? 'active' : 'inactive'" />
        </template>
    </DataTable>

    <Modal v-model="showAdd" title="إضافة طبيب جديد" size="md">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium">الاسم</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.name }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">التخصص</label>
                    <input v-model="form.specialty" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الهاتف</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نوع الحساب</label>
                    <select v-model="form.fee_type" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="percentage">نسبة مئوية %</option>
                        <option value="fixed">مبلغ ثابت لكل حالة</option>
                        <option value="insurance">تأمين صحي (صفر)</option>
                    </select>
                </div>
                <div v-if="form.fee_type !== 'insurance'">
                    <label class="mb-1 block text-sm font-medium">{{ form.fee_type === 'percentage' ? 'النسبة %' : 'المبلغ الثابت' }}</label>
                    <input v-model.number="form.fee_value" type="number" min="0" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">إضافة</button>
            </div>
        </form>
    </Modal>
</template>
