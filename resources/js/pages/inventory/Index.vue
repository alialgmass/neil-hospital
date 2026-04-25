<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, Package, PlusCircle, ShoppingCart, TrendingDown } from 'lucide-vue-next';
import { ref } from 'vue';
import DataTable from '@/components/shared/DataTable.vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';

interface Supplier { id: string; name: string }
interface InventoryItem {
    id: string;
    name: string;
    code?: string;
    category?: string;
    unit?: string;
    quantity: number;
    min_quantity: number;
    unit_cost: number;
    sell_price: number;
    supplier?: Supplier;
    expiry_date?: string;
    location?: string;
}

const props = defineProps<{
    items: { data: InventoryItem[]; current_page: number; last_page: number; total: number };
    categories: { value: string; label: string }[];
    units: { value: string; label: string }[];
    lowStockCount: number;
    totalValue: number;
    openOrdersCount: number;
    filters: { search?: string; category?: string; low_stock?: string };
}>();

const categoryTabs = [
    { label: 'كل الأصناف', value: '' },
    ...props.categories,
];

const columns = [
    { key: 'code',         label: 'الكود' },
    { key: 'name',         label: 'الصنف',       sortable: true },
    { key: 'category',     label: 'الفئة' },
    { key: 'quantity',     label: 'الكمية',      sortable: true },
    { key: 'min_quantity', label: 'الحد الأدنى' },
    { key: 'unit_cost',    label: 'سعر الشراء' },
    { key: 'sell_price',   label: 'سعر البيع' },
    { key: 'expiry_date',  label: 'تاريخ الانتهاء' },
    { key: 'supplier',     label: 'المورد' },
];

const search    = ref(props.filters.search   ?? '');
const catFilter = ref(props.filters.category ?? '');
const lowStock  = ref(!!props.filters.low_stock);

function applyFilters() {
    router.get('/inventory', {
        search:    search.value    || undefined,
        category:  catFilter.value || undefined,
        low_stock: lowStock.value  ? '1' : undefined,
    }, { preserveState: true });
}
function goToPage(page: number) {
    router.get('/inventory', {
        search:    search.value    || undefined,
        category:  catFilter.value || undefined,
        low_stock: lowStock.value  ? '1' : undefined,
        page,
    }, { preserveState: true });
}
function setTab(val: string) {
    catFilter.value = val;
    lowStock.value  = false;
    applyFilters();
}

const showAdd = ref(false);
const form = useForm({
    name:         '',
    code:         '',
    category:     '',
    unit:         '',
    quantity:     0,
    min_quantity: 0,
    unit_cost:    0,
    sell_price:   0,
    supplier_id:  '',
    expiry_date:  '',
    location:     '',
});
function submit() {
    form.post('/inventory', { onSuccess: () => { showAdd.value = false; form.reset(); } });
}

function fmt(n: number) { return Number(n).toLocaleString('ar-EG') + ' ج.م'; }
</script>

