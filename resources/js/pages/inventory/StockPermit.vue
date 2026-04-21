<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Minus, Plus, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'
import Modal from '@/components/shared/Modal.vue'

defineOptions({ layout: AppLayout })

interface InventoryItem {
    id: string
    name: string
    unit: string
    quantity: number
    unit_cost: number
}

interface PermitItem {
    item_id: string
    item_name: string
    qty: number
    unit_cost: number
}

interface Permit {
    id: string
    permit_no: string
    type: 'in' | 'out'
    department?: string
    reason?: string
    created_at: string
    items: PermitItem[]
}

const props = defineProps<{
    permits: { data: Permit[]; links: unknown[] }
    items: InventoryItem[]
}>()

const showIssueModal = ref(false)
const showAddModal = ref(false)

const issueForm = useForm({
    department: '',
    reason: '',
    notes: '',
    items: [] as { item_id: string; item_name: string; qty: number; unit_cost: number }[],
})

const addForm = useForm({
    department: '',
    reason: '',
    notes: '',
    items: [] as { item_id: string; item_name: string; qty: number; unit_cost: number }[],
})

function addItemRow(form: typeof issueForm | typeof addForm) {
    form.items.push({ item_id: '', item_name: '', qty: 1, unit_cost: 0 })
}

function removeItemRow(form: typeof issueForm | typeof addForm, index: number) {
    form.items.splice(index, 1)
}

function onItemSelect(form: typeof issueForm | typeof addForm, index: number) {
    const selected = props.items.find((i) => i.id === form.items[index].item_id)

    if (selected) {
        form.items[index].item_name = selected.name
        form.items[index].unit_cost = selected.unit_cost
    }
}

function submitIssue() {
    issueForm.post('/stock-permits/issue', {
        onSuccess: () => {
            showIssueModal.value = false
            issueForm.reset()
        },
    })
}

function submitAdd() {
    addForm.post('/stock-permits/add', {
        onSuccess: () => {
            showAddModal.value = false
            addForm.reset()
        },
    })
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">أذونات المخزن</h1>
            <div class="flex gap-3">
                <button
                    class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-white hover:bg-red-700"
                    @click="showIssueModal = true"
                >
                    <Minus class="h-4 w-4" />
                    إذن صرف
                </button>
                <button
                    class="flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2 text-white hover:bg-green-700"
                    @click="showAddModal = true"
                >
                    <Plus class="h-4 w-4" />
                    إذن إضافة
                </button>
            </div>
        </div>

        <!-- Permits Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الإذن</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">النوع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">القسم</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">السبب</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">عدد الأصناف</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="permit in permits.data"
                        :key="permit.id"
                        class="border-t border-gray-100 hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 font-mono font-medium">{{ permit.permit_no }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="(permit.type === 'in' ? 'active' : 'cancelled')">
                                {{ permit.type === 'in' ? 'إضافة' : 'صرف' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ permit.department || '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ permit.reason || '—' }}</td>
                        <td class="px-4 py-3">{{ permit.items.length }} صنف</td>
                        <td class="px-4 py-3 text-gray-500">{{ permit.created_at }}</td>
                    </tr>
                    <tr v-if="permits.data.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="6">لا توجد أذونات</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Issue Modal (صرف) -->
        <Modal :show="showIssueModal" title="إذن صرف مخزون" @close="showIssueModal = false">
            <form class="space-y-4" @submit.prevent="submitIssue">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">القسم</label>
                        <input v-model="issueForm.department" class="input-field" type="text" placeholder="مثال: غرفة عمليات 1" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">السبب</label>
                        <input v-model="issueForm.reason" class="input-field" type="text" />
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium">الأصناف</label>
                        <button type="button" class="text-sm text-blue-600 hover:underline" @click="addItemRow(issueForm)">+ إضافة صنف</button>
                    </div>
                    <div v-for="(item, idx) in issueForm.items" :key="idx" class="mb-2 grid grid-cols-[1fr_auto_auto] gap-2">
                        <select v-model="item.item_id" class="input-field" @change="onItemSelect(issueForm, idx)">
                            <option value="">— اختر صنف —</option>
                            <option v-for="inv in items" :key="inv.id" :value="inv.id">{{ inv.name }} ({{ inv.quantity }} {{ inv.unit }})</option>
                        </select>
                        <input v-model.number="item.qty" class="input-field w-24" type="number" min="0.01" step="0.01" placeholder="الكمية" />
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeItemRow(issueForm, idx)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <p v-if="issueForm.errors.items" class="text-sm text-red-500">{{ issueForm.errors.items }}</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showIssueModal = false">إلغاء</button>
                    <button type="submit" class="btn-danger" :disabled="issueForm.processing">
                        {{ issueForm.processing ? 'جارٍ الحفظ...' : 'إصدار الإذن' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Add Modal (إضافة) -->
        <Modal :show="showAddModal" title="إذن إضافة مخزون" @close="showAddModal = false">
            <form class="space-y-4" @submit.prevent="submitAdd">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">القسم</label>
                        <input v-model="addForm.department" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">السبب</label>
                        <input v-model="addForm.reason" class="input-field" type="text" />
                    </div>
                </div>

                <!-- Items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium">الأصناف</label>
                        <button type="button" class="text-sm text-blue-600 hover:underline" @click="addItemRow(addForm)">+ إضافة صنف</button>
                    </div>
                    <div v-for="(item, idx) in addForm.items" :key="idx" class="mb-2 grid grid-cols-[1fr_auto_auto] gap-2">
                        <select v-model="item.item_id" class="input-field" @change="onItemSelect(addForm, idx)">
                            <option value="">— اختر صنف —</option>
                            <option v-for="inv in items" :key="inv.id" :value="inv.id">{{ inv.name }}</option>
                        </select>
                        <input v-model.number="item.qty" class="input-field w-24" type="number" min="0.01" step="0.01" placeholder="الكمية" />
                        <button type="button" class="text-red-500 hover:text-red-700" @click="removeItemRow(addForm, idx)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showAddModal = false">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="addForm.processing">
                        {{ addForm.processing ? 'جارٍ الحفظ...' : 'إصدار الإذن' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
