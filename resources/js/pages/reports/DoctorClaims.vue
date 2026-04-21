<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Row {
    doctor_name: string
    fee_type: string
    cases: number
    total_billed: number
    ins_amount: number
    net_billed: number
    doctor_claim: number
    center_share: number
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string }
    filters: { from: string; to: string; doctorId?: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/doctor-claims', { from: from.value, to: to.value }, { preserveState: true })
}

function exportExcel() {
    window.location.href = `/reports/doctor-claims/export?from=${from.value}&to=${to.value}`
}

function printPage() {
 window.print() 
}

const totalClaim = props.data.rows.reduce((s, r) => s + Number(r.doctor_claim), 0)
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">تقرير مستحقات الأطباء</h1>
            <div class="flex gap-2">
                <button class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="exportExcel">
                    <Download class="h-4 w-4" />
                    Excel
                </button>
                <button class="rounded-lg border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" @click="printPage">طباعة</button>
            </div>
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
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالات</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">إجمالي الفواتير</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">تأمين</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الصافي</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">مستحق الطبيب</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">حصة المركز</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">{{ row.doctor_name }}</td>
                        <td class="px-4 py-3">{{ row.cases }}</td>
                        <td class="px-4 py-3">{{ Number(row.total_billed).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-blue-700">{{ Number(row.ins_amount).toFixed(2) }} ج</td>
                        <td class="px-4 py-3">{{ Number(row.net_billed).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 font-bold text-green-700">{{ Number(row.doctor_claim).toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-gray-600">{{ Number(row.center_share).toFixed(2) }} ج</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="7">لا توجد بيانات</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td class="px-4 py-3 font-bold" colspan="5">إجمالي المستحقات</td>
                        <td class="px-4 py-3 font-bold text-green-800">{{ totalClaim.toFixed(2) }} ج</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