<template>
    <Head title="المخزون" />

    <!-- Stats Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-p text-white">
                <Package class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold text-t2 uppercase tracking-wider">إجمالي الأصناف</p>
                <p class="text-xl font-bold text-t">{{ items.total }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-d text-white">
                <TrendingDown class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold text-t2 uppercase tracking-wider">أصناف منخفضة</p>
                <p class="text-xl font-bold text-d">{{ lowStockCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-s text-white">
                <span class="text-xs font-bold">ج</span>
            </div>
            <div>
                <p class="text-[10px] font-bold text-t2 uppercase tracking-wider">قيمة المخزون</p>
                <p class="text-xl font-bold text-s">{{ totalValue.toLocaleString('ar-EG', { maximumFractionDigits: 0 }) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-w text-white">
                <ShoppingCart class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold text-t2 uppercase tracking-wider">طلبات توريد</p>
                <p class="text-xl font-bold text-w">{{ openOrdersCount }}</p>
            </div>
        </div>
    </div>

    <!-- Low stock alert -->
    <div v-if="lowStockCount > 0" class="mb-4 flex items-center gap-2 rounded-lg border border-d/30 bg-d/5 px-4 py-3 text-d">
        <AlertTriangle class="h-5 w-5 flex-shrink-0" />
        <span class="text-sm font-medium">{{ lowStockCount }} صنف وصل للحد الأدنى</span>
        <button class="mr-auto text-xs underline" @click="lowStock = true; applyFilters()">عرض فقط</button>
    </div>

    <!-- Category Tabs -->
    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-br">
        <button
            v-for="tab in categoryTabs"
            :key="tab.value"
            class="whitespace-nowrap px-4 py-2 text-sm font-medium transition-colors"
            :class="catFilter === tab.value && !lowStock
                ? 'border-b-2 border-p text-p'
                : 'text-t2 hover:text-t'"
            @click="setTab(tab.value)"
        >
            {{ tab.label }}
        </button>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <SearchBar v-model="search" placeholder="ابحث بالاسم أو الكود..." @update:model-value="applyFilters" />
            <label class="flex cursor-pointer items-center gap-1.5 text-sm text-t">
                <input v-model="lowStock" type="checkbox" class="rounded border-br" @change="applyFilters" />
                منخفض فقط
            </label>
        </div>
        <button class="flex items-center gap-1.5 rounded-lg bg-p px-4 py-2 text-sm font-medium text-white hover:bg-pl shadow-sm transition-all" @click="showAdd = true">
            <PlusCircle class="h-4 w-4" /> صنف جديد
        </button>
    </div>

    <!-- Table Card -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <div class="flex items-center justify-between border-b border-br bg-sf2 px-4 py-3">
            <div>
                <p class="text-sm font-bold text-t">
                    {{ categoryTabs.find(t => t.value === catFilter)?.label ?? 'كل الأصناف' }}
                </p>
                <p class="text-[10px] text-t2">{{ items.total }} صنف</p>
            </div>
        </div>
        <DataTable
            :columns="columns"
            :rows="items.data"
            :current-page="items.current_page"
            :last-page="items.last_page"
            :total="items.total"
            empty-text="لا توجد أصناف"
            class="[&>div]:border-none [&>div]:shadow-none [&>div]:rounded-none"
            @page="goToPage"
        >
            <template #cell-category="{ row }">{{ (row as any).category_label }}</template>
            <template #cell-quantity="{ value, row }">
                <span :class="(row as InventoryItem).quantity <= (row as InventoryItem).min_quantity && (row as InventoryItem).min_quantity > 0 ? 'text-d font-semibold' : ''">
                    {{ value }} {{ (row as any).unit_label }}
                </span>
            </template>
            <template #cell-unit_cost="{ value }">{{ fmt(Number(value)) }}</template>
            <template #cell-sell_price="{ value }">{{ fmt(Number(value)) }}</template>
            <template #cell-supplier="{ row }">{{ (row as InventoryItem).supplier?.name ?? '—' }}</template>
        </DataTable>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="إضافة صنف جديد" size="lg">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">اسم الصنف <span class="text-d">*</span></label>
                    <input v-model="form.name" type="text" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-d">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الكود</label>
                    <input v-model="form.code" type="text" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الفئة</label>
                    <select v-model="form.category" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none">
                        <option value="">— اختر —</option>
                        <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">وحدة القياس</label>
                    <select v-model="form.unit" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none">
                        <option value="">— اختر —</option>
                        <option v-for="u in units" :key="u.value" :value="u.value">{{ u.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">الكمية الابتدائية</label>
                    <input v-model.number="form.quantity" type="number" min="0" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">حد التنبيه (الأدنى)</label>
                    <input v-model.number="form.min_quantity" type="number" min="0" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">سعر الشراء</label>
                    <input v-model.number="form.unit_cost" type="number" min="0" step="0.01" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">سعر البيع</label>
                    <input v-model.number="form.sell_price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">تاريخ الانتهاء</label>
                    <input v-model="form.expiry_date" type="date" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">مكان التخزين</label>
                    <input v-model="form.location" type="text" class="w-full rounded-lg border border-br bg-sf px-3 py-2 text-sm focus:border-p focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-br px-4 py-2 text-sm hover:bg-bg" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-p px-4 py-2 text-sm font-medium text-white hover:bg-pl disabled:opacity-60 transition-all shadow-sm">إضافة</button>
            </div>
        </form>
    </Modal>
</template>
