<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import Modal from '@/components/shared/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

defineOptions({ layout: AppLayout })

interface InvoiceItem {
    item_id?: string
    item_name: string
    qty: number
    unit_cost: number
}

interface Invoice {
    id: string
    invoice_no: string
    invoice_date: string
    total: number
    supplier?: { name: string }
    items: InvoiceItem[]
}

interface Return {
    id: string
    invoice_no: string
    created_at: string
    total: number
    supplier_name?: string
}

const props = defineProps<{
    returns: { data: Return[]; links: unknown[] }
    invoices: Invoice[]
}>()

const showModal = ref(false)
const selectedInvoice = ref<Invoice | null>(null)

const form = useForm({
    invoice_id: '',
    reason: '',
    items: [] as { item_id?: string; item_name: string; qty: number; unit_cost: number }[],
})

function onInvoiceSelect() {
    const inv = props.invoices.find((i) => i.id === form.invoice_id)
    selectedInvoice.value = inv ?? null
    form.items = (inv?.items ?? []).map((item) => ({
        item_id: item.item_id,
        item_name: item.item_name,
        qty: item.qty,
        unit_cost: item.unit_cost,
    }))
}

function removeRow(index: number) {
    form.items.splice(index, 1)
}

function submit() {
    form.post('/purchase-returns', {
        onSuccess: () => {
            showModal.value = false
            form.reset()
            selectedInvoice.value = null
        },
    })
}

const returnTotal = () => form.items.reduce((s, i) => s + i.qty * i.unit_cost, 0)

const totalReturns = computed(() => props.returns.data.length)
const totalValue   = computed(() => props.returns.data.reduce((s, r) => s + Number(r.total), 0))
</script>

<template>
    <div class="p-6">
        <!-- Stats Row -->
        <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-2">
            <div class="flex items-center gap-3 rounded-xl border border-red-100 bg-red-50 p-4">
                <div class="h-10 w-10 rounded-lg bg-red-600 text-white flex items-center justify-center text-lg font-bold">↩</div>
                <div>
                    <p class="text-xs font-medium text-red-600">إجمالي المردودات</p>
                    <p class="text-2xl font-bold text-red-700">{{ totalReturns }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-orange-100 bg-orange-50 p-4">
                <div class="h-10 w-10 rounded-lg bg-orange-600 text-white flex items-center justify-center text-lg font-bold">ج</div>
                <div>
                    <p class="text-xs font-medium text-orange-600">قيمة المردودات</p>
                    <p class="text-2xl font-bold text-orange-700">{{ totalValue.toLocaleString('ar-EG') }}</p>
                    <p class="text-xs text-orange-500">جنيه</p>
                </div>
            </div>
        </div>

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800">سجل مردودات المشتريات</h1>
            <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="showModal = true">+ تسجيل مرتجع</button>
        </div>

        <!-- Returns Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الفاتورة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المورد</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">قيمة المرتجع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ret in returns.data" :key="ret.id" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-medium">{{ ret.invoice_no }}</td>
                        <td class="px-4 py-3">{{ ret.supplier_name || '—' }}</td>
                        <td class="px-4 py-3 font-medium text-red-700">{{ Number(ret.total).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-gray-500">{{ ret.created_at }}</td>
                    </tr>
                    <tr v-if="returns.data.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="4">لا توجد مردودات</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Return Modal -->
        <Modal :show="showModal" title="تسجيل مرتجع مشتريات" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium">الفاتورة الأصلية</label>
                    <select v-model="form.invoice_id" class="input-field" required @change="onInvoiceSelect">
                        <option value="">— اختر فاتورة —</option>
                        <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                            {{ inv.invoice_no }} — {{ inv.supplier?.name }} ({{ Number(inv.total).toFixed(2) }} ج)
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">سبب المرتجع</label>
                    <input v-model="form.reason" class="input-field" type="text" />
                </div>

                <!-- Return Items -->
                <div v-if="form.items.length > 0">
                    <label class="mb-2 block text-sm font-medium">أصناف المرتجع (عدّل الكميات حسب الحاجة)</label>
                    <div v-for="(item, idx) in form.items" :key="idx" class="mb-2 grid grid-cols-[1fr_auto_auto_auto] gap-2 text-sm">
                        <span class="flex items-center rounded border border-gray-200 bg-gray-50 px-3 py-2">{{ item.item_name }}</span>
                        <input v-model.number="item.qty" class="input-field w-24" type="number" min="0.01" step="0.01" />
                        <span class="flex items-center text-gray-500">× {{ item.unit_cost.toFixed(2) }} ج</span>
                        <button type="button" class="text-red-500" @click="removeRow(idx)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="mt-3 text-left font-bold text-red-700">
                        إجمالي المرتجع: {{ returnTotal().toFixed(2) }} ج
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showModal = false">إلغاء</button>
                    <button type="submit" class="btn-danger" :disabled="form.processing || form.items.length === 0">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'تسجيل المرتجع' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
