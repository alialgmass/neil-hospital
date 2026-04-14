<script setup lang="ts">
import { Download, Printer } from 'lucide-vue-next';

const emit = defineEmits<{
    (e: 'export'): void;
    (e: 'print'): void;
}>();
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg transition-colors"
            @click="emit('export')"
        >
            <Download class="h-4 w-4" />
            تصدير Excel
        </button>
        <button
            type="button"
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg transition-colors"
            @click="emit('print')"
        >
            <Printer class="h-4 w-4" />
            طباعة
        </button>
    </div>
</template>
