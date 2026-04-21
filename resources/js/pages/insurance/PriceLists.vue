<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'
import Modal from '@/components/shared/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { ChevronDown, ChevronUp, Printer, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'

defineOptions({ layout: AppLayout })

interface Company {
    id: string
    name: string
    coverage_pct: number
}

interface ServiceItem {
    id: string
    name: string
    dept: string
    price: number
    ins_price: number
}

interface PriceListItem {
    service_id: string
    price: number
    service?: { name: string; dept: string }
}

interface PriceList {
    id: string
    name: string
    type: string
    ins_coverage?: number
    discount_pct: number
    is_active: boolean
    company?: Company
    items: PriceListItem[]
}

const props = defineProps<{
    priceLists: { data: PriceList[]; links: unknown[] }
    companies: Company[]
    services: ServiceItem[]
}>()

const showModal = ref(false)
const expandedList = ref<string | null>(null)

const form = useForm({
    name: '',
    type: 'insurance' as 'cash' | 'insurance' | 'vip' | 'special',
    ins_company_id: '',
    ins_coverage: 80,
    discount_pct: 0,
    notes: '',
    items: [] as { service_id: string; price: number }[],
})

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
}

const typeLabels: Record<string, string> = {
    cash: 'نقدي',
    insurance: 'تأمين',
    vip: 'VIP',
    special: 'خاص',
}

function addServiceRow() {
    form.items.push({ service_id: '', price: 0 })
}

function removeServiceRow(index: number) {
    form.items.splice(index, 1)
}

function onServiceSelect(index: number) {
    const svc = props.services.find((s) => s.id === form.items[index].service_id)
    if (svc) {
        form.items[index].price = svc.ins_price || svc.price
    }
}

function submit() {
    form.post('/price-lists', { onSuccess: () => { showModal.value = false; form.reset() } })
}

function toggleExpand(id: string) {
    expandedList.value = expandedList.value === id ? null : id
}

function printList(list: PriceList) {
    window.print()
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">قوائم الأسعار</h1>
            <button class="btn-primary" @click="showModal = true">+ إنشاء قائمة</button>
        </div>

        <!-- Price Lists -->
        <div class="space-y-4">
            <div
                v-for="list in priceLists.data"
                :key="list.id"
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
            >
                <!-- List Header -->
                <div class="flex items-center justify-between p-4">
                    <div class="flex items-center gap-3">
                        <button class="text-gray-400 hover:text-gray-600" @click="toggleExpand(list.id)">
                            <ChevronDown v-if="expandedList !== list.id" class="h-5 w-5" />
                            <ChevronUp v-else class="h-5 w-5" />
                        </button>
                        <div>
                            <p class="font-semibold text-gray-800">{{ list.name }}</p>
                            <p v-if="list.company" class="text-sm text-gray-500">{{ list.company.name }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Badge variant="active">{{ typeLabels[list.type] || list.type }}</Badge>
                        <span v-if="list.ins_coverage" class="text-sm text-blue-700 font-medium">
                            تغطية {{ list.ins_coverage }}%
                        </span>
                        <button class="text-gray-400 hover:text-blue-600" @click="printList(list)">
                            <Printer class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Items Table (expanded) -->
                <div v-if="expandedList === list.id" class="border-t border-gray-100">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">الخدمة</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">القسم</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">السعر</th>
                                <th v-if="list.ins_coverage" class="px-4 py-2 text-right font-medium text-gray-600">يتحمله التأمين</th>
                                <th v-if="list.ins_coverage" class="px-4 py-2 text-right font-medium text-gray-600">يتحمله المريض</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in list.items" :key="item.service_id" class="border-t border-gray-100">
                                <td class="px-4 py-2">{{ item.service?.name || item.service_id }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ deptLabels[item.service?.dept ?? ''] || '' }}</td>
                                <td class="px-4 py-2">{{ item.price.toFixed(2) }} ج</td>
                                <td v-if="list.ins_coverage" class="px-4 py-2 text-blue-700">
                                    {{ ((item.price * (list.ins_coverage ?? 0)) / 100).toFixed(2) }} ج
                                </td>
                                <td v-if="list.ins_coverage" class="px-4 py-2 text-orange-700">
                                    {{ (item.price - (item.price * (list.ins_coverage ?? 0)) / 100).toFixed(2) }} ج
                                </td>
                            </tr>
                            <tr v-if="list.items.length === 0">
                                <td class="px-4 py-4 text-center text-gray-400" colspan="5">لا توجد خدمات في هذه القائمة</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="priceLists.data.length === 0" class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-400">
                لا توجد قوائم أسعار
            </div>
        </div>

        <!-- Create Modal -->
        <Modal v-model="showModal" title="إنشاء قائمة أسعار" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">اسم القائمة *</label>
                        <input v-model="form.name" class="input-field" type="text" required />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">النوع</label>
                        <select v-model="form.type" class="input-field">
                            <option value="cash">نقدي</option>
                            <option value="insurance">تأمين</option>
                            <option value="vip">VIP</option>
                            <option value="special">خاص</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'insurance'">
                        <label class="mb-1 block text-sm font-medium">شركة التأمين</label>
                        <select v-model="form.ins_company_id" class="input-field">
                            <option value="">— اختر شركة —</option>
                            <option v-for="co in companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'insurance'">
                        <label class="mb-1 block text-sm font-medium">نسبة التغطية %</label>
                        <input v-model.number="form.ins_coverage" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نسبة الخصم %</label>
                        <input v-model.number="form.discount_pct" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                </div>

                <!-- Service Items -->
                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <label class="text-sm font-medium">الخدمات وأسعارها</label>
                        <button type="button" class="text-sm text-blue-600 hover:underline" @click="addServiceRow">+ إضافة خدمة</button>
                    </div>
                    <div v-for="(item, idx) in form.items" :key="idx" class="mb-2 grid grid-cols-[1fr_auto_auto] gap-2">
                        <select v-model="item.service_id" class="input-field" @change="onServiceSelect(idx)">
                            <option value="">— اختر خدمة —</option>
                            <option v-for="svc in services" :key="svc.id" :value="svc.id">{{ svc.name }}</option>
                        </select>
                        <input v-model.number="item.price" class="input-field w-28" type="number" min="0" step="0.01" placeholder="السعر" />
                        <button type="button" class="text-red-500" @click="removeServiceRow(idx)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showModal = false">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
