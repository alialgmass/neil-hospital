<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Row {
    item_name: string
    supplier_name?: string
    avg_cost: number
    min_cost: number
    max_cost: number
    total_qty: number
    total_value: number
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/purchase-prices', { from: from.value, to: to.value }, { preserveState: true })
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">تقرير أسعار الشراء</h1>
        </div>

        <div class="mb-4 flex items-center gap-3">
            <input v-model="from" class="input-field" type="date" />
            <span class="text-gray-500">إلى</span>
            <input v-model="to" class="input-field" type="date" />
            <button class="btn-primary" @click="search">بحث</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الصنف</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المورد</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">متوسط السعر</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">أقل سعر</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">أعلى سعر</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الكمية</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">إجمالي</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ row.item_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ row.supplier_name || '—' }}</td>
                        <td class="px-4 py-3">{{ Number(row.avg_cost).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-green-700">{{ Number(row.min_cost).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-red-700">{{ Number(row.max_cost).toFixed(2) }} ج</td>
                        <td class="px-4 py-3">{{ row.total_qty }}</td>
                        <td class="px-4 py-3 font-medium">{{ Number(row.total_value).toFixed(2) }} ج</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="7">لا توجد بيانات</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
