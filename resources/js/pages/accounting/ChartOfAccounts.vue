<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { PlusCircle, Pencil } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface Account {
    id: string;
    code: string;
    name: string;
    group: string;
    nature: string;
    parent_id?: string;
    balance: number;
    is_active: boolean;
}

const props = defineProps<{
    accounts: Account[];
}>();

const groupLabels: Record<string, string> = {
    assets:      'أصول',
    liabilities: 'خصوم',
    equity:      'حقوق ملكية',
    revenues:    'إيرادات',
    expenses:    'مصروفات',
};
const natureLabels: Record<string, string> = {
    debit:  'مدين',
    credit: 'دائن',
};

// Group accounts by group for display
const groups = ['assets', 'liabilities', 'equity', 'revenues', 'expenses'] as const;
const byGroup = computed(() => {
    const map: Record<string, Account[]> = {};
    groups.forEach(g => {
 map[g] = []; 
});
    props.accounts.forEach(a => {
        if (map[a.group]) {
 map[a.group].push(a); 
}
    });

    return map;
});

// Parent accounts for selector
const parentOptions = computed(() => props.accounts.filter(a => a.is_active));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

// Add form
const showAdd = ref(false);
const addForm = useForm({
    code:      '',
    name:      '',
    group:     'assets' as string,
    nature:    'debit' as string,
    parent_id: '',
});
function submitAdd() {
    addForm.post('/accounts', {
        onSuccess: () => {
 showAdd.value = false; addForm.reset(); 
},
    });
}

// Edit form
const showEdit   = ref(false);
const editTarget = ref<Account | null>(null);
const editForm   = useForm({
    name:      '',
    group:     'assets' as string,
    nature:    'debit' as string,
    parent_id: '',
    is_active: true,
});
function openEdit(account: Account) {
    editTarget.value  = account;
    editForm.name      = account.name;
    editForm.group     = account.group;
    editForm.nature    = account.nature;
    editForm.parent_id = account.parent_id ?? '';
    editForm.is_active = account.is_active;
    showEdit.value = true;
}
function submitEdit() {
    if (!editTarget.value) {
 return; 
}

    editForm.put(`/accounts/${editTarget.value.id}`, {
        onSuccess: () => {
 showEdit.value = false; 
},
    });
}
</script>

