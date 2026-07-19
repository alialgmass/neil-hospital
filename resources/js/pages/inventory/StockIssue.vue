<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3'
import { ChevronDown, ChevronLeft, ClipboardList, PackageOpen, Trash2, TrendingDown } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Modal from '@/components/shared/Modal.vue'
import StatCard from '@/components/shared/StatCard.vue'

defineOptions({ layout: AppLayout })

interface SelectableItem {
    id: string
    name: string
    unit: string
    quantity: number
    unit_cost: number
}

interface PermitItem {
    item_id: string | null
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
    notes?: string
    created_at: string
    items: PermitItem[]
    creator?: { id: string; name: string }
}

interface ConsumptionRow {
    item_id: string | null
    item_name: string
    total_qty: number
    total_value: number
}

const props = defineProps<{
    date: string
    permits: { data: Permit[]; links: unknown[]; total: number; current_page: number; last_page: number }
    consumption: ConsumptionRow[]
    voucherCount: number
    totalItems: number
    totalValue: number
    selectableItems: SelectableItem[]
}>()

const selectedDate = ref(props.date)
const showModal = ref(false)
const expandedRows = ref<Set<string>>(new Set())

const isToday = computed(() => selectedDate.value === new Date().toISOString().slice(0, 10))

function navigateDate() {
    router.get('/stock-issue', { date: selectedDate.value }, { preserveState: false })
}

function toggleRow(id: string) {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id)
    } else {
        expandedRows.value.add(id)
    }
}

function permitTotal(permit: Permit): number {
    return permit.items.reduce((sum, item) => sum + item.qty * item.unit_cost, 0)
}

function formatTime(dateStr: string): string {
    return new Date(dateStr).toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' })
}

