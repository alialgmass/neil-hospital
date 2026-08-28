<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDownCircle,
    ArrowUpCircle,
    Edit3,
    FileText,
    PlusCircle,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';

interface TreasuryEntry {
    id: string;
    type: 'in' | 'out';
    description: string;
    amount: number;
    date: string;
    reference_no?: string;
    beneficiary?: string;
    source: string;
    reversed_at?: string | null;
    reversal_of_id?: string | null;
    account?: { code: string; name: string };
    account_id?: string;
    creator?: { name: string };
}

interface Balance {
    total_in: number;
    total_out: number;
    balance: number;
}

const props = defineProps<{
    entries: {
        data: TreasuryEntry[];
        current_page: number;
        last_page: number;
        total: number;
    };
    balance: Balance;
    todayNet: number;
    filters: { type?: string; source?: string; from?: string; to?: string };
}>();

const columns = [
    { key: 'date', label: 'التاريخ', sortable: true },
    { key: 'type', label: 'النوع' },
    { key: 'description', label: 'البيان' },
    { key: 'amount', label: 'المبلغ', sortable: true },
    { key: 'beneficiary', label: 'الجهة' },
    { key: 'source', label: 'المصدر' },
    { key: 'reference_no', label: 'المرجع' },
    { key: 'creator', label: 'المسؤول' },
];

// ── Permissions ──
const page = usePage<{ permissions?: string[] }>();
const permissions = computed<string[]>(() => (page.props.permissions as string[]) ?? []);
function can(permission: string): boolean {
    return permissions.value.includes('*') || permissions.value.includes(permission);
}
const canEdit = computed(() => can('treasury.edit'));
const canDelete = computed(() => can('treasury.delete'));

function isEditable(entry: TreasuryEntry): boolean {
    return entry.source === 'manual' && !entry.reversed_at && !entry.reversal_of_id;
}

