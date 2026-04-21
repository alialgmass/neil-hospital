<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface InventoryItem {
    id: string
    name: string
    code?: string
    category?: string
    unit?: string
    quantity: number
    min_quantity: number
}

const props = defineProps<{ items: InventoryItem[] }>()

interface CountRow {
    item_id: string
    item_name: string
    system_qty: number
    physical_qty: number
    unit?: string
    category?: string
}

const form = useForm({
    notes: '',
    counts: props.items.map((item): CountRow => ({
        item_id: item.id,
        item_name: item.name,
        system_qty: item.quantity,
        physical_qty: item.quantity,
        unit: item.unit,
        category: item.category,
    })),
})

const categories = [...new Set(props.items.map((i) => i.category).filter(Boolean))]

function variance(row: CountRow): number {
    return row.physical_qty - row.system_qty
}

function submit() {
    form.post('/stock-take', {
        onSuccess: () => {
            // Refresh physical qtys to match submitted
            form.counts.forEach((row) => {
                row.system_qty = row.physical_qty
            })
        },
    })
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">تسوية الجرد</h1>
            <p class="text-sm text-gray-500">أدخل الكميات الفعلية لكل صنف ثم اضغط «تسجيل الجرد»</p>
        </div>

        <form @submit.prevent="submit">
            <div class="mb-4">
                <label class="mb-1 block text-sm font-medium">ملاحظات الجرد</label>
                <input v-model="form.notes" class="input-field w-full max-w-lg" type="text" placeholder="مثال: جرد شهر أبريل 2026" />
            </div>

            <!-- Group by category -->
            <div v-for="cat in categories" :key="cat ?? 'other'" class="mb-6">
                <h2 class="mb-3 border-b border-gray-200 pb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">
                    {{ cat || 'غير مصنف' }}
                </h2>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">الصنف</th>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">الوحدة</th>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">الكمية في النظام</th>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">الكمية الفعلية</th>
                                <th class="px-4 py-2 text-right font-semibold text-gray-600">الفرق</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in form.counts.filter((r) => r.category === cat)"
                                :key="row.item_id"
                                class="border-t border-gray-100"
                                :class="{ 'bg-yellow-50': variance(row) !== 0 }"
                            >
                                <td class="px-4 py-2 font-medium">{{ row.item_name }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ row.unit || '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ row.system_qty }}</td>
                                <td class="px-4 py-2">
                                    <input
                                        v-model.number="row.physical_qty"
                                        class="w-24 rounded border border-gray-300 px-2 py-1 text-center text-sm focus:border-blue-500 focus:outline-none"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </td>
                                <td
                                    class="px-4 py-2 font-medium"
                                    :class="{
                                        'text-green-700': variance(row) > 0,
                                        'text-red-700': variance(row) < 0,
                                        'text-gray-400': variance(row) === 0,
                                    }"
                                >
                                    {{ variance(row) > 0 ? '+' : '' }}{{ variance(row) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sticky bottom-4 flex justify-end">
                <button
                    type="submit"
                    class="rounded-lg bg-blue-600 px-8 py-3 font-semibold text-white shadow-lg hover:bg-blue-700 disabled:opacity-50"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'جارٍ التسوية...' : 'تسجيل الجرد وتحديث المخزون' }}
                </button>
            </div>
        </form>
    </div>
</template>