function formatMoney(val: number): string {
    return val.toLocaleString('ar-EG', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const allDeptOptions = [
    { value: 'surgery', label: 'العمليات' },
    { value: 'lasik', label: 'الليزك' },
    { value: 'laser', label: 'الليزر' },
    { value: 'clinic', label: 'العيادة' },
    { value: 'labs', label: 'الفحوصات' },
    { value: 'admin', label: 'الإدارة' },
]

const page = usePage<{ moduleStatus?: Record<string, boolean> }>()
const deptOptions = computed(() => {
    const moduleStatus = (page.props.moduleStatus as Record<string, boolean>) ?? {}

    return allDeptOptions.filter((dept) => moduleStatus[dept.value] !== false)
})

// Issue form
const form = useForm({
    department: '',
    reason: '',
    notes: '',
    items: [] as { item_id: string; item_name: string; qty: number; unit_cost: number }[],
})

const formTotal = computed(() =>
    form.items.reduce((sum, item) => sum + item.qty * item.unit_cost, 0),
)

function addRow() {
    form.items.push({ item_id: '', item_name: '', qty: 1, unit_cost: 0 })
}

function removeRow(idx: number) {
    form.items.splice(idx, 1)
}

function onItemSelect(idx: number) {
    const found = props.selectableItems.find((i) => i.id === form.items[idx].item_id)
    if (found) {
        form.items[idx].item_name = found.name
        form.items[idx].unit_cost = found.unit_cost
    }
}

function submitIssue() {
    form.post('/stock-issue', {
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
                <h1 class="text-2xl font-bold text-gray-800">أذون الصرف اليومية</h1>
                <p class="mt-0.5 text-sm text-gray-500">المستهلكات وأذون الصرف بحسب اليوم</p>
            </div>
            <button
                class="flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-red-700"
                @click="showModal = true"
            >
                <PackageOpen class="h-4 w-4" />
                إذن صرف جديد
            </button>
        </div>

        <!-- Date Filter -->
        <div class="mb-6 flex items-center gap-3">
            <label class="text-sm font-medium text-gray-600">اليوم:</label>
            <input
                v-model="selectedDate"
                type="date"
                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm focus:border-blue-500 focus:outline-none"
                @change="navigateDate"
            />
            <span
                v-if="isToday"
                class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-700"
            >
                اليوم
            </span>
        </div>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-3 gap-4">
            <StatCard label="عدد أذون الصرف" :value="voucherCount" color="primary">
                <template #icon><ClipboardList class="h-8 w-8" /></template>
            </StatCard>
            <StatCard label="إجمالي الأصناف المصروفة" :value="totalItems.toFixed(2)" color="warning">
                <template #icon><PackageOpen class="h-8 w-8" /></template>
            </StatCard>
            <StatCard label="إجمالي القيمة المصروفة (ج.م)" :value="formatMoney(totalValue)" color="danger">
                <template #icon><TrendingDown class="h-8 w-8" /></template>
            </StatCard>
        </div>

        <!-- Permits List -->
        <div class="mb-8">
            <h2 class="mb-3 text-base font-semibold text-gray-700">أذون الصرف</h2>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-8 px-3 py-3"></th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الإذن</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">القسم</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">السبب</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الأصناف</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">القيمة (ج.م)</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الوقت</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="permit in permits.data" :key="permit.id">
                            <!-- Main row -->
                            <tr
                                class="cursor-pointer border-t border-gray-100 hover:bg-gray-50"
                                @click="toggleRow(permit.id)"
                            >
                                <td class="px-3 py-3 text-gray-400">
                                    <component
                                        :is="expandedRows.has(permit.id) ? ChevronDown : ChevronLeft"
                                        class="h-4 w-4"
                                    />
                                </td>
                                <td class="px-4 py-3 font-mono font-semibold text-red-700">
                                    {{ permit.permit_no }}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ permit.department || '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ permit.reason || '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ permit.items.length }} صنف
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-800">
                                    {{ formatMoney(permitTotal(permit)) }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ formatTime(permit.created_at) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ permit.creator?.name || '—' }}</td>
                            </tr>
                            <!-- Expanded items -->
                            <tr v-if="expandedRows.has(permit.id)" class="bg-red-50/40">
                                <td></td>
                                <td colspan="7" class="px-4 pb-3 pt-1">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-gray-500">
                                                <th class="pb-1 text-right font-medium">الصنف</th>
                                                <th class="pb-1 text-right font-medium">الكمية</th>
                                                <th class="pb-1 text-right font-medium">سعر الوحدة</th>
                                                <th class="pb-1 text-right font-medium">الإجمالي</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(item, idx) in permit.items"
                                                :key="idx"
                                                class="border-t border-red-100"
                                            >
                                                <td class="py-1.5 font-medium text-gray-800">{{ item.item_name }}</td>
                                                <td class="py-1.5 text-gray-600">{{ item.qty }}</td>
                                                <td class="py-1.5 text-gray-600">{{ formatMoney(item.unit_cost) }}</td>
                                                <td class="py-1.5 font-medium text-red-700">
                                                    {{ formatMoney(item.qty * item.unit_cost) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="permits.data.length === 0">
                            <td class="px-4 py-10 text-center text-gray-400" colspan="8">
                                لا توجد أذون صرف في هذا اليوم
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div v-if="permits.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                    <span class="text-xs text-gray-500">صفحة {{ permits.current_page }} من {{ permits.last_page }}</span>
                    <div class="flex gap-2">
                        <button
                            v-for="link in (permits.links as Array<{url: string|null; label: string; active: boolean}>)"
                            :key="link.label"
                            :disabled="!link.url"
                            class="rounded px-2.5 py-1 text-xs"
                            :class="link.active ? 'bg-red-600 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50 disabled:opacity-40'"
                            @click="link.url && router.get(link.url)"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Consumption Summary -->
        <div>
            <h2 class="mb-3 text-base font-semibold text-gray-700">ملخص المستهلكات</h2>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الصنف</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">إجمالي الكمية</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">إجمالي القيمة (ج.م)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in consumption"
                            :key="row.item_name"
                            class="border-t border-gray-100"
                        >
                            <td class="px-4 py-3 font-medium text-gray-800">{{ row.item_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ row.total_qty }}</td>
                            <td class="px-4 py-3 font-semibold text-red-700">{{ formatMoney(Number(row.total_value)) }}</td>
                        </tr>
                        <tr v-if="consumption.length === 0">
                            <td class="px-4 py-10 text-center text-gray-400" colspan="3">لا توجد مستهلكات في هذا اليوم</td>
                        </tr>
                        <!-- Totals row -->
                        <tr v-if="consumption.length > 0" class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                            <td class="px-4 py-3 text-gray-700">الإجمالي</td>
                            <td class="px-4 py-3 text-gray-700">{{ totalItems.toFixed(2) }}</td>
                            <td class="px-4 py-3 text-red-700">{{ formatMoney(totalValue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Issue Voucher Modal -->
        <Modal v-model="showModal" title="إذن صرف مخزون" size="lg">
            <form class="space-y-4" @submit.prevent="submitIssue">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">القسم</label>
                        <select v-model="form.department" class="input-field">
                            <option value="">— اختر القسم —</option>
                            <option v-for="opt in deptOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">السبب / الغرض</label>
                        <input v-model="form.reason" class="input-field" type="text" placeholder="مثال: استهلاك تشغيلي" />
                    </div>
                </div>

                <div>
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="form.notes" class="input-field" rows="2" placeholder="اختياري" />
                </div>

                <!-- Items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-semibold text-hospital-text">الأصناف المصروفة</label>
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
                                    <th class="w-28 px-3 py-2 text-right font-medium">سعر الوحدة</th>
                                    <th class="w-8"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, idx) in form.items" :key="idx" class="border-t border-hospital-border">
                                    <td class="px-3 py-2">
                                        <select v-model="item.item_id" class="input-field text-xs" @change="onItemSelect(idx)">
                                            <option value="">— اختر صنف —</option>
                                            <option v-for="inv in selectableItems" :key="inv.id" :value="inv.id">
                                                {{ inv.name }} ({{ inv.quantity }} {{ inv.unit }})
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2">
                                        <input v-model.number="item.qty" class="input-field text-center text-xs" type="number" min="0.01" step="0.01" placeholder="0" />
                                    </td>
                                    <td class="px-3 py-2 text-xs text-hospital-text-2">
                                        {{ item.unit_cost > 0 ? formatMoney(item.unit_cost) : '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button type="button" class="text-hospital-danger/60 hover:text-hospital-danger" @click="removeRow(idx)">
                                            <Trash2 class="h-3.5 w-3.5" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="form.items.length === 0">
                                    <td class="px-3 py-5 text-center text-xs text-hospital-text-3" colspan="4">
                                        اضغط "+ إضافة صنف" لإضافة الأصناف المراد صرفها
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot v-if="form.items.length > 0" class="border-t-2 border-hospital-border bg-hospital-bg">
                                <tr>
                                    <td class="px-3 py-2 text-xs font-semibold text-hospital-text-2" colspan="2">الإجمالي</td>
                                    <td class="px-3 py-2 text-sm font-bold text-hospital-danger" colspan="2">
                                        {{ formatMoney(formTotal) }} ج.م
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p v-if="form.errors.items" class="form-error">{{ form.errors.items }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                    <button type="button" class="btn-secondary" @click="showModal = false">إلغاء</button>
                    <button type="submit" class="btn-danger" :disabled="form.processing || form.items.length === 0">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'إصدار الإذن' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
