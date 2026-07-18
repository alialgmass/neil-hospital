<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowDownCircle, ArrowUpCircle, PlusCircle, Wallet } from 'lucide-vue-next';
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
    account?: { code: string; name: string };
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
    filters: { type?: string; from?: string; to?: string };
}>();

const columns = [
    { key: 'date',         label: 'التاريخ',  sortable: true },
    { key: 'type',         label: 'النوع' },
    { key: 'description',  label: 'البيان' },
    { key: 'amount',       label: 'المبلغ',   sortable: true },
    { key: 'beneficiary',  label: 'الجهة' },
    { key: 'source',       label: 'المصدر' },
    { key: 'reference_no', label: 'المرجع' },
    { key: 'creator',      label: 'المسؤول' },
];

const typeFilter = ref(props.filters.type ?? '');
const fromFilter = ref(props.filters.from ?? '');
const toFilter   = ref(props.filters.to   ?? '');

function applyFilters() {
    router.get('/treasury', {
        type: typeFilter.value || undefined,
        from: fromFilter.value || undefined,
        to:   toFilter.value   || undefined,
    }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/treasury', {
        type: typeFilter.value || undefined,
        from: fromFilter.value || undefined,
        to:   toFilter.value   || undefined,
        page,
    }, { preserveState: true });
}

const showAdd = ref(false);
const form = useForm({
    type:         'in' as 'in' | 'out',
    description:  '',
    amount:       '' as string | number,
    date:         new Date().toISOString().slice(0, 10),
    reference_no: '',
    beneficiary:  '',
    account_id:   '',
});
function submit() {
    form.post('/treasury', { onSuccess: () => { showAdd.value = false; form.reset(); } });
}

const sourceLabels: Record<string, string> = {
    manual: 'يدوي', booking: 'حجز', payment: 'دفعة', purchase: 'مشتريات',
};

const totalIn  = computed(() => props.entries.data.filter(e => e.type === 'in').reduce((s, e) => s + Number(e.amount), 0));
const totalOut = computed(() => props.entries.data.filter(e => e.type === 'out').reduce((s, e) => s + Number(e.amount), 0));

function printPage() {
    window.print();
}
</script>

<template>
    <Head title="الخزنة" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-green-100 bg-green-50 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-600 text-white">
                <Wallet class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-green-600">رصيد الخزنة الحالي</p>
                <p class="text-lg font-bold text-green-700">{{ balance.balance.toLocaleString('ar-EG') }}</p>
                <p class="text-xs text-green-500">جنيه</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white">
                <ArrowDownCircle class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-blue-600">إجمالي الإيرادات</p>
                <p class="text-lg font-bold text-blue-700">{{ balance.total_in.toLocaleString('ar-EG') }}</p>
                <p class="text-xs text-blue-500">جنيه</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-500 text-white">
                <ArrowUpCircle class="h-5 w-5" />
            </div>
            <div>
                <p class="text-xs font-medium text-red-600">إجمالي المصروفات</p>
                <p class="text-lg font-bold text-red-700">{{ balance.total_out.toLocaleString('ar-EG') }}</p>
                <p class="text-xs text-red-500">جنيه</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-orange-100 bg-orange-50 p-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-orange-500 text-white">
                <span class="text-xs font-bold">اليوم</span>
            </div>
            <div>
                <p class="text-xs font-medium text-orange-600">صافي اليوم</p>
                <p class="text-lg font-bold" :class="todayNet >= 0 ? 'text-orange-700' : 'text-red-700'">
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
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">إلى</label>
            <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-hospital-muted">النوع</label>
            <select v-model="typeFilter" class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters">
                <option value="">الكل</option>
                <option value="in">إيراد</option>
                <option value="out">مصروف</option>
            </select>
        </div>
        <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-semibold text-white" @click="applyFilters">🔍 عرض</button>
        <button
            class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
            @click="showAdd = true"
        >
            <PlusCircle class="h-4 w-4" /> قيد يدوي
        </button>
        <button class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="printPage">🖨️ طباعة</button>
    </div>

    <!-- Table Card -->
    <div class="overflow-hidden rounded-xl border border-hospital-border shadow-sm">
        <div class="flex items-center justify-between border-b border-hospital-border bg-hospital-bg px-4 py-3">
            <p class="text-sm font-bold text-hospital-text">كشف حركة الخزنة الرئيسية</p>
            <p class="text-xs text-hospital-muted">{{ entries.total }} حركة</p>
        </div>
        <DataTable
            :columns="columns"
            :rows="entries.data"
            :current-page="entries.current_page"
            :last-page="entries.last_page"
            :total="entries.total"
            empty-text="لا توجد حركات"
            class="[&>div]:border-none [&>div]:shadow-none [&>div]:rounded-none"
            @page="goToPage"
        >
            <template #cell-type="{ value }">
                <span :class="value === 'in' ? 'text-hospital-success font-medium' : 'text-hospital-danger font-medium'">
                    {{ value === 'in' ? 'إيراد ↓' : 'مصروف ↑' }}
                </span>
            </template>
            <template #cell-amount="{ value, row }">
                <span :class="(row as TreasuryEntry).type === 'in' ? 'text-hospital-success font-mono' : 'text-hospital-danger font-mono'">
                    {{ Number(value).toLocaleString('ar-EG') }} ج.م
                </span>
            </template>
            <template #cell-source="{ value }">
                {{ sourceLabels[value as string] ?? value }}
            </template>
            <template #cell-creator="{ row }">
                {{ (row as TreasuryEntry).creator?.name ?? '—' }}
            </template>
        </DataTable>

        <!-- Totals bar -->
        <div class="flex gap-6 rounded-b-xl px-4 py-3 text-sm font-bold text-white" style="background: linear-gradient(135deg, #072E63, #0A4FA6)">
            <span>إجمالي الوارد: {{ totalIn.toLocaleString('ar-EG') }} ج.م</span>
            <span>إجمالي الصادر: {{ totalOut.toLocaleString('ar-EG') }} ج.م</span>
            <span class="mr-auto">الرصيد: {{ (totalIn - totalOut).toLocaleString('ar-EG') }} ج.م</span>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="تسجيل حركة خزنة" size="md">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">النوع</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="in">وارد (دخول)</option>
                        <option value="out">صادر (خروج)</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">التاريخ</label>
                    <input v-model="form.date" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">البيان</label>
                    <input v-model="form.description" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="form.errors.description" class="mt-1 text-xs text-hospital-danger">{{ form.errors.description }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">المبلغ (ج.م)</label>
                    <input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="form.errors.amount" class="mt-1 text-xs text-hospital-danger">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الجهة / المستفيد</label>
                    <input v-model="form.beneficiary" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">رقم المرجع (اختياري)</label>
                    <input v-model="form.reference_no" type="text" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60">تسجيل</button>
            </div>
        </form>
    </Modal>
</template>
