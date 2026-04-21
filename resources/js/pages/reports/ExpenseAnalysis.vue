<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Row {
    code: string
    name: string
    entries: number
    total: number
}

const props = defineProps<{
    data: { rows: Row[]; total: number; from: string; to: string }
    filters: { from: string; to: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function search() {
    router.get('/reports/expense-analysis', { from: from.value, to: to.value }, { preserveState: true })
}

function pct(amount: number): string {
    return props.data.total > 0 ? ((amount / props.data.total) * 100).toFixed(1) + '%' : '0%'
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800">تحليل المصروفات</h1>
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
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الكود</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">البند</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">عدد القيود</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الإجمالي</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">النسبة</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ row.code }}</td>
                        <td class="px-4 py-3 font-medium">{{ row.name }}</td>
                        <td class="px-4 py-3">{{ row.entries }}</td>
                        <td class="px-4 py-3 font-medium text-red-700">{{ Number(row.total).toFixed(2) }} ج</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                    <div
                                        class="h-full rounded-full bg-red-500"
                                        :style="{ width: pct(Number(row.total)) }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-500">{{ pct(Number(row.total)) }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="5">لا توجد مصروفات</td>
                    </tr>
                </tbody>
                <tfoot class="border-t-2 border-gray-300 bg-gray-50">
                    <tr>
                        <td class="px-4 py-3 font-bold" colspan="3">الإجمالي</td>
                        <td class="px-4 py-3 font-bold text-red-800">{{ Number(data.total).toFixed(2) }} ج</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
