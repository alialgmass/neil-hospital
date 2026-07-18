<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

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

const search = ref('')
const selectedCategory = ref('')

function variance(row: CountRow): number {
    return row.physical_qty - row.system_qty
}

const filteredCounts = computed(() =>
    form.counts.filter((r) => {
        const matchCat = !selectedCategory.value || r.category === selectedCategory.value
        const matchSearch = !search.value || r.item_name.toLowerCase().includes(search.value.toLowerCase())
        return matchCat && matchSearch
    }),
)

const visibleCategories = computed(() => {
    if (selectedCategory.value) return [selectedCategory.value]
    return categories.filter((cat) => filteredCounts.value.some((r) => r.category === cat))
})

const totalItems = computed(() => form.counts.length)
const withVariance = computed(() => form.counts.filter((r) => variance(r) !== 0).length)
const surplusCount = computed(() => form.counts.filter((r) => variance(r) > 0).length)
const deficitCount = computed(() => form.counts.filter((r) => variance(r) < 0).length)

function submit() {
    form.post('/stock-take', {
        onSuccess: () => {
            form.counts.forEach((row) => {
                row.system_qty = row.physical_qty
            })
        },
    })
}
</script>

<template>
    <Head title="تسوية الجرد" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تسوية الجرد</h1>
        <p class="mt-0.5 text-sm text-t3">أدخل الكميات الفعلية لكل صنف ثم اضغط «تسجيل الجرد»</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-4 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <span class="text-sm font-bold text-p">{{ totalItems }}</span>
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الأصناف</p>
                <p class="text-xl font-bold text-t">{{ totalItems }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <span class="text-sm font-bold text-w">{{ withVariance }}</span>
            </div>
            <div>
                <p class="text-xs text-t3">أصناف بفرق</p>
                <p class="text-xl font-bold text-t">{{ withVariance }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <span class="text-sm font-bold text-s">+{{ surplusCount }}</span>
            </div>
            <div>
                <p class="text-xs text-t3">زيادة</p>
                <p class="text-xl font-bold text-s">{{ surplusCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <span class="text-sm font-bold text-d">-{{ deficitCount }}</span>
            </div>
            <div>
                <p class="text-xs text-t3">نقص</p>
                <p class="text-xl font-bold text-d">{{ deficitCount }}</p>
            </div>
        </div>
    </div>

    <form @submit.prevent="submit">
        <!-- Toolbar -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <input
                    v-model="search"
                    type="search"
                    placeholder="بحث بالاسم..."
                    class="input-field w-52"
                />
                <select v-model="selectedCategory" class="input-field w-44">
                    <option value="">كل الفئات</option>
                    <option v-for="cat in categories" :key="cat ?? 'other'" :value="cat">{{ cat }}</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input
                    v-model="form.notes"
                    type="text"
                    placeholder="ملاحظات الجرد (اختياري)"
                    class="input-field w-64"
                />
                <button
                    type="submit"
                    class="btn-primary"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'جارٍ التسوية...' : 'تسجيل الجرد وتحديث المخزون' }}
                </button>
            </div>
        </div>

        <!-- Category Groups -->
        <div v-for="cat in visibleCategories" :key="cat ?? 'other'" class="mb-5">
            <h2 class="mb-2 pb-1.5 text-sm font-semibold text-t2 border-b border-br">
                {{ cat || 'غير مصنف' }}
            </h2>
            <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
                <table class="w-full text-sm">
                    <thead class="bg-sf2">
                        <tr>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الصنف</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الوحدة</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">كمية النظام</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الكمية الفعلية</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الفرق</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-br/50">
                        <tr
                            v-for="row in filteredCounts.filter((r) => r.category === cat)"
                            :key="row.item_id"
                            :class="[
                                variance(row) > 0 ? 'bg-sp/10' : variance(row) < 0 ? 'bg-dp/10' : '',
                                'hover:bg-sf2 transition-colors',
                            ]"
                        >
                            <td class="px-4 py-2.5 font-medium text-t">{{ row.item_name }}</td>
                            <td class="px-4 py-2.5 text-t3">{{ row.unit || '—' }}</td>
                            <td class="px-4 py-2.5 font-mono text-t2">{{ row.system_qty }}</td>
                            <td class="px-4 py-2.5">
                                <input
                                    v-model.number="row.physical_qty"
                                    class="input-field w-28 text-center font-mono"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                />
                            </td>
                            <td
                                class="px-4 py-2.5 font-mono font-semibold"
                                :class="{
                                    'text-s': variance(row) > 0,
                                    'text-d': variance(row) < 0,
                                    'text-t3': variance(row) === 0,
                                }"
                            >
                                {{ variance(row) > 0 ? '+' : '' }}{{ variance(row) }}
                            </td>
                        </tr>
                        <tr v-if="filteredCounts.filter((r) => r.category === cat).length === 0">
                            <td class="px-4 py-6 text-center text-t3" colspan="5">لا توجد أصناف</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="visibleCategories.length === 0" class="py-16 text-center text-t3">
            لا توجد أصناف تطابق البحث
        </div>
    </form>
</template>
