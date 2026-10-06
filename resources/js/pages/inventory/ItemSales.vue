<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3'
import { Banknote, FileText, Percent, Printer, Receipt, ShoppingBag, Trash2, TrendingUp } from 'lucide-vue-next'
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Modal from '@/components/shared/Modal.vue'
import StatCard from '@/components/shared/StatCard.vue'
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions'

defineOptions({ layout: AppLayout })

interface Option {
    value: string
    label: string
}

interface SelectableItem {
    id: string
    name: string
    code: string | null
    unit: string
    quantity: string
    sell_price: string
}

interface InvoiceItem {
    id: number
    item_name: string
    qty: string
    unit_price: string
    line_total: string
}

interface SalesInvoice {
    id: string
    invoice_no: string
    invoice_date: string
    customer_name: string
    customer_phone: string | null
    file_no: string | null
    pay_method: string
    subtotal: string
    discount: string
    total: string
    items: InvoiceItem[]
    creator?: { id: number; name: string } | null
}

const props = defineProps<{
    filters: { from: string; to: string; search: string | null }
    invoices: {
        data: SalesInvoice[]
        links: Array<{ url: string | null; label: string; active: boolean }>
        current_page: number
        last_page: number
    }
    totals: { count: number; subtotal: number; discount: number; total: number; profit: number }
    selectableItems: SelectableItem[]
    deptOptions: Array<Option & { module: string }>
    payMethodOptions: Option[]
}>()

const { can } = usePermissions()
const canWrite = computed(() => can('inventory.write'))

// ── Filters ──
const filterForm = reactive({
    from: props.filters.from,
    to: props.filters.to,
    search: props.filters.search ?? '',
})

function applyFilters() {
    const query = Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== ''))

    router.get('/item-sales', query, { preserveState: true, preserveScroll: true })
}

