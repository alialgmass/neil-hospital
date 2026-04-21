<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'

defineOptions({ layout: AppLayout })

interface Row {
    item_name?: string
    unit?: string
    type: 'in' | 'out'
    permit_no: string
    department?: string
    qty: number
    unit_cost: number
    total: number
    permit_date: string
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/inventory-movement', { from: from.value, to: to.value }, { preserveState: true })
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">تقرير حركة المخزون</h1>
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
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الإذن</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">النوع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">القسم</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الكمية</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">القيمة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ row.item_name || '—' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ row.permit_no }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="(row.type === 'in' ? 'active' : 'cancelled')">
                                {{ row.type === 'in' ? 'وارد' : 'صادر' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ row.department || '—' }}</td>
                        <td class="px-4 py-3">{{ row.qty }} {{ row.unit || '' }}</td>
                        <td class="px-4 py-3">{{ Number(row.total).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-gray-500">{{ row.permit_date }}</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="7">لا توجد حركات</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
