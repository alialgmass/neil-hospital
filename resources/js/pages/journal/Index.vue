<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BookOpen, Printer, TrendingUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';

interface Account {
    id: string;
    code: string;
    name: string;
}

interface JournalEntry {
    id: string;
    date: string;
    description: string;
    debit_account: Account;
    credit_account: Account;
    amount: number;
    reference?: string;
    source: string;
    cost_center?: string;
    creator?: { name: string };
}

const props = defineProps<{
    entries: {
        data: JournalEntry[];
        current_page: number;
        last_page: number;
        total: number;
    };
    accounts: Account[];
    totalAmount: number;
    filters: { from?: string; to?: string; source?: string; cost_center?: string };
}>();

const costCenterOptions = [
    { value: 'CC-CLINIC', label: 'العيادة الخارجية' },
    { value: 'CC-LAB',    label: 'الفحوصات والمختبر' },
    { value: 'CC-SURG',   label: 'غرفة العمليات' },
    { value: 'CC-LASIK',  label: 'وحدة الليزك' },
    { value: 'CC-LASER',  label: 'الليزر التشخيصي' },
    { value: 'CC-INS',    label: 'التأمين الصحي' },
    { value: 'CC-DR',     label: 'الأطباء والمستحقات' },
    { value: 'CC-INV',    label: 'إدارة المخزون' },
    { value: 'CC-EQUIP',  label: 'الأجهزة والمعدات' },
];

const sourceOptions = [
    { value: 'manual',            label: 'يدوي' },
    { value: 'booking',           label: 'حجز' },
    { value: 'auto_booking',      label: 'حجز تلقائي' },
    { value: 'purchase',          label: 'مشتريات' },
    { value: 'expense',           label: 'مصروفات' },
    { value: 'salary',            label: 'رواتب' },
    { value: 'settlement',        label: 'تسوية' },
    { value: 'insurance_claim',   label: 'مطالبة تأمين' },
    { value: 'insurance_collect', label: 'تحصيل تأمين' },
    { value: 'supplies_used',     label: 'صرف مستلزمات' },
    { value: 'doctor_shift',      label: 'شيفت طبيب' },
    { value: 'doctor_payment',    label: 'صرف مستحقات طبيب' },
    { value: 'supplier_payment',  label: 'سداد مورد' },
    { value: 'reversal',          label: 'عكس قيد (إلغاء)' },
];

const sourceBadge: Record<string, { label: string; classes: string }> = {
    manual:            { label: 'يدوي',                classes: 'bg-pp text-p' },
    booking:           { label: 'حجز',                 classes: 'bg-sp text-s' },
    auto_booking:      { label: 'حجز تلقائي',          classes: 'bg-sp text-s' },
    purchase:          { label: 'مشتريات',             classes: 'bg-wp text-w' },
    expense:           { label: 'مصروفات',             classes: 'bg-dp text-d' },
    salary:            { label: 'رواتب',               classes: 'bg-dp text-d' },
    settlement:        { label: 'تسوية',               classes: 'bg-ap text-a' },
    insurance_claim:   { label: 'مطالبة تأمين',        classes: 'bg-pp text-pd' },
    insurance_collect: { label: 'تحصيل تأمين',        classes: 'bg-sp text-s' },
    supplies_used:     { label: 'صرف مستلزمات',       classes: 'bg-wp text-w' },
    doctor_shift:      { label: 'شيفت طبيب',           classes: 'bg-pp text-p' },
    doctor_payment:    { label: 'صرف مستحقات',        classes: 'bg-dp text-d' },
    supplier_payment:  { label: 'سداد مورد',           classes: 'bg-wp text-w' },
    reversal:          { label: 'عكس قيد',             classes: 'bg-sf2 text-t2' },
};

const columns = [
    { key: 'date',           label: 'التاريخ',  sortable: true },
    { key: 'reference',      label: 'رقم القيد' },
    { key: 'source',         label: 'المصدر' },
    { key: 'cost_center',   label: 'مركز التكلفة' },
    { key: 'description',    label: 'البيان' },
    { key: 'debit_account',  label: 'مدين' },
    { key: 'credit_account', label: 'دائن' },
    { key: 'amount',         label: 'المبلغ',   sortable: true },
    { key: 'creator',        label: 'المسؤول' },
];

