<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, FileText, PlusCircle, ShoppingCart } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface Supplier {
    id: string;
    name: string;
}

interface InvoiceItem {
    item_id?: string;
    item_name: string;
    qty: number;
    unit_cost: number;
}

interface PurchaseInvoice {
    id: string;
    invoice_no: string;
    supplier?: Supplier;
    invoice_date: string;
    total: number;
    paid_amount: number;
    remaining: number;
    status: string;
    notes?: string;
}

const props = defineProps<{
    invoices: { data: PurchaseInvoice[]; current_page: number; last_page: number; total: number };
    suppliers: Supplier[];
    filters: { from?: string; to?: string; supplier_id?: string; status?: string };
    stats: { invoice_count: number; total_amount: number; unpaid_count: number; total_due: number };
    next_invoice_no: string;
}>();

const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');
const supplierFilter = ref(props.filters.supplier_id ?? '');
const statusFilter = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(
        '/purchases',
        {
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
            supplier_id: supplierFilter.value || undefined,
            status: statusFilter.value || undefined,
        },
        { preserveState: true },
    );
}

function goToPage(page: number) {
    router.get(
        '/purchases',
        {
            from: fromFilter.value || undefined,
            to: toFilter.value || undefined,
            supplier_id: supplierFilter.value || undefined,
            status: statusFilter.value || undefined,
            page,
        },
        { preserveState: true },
    );
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

// New invoice form
const showAdd = ref(false);
const formData = ref({
    invoice_no: props.next_invoice_no,
    supplier_id: '',
    invoice_date: new Date().toISOString().slice(0, 10),
    discount: 0,
    paid_amount: 0,
    notes: '',
});
const items = ref<InvoiceItem[]>([{ item_id: '', item_name: '', qty: 1, unit_cost: 0 }]);

const subtotal = computed(() => items.value.reduce((s, i) => s + i.qty * i.unit_cost, 0));
const total = computed(() => Math.max(0, subtotal.value - formData.value.discount));
const remaining = computed(() => Math.max(0, total.value - formData.value.paid_amount));

function addItem() {
    items.value.push({ item_id: '', item_name: '', qty: 1, unit_cost: 0 });
}

function removeItem(idx: number) {
    if (items.value.length > 1) {
        items.value.splice(idx, 1);
    }
}

function openAdd() {
    formData.value = {
        invoice_no: props.next_invoice_no,
        supplier_id: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        discount: 0,
        paid_amount: 0,
        notes: '',
    };
    items.value = [{ item_id: '', item_name: '', qty: 1, unit_cost: 0 }];
    showAdd.value = true;
}

function submit() {
    router.post('/purchases', { ...formData.value, items: items.value }, {
        onSuccess: () => {
            showAdd.value = false;
        },
    });
}

const statusConfig: Record<string, { label: string; class: string }> = {
    paid: { label: 'مدفوعة', class: 'bg-sp text-s' },
    partial: { label: 'مدفوعة جزئياً', class: 'bg-wp text-w' },
    unpaid: { label: 'غير مدفوعة', class: 'bg-dp text-d' },
    posted: { label: 'مرحّلة', class: 'bg-pp text-p' },
    cancelled: { label: 'ملغاة', class: 'bg-br text-t3' },
    draft: { label: 'مسودة', class: 'bg-sf2 text-t2' },
};
</script>

<template>
    <Head title="فواتير الشراء" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">فواتير الشراء</h1>
        <p class="mt-0.5 text-sm text-t3">تسجيل ومتابعة فواتير المشتريات من الموردين</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <FileText class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الفواتير</p>
                <p class="text-xl font-bold text-t">{{ stats.invoice_count }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <ShoppingCart class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المشتريات</p>
                <p class="text-xl font-bold text-t">{{ fmt(stats.total_amount) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <AlertCircle class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">فواتير غير مسددة</p>
                <p class="text-xl font-bold text-w">{{ stats.unpaid_count }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <AlertCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي المستحق</p>
                <p class="text-xl font-bold text-d">{{ fmt(stats.total_due) }}</p>
                <p class="text-xs text-t3">ج.م</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">من</label>
            <input v-model="fromFilter" type="date" class="input-field" @change="applyFilters" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى</label>
            <input v-model="toFilter" type="date" class="input-field" @change="applyFilters" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">المورد</label>
            <select v-model="supplierFilter" class="input-field" @change="applyFilters">
                <option value="">كل الموردين</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">الحالة</label>
            <select v-model="statusFilter" class="input-field" @change="applyFilters">
                <option value="">كل الحالات</option>
                <option value="unpaid">غير مدفوعة</option>
                <option value="partial">مدفوعة جزئياً</option>
                <option value="paid">مدفوعة</option>
            </select>
        </div>
        <button class="btn-primary self-end flex items-center gap-1.5" @click="openAdd">
            <PlusCircle class="h-4 w-4" />
            فاتورة جديدة
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">رقم الفاتورة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المورد</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الإجمالي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المدفوع</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المتبقي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="inv in invoices.data" :key="inv.id" class="hover:bg-sf2">
                    <td class="px-4 py-3 font-mono text-xs font-medium text-p">{{ inv.invoice_no }}</td>
                    <td class="px-4 py-3 text-t2">{{ inv.invoice_date }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ inv.supplier?.name ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono font-medium text-t">{{ fmt(inv.total) }} ج</td>
                    <td class="px-4 py-3 font-mono text-s">{{ fmt(inv.paid_amount) }} ج</td>
                    <td class="px-4 py-3 font-mono" :class="Number(inv.remaining) > 0 ? 'font-medium text-d' : 'text-t3'">
                        {{ fmt(inv.remaining) }} ج
                    </td>
                    <td class="px-4 py-3">
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="statusConfig[inv.status]?.class ?? 'bg-sf2 text-t2'"
                        >
                            {{ statusConfig[inv.status]?.label ?? inv.status }}
                        </span>
                    </td>
                </tr>
                <tr v-if="invoices.data.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="7">لا توجد فواتير</td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="invoices.last_page > 1" class="flex items-center justify-between border-t border-br px-4 py-3">
            <span class="text-xs text-t3">إجمالي {{ invoices.total }} فاتورة</span>
            <div class="flex gap-1">
                <button
                    v-for="p in invoices.last_page"
                    :key="p"
                    class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === invoices.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'"
                    @click="goToPage(p)"
                >
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Invoice Modal -->
    <Modal v-model="showAdd" title="فاتورة مشتريات جديدة" size="xl">
        <div class="space-y-5">
            <!-- Header fields -->
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="form-label">رقم الفاتورة</label>
                    <input v-model="formData.invoice_no" type="text" class="input-field" placeholder="PI-0001" />
                </div>
                <div>
                    <label class="form-label">المورد</label>
                    <select v-model="formData.supplier_id" class="input-field">
                        <option value="">— بدون مورد —</option>
                        <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">تاريخ الفاتورة</label>
                    <input v-model="formData.invoice_date" type="date" class="input-field" />
                </div>
            </div>

            <!-- Items -->
            <div>
                <div class="mb-2 grid grid-cols-12 gap-2 text-xs font-semibold text-t2">
                    <span class="col-span-5">الصنف</span>
                    <span class="col-span-2 text-center">الكمية</span>
                    <span class="col-span-3 text-center">سعر الوحدة (ج)</span>
                    <span class="col-span-1 text-center">الإجمالي</span>
                    <span class="col-span-1" />
                </div>
                <div class="space-y-2">
                    <div v-for="(item, idx) in items" :key="idx" class="grid grid-cols-12 items-center gap-2">
                        <input
                            v-model="item.item_name"
                            type="text"
                            placeholder="اسم الصنف"
                            class="input-field col-span-5"
                        />
                        <input
                            v-model.number="item.qty"
                            type="number"
                            min="0.01"
                            step="0.01"
                            class="input-field col-span-2"
                        />
                        <input
                            v-model.number="item.unit_cost"
                            type="number"
                            min="0"
                            step="0.01"
                            class="input-field col-span-3"
                        />
                        <span class="col-span-1 text-center font-mono text-xs text-t2">
                            {{ (item.qty * item.unit_cost).toLocaleString('ar-EG') }}
                        </span>
                        <button
                            class="col-span-1 flex h-8 w-8 items-center justify-center rounded-lg text-t3 hover:bg-dp hover:text-d transition-colors"
                            :disabled="items.length === 1"
                            @click="removeItem(idx)"
                        >
                            ×
                        </button>
                    </div>
                </div>
                <button class="mt-2 text-sm text-p hover:underline" @click="addItem">+ إضافة صنف</button>
            </div>

            <!-- Totals row -->
            <div class="grid grid-cols-3 gap-4 border-t border-br pt-4">
                <div>
                    <label class="form-label">الخصم (ج)</label>
                    <input v-model.number="formData.discount" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div>
                    <label class="form-label">المدفوع (ج)</label>
                    <input v-model.number="formData.paid_amount" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div class="flex flex-col gap-1 rounded-xl bg-sf2 p-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-t3">المجموع الفرعي</span>
                        <span class="font-mono text-t2">{{ fmt(subtotal) }} ج</span>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-t3">الإجمالي المستحق</span>
                        <span class="font-mono font-bold text-p">{{ fmt(total) }} ج</span>
                    </div>
                    <div v-if="remaining > 0" class="flex items-center justify-between text-xs">
                        <span class="text-t3">المتبقي</span>
                        <span class="font-mono font-bold text-d">{{ fmt(remaining) }} ج</span>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="form-label">ملاحظات</label>
                <textarea v-model="formData.notes" rows="2" class="input-field" placeholder="ملاحظات اختيارية..." />
            </div>

            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button class="btn-primary" @click="submit">تسجيل الفاتورة</button>
            </div>
        </div>
    </Modal>
</template>
