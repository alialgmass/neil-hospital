<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Building2, PlusCircle, TrendingDown, Users } from 'lucide-vue-next';
import { ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import Modal from '@/components/shared/Modal.vue';

interface Supplier {
    id: string;
    name: string;
    contact?: string;
    type?: string;
    phone?: string;
    email?: string;
    address?: string;
    tax_no?: string;
    terms?: string;
    notes?: string;
    balance: number;
    is_active: boolean;
}

const props = defineProps<{
    suppliers: { data: Supplier[]; current_page: number; last_page: number; total: number };
    filters: { search?: string };
    stats: { total_suppliers: number; total_purchases: number; total_due: number };
}>();

const supplierTypes = ['أدوية وقطرات', 'مستلزمات جراحية', 'معدات وأجهزة', 'مستهلكات', 'متنوع'];
const paymentTerms = ['فوري', '30 يوم', '60 يوم', '90 يوم'];

const search = ref(props.filters.search ?? '');
function applySearch() {
    router.get('/suppliers', { search: search.value || undefined }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/suppliers', { search: search.value || undefined, page }, { preserveState: true });
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

// Add
const showAdd = ref(false);
const addForm = useForm({
    name: '', contact: '', type: '', phone: '', email: '',
    address: '', tax_no: '', terms: '', notes: '',
});
function submitAdd() {
    addForm.post('/suppliers', {
        onSuccess: () => { showAdd.value = false; addForm.reset(); },
    });
}

// Edit
const showEdit = ref(false);
const editingId = ref('');
const editForm = useForm({
    name: '', contact: '', type: '', phone: '', email: '',
    address: '', tax_no: '', terms: '', notes: '', is_active: true,
});
function openEdit(s: Supplier) {
    editingId.value = s.id;
    editForm.name = s.name;
    editForm.contact = s.contact ?? '';
    editForm.type = s.type ?? '';
    editForm.phone = s.phone ?? '';
    editForm.email = s.email ?? '';
    editForm.address = s.address ?? '';
    editForm.tax_no = s.tax_no ?? '';
    editForm.terms = s.terms ?? '';
    editForm.notes = s.notes ?? '';
    editForm.is_active = s.is_active;
    showEdit.value = true;
}
function submitEdit() {
    editForm.put(`/suppliers/${editingId.value}`, {
        onSuccess: () => { showEdit.value = false; },
    });
}
</script>

<template>
    <Head title="الموردون" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الموردون</h1>
        <p class="mt-0.5 text-sm text-t3">إدارة موردي المستلزمات والأدوية والمعدات</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Users class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الموردين</p>
                <p class="text-xl font-bold text-t">{{ stats.total_suppliers }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Building2 class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المشتريات</p>
                <p class="text-xl font-bold text-t">{{ fmt(stats.total_purchases) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <TrendingDown class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">المستحق للموردين</p>
                <p class="text-xl font-bold" :class="stats.total_due > 0 ? 'text-d' : 'text-s'">{{ fmt(stats.total_due) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <input
                v-model="search"
                type="search"
                placeholder="بحث بالاسم..."
                class="input-field w-56"
                @keyup.enter="applySearch"
            />
            <button class="btn-secondary" @click="applySearch">بحث</button>
        </div>
        <button class="btn-primary flex items-center gap-1.5" @click="showAdd = true">
            <PlusCircle class="h-4 w-4" />
            مورد جديد
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المورد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">النوع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المسؤول</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الهاتف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">شروط الدفع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المستحق</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="s in suppliers.data" :key="s.id" class="hover:bg-sf2">
                    <td class="px-4 py-3">
                        <p class="font-medium text-t">{{ s.name }}</p>
                        <p v-if="s.email" class="text-xs text-t3">{{ s.email }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span v-if="s.type" class="rounded-full bg-pp px-2 py-0.5 text-xs text-p">{{ s.type }}</span>
                        <span v-else class="text-t3">—</span>
                    </td>
                    <td class="px-4 py-3 text-t2">{{ s.contact || '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-t2">{{ s.phone || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ s.terms || '—' }}</td>
                    <td class="px-4 py-3 font-mono font-medium" :class="Number(s.balance) > 0 ? 'text-d' : 'text-s'">
                        {{ fmt(s.balance) }} ج.م
                    </td>
                    <td class="px-4 py-3">
                        <Badge :variant="s.is_active ? 'active' : 'inactive'" />
                    </td>
                    <td class="px-4 py-3">
                        <button class="rounded-lg border border-br px-3 py-1 text-xs text-t2 hover:border-p/40 hover:bg-pp hover:text-p transition-colors" @click="openEdit(s)">
                            تعديل
                        </button>
                    </td>
                </tr>
                <tr v-if="suppliers.data.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="8">لا يوجد موردون</td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="suppliers.last_page > 1" class="flex items-center justify-between border-t border-br px-4 py-3">
            <span class="text-xs text-t3">إجمالي {{ suppliers.total }} مورد</span>
            <div class="flex gap-1">
                <button
                    v-for="p in suppliers.last_page"
                    :key="p"
                    class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === suppliers.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'"
                    @click="goToPage(p)"
                >
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة مورد جديد" size="lg">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">اسم المورد / الشركة <span class="text-d">*</span></label>
                    <input v-model="addForm.name" type="text" class="input-field" />
                    <p v-if="addForm.errors.name" class="form-error">{{ addForm.errors.name }}</p>
                </div>
                <div>
                    <label class="form-label">نوع المورد</label>
                    <select v-model="addForm.type" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="t in supplierTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">المسؤول / التواصل</label>
                    <input v-model="addForm.contact" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">الهاتف</label>
                    <input v-model="addForm.phone" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">البريد الإلكتروني</label>
                    <input v-model="addForm.email" type="email" class="input-field" />
                </div>
                <div>
                    <label class="form-label">شروط الدفع</label>
                    <select v-model="addForm.terms" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="t in paymentTerms" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">الرقم الضريبي</label>
                    <input v-model="addForm.tax_no" type="text" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="form-label">العنوان</label>
                    <input v-model="addForm.address" type="text" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="addForm.notes" rows="2" class="input-field" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="btn-primary">
                    {{ addForm.processing ? 'جارٍ الحفظ...' : 'إضافة المورد' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" title="تعديل بيانات المورد" size="lg">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">اسم المورد / الشركة <span class="text-d">*</span></label>
                    <input v-model="editForm.name" type="text" class="input-field" />
                    <p v-if="editForm.errors.name" class="form-error">{{ editForm.errors.name }}</p>
                </div>
                <div>
                    <label class="form-label">نوع المورد</label>
                    <select v-model="editForm.type" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="t in supplierTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">المسؤول / التواصل</label>
                    <input v-model="editForm.contact" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">الهاتف</label>
                    <input v-model="editForm.phone" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">البريد الإلكتروني</label>
                    <input v-model="editForm.email" type="email" class="input-field" />
                </div>
                <div>
                    <label class="form-label">شروط الدفع</label>
                    <select v-model="editForm.terms" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="t in paymentTerms" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">الرقم الضريبي</label>
                    <input v-model="editForm.tax_no" type="text" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="form-label">العنوان</label>
                    <input v-model="editForm.address" type="text" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="editForm.notes" rows="2" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="flex cursor-pointer items-center gap-2">
                        <input v-model="editForm.is_active" type="checkbox" class="h-4 w-4 rounded border-br" />
                        <span class="text-sm font-medium text-t">مورد نشط</span>
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showEdit = false">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="btn-primary">
                    {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
