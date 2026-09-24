<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

interface InventoryItemMatch {
    id: string;
    name: string;
    code: string | null;
    unit_cost: number;
    sell_price: number;
    expiry_date: string | null;
}

interface Props {
    modelValue: string; // item_name text
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'select', item: InventoryItemMatch): void;
}>();

const query = ref(props.modelValue);
const results = ref<InventoryItemMatch[]>([]);
const loading = ref(false);
const open = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => props.modelValue,
    (v) => {
        query.value = v;
    },
);

function onInput(value: string) {
    query.value = value;
    emit('update:modelValue', value);

    clearTimeout(debounceTimer);

    if (!value.trim()) {
        results.value = [];
        open.value = false;
        return;
    }

    debounceTimer = setTimeout(async () => {
        loading.value = true;
        try {
            const res = await fetch(`/purchases/items/search?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            });
            results.value = res.ok ? await res.json() : [];
            open.value = results.value.length > 0;
        } catch {
            results.value = [];
        } finally {
            loading.value = false;
        }
    }, 300);
}

function select(item: InventoryItemMatch) {
    emit('update:modelValue', item.name);
    emit('select', item);
    open.value = false;
}

function closeOnBlur() {
    setTimeout(() => {
        open.value = false;
    }, 150);
}

onBeforeUnmount(() => clearTimeout(debounceTimer));
</script>

<template>
    <div class="relative">
        <input
            :value="query"
            type="text"
            placeholder="اسم الصنف أو الكود"
            class="input-field w-full"
            @input="onInput(($event.target as HTMLInputElement).value)"
            @focus="open = results.length > 0"
            @blur="closeOnBlur"
        />
        <div v-if="loading" class="absolute left-2 top-1/2 -translate-y-1/2 text-xs text-t3">…</div>
        <ul
            v-if="open && results.length > 0"
            class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-br bg-sf shadow-lg"
        >
            <li
                v-for="item in results"
                :key="item.id"
                class="cursor-pointer px-3 py-2 text-xs hover:bg-sf2"
                @mousedown.prevent="select(item)"
            >
                <div class="flex items-center justify-between">
                    <span class="font-medium text-t">{{ item.name }}</span>
                    <span v-if="item.code" class="font-mono text-t3">{{ item.code }}</span>
                </div>
                <div class="mt-0.5 flex items-center gap-3 text-t3">
                    <span>شراء: {{ item.unit_cost }} ج</span>
                    <span>بيع: {{ item.sell_price }} ج</span>
                    <span v-if="item.expiry_date">صلاحية: {{ item.expiry_date }}</span>
                </div>
            </li>
        </ul>
    </div>
</template>
