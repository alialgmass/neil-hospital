<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { PlusCircle, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface InsuranceCompany {
    id: string;
    name: string;
    code?: string;
    phone?: string;
    coverage_pct: number;
    disc_pct: number;
    contact_person?: string;
    email?: string;
    contract_no?: string;
    status: 'active' | 'inactive';
}

defineProps<{
    companies: {
        data: InsuranceCompany[];
        current_page: number;
        last_page: number;
        total: number;
    };
}>();

const columns = [
    { key: 'name',           label: 'اسم الشركة' },
    { key: 'code',           label: 'الكود' },
    { key: 'phone',          label: 'الهاتف' },
    { key: 'coverage_pct',   label: 'نسبة التغطية' },
    { key: 'disc_pct',       label: 'نسبة الخصم' },
    { key: 'contact_person', label: 'المسؤول' },
    { key: 'status',         label: 'الحالة' },
    { key: 'actions',        label: '' },
];

// Add form
const showAdd = ref(false);
const addForm = useForm({
    name:           '',
    code:           '',
    phone:          '',
    coverage_pct:   80 as number,
    disc_pct:       0 as number,
    contact_person: '',
    email:          '',
    contract_no:    '',
});
function submitAdd() {
    addForm.post('/insurance', {
        onSuccess: () => {
 showAdd.value = false; addForm.reset(); 
},
    });
}

// Edit form
const showEdit   = ref(false);
const editTarget = ref<InsuranceCompany | null>(null);
const editForm   = useForm({
    name:           '',
    phone:          '',
    coverage_pct:   80 as number,
    disc_pct:       0 as number,
    contact_person: '',
    email:          '',
    status:         'active' as 'active' | 'inactive',
});
function openEdit(company: InsuranceCompany) {
    editTarget.value  = company;
    editForm.name           = company.name;
    editForm.phone          = company.phone ?? '';
    editForm.coverage_pct   = company.coverage_pct;
    editForm.disc_pct       = company.disc_pct;
    editForm.contact_person = company.contact_person ?? '';
    editForm.email          = company.email ?? '';
    editForm.status         = company.status;
    showEdit.value = true;
}
function submitEdit() {
    if (!editTarget.value) {
 return; 
}

    editForm.put(`/insurance/${editTarget.value.id}`, {
        onSuccess: () => {
 showEdit.value = false; 
},
    });
}

function goToPage(page: number) {
    router.get('/insurance', { page }, { preserveState: true });
}
</script>

<template>
    <Head title="شركات التأمين" />

    <!-- Header -->
    <div class="mb-5 flex items-center justify-between">
        <h2 class="text-lg font-bold text-hospital-text">شركات التأمين</h2>
        <button
            class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 transition-colors"
            @click="showAdd = true"
        >
            <PlusCircle class="h-4 w-4" /> شركة جديدة
        </button>
    </div>

    <DataTable
        :columns="columns"
        :rows="companies.data"
        :current-page="companies.current_page"
        :last-page="companies.last_page"
        :total="companies.total"
        empty-text="لا توجد شركات تأمين"
        @page="goToPage"
    >
        <template #cell-coverage_pct="{ value }">
            {{ value }}%
        </template>
        <template #cell-disc_pct="{ value }">
            {{ value }}%
        </template>
        <template #cell-status="{ value }">
            <span
                class="rounded-full px-2 py-0.5 text-xs font-medium"
                :class="value === 'active' ? 'bg-hospital-success/10 text-hospital-success' : 'bg-hospital-muted/20 text-hospital-text-3'"
            >
                {{ value === 'active' ? 'نشطة' : 'متوقفة' }}
            </span>
        </template>
        <template #cell-actions="{ row }">
            <button
                class="rounded p-1.5 text-hospital-text-3 hover:bg-hospital-bg hover:text-hospital-primary transition-colors"
                @click="openEdit(row as InsuranceCompany)"
            >
                <Pencil class="h-4 w-4" />
            </button>
        </template>
    </DataTable>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة شركة تأمين" size="md">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">اسم الشركة <span class="text-hospital-danger">*</span></label>
                    <input v-model="addForm.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="addForm.errors.name" class="mt-1 text-xs text-hospital-danger">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الكود</label>
                    <input v-model="addForm.code" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">رقم العقد</label>
                    <input v-model="addForm.contract_no" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الهاتف</label>
                    <input v-model="addForm.phone" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">البريد الإلكتروني</label>
                    <input v-model="addForm.email" type="email" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نسبة التغطية %</label>
                    <input v-model.number="addForm.coverage_pct" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نسبة الخصم %</label>
                    <input v-model.number="addForm.disc_pct" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">المسؤول / مسؤول التواصل</label>
                    <input v-model="addForm.contact_person" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">إضافة</button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" title="تعديل شركة التأمين" size="md">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">اسم الشركة <span class="text-hospital-danger">*</span></label>
                    <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="editForm.errors.name" class="mt-1 text-xs text-hospital-danger">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الهاتف</label>
                    <input v-model="editForm.phone" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">البريد الإلكتروني</label>
                    <input v-model="editForm.email" type="email" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نسبة التغطية %</label>
                    <input v-model.number="editForm.coverage_pct" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">نسبة الخصم %</label>
                    <input v-model.number="editForm.disc_pct" type="number" min="0" max="100" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">المسؤول</label>
                    <input v-model="editForm.contact_person" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الحالة</label>
                    <select v-model="editForm.status" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="active">نشطة</option>
                        <option value="inactive">متوقفة</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showEdit = false">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">حفظ</button>
            </div>
        </form>
    </Modal>
</template>