const fromFilter       = ref(props.filters.from        ?? '');
const toFilter         = ref(props.filters.to          ?? '');
const sourceFilter     = ref(props.filters.source      ?? '');
const costCenterFilter = ref(props.filters.cost_center ?? '');

function applyFilters() {
    router.get('/journal', {
        from:        fromFilter.value       || undefined,
        to:          toFilter.value         || undefined,
        source:      sourceFilter.value     || undefined,
        cost_center: costCenterFilter.value || undefined,
    }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/journal', {
        from:        fromFilter.value       || undefined,
        to:          toFilter.value         || undefined,
        source:      sourceFilter.value     || undefined,
        cost_center: costCenterFilter.value || undefined,
        page,
    }, { preserveState: true });
}

const form = useForm({
    date:              new Date().toISOString().slice(0, 10),
    description:       '',
    debit_account_id:  '',
    credit_account_id: '',
    amount:            '' as string | number,
    reference:         '',
    cost_center:       '',
});

function submit() {
    form.post('/journal', {
        onSuccess: () => {
            form.reset();
            form.date = new Date().toISOString().slice(0, 10);
        },
    });
}

function clearForm() {
    form.reset();
    form.date = new Date().toISOString().slice(0, 10);
}

function printPage() {
    window.print();
}

const pageTotal = computed(() => props.entries.data.reduce((s, e) => s + Number(e.amount), 0));

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="قيود اليومية" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4">
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-p text-white">
                <BookOpen class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-t2">إجمالي القيود</p>
                <p class="text-xl font-bold text-t">{{ entries.total }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-s text-white">
                <TrendingUp class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-t2">إجمالي المبالغ</p>
                <p class="text-xl font-bold text-s">{{ fmt(totalAmount) }}</p>
            </div>
        </div>
    </div>

    <!-- Add Form Card -->
    <div class="mb-5 overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="border-b border-br bg-pd px-4 py-3">
            <p class="text-sm font-bold text-white">إضافة قيد يومي جديد</p>
        </div>
        <div class="p-4">
            <form @submit.prevent="submit">
                <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <label class="form-label">رقم القيد</label>
                        <input
                            v-model="form.reference"
                            type="text"
                            placeholder="تلقائي"
                            class="input-field"
                            :class="{ 'border-d': form.errors.reference }"
                        />
                        <p v-if="form.errors.reference" class="form-error">{{ form.errors.reference }}</p>
                    </div>
                    <div>
                        <label class="form-label">التاريخ <span class="text-d">*</span></label>
                        <input
                            v-model="form.date"
                            type="date"
                            class="input-field"
                            :class="{ 'border-d': form.errors.date }"
                        />
                        <p v-if="form.errors.date" class="form-error">{{ form.errors.date }}</p>
                    </div>
                    <div>
                        <label class="form-label">الحساب المدين <span class="text-d">*</span></label>
                        <select
                            v-model="form.debit_account_id"
                            class="input-field"
                            :class="{ 'border-d': form.errors.debit_account_id }"
                        >
                            <option value="">— اختر —</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                        </select>
                        <p v-if="form.errors.debit_account_id" class="form-error">{{ form.errors.debit_account_id }}</p>
                    </div>
                    <div>
                        <label class="form-label">الحساب الدائن <span class="text-d">*</span></label>
                        <select
                            v-model="form.credit_account_id"
                            class="input-field"
                            :class="{ 'border-d': form.errors.credit_account_id }"
                        >
                            <option value="">— اختر —</option>
                            <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
                        </select>
                        <p v-if="form.errors.credit_account_id" class="form-error">{{ form.errors.credit_account_id }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="form-label">البيان <span class="text-d">*</span></label>
                        <input
                            v-model="form.description"
                            type="text"
                            placeholder="وصف القيد..."
                            class="input-field"
                            :class="{ 'border-d': form.errors.description }"
                        />
                        <p v-if="form.errors.description" class="form-error">{{ form.errors.description }}</p>
                    </div>
                    <div>
                        <label class="form-label">المبلغ (ج.م) <span class="text-d">*</span></label>
                        <input
                            v-model.number="form.amount"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            class="input-field"
                            :class="{ 'border-d': form.errors.amount }"
                        />
                        <p v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="form-label">مركز التكلفة</label>
                        <select v-model="form.cost_center" class="input-field">
                            <option value="">— اختياري —</option>
                            <option v-for="cc in costCenterOptions" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" @click="clearForm">مسح</button>
                    <button type="submit" :disabled="form.processing" class="btn-primary">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ القيد' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters + Actions Row -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">من تاريخ</label>
            <input
                v-model="fromFilter"
                type="date"
                class="input-field w-auto"
                @change="applyFilters"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى تاريخ</label>
            <input
                v-model="toFilter"
                type="date"
                class="input-field w-auto"
                @change="applyFilters"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">المصدر</label>
            <select
                v-model="sourceFilter"
                class="input-field w-auto"
                @change="applyFilters"
            >
                <option value="">كل المصادر</option>
                <option v-for="s in sourceOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">مركز التكلفة</label>
            <select
                v-model="costCenterFilter"
                class="input-field w-auto"
                @change="applyFilters"
            >
                <option value="">كل المراكز</option>
                <option v-for="cc in costCenterOptions" :key="cc.value" :value="cc.value">{{ cc.label }}</option>
            </select>
        </div>
        <div class="flex gap-2 pb-0.5">
            <button class="btn-primary" @click="applyFilters">عرض</button>
            <button class="btn-secondary flex items-center gap-1.5" @click="printPage">
                <Printer class="h-4 w-4" />
                طباعة
            </button>
        </div>
    </div>

    <!-- Table Card -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex items-center justify-between border-b border-br bg-sf2 px-4 py-3">
            <p class="text-sm font-bold text-t">قيود اليومية</p>
            <p class="text-xs text-t2">{{ entries.total }} قيد</p>
        </div>

        <DataTable
            :columns="columns"
            :rows="entries.data"
            :current-page="entries.current_page"
            :last-page="entries.last_page"
            :total="entries.total"
            empty-text="لا توجد قيود"
            class="[&>div]:border-none [&>div]:shadow-none [&>div]:rounded-none"
            @page="goToPage"
        >
            <template #cell-source="{ row }">
                <span
                    class="inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="sourceBadge[(row as JournalEntry).source]?.classes ?? 'bg-sf2 text-t2'"
                >
                    {{ sourceBadge[(row as JournalEntry).source]?.label ?? (row as JournalEntry).source }}
                </span>
            </template>
            <template #cell-debit_account="{ row }">
                <div class="flex items-center gap-1.5">
                    <span class="rounded bg-pp px-1.5 py-0.5 font-mono text-[10px] font-bold text-p">
                        {{ (row as JournalEntry).debit_account?.code }}
                    </span>
                    <span class="text-sm text-t">{{ (row as JournalEntry).debit_account?.name }}</span>
                </div>
            </template>
            <template #cell-credit_account="{ row }">
                <div class="flex items-center gap-1.5">
                    <span class="rounded bg-dp px-1.5 py-0.5 font-mono text-[10px] font-bold text-d">
                        {{ (row as JournalEntry).credit_account?.code }}
                    </span>
                    <span class="text-sm text-t">{{ (row as JournalEntry).credit_account?.name }}</span>
                </div>
            </template>
            <template #cell-cost_center="{ row }">
                <span v-if="(row as JournalEntry).cost_center" class="inline-block rounded-full bg-pp px-2 py-0.5 text-[10px] font-medium text-pd">
                    {{ costCenterOptions.find(c => c.value === (row as JournalEntry).cost_center)?.label ?? (row as JournalEntry).cost_center }}
                </span>
                <span v-else class="text-t3">—</span>
            </template>
            <template #cell-amount="{ value }">
                <span class="font-mono font-semibold text-t">{{ fmt(Number(value)) }} ج.م</span>
            </template>
            <template #cell-creator="{ row }">
                <span class="text-t2">{{ (row as JournalEntry).creator?.name ?? '—' }}</span>
            </template>
        </DataTable>

        <!-- Totals Bar -->
        <div class="flex items-center justify-between border-t border-br bg-pd px-4 py-3 text-sm font-bold text-white">
            <span>إجمالي المبالغ ({{ entries.total }} قيد): {{ fmt(totalAmount) }} ج.م</span>
            <span class="font-normal opacity-80">هذه الصفحة: {{ fmt(pageTotal) }} ج.م</span>
        </div>
    </div>
</template>
