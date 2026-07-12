<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Briefcase,
    PlusCircle,
    UserCheck,
    UserMinus,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface Role {
    id: number;
    name: string;
}

interface Employee {
    id: string;
    employee_no: string;
    name: string;
    national_id?: string;
    phone?: string;
    email?: string;
    dept: string;
    position: string;
    hire_date: string;
    base_salary: number;
    allowances: number;
    contract_type: string;
    status: string;
    notes?: string;
}

const props = defineProps<{
    employees: {
        data: Employee[];
        current_page: number;
        last_page: number;
        total: number;
    };
    depts: string[];
    dept_options: string[];
    filters: { search?: string; dept?: string; status?: string };
    stats: {
        total: number;
        active: number;
        on_leave: number;
        inactive: number;
    };
    next_employee_no: string;
    roles: Role[];
}>();

const deptOptions = computed(() => props.dept_options);
const contractOptions = [
    { value: 'full_time', label: 'دوام كامل' },
    { value: 'part_time', label: 'دوام جزئي' },
    { value: 'contract', label: 'عقد' },
];
const statusOptions = [
    { value: 'active', label: 'نشط' },
    { value: 'inactive', label: 'غير نشط' },
    { value: 'on_leave', label: 'في إجازة' },
];
const statusConfig: Record<string, { label: string; class: string }> = {
    active: { label: 'نشط', class: 'bg-sp text-s' },
    inactive: { label: 'غير نشط', class: 'bg-sf2 text-t3' },
    on_leave: { label: 'في إجازة', class: 'bg-wp text-w' },
};
const contractLabel: Record<string, string> = {
    full_time: 'دوام كامل',
    part_time: 'دوام جزئي',
    contract: 'عقد',
};

const search = ref(props.filters.search ?? '');
const deptFilter = ref(props.filters.dept ?? '');
const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(
        '/employees',
        {
            search: search.value || undefined,
            dept: deptFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true },
    );
}
function goToPage(page: number) {
    router.get(
        '/employees',
        {
            search: search.value || undefined,
            dept: deptFilter.value || undefined,
            status: statusFilter.value || undefined,
            page,
        },
        { preserveState: true },
    );
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 0 });
}

// Add
const showAdd = ref(false);
const addForm = useForm({
    employee_no: props.next_employee_no,
    name: '',
    national_id: '',
    phone: '',
    email: '',
    dept: '',
    position: '',
    hire_date: '',
    base_salary: '',
    allowances: '',
    password: '',
    role: '',
    contract_type: 'full_time',
    status: 'active',
    notes: '',
});
function submitAdd() {
    addForm.post('/employees', {
        onSuccess: () => {
            showAdd.value = false;
            addForm.reset();
            addForm.employee_no = props.next_employee_no;
        },
    });
}

// Edit
const showEdit = ref(false);
const editingId = ref('');
const editForm = useForm({
    name: '',
    national_id: '',
    phone: '',
    email: '',
    dept: '',
    position: '',
    hire_date: '',
    base_salary: '',
    allowances: '',
    password: '',
    role: '',
    contract_type: 'full_time',
    status: 'active',
    notes: '',
});
function openEdit(e: Employee) {
    editingId.value = e.id;
    editForm.name = e.name;
    editForm.national_id = e.national_id ?? '';
    editForm.phone = e.phone ?? '';
    editForm.email = e.email ?? '';
    editForm.dept = e.dept;
    editForm.position = e.position;
    editForm.hire_date = e.hire_date;
    editForm.base_salary = String(e.base_salary);
    editForm.allowances = String(e.allowances);
    editForm.contract_type = e.contract_type;
    editForm.status = e.status;
    editForm.notes = e.notes ?? '';
    showEdit.value = true;
}
function submitEdit() {
    editForm.put(`/employees/${editingId.value}`, {
        onSuccess: () => {
            showEdit.value = false;
        },
    });
}
</script>

