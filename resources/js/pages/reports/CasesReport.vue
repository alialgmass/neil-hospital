<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'

defineOptions({ layout: AppLayout })

interface Row {
    file_no: string
    patient_name: string
    dept: string
    service: string
    doctor_name: string
    price: number
    pay_status: string
    status: string
    visit_date: string
}

const props = defineProps<{
    data: { rows: Row[]; from: string; to: string; dept?: string }
    filters: { from: string; to: string; dept?: string }
}>()

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const dept = ref(props.filters.dept ?? '')

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات', lasik: 'الليزك', laser: 'الليزر',
}

function search() {
    router.get('/reports/cases', { from: from.value, to: to.value, dept: dept.value || undefined }, { preserveState: true })
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">تقرير الحالات</h1>
            <span class="text-sm text-gray-500">{{ data.rows.length }} حالة</span>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <input v-model="from" class="input-field" type="date" />
            <span class="text-gray-500">إلى</span>
            <input v-model="to" class="input-field" type="date" />
            <select v-model="dept" class="input-field">
                <option value="">كل الأقسام</option>
                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <button class="btn-primary" @click="search">بحث</button>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم الملف</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">المريض</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">القسم</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الخدمة</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الطبيب</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">السعر</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">الدفع</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in data.rows" :key="idx" class="border-t border-gray-100 hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ row.file_no }}</td>
                        <td class="px-4 py-3 font-medium">{{ row.patient_name }}</td>
                        <td class="px-4 py-3">{{ deptLabels[row.dept] || row.dept }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ row.service }}</td>
                        <td class="px-4 py-3">{{ row.doctor_name || '—' }}</td>
                        <td class="px-4 py-3">{{ Number(row.price).toFixed(2) }} ج</td>
                        <td class="px-4 py-3">
                            <Badge :variant="(row.pay_status === 'paid' ? 'active' : row.pay_status === 'partial' ? 'pending' : 'cancelled')">
                                {{ row.pay_status === 'paid' ? 'مسدد' : row.pay_status === 'partial' ? 'جزئي' : 'غير مسدد' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ row.visit_date }}</td>
                    </tr>
                    <tr v-if="data.rows.length === 0">
                        <td class="px-4 py-8 text-center text-gray-400" colspan="8">لا توجد حالات</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