function money(value: number | string | null | undefined): string {
    return Number(value ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function payMethodLabel(value: string): string {
    return props.payMethodOptions.find((option) => option.value === value)?.label ?? value
}

// ── Create invoice ──
const showModal = ref(false)

const form = useForm({
    invoice_date: new Date().toISOString().slice(0, 10),
    customer_name: '',
    customer_phone: '',
    file_no: '',
    department: '',
    pay_method: 'cash',
    discount: 0,
    notes: '',
    items: [] as { item_id: string; qty: number; unit_price: number }[],
})

const subtotal = computed(() => form.items.reduce((sum, item) => sum + Number(item.qty) * Number(item.unit_price), 0))
const netTotal = computed(() => Math.max(0, subtotal.value - Number(form.discount || 0)))

function openCreate() {
    if (!canWrite.value) {
        return
    }

    form.reset()
    form.clearErrors()
    form.items = [{ item_id: '', qty: 1, unit_price: 0 }]
    showModal.value = true
}

interface CustomerMatch {
    customer_name: string
    customer_phone: string | null
    file_no: string | null
}

const customerResults = ref<CustomerMatch[]>([])
const customerDropdownOpen = ref(false)
let customerDebounce: ReturnType<typeof setTimeout> | undefined

function onCustomerNameInput(value: string) {
    form.customer_name = value
    clearTimeout(customerDebounce)

    if (!value.trim()) {
        customerResults.value = []
        customerDropdownOpen.value = false

        return
    }

    customerDebounce = setTimeout(async () => {
        try {
            const res = await fetch(`/item-sales/customers/search?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            })
            customerResults.value = res.ok ? await res.json() : []
            customerDropdownOpen.value = customerResults.value.length > 0
        } catch {
            customerResults.value = []
        }
    }, 300)
}

function selectCustomer(customer: CustomerMatch) {
    form.customer_name = customer.customer_name
    form.customer_phone = customer.customer_phone ?? form.customer_phone
    form.file_no = customer.file_no ?? form.file_no
    customerDropdownOpen.value = false
}

function closeCustomerDropdown() {
    setTimeout(() => {
        customerDropdownOpen.value = false
    }, 150)
}

onBeforeUnmount(() => clearTimeout(customerDebounce))

function addRow() {
    form.items.push({ item_id: '', qty: 1, unit_price: 0 })
}

function removeRow(idx: number) {
    form.items.splice(idx, 1)
}

function findItem(id: string): SelectableItem | undefined {
    return props.selectableItems.find((item) => item.id === id)
}

function onItemSelect(idx: number) {
    const found = findItem(form.items[idx].item_id)

    if (found) {
        form.items[idx].unit_price = Number(found.sell_price)
    }
}

function itemError(idx: number, field: string): string | undefined {
    return (form.errors as Record<string, string>)[`items.${idx}.${field}`]
}

function submit() {
    if (!canWrite.value) {
        return
    }

    form.post('/item-sales', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false
            form.reset()
        },
    })
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">فواتير البيع</h1>
                <p class="mt-0.5 text-sm text-gray-500">بيع أصناف المخزن للمرضى والعملاء</p>
            </div>
            <button
                class="flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canWrite"
                :title="canWrite ? undefined : NO_PERMISSION_TITLE"
                @click="openCreate"
            >
                <ShoppingBag class="h-4 w-4" />
                فاتورة بيع جديدة
            </button>
        </div>

        <!-- Filters -->
        <form
            class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
            @submit.prevent="applyFilters"
        >
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">من</label>
                <input v-model="filterForm.from" type="date" class="input-field" />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">إلى</label>
                <input v-model="filterForm.to" type="date" class="input-field" />
            </div>
            <div class="flex min-w-48 flex-1 flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">بحث</label>
                <input
                    v-model="filterForm.search"
                    type="text"
                    class="input-field"
                    placeholder="رقم الفاتورة / العميل / الهاتف / رقم الملف"
                />
            </div>
            <button type="submit" class="btn-primary">تطبيق</button>
        </form>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <StatCard label="عدد الفواتير" :value="totals.count" color="primary">
                <template #icon><FileText class="h-8 w-8" /></template>
            </StatCard>
            <StatCard label="الخصومات (ج.م)" :value="money(totals.discount)" color="warning">
                <template #icon><Percent class="h-8 w-8" /></template>
            </StatCard>
            <StatCard label="صافي المبيعات (ج.م)" :value="money(totals.total)" color="success">
                <template #icon><Banknote class="h-8 w-8" /></template>
            </StatCard>
            <StatCard label="مجمل الربح (ج.م)" :value="money(totals.profit)" color="accent">
                <template #icon><TrendingUp class="h-8 w-8" /></template>
            </StatCard>
        </div>

        <!-- Invoices -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الفاتورة</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">العميل</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الأصناف</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الإجمالي</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">خصم</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الصافي</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الدفع</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">بواسطة</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono font-semibold text-blue-700">{{ invoice.invoice_no }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ invoice.invoice_date.slice(0, 10) }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-gray-800">{{ invoice.customer_name }}</span>
                                <span v-if="invoice.file_no || invoice.customer_phone" class="block text-xs text-gray-400">
                                    {{ [invoice.file_no, invoice.customer_phone].filter(Boolean).join(' — ') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-600">
                                {{ invoice.items.map((item) => `${item.item_name} × ${Number(item.qty)}`).join('، ') }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ money(invoice.subtotal) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ money(invoice.discount) }}</td>
                            <td class="px-4 py-3 font-semibold text-green-700">{{ money(invoice.total) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ payMethodLabel(invoice.pay_method) }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ invoice.creator?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/item-sales/${invoice.id}`"
                                    class="flex items-center gap-1 text-xs font-medium text-gray-600 hover:underline"
                                >
                                    <Printer class="h-3.5 w-3.5" />
                                    طباعة
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="invoices.data.length === 0">
                            <td class="px-4 py-10 text-center text-gray-400" colspan="10">لا توجد فواتير بيع في هذه الفترة</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="invoices.data.length > 0" class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-3 text-gray-700" colspan="4">إجمالي الفترة ({{ totals.count }} فاتورة)</td>
                            <td class="px-4 py-3 text-gray-700">{{ money(totals.subtotal) }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ money(totals.discount) }}</td>
                            <td class="px-4 py-3 text-green-700">{{ money(totals.total) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="invoices.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                <span class="text-xs text-gray-500">صفحة {{ invoices.current_page }} من {{ invoices.last_page }}</span>
                <div class="flex gap-2">
                    <button
                        v-for="link in invoices.links"
                        :key="link.label"
                        :disabled="!link.url"
                        class="rounded px-2.5 py-1 text-xs"
                        :class="link.active ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-40'"
                        @click="link.url && router.get(link.url, {}, { preserveScroll: true })"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal v-model="showModal" title="فاتورة بيع جديدة" size="lg">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="relative">
                        <label class="form-label">اسم العميل / المريض *</label>
                        <input
                            :value="form.customer_name"
                            class="input-field"
                            type="text"
                            placeholder="ابحث بالاسم..."
                            autocomplete="off"
                            @input="onCustomerNameInput(($event.target as HTMLInputElement).value)"
                            @focus="customerDropdownOpen = customerResults.length > 0"
                            @blur="closeCustomerDropdown"
                        />
                        <p v-if="form.errors.customer_name" class="form-error">{{ form.errors.customer_name }}</p>
                        <ul
                            v-if="customerDropdownOpen && customerResults.length > 0"
                            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-hospital-border bg-white shadow-lg"
                        >
                            <li
                                v-for="(customer, idx) in customerResults"
                                :key="`${customer.file_no ?? ''}-${idx}`"
                                class="cursor-pointer px-3 py-2 text-xs hover:bg-hospital-bg"
                                @mousedown.prevent="selectCustomer(customer)"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-hospital-text">{{ customer.customer_name }}</span>
                                    <span v-if="customer.file_no" class="font-mono text-hospital-text-3">{{ customer.file_no }}</span>
                                </div>
                                <div v-if="customer.customer_phone" class="mt-0.5 text-hospital-text-3">{{ customer.customer_phone }}</div>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <label class="form-label">التاريخ *</label>
                        <input v-model="form.invoice_date" class="input-field" type="date" />
                        <p v-if="form.errors.invoice_date" class="form-error">{{ form.errors.invoice_date }}</p>
                    </div>
                    <div>
                        <label class="form-label">رقم الملف</label>
                        <input v-model="form.file_no" class="input-field" type="text" placeholder="اختياري" />
                    </div>
                    <div>
                        <label class="form-label">الهاتف</label>
                        <input v-model="form.customer_phone" class="input-field" type="text" placeholder="اختياري" />
                    </div>
                    <div>
                        <label class="form-label">القسم</label>
                        <select v-model="form.department" class="input-field">
                            <option value="">— بدون —</option>
                            <option v-for="opt in deptOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">طريقة الدفع *</label>
                        <select v-model="form.pay_method" class="input-field">
                            <option v-for="opt in payMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                        <p v-if="form.errors.pay_method" class="form-error">{{ form.errors.pay_method }}</p>
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-semibold text-hospital-text">الأصناف</label>
                        <button type="button" class="text-sm font-medium text-hospital-primary hover:underline" @click="addRow">
                            + إضافة صنف
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-hospital-border">
                        <table class="w-full text-sm">
                            <thead class="bg-hospital-bg text-xs text-hospital-text-2">
                                <tr>
                                    <th class="px-3 py-2 text-right font-medium">الصنف</th>
                                    <th class="w-24 px-3 py-2 text-right font-medium">الكمية</th>
                                    <th class="w-28 px-3 py-2 text-right font-medium">سعر البيع</th>
                                    <th class="w-28 px-3 py-2 text-right font-medium">الإجمالي</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in form.items" :key="idx" class="border-t border-hospital-border align-top">
                                    <td class="px-3 py-2">
                                        <select v-model="item.item_id" class="input-field text-xs" @change="onItemSelect(idx)">
                                            <option value="">— اختر صنف —</option>
                                            <option v-for="inv in selectableItems" :key="inv.id" :value="inv.id">
                                                {{ inv.name }} (متاح {{ Number(inv.quantity) }})
                                            </option>
                                        </select>
                                        <p v-if="itemError(idx, 'item_id')" class="form-error">{{ itemError(idx, 'item_id') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="item.qty" class="input-field text-center text-xs" type="number" min="0.01" step="0.01" />
                                        <p v-if="itemError(idx, 'qty')" class="form-error">{{ itemError(idx, 'qty') }}</p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="item.unit_price" class="input-field text-center text-xs" type="number" min="0" step="0.01" />
                                    </td>
                                    <td class="px-3 py-2 text-xs font-medium text-hospital-text">
                                        {{ money(Number(item.qty) * Number(item.unit_price)) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-hospital-danger/60 hover:text-hospital-danger" @click="removeRow(idx)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td class="px-3 py-5 text-center text-xs text-hospital-text-3" colspan="5">
                                        اضغط "+ إضافة صنف" لإضافة الأصناف
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="form.errors.items" class="form-error">{{ form.errors.items }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">خصم (ج.م)</label>
                        <input v-model.number="form.discount" class="input-field" type="number" min="0" step="0.01" />
                        <p v-if="form.errors.discount" class="form-error">{{ form.errors.discount }}</p>
                    </div>
                    <div>
                        <label class="form-label">ملاحظات</label>
                        <input v-model="form.notes" class="input-field" type="text" placeholder="اختياري" />
                    </div>
                </div>

                <div class="flex items-center justify-between rounded-lg bg-hospital-bg px-4 py-3 text-sm">
                    <span class="text-hospital-text-2">الإجمالي: {{ money(subtotal) }} ج.م</span>
                    <span class="flex items-center gap-2 font-bold text-hospital-primary">
                        <Receipt class="h-4 w-4" />
                        الصافي: {{ money(netTotal) }} ج.م
                    </span>
                </div>

                <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                    <button type="button" class="btn-secondary" @click="showModal = false">إلغاء</button>
                    <button
                        type="submit"
                        class="btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="form.processing || form.items.length === 0 || !canWrite"
                        :title="canWrite ? undefined : NO_PERMISSION_TITLE"
                    >
                        {{ form.processing ? 'جارٍ الحفظ...' : 'إصدار الفاتورة' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