<template>
    <Head title="الموظفون" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الموظفون</h1>
        <p class="mt-0.5 text-sm text-t3">
            إدارة بيانات موظفي المستشفى وعقودهم
        </p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-4 gap-4">
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp"
            >
                <Users class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الموظفين</p>
                <p class="text-xl font-bold text-t">{{ stats.total }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp"
            >
                <UserCheck class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">نشطون</p>
                <p class="text-xl font-bold text-s">{{ stats.active }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp"
            >
                <Briefcase class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">في إجازة</p>
                <p class="text-xl font-bold text-w">{{ stats.on_leave }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp"
            >
                <UserMinus class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">غير نشطين</p>
                <p class="text-xl font-bold text-d">{{ stats.inactive }}</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <input
                v-model="search"
                type="search"
                placeholder="بحث بالاسم أو الرقم..."
                class="input-field w-52"
                @keyup.enter="applyFilters"
            />
            <select
                v-model="deptFilter"
                class="input-field w-36"
                @change="applyFilters"
            >
                <option value="">كل الأقسام</option>
                <option v-for="d in depts" :key="d" :value="d">{{ d }}</option>
            </select>
            <select
                v-model="statusFilter"
                class="input-field w-32"
                @change="applyFilters"
            >
                <option value="">كل الحالات</option>
                <option
                    v-for="s in statusOptions"
                    :key="s.value"
                    :value="s.value"
                >
                    {{ s.label }}
                </option>
            </select>
        </div>
        <button
            class="btn-primary flex items-center gap-1.5"
            @click="showAdd = true"
        >
            <PlusCircle class="h-4 w-4" />
            موظف جديد
        </button>
    </div>

    <!-- Table -->
    <div
        class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]"
    >
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الموظف
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        القسم
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الهاتف
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        تاريخ التعيين
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الراتب الأساسي
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        نوع العقد
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الحالة
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        إجراء
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr
                    v-for="e in employees.data"
                    :key="e.id"
                    class="transition-colors hover:bg-sf2"
                >
                    <td class="px-4 py-3">
                        <p class="font-medium text-t">{{ e.name }}</p>
                        <p class="font-mono text-xs text-t3">
                            {{ e.employee_no }} · {{ e.position }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <span
                            class="rounded-full bg-pp px-2 py-0.5 text-xs text-p"
                            >{{ e.dept }}</span
                        >
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-t2">
                        {{ e.phone || '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-t2">{{ e.hire_date }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-t">
                        {{ fmt(e.base_salary) }} ج.م
                    </td>
                    <td class="px-4 py-3 text-xs text-t2">
                        {{ contractLabel[e.contract_type] ?? e.contract_type }}
                    </td>
                    <td class="px-4 py-3">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="
                                statusConfig[e.status]?.class ??
                                'bg-sf2 text-t3'
                            "
                        >
                            {{ statusConfig[e.status]?.label ?? e.status }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <button
                            class="rounded-lg border border-br px-3 py-1 text-xs text-t2 transition-colors hover:border-p/40 hover:bg-pp hover:text-p"
                            @click="openEdit(e)"
                        >
                            تعديل
                        </button>
                    </td>
                </tr>
                <tr v-if="employees.data.length === 0">
                    <td class="px-4 py-12 text-center text-t3" colspan="8">
                        لا يوجد موظفون
                    </td>
                </tr>
            </tbody>
        </table>
        <div
            v-if="employees.last_page > 1"
            class="flex items-center justify-between border-t border-br px-4 py-3"
        >
            <span class="text-xs text-t3"
                >إجمالي {{ employees.total }} موظف</span
            >
            <div class="flex gap-1">
                <button
                    v-for="p in employees.last_page"
                    :key="p"
                    class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="
                        p === employees.current_page
                            ? 'bg-p text-white'
                            : 'text-t2 hover:bg-sf2'
                    "
                    @click="goToPage(p)"
                >
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة موظف جديد" size="lg">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">رقم الموظف</label>
                    <input
                        v-model="addForm.employee_no"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.employee_no" class="form-error">
                        {{ addForm.errors.employee_no }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >الاسم <span class="text-d">*</span></label
                    >
                    <input
                        v-model="addForm.name"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.name" class="form-error">
                        {{ addForm.errors.name }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >القسم <span class="text-d">*</span></label
                    >
                    <select v-model="addForm.dept" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="d in deptOptions" :key="d" :value="d">
                            {{ d }}
                        </option>
                    </select>
                    <p v-if="addForm.errors.dept" class="form-error">
                        {{ addForm.errors.dept }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >المسمى الوظيفي <span class="text-d">*</span></label
                    >
                    <input
                        v-model="addForm.position"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.position" class="form-error">
                        {{ addForm.errors.position }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الهاتف</label>
                    <input
                        v-model="addForm.phone"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.phone" class="form-error">
                        {{ addForm.errors.phone }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الرقم القومي</label>
                    <input
                        v-model="addForm.national_id"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.national_id" class="form-error">
                        {{ addForm.errors.national_id }}
                    </p>
                </div>
                <div>
                    <label class="form-label">البريد الإلكتروني</label>
                    <input
                        v-model="addForm.email"
                        type="email"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.email" class="form-error">
                        {{ addForm.errors.email }}
                    </p>
                </div>
                <div>
                    <label class="form-label">دور المستخدم</label>
                    <select v-model="addForm.role" class="input-field">
                        <option value="">بدون صلاحيات</option>
                        <option
                            v-for="role in roles"
                            :key="role.id"
                            :value="role.name"
                        >
                            {{ role.name }}
                        </option>
                    </select>
                    <p v-if="addForm.errors.role" class="form-error">
                        {{ addForm.errors.role }}
                    </p>
                </div>
                <div>
                    <label class="form-label">كلمة مرور المستخدم</label>
                    <input
                        v-model="addForm.password"
                        type="password"
                        class="input-field"
                        placeholder="افتراضيًا رقم الموظف"
                    />
                    <p v-if="addForm.errors.password" class="form-error">
                        {{ addForm.errors.password }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >تاريخ التعيين <span class="text-d">*</span></label
                    >
                    <input
                        v-model="addForm.hire_date"
                        type="date"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.hire_date" class="form-error">
                        {{ addForm.errors.hire_date }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الراتب الأساسي (ج.م)</label>
                    <input
                        v-model="addForm.base_salary"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.base_salary" class="form-error">
                        {{ addForm.errors.base_salary }}
                    </p>
                </div>
                <div>
                    <label class="form-label">البدلات (ج.م)</label>
                    <input
                        v-model="addForm.allowances"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-field"
                    />
                    <p v-if="addForm.errors.allowances" class="form-error">
                        {{ addForm.errors.allowances }}
                    </p>
                </div>
                <div>
                    <label class="form-label">نوع العقد</label>
                    <select v-model="addForm.contract_type" class="input-field">
                        <option
                            v-for="c in contractOptions"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label">الحالة</label>
                    <select v-model="addForm.status" class="input-field">
                        <option
                            v-for="s in statusOptions"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </select>
                    <p v-if="addForm.errors.status" class="form-error">
                        {{ addForm.errors.status }}
                    </p>
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea
                        v-model="addForm.notes"
                        rows="2"
                        class="input-field"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button
                    type="button"
                    class="btn-secondary"
                    @click="showAdd = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="addForm.processing"
                    class="btn-primary"
                >
                    {{ addForm.processing ? 'جارٍ الحفظ...' : 'إضافة الموظف' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" title="تعديل بيانات الموظف" size="lg">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label"
                        >الاسم <span class="text-d">*</span></label
                    >
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.name" class="form-error">
                        {{ editForm.errors.name }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >القسم <span class="text-d">*</span></label
                    >
                    <select v-model="editForm.dept" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="d in deptOptions" :key="d" :value="d">
                            {{ d }}
                        </option>
                    </select>
                    <p v-if="editForm.errors.dept" class="form-error">
                        {{ editForm.errors.dept }}
                    </p>
                </div>
                <div>
                    <label class="form-label"
                        >المسمى الوظيفي <span class="text-d">*</span></label
                    >
                    <input
                        v-model="editForm.position"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.position" class="form-error">
                        {{ editForm.errors.position }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الهاتف</label>
                    <input
                        v-model="editForm.phone"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.phone" class="form-error">
                        {{ editForm.errors.phone }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الرقم القومي</label>
                    <input
                        v-model="editForm.national_id"
                        type="text"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.national_id" class="form-error">
                        {{ editForm.errors.national_id }}
                    </p>
                </div>
                <div>
                    <label class="form-label">البريد الإلكتروني</label>
                    <input
                        v-model="editForm.email"
                        type="email"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.email" class="form-error">
                        {{ editForm.errors.email }}
                    </p>
                </div>
                <div>
                    <label class="form-label">تاريخ التعيين</label>
                    <input
                        v-model="editForm.hire_date"
                        type="date"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.hire_date" class="form-error">
                        {{ editForm.errors.hire_date }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الراتب الأساسي (ج.م)</label>
                    <input
                        v-model="editForm.base_salary"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.base_salary" class="form-error">
                        {{ editForm.errors.base_salary }}
                    </p>
                </div>
                <div>
                    <label class="form-label">البدلات (ج.م)</label>
                    <input
                        v-model="editForm.allowances"
                        type="number"
                        min="0"
                        step="0.01"
                        class="input-field"
                    />
                    <p v-if="editForm.errors.allowances" class="form-error">
                        {{ editForm.errors.allowances }}
                    </p>
                </div>
                <div>
                    <label class="form-label">نوع العقد</label>
                    <select
                        v-model="editForm.contract_type"
                        class="input-field"
                    >
                        <option
                            v-for="c in contractOptions"
                            :key="c.value"
                            :value="c.value"
                        >
                            {{ c.label }}
                        </option>
                    </select>
                    <p v-if="editForm.errors.contract_type" class="form-error">
                        {{ editForm.errors.contract_type }}
                    </p>
                </div>
                <div>
                    <label class="form-label">الحالة</label>
                    <select v-model="editForm.status" class="input-field">
                        <option
                            v-for="s in statusOptions"
                            :key="s.value"
                            :value="s.value"
                        >
                            {{ s.label }}
                        </option>
                    </select>
                    <p v-if="editForm.errors.status" class="form-error">
                        {{ editForm.errors.status }}
                    </p>
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea
                        v-model="editForm.notes"
                        rows="2"
                        class="input-field"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button
                    type="button"
                    class="btn-secondary"
                    @click="showEdit = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="editForm.processing"
                    class="btn-primary"
                >
                    {{
                        editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات'
                    }}
                </button>
            </div>
        </form>
    </Modal>
</template>
