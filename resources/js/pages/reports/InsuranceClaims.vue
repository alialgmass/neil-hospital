<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Row {
    company_name?: string
    cases: number
    total_billed: number
    ins_amount: number
    patient_amount: number
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/insurance', { from: from.value, to: to.value }, { preserveState: true })
}

const totalIns = props.data.rows.reduce((s, r) => s + Number(r.ins_amount), 0)
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">تقرير مطالبات التأمين</h1>
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
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">شركة التأمين</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالات</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">إجمالي الفواتير</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">مطالبة التأمين</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">تحمّل المريض</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ row.company_name || 'غير محدد' }}</td>
                        <td class="px-4 py-3">{{ row.cases }}</td>
                        <td class="px-4 py-3">{{ Number(row.total_billed).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 font-medium text-blue-700">{{ Number(row.ins_amount).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-orange-700">{{ Number(row.patient_amount).toFixed(2) }} ج</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="5">لا توجد بيانات</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td class="px-4 py-3 font-bold" colspan="3">إجمالي مطالبات التأمين</td>
                        <td class="px-4 py-3 font-bold text-blue-800">{{ totalIns.toFixed(2) }} ج</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
