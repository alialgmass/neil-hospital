<script setup lang="ts" generic="T extends Record<string, unknown>">
import { computed } from 'vue';
import { ChevronUp, ChevronDown, ChevronsUpDown } from 'lucide-vue-next';

interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
    headerClass?: string;
}

interface Props {
    columns: Column[];
    rows: T[];
    sortKey?: string;
    sortDir?: 'asc' | 'desc';
    loading?: boolean;
    emptyText?: string;
    /** Current page (1-based) */
    currentPage?: number;
    lastPage?: number;
    total?: number;
    perPage?: number;
}

const props = withDefaults(defineProps<Props>(), {
    sortKey: '',
    sortDir: 'asc',
    loading: false,
    emptyText: 'لا توجد بيانات',
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 20,
});

const emit = defineEmits<{
    (e: 'sort', key: string): void;
    (e: 'page', page: number): void;
}>();

function sortIcon(col: Column) {
    if (!col.sortable) return null;
    if (props.sortKey !== col.key) return ChevronsUpDown;
    return props.sortDir === 'asc' ? ChevronUp : ChevronDown;
}

const pages = computed<number[]>(() => {
    const total = props.lastPage;
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const cur = props.currentPage;
    const set = new Set([1, total, cur, cur - 1, cur + 1].filter((p) => p >= 1 && p <= total));
    return [...set].sort((a, b) => a - b);
});
</script>

<template>
    <div class="w-full overflow-x-auto rounded-xl border border-hospital-border bg-hospital-surface shadow-sm">
        <!-- Loading overlay -->
        <div v-if="loading" class="flex items-center justify-center py-16 text-hospital-text-3">
            <span class="text-sm">جاري التحميل…</span>
        </div>

        <table v-else class="w-full text-sm">
            <thead class="border-b border-hospital-border bg-hospital-bg">
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        :class="[
                            'px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2 select-none',
                            col.headerClass,
                            col.sortable ? 'cursor-pointer hover:text-hospital-primary' : '',
                        ]"
                        @click="col.sortable && emit('sort', col.key)"
                    >
                        <span class="flex items-center justify-end gap-1">
                            {{ col.label }}
                            <component :is="sortIcon(col)" v-if="col.sortable" class="h-3.5 w-3.5 opacity-60" />
                        </span>
                    </th>
                    <!-- Actions slot header -->
                    <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">
                        إجراءات
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="rows.length === 0">
                    <td :colspan="columns.length + ($slots.actions ? 1 : 0)" class="py-12 text-center text-hospital-text-3">
                        {{ emptyText }}
                    </td>
                </tr>
                <tr
                    v-for="(row, index) in rows"
                    :key="(row['id'] as string) ?? index"
                    class="border-b border-hospital-border/50 transition-colors hover:bg-hospital-primary-pale/40"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        :class="['px-4 py-3 text-right text-hospital-text', col.class]"
                    >
                        <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                            {{ row[col.key] }}
                        </slot>
                    </td>
                    <td v-if="$slots.actions" class="px-4 py-3 text-right">
                        <slot name="actions" :row="row" />
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div
            v-if="lastPage > 1"
            class="flex items-center justify-between border-t border-hospital-border px-4 py-3 text-sm text-hospital-text-2"
        >
            <span>إجمالي {{ total }} سجل</span>
            <div class="flex items-center gap-1">
                <button
                    :disabled="currentPage <= 1"
                    class="rounded px-2 py-1 hover:bg-hospital-bg disabled:opacity-40"
                    @click="emit('page', currentPage - 1)"
                >
                    السابق
                </button>
                <template v-for="(p, i) in pages" :key="p">
                    <span v-if="i > 0 && p - pages[i - 1] > 1" class="px-1 text-hospital-text-3">…</span>
                    <button
                        :class="[
                            'min-w-[2rem] rounded px-2 py-1 text-center',
                            p === currentPage
                                ? 'bg-hospital-primary text-white font-semibold'
                                : 'hover:bg-hospital-bg',
                        ]"
                        @click="emit('page', p)"
                    >
                        {{ p }}
                    </button>
                </template>
                <button
                    :disabled="currentPage >= lastPage"
                    class="rounded px-2 py-1 hover:bg-hospital-bg disabled:opacity-40"
                    @click="emit('page', currentPage + 1)"
                >
                    التالي
                </button>
            </div>
        </div>
    </div>
</template>
