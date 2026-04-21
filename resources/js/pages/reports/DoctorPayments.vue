<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Row {
    doctor_name: string
    amount: number
    period_from: string
    period_to: string
    paid_at: string
    paid_by_name: string
    notes?: string
}

const props = defineProps<{
    data: { rows: Row[]; total: number; from: string; to: string }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/doctor-payments', { from: from.value, to: to.value }, { preserveState: true })
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">تقرير مدفوعات الأطباء</h1>
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
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الطبيب</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المبلغ</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">فترة الاستحقاق</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">تاريخ الدفع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">بواسطة</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ row.doctor_name }}</td>
                        <td class="px-4 py-3 font-medium text-green-700">{{ Number(row.amount).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-gray-600">{{ row.period_from }} — {{ row.period_to }}</td>
                        <td class="px-4 py-3">{{ row.paid_at }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ row.paid_by_name || '—' }}</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="5">لا توجد مدفوعات</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td class="px-4 py-3 font-bold">الإجمالي</td>
                        <td class="px-4 py-3 font-bold text-green-800">{{ Number(data.total).toFixed(2) }} ج</td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
