<script setup lang="ts">
import { Upload, FileCheck } from 'lucide-vue-next';
import { ref } from 'vue';

const emit = defineEmits<{
    (e: 'file', file: File): void;
}>();

const fileName = ref('');

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) {
        fileName.value = file.name;
        emit('file', file);
    }
}

function clear() {
    fileName.value = '';
}
</script>

<template>
    <label
        class="flex w-full cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-hospital-border px-4 py-4 transition-colors hover:border-hospital-primary hover:bg-hospital-primary-pale/30"
        :class="fileName ? 'border-hospital-success bg-hospital-success-pale/30' : ''"
    >
        <component
            :is="fileName ? FileCheck : Upload"
            class="h-5 w-5 shrink-0"
            :class="fileName ? 'text-hospital-success' : 'text-hospital-text-3'"
        />
        <div class="min-w-0">
            <p v-if="fileName" class="truncate text-sm font-medium text-hospital-text">{{ fileName }}</p>
            <p v-else class="text-sm text-hospital-text-3">اضغط لاختيار ملف...</p>
            <p class="mt-0.5 text-xs text-hospital-text-3">.xlsx, .xls, .csv</p>
        </div>
        <input
            type="file"
            accept=".xlsx,.xls,.csv"
            class="sr-only"
            @change="onFileChange"
        />
    </label>
</template>