<template>
    <Head title="الدليل المحاسبي" />

    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[15px] font-bold text-hospital-text">دليل الحسابات</h2>
            <p class="text-[10px] text-hospital-text-3">إدارة وتنظيم الهيكل المالي للمستشفى</p>
        </div>
        <button
            class="btn btn-p flex items-center gap-1.5 rounded-[7px] bg-hospital-primary px-[13px] py-[7.5px] text-[12px] font-bold text-white transition-all hover:bg-hospital-primary-light active:scale-95 shadow-sm"
            @click="showAdd = true"
        >
            <PlusCircle class="h-3.5 w-3.5" />
            <span>إضافة حساب</span>
        </button>
    </div>

    <!-- Accounts by Group -->
    <div class="space-y-6">
        <div
            v-for="group in groups"
            :key="group"
            class="card rounded-[var(--rl)] border border-hospital-border bg-white overflow-hidden [box-shadow:var(--sh)]"
        >
            <div class="card-hd flex items-center justify-between border-b border-hospital-border bg-hospital-surface-2 px-4 py-3">
                <h3 class="font-bold text-[13px] text-hospital-text">{{ groupLabels[group] }}</h3>
                <span class="text-[10px] font-bold text-hospital-primary bg-hospital-primary-pale px-2 py-0.5 rounded-full uppercase">
                    {{ byGroup[group].length }} حساب
                </span>
            </div>

            <div v-if="byGroup[group].length === 0" class="p-8 text-center text-[12px] text-hospital-text-3 italic">
                لا توجد حسابات مسجلة في هذا القسم حالياً.
            </div>

            <div v-else class="tbl-wrap overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-hospital-surface-2">
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b">الكود</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b">اسم الحساب</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b text-center">الطبيعة</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b text-center">الحالة</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b text-left">الرصيد</th>
                            <th class="px-4 py-2.5 text-[11px] font-bold text-hospital-text-3 border-b text-left w-20">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="account in byGroup[group]"
                            :key="account.id"
                            class="hover:bg-hospital-primary-pale transition-colors border-b border-hospital-border/50 last:border-0"
                            :class="{ 'opacity-60 bg-gray-50/50': !account.is_active }"
                        >
                            <td class="px-4 py-3 text-[11px] font-mono font-bold text-hospital-text-2">{{ account.code }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col">
                                    <span class="text-[12px] font-bold text-hospital-text">{{ account.name }}</span>
                                    <span v-if="account.parent_id" class="text-[9px] text-hospital-text-3">تابع لحساب: {{ accounts.find(a => a.id === account.parent_id)?.name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span
                                    class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                                    :class="account.nature === 'debit' ? 'bg-blue-50 text-blue-600 border border-blue-100' : 'bg-emerald-50 text-emerald-600 border border-emerald-100'"
                                >
                                    {{ natureLabels[account.nature] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center">
                                    <span
                                        class="h-2 w-2 rounded-full ml-1.5"
                                        :class="account.is_active ? 'bg-hospital-success animate-pulse' : 'bg-hospital-danger'"
                                    ></span>
                                    <span class="text-[10px] font-bold" :class="account.is_active ? 'text-hospital-success' : 'text-hospital-danger'">
                                        {{ account.is_active ? 'نشط' : 'متوقف' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-left font-mono">
                                <span class="text-[12px] font-bold" :class="account.balance >= 0 ? 'text-hospital-text' : 'text-hospital-danger'">
                                    {{ fmt(account.balance) }} 
                                    <span class="text-[9px] text-hospital-text-3 mr-0.5">ج.م</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-left">
                                <button
                                    class="rounded-[5px] border border-hospital-border p-1.5 text-hospital-text-3 hover:bg-hospital-primary-pale hover:text-hospital-primary hover:border-hospital-primary transition-all"
                                    @click="openEdit(account)"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة حساب جديد" size="sm">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div>
                <label class="mb-1 block text-sm font-medium">كود الحساب <span class="text-hospital-danger">*</span></label>
                <input v-model="addForm.code" type="text" placeholder="مثال: 1010" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="addForm.errors.code" class="mt-1 text-xs text-hospital-danger">{{ addForm.errors.code }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">اسم الحساب <span class="text-hospital-danger">*</span></label>
                <input v-model="addForm.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                <p v-if="addForm.errors.name" class="mt-1 text-xs text-hospital-danger">{{ addForm.errors.name }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">المجموعة</label>
                    <select v-model="addForm.group" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option v-for="(label, key) in groupLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الطبيعة</label>
                    <select v-model="addForm.nature" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="debit">مدين</option>
                        <option value="credit">دائن</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">الحساب الأب (اختياري)</label>
                <select v-model="addForm.parent_id" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">— بدون حساب أب —</option>
                    <option v-for="a in parentOptions" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                </select>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">إضافة</button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" title="تعديل الحساب" size="sm">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div>
                <label class="mb-1 block text-sm font-medium">اسم الحساب <span class="text-hospital-danger">*</span></label>
                <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">المجموعة</label>
                    <select v-model="editForm.group" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option v-for="(label, key) in groupLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الطبيعة</label>
                    <select v-model="editForm.nature" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="debit">مدين</option>
                        <option value="credit">دائن</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">الحساب الأب (اختياري)</label>
                <select v-model="editForm.parent_id" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                    <option value="">— بدون حساب أب —</option>
                    <option v-for="a in parentOptions" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input id="is_active" v-model="editForm.is_active" type="checkbox" class="rounded border-hospital-border text-hospital-primary focus:ring-hospital-primary" />
                <label for="is_active" class="text-sm">حساب نشط</label>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showEdit = false">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">حفظ</button>
            </div>
        </form>
    </Modal>
</template>
