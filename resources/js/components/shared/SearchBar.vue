<script setup lang="ts">
import { Search, X } from 'lucide-vue-next';

interface Props {
    modelValue: string;
    placeholder?: string;
    class?: string;
}

withDefaults(defineProps<Props>(), {
    placeholder: 'بحث...',
    class: '',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();
</script>

<template>
    <div class="relative">
        <Search class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-hospital-text-3 pointer-events-none" />
        <input
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            :class="[
                'w-full rounded-lg border border-hospital-border bg-hospital-bg py-2 pr-9 pl-9 text-sm text-hospital-text placeholder-hospital-text-3',
                'focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20',
                $props.class,
            ]"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
        <button
            v-if="modelValue"
            type="button"
            class="absolute left-3 top-1/2 -translate-y-1/2 text-hospital-text-3 hover:text-hospital-text"
            @click="emit('update:modelValue', '')"
        >
            <X class="h-4 w-4" />
        </button>
    </div>
</template>
