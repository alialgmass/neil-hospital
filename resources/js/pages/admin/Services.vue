<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, PlusCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';

interface Service {
    id: string;
    name: string;
    dept: string;
    price: number;
    ins_price: number;
    center_type: 'pct' | 'fixed';
    center_val: number;
    center_share: number;
    dr_share: number;
    status: 'active' | 'inactive';
    revenue_account_id: string | null;
}

interface RevenueAccount {
    id: string;
    code: string;
    name: string;
}

const props = defineProps<{
    services: { data: Service[]; current_page: number; last_page: number; total: number };
    filters: { dept?: string; search?: string };
    revenueAccounts: RevenueAccount[];
}>();

const columns = [
    { key: 'name',         label: 'الخدمة',     sortable: true },
    { key: 'dept',         label: 'القسم' },
    { key: 'price',        label: 'السعر' },
    { key: 'ins_price',    label: 'سعر التأمين' },
    { key: 'center_share', label: 'حصة المركز' },
    { key: 'dr_share',     label: 'حصة الطبيب' },
    { key: 'status',       label: 'الحالة' },
    { key: '_actions',     label: '' },
];

const deptFilter = ref(props.filters.dept   ?? '');
const search     = ref(props.filters.search ?? '');

function applyFilters() {
    router.get('/services', { dept: deptFilter.value || undefined, search: search.value || undefined }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/services', { dept: deptFilter.value || undefined, search: search.value || undefined, page }, { preserveState: true });
}

const showAdd  = ref(false);
const editId   = ref<string | null>(null);

const form = useForm({
    name:               '',
    dept:               'clinic' as string,
    price:              0,
    ins_price:          0,
    center_type:        'pct' as 'pct' | 'fixed',
    center_val:         40,
    duration_mins:      30,
    status:             'active' as string,
    revenue_account_id: null as string | null,
});

function openAdd() {
    editId.value = null;
    form.reset();
    form.dept         = 'clinic';
    form.center_type  = 'pct';
    form.center_val   = 40;
    form.duration_mins = 30;
    form.status       = 'active';
    form.revenue_account_id = null;
    showAdd.value = true;
}

function openEdit(service: Service) {
    editId.value = service.id;
    form.name               = service.name;
    form.dept               = service.dept;
    form.price              = Number(service.price);
    form.ins_price          = Number(service.ins_price);
    form.center_type        = service.center_type;
    form.center_val         = Number(service.center_val);
    form.duration_mins      = 30;
    form.status             = service.status;
    form.revenue_account_id = service.revenue_account_id;
    showAdd.value = true;
}

function submit() {
    if (editId.value) {
        form.put(`/services/${editId.value}`, {
            onSuccess: () => {
                showAdd.value = false;
                editId.value = null;
            },
        });
    } else {
        form.post('/services', {
            onSuccess: () => {
                showAdd.value = false;
            },
        });
    }
}

const deptLabels: Record<string, string> = {
    clinic: 'عيادة',
    labs: 'فحوصات',
    surgery: 'عمليات',
    lasik: 'ليزك',
    laser: 'ليزر',
};

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG') + ' ج.م';
}
</script>

<template>
    <Head title="الخدمات والأسعار" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">الخدمات وقوائم الأسعار</h2>
        <div class="flex flex-wrap items-center gap-2">
            <SearchBar v-model="search" placeholder="ابحث بالاسم..." @update:model-value="applyFilters" />
            <select v-model="deptFilter" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters">
                <option value="">كل الأقسام</option>
                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <button class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="openAdd">
                <PlusCircle class="h-4 w-4" /> خدمة جديدة
            </button>
        </div>
    </div>

    <DataTable :columns="columns" :rows="services.data" :current-page="services.current_page" :last-page="services.last_page" :total="services.total" empty-text="لا توجد خدمات" @page="goToPage">
        <template #cell-dept="{ value }">{{ deptLabels[value as string] ?? value }}</template>
        <template #cell-price="{ value }"><span class="font-mono">{{ fmt(Number(value)) }}</span></template>
        <template #cell-ins_price="{ value }"><span class="font-mono">{{ fmt(Number(value)) }}</span></template>
        <template #cell-center_share="{ value }"><span class="font-mono text-hospital-warning">{{ fmt(Number(value)) }}</span></template>
        <template #cell-dr_share="{ value }"><span class="font-mono text-hospital-primary">{{ fmt(Number(value)) }}</span></template>
        <template #cell-status="{ value }">
            <Badge :variant="(value as 'active' | 'inactive')" />
        </template>
        <template #cell-_actions="{ row }">
            <button class="rounded p-1 text-hospital-muted hover:text-hospital-primary" @click="openEdit(row as Service)">
                <Pencil class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <Modal v-model="showAdd" :title="editId ? 'تعديل الخدمة' : 'إضافة خدمة جديدة'" size="lg">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">اسم الخدمة</label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">القسم</label>
                    <select v-model="form.dept" :disabled="!!editId" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none disabled:opacity-60">
                        <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">المدة (دقيقة)</label>
                    <input v-model.number="form.duration_mins" type="number" min="1" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">السعر (ج.م)</label>
                    <input v-model.number="form.price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">سعر التأمين (ج.م)</label>
                    <input v-model.number="form.ins_price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نوع حصة المركز</label>
                    <select v-model="form.center_type" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="pct">نسبة مئوية %</option>
                        <option value="fixed">مبلغ ثابت</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">{{ form.center_type === 'pct' ? 'النسبة %' : 'المبلغ الثابت' }}</label>
                    <input v-model.number="form.center_val" type="number" min="0" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">حساب الإيراد <span class="text-xs text-hospital-muted">(اختياري — يُستخدم في القيود المحاسبية)</span></label>
                    <select v-model="form.revenue_account_id" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option :value="null">— افتراضي حسب القسم —</option>
                        <option v-for="acc in revenueAccounts" :key="acc.id" :value="acc.id">
                            {{ acc.code }} — {{ acc.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.revenue_account_id" class="mt-1 text-xs text-hospital-danger">{{ form.errors.revenue_account_id }}</p>
                </div>
                <div v-if="editId">
                    <label class="mb-1 block text-sm font-medium">الحالة</label>
                    <select v-model="form.status" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="active">نشط</option>
                        <option value="inactive">متوقف</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">
                    {{ editId ? 'حفظ التغييرات' : 'إضافة' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
