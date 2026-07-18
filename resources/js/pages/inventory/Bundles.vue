<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { PackagePlus, Pencil, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Modal from '@/components/shared/Modal.vue'

defineOptions({ layout: AppLayout })

interface InventoryItem {
    id: string
    name: string
    code: string
    unit: string
    unit_cost: number
    quantity: number
}

interface BundleItem {
    inventory_item_id: string
    item_name: string
    qty: number
    unit_cost: number
}

interface Bundle {
    id: string
    name: string
    code: string | null
    dept: string | null
    price: number
    is_active: boolean
    notes: string | null
    items: (BundleItem & { id: number })[]
}

const props = defineProps<{
    bundles: { data: Bundle[]; links: unknown[]; total: number }
    inventoryItems: InventoryItem[]
}>()

const deptLabels: Record<string, string> = {
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
}

const showModal = ref(false)
const editingBundle = ref<Bundle | null>(null)

const form = useForm({
    name: '',
    code: '',
    dept: '' as string,
    price: 0,
    is_active: true,
    notes: '',
    items: [] as { inventory_item_id: string; item_name: string; qty: number; unit_cost: number }[],
})

function openCreate() {
    editingBundle.value = null
    form.reset()
    form.items = []
    showModal.value = true
}

function openEdit(bundle: Bundle) {
    editingBundle.value = bundle
    form.name = bundle.name
    form.code = bundle.code ?? ''
    form.dept = bundle.dept ?? ''
    form.price = bundle.price
    form.is_active = bundle.is_active
    form.notes = bundle.notes ?? ''
    form.items = bundle.items.map((i) => ({
        inventory_item_id: i.inventory_item_id ?? '',
        item_name: i.item_name,
        qty: Number(i.qty),
        unit_cost: Number(i.unit_cost),
    }))
    showModal.value = true
}

function addItemRow() {
    form.items.push({ inventory_item_id: '', item_name: '', qty: 1, unit_cost: 0 })
}

function removeItemRow(idx: number) {
    form.items.splice(idx, 1)
}

function onInventorySelect(idx: number) {
    const inv = props.inventoryItems.find((i) => i.id === form.items[idx].inventory_item_id)
    if (inv) {
        form.items[idx].item_name = inv.name
        form.items[idx].unit_cost = inv.unit_cost
    }
}

function submit() {
    if (editingBundle.value) {
        form.put(`/supply-bundles/${editingBundle.value.id}`, {
            onSuccess: () => {
                showModal.value = false
                form.reset()
                form.items = []
            },
        })
    } else {
        form.post('/supply-bundles', {
            onSuccess: () => {
                showModal.value = false
                form.reset()
                form.items = []
            },
        })
    }
}

const itemsSubtotal = computed(() =>
    form.items.reduce((sum, i) => sum + i.qty * i.unit_cost, 0),
)

function formatMoney(val: number) {
    return Number(val).toLocaleString('ar-EG', { minimumFractionDigits: 2 })
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">بنود المستلزمات</h1>
                <p class="mt-0.5 text-sm text-gray-500">
                    مجموعات مستلزمات جاهزة تُستخدم في العمليات والليزك
                </p>
            </div>
            <button
                class="flex items-center gap-2 rounded-lg bg-purple-700 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-purple-800"
                @click="openCreate"
            >
                <PackagePlus class="h-4 w-4" />
                إنشاء بند جديد
            </button>
        </div>

        <!-- Bundles table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">اسم البند</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الكود</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">القسم</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">السعر (ج.م)</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">عدد الأصناف</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالة</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="bundle in bundles.data"
                        :key="bundle.id"
                        class="border-t border-gray-100 hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 font-medium text-gray-800">{{ bundle.name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ bundle.code || '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ bundle.dept ? (deptLabels[bundle.dept] ?? bundle.dept) : 'الكل' }}
                        </td>
                        <td class="px-4 py-3 font-semibold text-purple-700">{{ formatMoney(bundle.price) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-purple-100 px-2.5 py-0.5 text-xs font-medium text-purple-800">
                                {{ bundle.items.length }} صنف
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                :class="bundle.is_active
                                    ? 'bg-green-100 text-green-700'
                                    : 'bg-gray-100 text-gray-500'"
                                class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            >
                                {{ bundle.is_active ? 'نشط' : 'معطّل' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-left">
                            <button
                                class="rounded p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                                @click="openEdit(bundle)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="bundles.data.length === 0">
                        <td class="px-4 py-10 text-center text-gray-400" colspan="7">
                            لا توجد بنود مستلزمات — أنشئ بنداً جديداً
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create / Edit Modal -->
        <Modal v-model="showModal" :title="editingBundle ? 'تعديل البند' : 'إنشاء بند جديد'" size="xl">
            <form class="space-y-5" @submit.prevent="submit">
                <!-- Basic info -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="form-label">اسم البند <span class="text-hospital-danger">*</span></label>
                        <input v-model="form.name" class="input-field" type="text" placeholder="مثال: فتح غرفة العمليات" />
                        <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="form-label">الكود</label>
                        <input v-model="form.code" class="input-field" type="text" placeholder="اختياري" />
                    </div>
                    <div>
                        <label class="form-label">القسم</label>
                        <select v-model="form.dept" class="input-field">
                            <option value="">الكل (جميع الأقسام)</option>
                            <option value="surgery">العمليات</option>
                            <option value="lasik">الليزك</option>
                            <option value="laser">الليزر</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">سعر البند (ج.م) <span class="text-hospital-danger">*</span></label>
                        <input v-model.number="form.price" class="input-field" type="number" min="0" step="0.01" placeholder="0.00" />
                        <p class="form-hint">يُخصم من مستحقات الطبيب عند الاستخدام</p>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-hospital-border text-hospital-primary" />
                            <span class="text-sm font-medium text-hospital-text">بند نشط</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="form.notes" class="input-field" rows="2" placeholder="اختياري" />
                </div>

                <!-- Bundle items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-semibold text-hospital-text">أصناف البند</span>
                            <span class="mr-1.5 text-xs text-hospital-text-3">(تُنقص من المخزن عند الاستخدام)</span>
                        </div>
                        <button type="button" class="text-sm font-medium text-hospital-primary hover:underline" @click="addItemRow">
                            + إضافة صنف
                        </button>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-hospital-border">
                        <table class="w-full text-sm">
                            <thead class="bg-hospital-bg text-xs text-hospital-text-2">
                                <tr>
                                    <th class="px-3 py-2 text-right font-medium">الصنف من المخزن</th>
                                    <th class="px-3 py-2 text-right font-medium">الاسم المعروض</th>
                                    <th class="w-20 px-3 py-2 text-right font-medium">الكمية</th>
                                    <th class="w-24 px-3 py-2 text-right font-medium">التكلفة</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in form.items" :key="idx" class="border-t border-hospital-border">
                                    <td class="px-3 py-2">
                                        <select v-model="item.inventory_item_id" class="input-field text-xs" @change="onInventorySelect(idx)">
                                            <option value="">— اختر من المخزن —</option>
                                            <option v-for="inv in inventoryItems" :key="inv.id" :value="inv.id">
                                                {{ inv.name }} ({{ inv.quantity }} {{ inv.unit }})
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model="item.item_name" class="input-field text-xs" type="text" placeholder="يُملأ تلقائياً" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="item.qty" class="input-field text-center text-xs" type="number" min="0.01" step="0.01" placeholder="1" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="item.unit_cost" class="input-field text-center text-xs" type="number" min="0" step="0.01" placeholder="0.00" />
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-hospital-danger/50 hover:text-hospital-danger" @click="removeItemRow(idx)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td class="px-3 py-5 text-center text-xs text-hospital-text-3" colspan="5">
                                        اضغط "+ إضافة صنف" لتحديد أصناف المخزن داخل البند
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="form.items.length > 0" class="border-t-2 border-hospital-border bg-hospital-bg">
                                <tr>
                                    <td class="px-3 py-2 text-xs font-semibold text-hospital-text-2" colspan="3">إجمالي التكلفة الفعلية</td>
                                    <td class="px-3 py-2 text-sm font-bold text-hospital-success" colspan="2">
                                        {{ formatMoney(itemsSubtotal) }} ج.م
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="form.errors.items" class="form-error">{{ form.errors.items }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                    <button type="button" class="btn-secondary" @click="showModal = false">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : (editingBundle ? 'تحديث البند' : 'إنشاء البند') }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
