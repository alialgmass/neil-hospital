<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, EyeOff, Package, Search, X, XCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useReportRowFilter } from '@/composables/useReportRowFilter';

interface Row {
    id: string;
    name: string;
    code: string | null;
    category: string | null;
    quantity: number;
    unit: string | null;
    unit_cost: number;
    expiry_date: string;
    status: 'expired' | 'expiring_soon' | 'ok';
    days_left: number;
}

const props = defineProps<{
    data: {
        rows: Row[];
        expired_count: number;
        soon_count: number;
        total_with_expiry: number;
    };
    filters: { days: number; category?: string; status_filter?: string };
    categories: string[];
}>();

const days = ref(props.filters.days);
const category = ref(props.filters.category ?? '');
const statusFilter = ref(props.filters.status_filter ?? '');

const { search: rowSearch, visibleRows, excludedCount, exclude, restoreAll } = useReportRowFilter(
    () => props.data.rows,
    ['name', 'code'],
    (r) => r.id,
);

const visibleExpiredCount = computed(() => visibleRows.value.filter((r) => r.status === 'expired').length);
const visibleSoonCount = computed(() => visibleRows.value.filter((r) => r.status === 'expiring_soon').length);

function search() {
    router.get(
        '/reports/expiry',
        { days: days.value, category: category.value || undefined, status: statusFilter.value || undefined },
        { preserveState: true },
    );
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

const statusInfo: Record<string, { label: string; class: string }> = {
    expired: { label: 'منتهي الصلاحية', class: 'bg-dp text-d' },
    expiring_soon: { label: 'قارب الانتهاء', class: 'bg-wp text-w' },
    ok: { label: 'صالح', class: 'bg-sp text-s' },
};
</script>

<template>
    <Head title="تقرير انتهاء الصلاحية" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير انتهاء الصلاحية</h1>
        <p class="mt-0.5 text-sm text-t3">أصناف منتهية أو قاربت على الانتهاء</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <XCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">منتهية الصلاحية</p>
                <p class="text-xl font-bold text-d">{{ visibleExpiredCount }}</p>
                <p class="text-xs text-t3">صنف</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <AlertTriangle class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">تنتهي خلال {{ days }} يوم</p>
                <p class="text-xl font-bold text-w">{{ visibleSoonCount }}</p>
                <p class="text-xs text-t3">صنف</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Package class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">أصناف بتاريخ انتهاء</p>
                <p class="text-xl font-bold text-t">{{ visibleRows.length }}</p>
                <p class="text-xs text-t3">صنف</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">عرض خلال (يوم)</label>
            <input v-model="days" class="input-field w-32" type="number" min="7" max="365" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">الفئة</label>
            <select v-model="category" class="input-field">
                <option value="">كل الفئات</option>
                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">الحالة</label>
            <select v-model="statusFilter" class="input-field">
                <option value="">الكل</option>
                <option value="expired">منتهية الصلاحية</option>
                <option value="expiring_soon">قاربت على الانتهاء</option>
                <option value="ok">صالحة</option>
            </select>
        </div>
        <button class="btn-primary self-end" @click="search">بحث</button>
    </div>

    <!-- Row search + exclusion status -->
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <div class="relative">
            <Search class="absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-t3" />
            <input v-model="rowSearch" type="text" placeholder="ابحث بالصنف أو الكود..." class="input-field h-9 w-64 pr-9" />
        </div>
        <button v-if="excludedCount > 0" type="button" class="flex items-center gap-1.5 rounded-lg border border-br px-3 py-1.5 text-xs text-t2 hover:bg-sf2" @click="restoreAll">
            <EyeOff class="h-3.5 w-3.5" />
            {{ excludedCount }} صف مستبعد من العرض — إظهار الكل
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصنف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكود</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الفئة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الكمية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">سعر الوحدة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">تاريخ الانتهاء</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الأيام المتبقية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="w-8 px-2 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr
                    v-for="row in visibleRows"
                    :key="row.id"
                    class="hover:bg-sf2"
                    :class="{ 'bg-dp/20': row.status === 'expired' }"
                >
                    <td class="px-4 py-3 font-medium text-t">{{ row.name }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-t3">{{ row.code || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.category || '—' }}</td>
                    <td class="px-4 py-3 text-t2">{{ row.quantity }} {{ row.unit || '' }}</td>
                    <td class="px-4 py-3 font-mono text-t">{{ fmt(row.unit_cost) }} ج</td>
                    <td class="px-4 py-3" :class="row.status === 'expired' ? 'font-medium text-d' : 'text-t2'">
                        {{ row.expiry_date }}
                    </td>
                    <td class="px-4 py-3">
                        <span v-if="row.days_left < 0" class="font-bold text-d">{{ Math.abs(row.days_left) }} يوم مضى</span>
                        <span v-else-if="row.days_left === 0" class="font-bold text-d">اليوم</span>
                        <span v-else :class="row.days_left <= 30 ? 'font-medium text-w' : 'text-t2'">{{ row.days_left }} يوم</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusInfo[row.status]?.class">
                            {{ statusInfo[row.status]?.label ?? row.status }}
                        </span>
                    </td>
                    <td class="px-2 py-3">
                        <button type="button" title="استبعاد من التقرير" class="rounded p-1 text-t3 hover:bg-hospital-danger-pale hover:text-hospital-danger" @click="exclude(row)">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </td>
                </tr>
                <tr v-if="visibleRows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="9">
                        {{ data.rows.length === 0 ? 'لا توجد أصناف بتاريخ انتهاء' : 'لا توجد نتائج مطابقة' }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