const typeFilter = ref(props.filters.type ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');
const sourceFilter = ref(props.filters.source ?? '');

function applyFilters() {
    router.get(
        '/treasury',
        {
            type: typeFilter.value || undefined,
            source: sourceFilter.value || undefined,
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
        },
        { preserveState: true },
    );
}
function goToPage(page: number) {
    router.get(
        '/treasury',
        {
            type: typeFilter.value || undefined,
            source: sourceFilter.value || undefined,
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
            page,
        },
        { preserveState: true },
    );
}

const showAdd = ref(false);
const editingId = ref<string | null>(null);
const form = useForm({
    type: 'in' as 'in' | 'out',
    description: '',
    amount: '' as string | number,
    date: new Date().toISOString().slice(0, 10),
    reference_no: '',
    beneficiary: '',
    account_id: '',
});

function openAdd() {
    editingId.value = null;
    form.reset();
    form.date = new Date().toISOString().slice(0, 10);
    showAdd.value = true;
}

function openEdit(entry: TreasuryEntry) {
    editingId.value = entry.id;
    form.type = entry.type;
    form.description = entry.description;
    form.amount = entry.amount;
    form.date = entry.date.slice(0, 10);
    form.reference_no = entry.reference_no ?? '';
    form.beneficiary = entry.beneficiary ?? '';
    form.account_id = entry.account_id ?? '';
    showAdd.value = true;
}

function submit() {
    if (editingId.value) {
        form.put(`/treasury/${editingId.value}`, {
            onSuccess: () => {
                showAdd.value = false;
                editingId.value = null;
                form.reset();
            },
        });
        return;
    }

    form.post('/treasury', {
        onSuccess: () => {
            showAdd.value = false;
            form.reset();
        },
    });
}

const confirmingDeleteId = ref<string | null>(null);

function confirmDelete(entry: TreasuryEntry) {
    confirmingDeleteId.value = entry.id;
}

function doDelete() {
    if (!confirmingDeleteId.value) {
        return;
    }
    router.delete(`/treasury/${confirmingDeleteId.value}`, {
        onFinish: () => {
            confirmingDeleteId.value = null;
        },
    });
}

const sourceLabels: Record<string, string> = {
    manual: 'يدوي',
    booking: 'حجز',
    payment: 'دفعة',
    purchase: 'مشتريات',
};

const totalIn = computed(() =>
    props.entries.data
        .filter((e) => e.type === 'in')
        .reduce((s, e) => s + Number(e.amount), 0),
);
const totalOut = computed(() =>
    props.entries.data
        .filter((e) => e.type === 'out')
        .reduce((s, e) => s + Number(e.amount), 0),
);

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="الخزنة" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div
            class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white"
            >
                <Wallet class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-green-600">
                    رصيد الخزنة الحالي
                </p>
                <p class="text-lg font-bold text-green-700">
                    {{ balance.balance.toLocaleString('ar-EG') }}
                </p>
                <p class="text-xs text-green-500">جنيه</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white"
            >
                <ArrowDownCircle class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-blue-600">
                    إجمالي الإيرادات
                </p>
                <p class="text-lg font-bold text-blue-700">
                    {{ balance.total_in.toLocaleString('ar-EG') }}
                </p>
                <p class="text-xs text-blue-500">جنيه</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-500 text-white"
            >
                <ArrowUpCircle class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-red-600">إجمالي المصروفات</p>
                <p class="text-lg font-bold text-red-700">
                    {{ balance.total_out.toLocaleString('ar-EG') }}
                </p>
                <p class="text-xs text-red-500">جنيه</p>
            </div>
        </div>
        <div
            class="flex items-center gap-3 rounded-xl border border-orange-100 bg-orange-50 p-4"
        >
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-500 text-white"
            >
                <span class="text-xs font-bold">اليوم</span>
            </div>
            <div>
                <p class="text-xs font-medium text-orange-600">صافي اليوم</p>
                <p
                    class="text-lg font-bold"
                    :class="todayNet >= 0 ? 'text-orange-700' : 'text-red-700'"
                >
                    {{ todayNet.toLocaleString('ar-EG') }}
                </p>
                <p class="text-xs text-orange-500">جنيه</p>
            </div>
        </div>
    </div>

    <!-- Filters + Actions -->
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">من</label>
            <input
                v-model="fromFilter"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">إلى</label>
            <input
                v-model="toFilter"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">النوع</label>
            <select
                v-model="typeFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">الكل</option>
                <option value="in">إيراد</option>
                <option value="out">مصروف</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">المصدر</label>
            <select
                v-model="sourceFilter"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">كل المصادر</option>
                <option value="manual">يدوي</option>
                <option value="booking">حجز</option>
                <option value="payment">دفعة</option>
                <option value="purchase">مشتريات</option>
            </select>
        </div>
        <button
            class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-semibold text-white"
            @click="applyFilters"
        >
            🔍 عرض
        </button>
        <button
            class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
            @click="openAdd"
        >
            <PlusCircle class="h-4 w-4" /> قيد يدوي
        </button>
        <Link
            href="/treasury/statement"
            class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
        >
            <FileText class="h-4 w-4" /> كشف حركة الخزنة
        </Link>
        <button
            class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
            @click="printPage"
        >
            🖨️ طباعة
        </button>
    </div>

    <!-- Table Card -->
    <div
        class="overflow-hidden rounded-xl border border-hospital-border shadow-sm"
    >
        <div
            class="flex items-center justify-between border-b border-hospital-border bg-hospital-bg px-4 py-3"
        >
            <p class="text-sm font-bold text-hospital-text">
                كشف حركة الخزنة الرئيسية
            </p>
            <p class="text-xs text-hospital-muted">{{ entries.total }} حركة</p>
        </div>
        <DataTable
            :columns="columns"
            :rows="entries.data"
            :current-page="entries.current_page"
            :last-page="entries.last_page"
            :total="entries.total"
            empty-text="لا توجد حركات"
            class="[&>div]:rounded-none [&>div]:border-none [&>div]:shadow-none"
            @page="goToPage"
        >
            <template #cell-type="{ value }">
                <span
                    :class="
                        value === 'in'
                            ? 'font-medium text-hospital-success'
                            : 'font-medium text-hospital-danger'
                    "
                >
                    {{ value === 'in' ? 'إيراد ↓' : 'مصروف ↑' }}
                </span>
            </template>
            <template #cell-amount="{ value, row }">
                <span
                    :class="
                        (row as TreasuryEntry).type === 'in'
                            ? 'font-mono text-hospital-success'
                            : 'font-mono text-hospital-danger'
                    "
                >
                    {{ Number(value).toLocaleString('ar-EG') }} ج.م
                </span>
            </template>
            <template #cell-source="{ value, row }">
                {{ sourceLabels[value as string] ?? value }}
                <span v-if="(row as TreasuryEntry).reversed_at" class="ms-1 rounded-full bg-hospital-bg px-1.5 py-0.5 text-[10px] font-bold text-hospital-text-3">
                    معكوسة ↩
                </span>
            </template>
            <template #cell-creator="{ row }">
                {{ (row as TreasuryEntry).creator?.name ?? '—' }}
            </template>
            <template #actions="{ row }">
                <div class="flex items-center gap-1">
                    <button
                        v-if="canEdit"
                        type="button"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                        :class="isEditable(row as TreasuryEntry) ? 'hover:bg-hospital-warning-pale hover:text-hospital-warning' : ''"
                        :disabled="!isEditable(row as TreasuryEntry)"
                        :title="isEditable(row as TreasuryEntry) ? 'تعديل' : 'يُعدَّل من شاشته الأصلية أو معكوسة بالفعل'"
                        @click="openEdit(row as TreasuryEntry)"
                    >
                        <Edit3 class="h-4 w-4" />
                    </button>
                    <button
                        v-if="canDelete"
                        type="button"
                        class="rounded p-1.5 text-hospital-text-3 transition-colors disabled:cursor-not-allowed disabled:opacity-30"
                        :class="isEditable(row as TreasuryEntry) ? 'hover:bg-hospital-danger-pale hover:text-hospital-danger' : ''"
                        :disabled="!isEditable(row as TreasuryEntry)"
                        :title="isEditable(row as TreasuryEntry) ? 'حذف' : 'يُعدَّل من شاشته الأصلية أو معكوسة بالفعل'"
                        @click="confirmDelete(row as TreasuryEntry)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Totals bar -->
        <div
            class="flex gap-6 rounded-b-xl px-4 py-3 text-sm font-bold text-white"
            style="background: linear-gradient(135deg, #072e63, #0a4fa6)"
        >
            <span
                >إجمالي الوارد: {{ totalIn.toLocaleString('ar-EG') }} ج.م</span
            >
            <span
                >إجمالي الصادر: {{ totalOut.toLocaleString('ar-EG') }} ج.م</span
            >
            <span class="mr-auto"
                >الرصيد:
                {{ (totalIn - totalOut).toLocaleString('ar-EG') }} ج.م</span
            >
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <Modal v-model="showAdd" :title="editingId ? 'تعديل حركة خزنة' : 'تسجيل حركة خزنة'" size="md">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">النوع</label>
                    <select
                        v-model="form.type"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    >
                        <option value="in">وارد (دخول)</option>
                        <option value="out">صادر (خروج)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >التاريخ</label
                    >
                    <input
                        v-model="form.date"
                        type="date"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">البيان</label>
                    <input
                        v-model="form.description"
                        type="text"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                    <p
                        v-if="form.errors.description"
                        class="mt-1 text-xs text-hospital-danger"
                    >
                        {{ form.errors.description }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >المبلغ (ج.م)</label
                    >
                    <input
                        v-model.number="form.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                    <p
                        v-if="form.errors.amount"
                        class="mt-1 text-xs text-hospital-danger"
                    >
                        {{ form.errors.amount }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >الجهة / المستفيد</label
                    >
                    <input
                        v-model="form.beneficiary"
                        type="text"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium"
                        >رقم المرجع (اختياري)</label
                    >
                    <input
                        v-model="form.reference_no"
                        type="text"
                        class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="showAdd = false"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                >
                    {{ editingId ? 'حفظ التعديلات' : 'تسجيل' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :model-value="confirmingDeleteId !== null" title="تأكيد الحذف" size="sm" @update:model-value="confirmingDeleteId = null">
        <div class="space-y-4">
            <p class="text-sm text-hospital-text-2">
                هل أنت متأكد من حذف هذه الحركة؟ سيتم تسجيل قيد عكسي بنفس المبلغ للحفاظ على أرشيف الحركات — لن يُحذف السجل الأصلي.
            </p>
            <div class="flex justify-end gap-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="confirmingDeleteId = null">إلغاء</button>
                <button type="button" class="rounded-lg bg-hospital-danger px-4 py-2 text-sm font-medium text-white hover:opacity-90" @click="doDelete">تأكيد الحذف</button>
            </div>
        </div>
    </Modal>
</template>
