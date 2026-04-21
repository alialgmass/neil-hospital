<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Download } from 'lucide-vue-next'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface AccountRow {
    name: string
    amount: number
}

const props = defineProps<{
    data: {
        revenues: AccountRow[]
        expenses: AccountRow[]
        totalRevenue: number
        totalExpense: number
        netIncome: number
        from: string
        to: string
    }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/profit-loss', { from: from.value, to: to.value }, { preserveState: true })
}

function exportExcel() {
    window.location.href = `/reports/profit-loss/export?from=${from.value}&to=${to.value}`
}

function printPage() {
 window.print() 
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">قائمة الدخل (ربح وخسارة)</h1>
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

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Revenues -->
            <div class="overflow-hidden rounded-xl border border-green-200 bg-white shadow-sm">
                <div class="border-b border-green-100 bg-green-50 px-4 py-3">
                    <h2 class="font-semibold text-green-800">الإيرادات</h2>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="(row, idx) in data.revenues" :key="idx" class="border-t border-gray-100">
                            <td class="px-4 py-2">{{ row.name }}</td>
                            <td class="px-4 py-2 text-right text-green-700">{{ Number(row.amount).toFixed(2) }} ج</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-green-300 bg-green-50">
                        <tr>
                            <td class="px-4 py-2 font-bold">إجمالي الإيرادات</td>
                            <td class="px-4 py-2 text-right font-bold text-green-800">{{ Number(data.totalRevenue).toFixed(2) }} ج</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Expenses -->
            <div class="overflow-hidden rounded-xl border border-red-200 bg-white shadow-sm">
                <div class="border-b border-red-100 bg-red-50 px-4 py-3">
                    <h2 class="font-semibold text-red-800">المصروفات</h2>
                </div>
                <table class="w-full text-sm">
                    <tbody>
                        <tr v-for="(row, idx) in data.expenses" :key="idx" class="border-t border-gray-100">
                            <td class="px-4 py-2">{{ row.name }}</td>
                            <td class="px-4 py-2 text-right text-red-700">{{ Number(row.amount).toFixed(2) }} ج</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-red-300 bg-red-50">
                        <tr>
                            <td class="px-4 py-2 font-bold">إجمالي المصروفات</td>
                            <td class="px-4 py-2 text-right font-bold text-red-800">{{ Number(data.totalExpense).toFixed(2) }} ج</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Net Income -->
        <div
            class="mt-6 rounded-xl border-2 p-6 text-center"
            :class="data.netIncome >= 0 ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50'"
        >
            <p class="text-lg font-semibold text-gray-700">صافي الدخل</p>
            <p
                class="mt-2 text-3xl font-bold"
                :class="data.netIncome >= 0 ? 'text-green-700' : 'text-red-700'"
            >
                {{ Number(data.netIncome).toFixed(2) }} ج
            </p>
            <p class="mt-1 text-sm text-gray-500">{{ data.netIncome >= 0 ? 'ربح' : 'خسارة' }}</p>
        </div>
    </div>
</template>
