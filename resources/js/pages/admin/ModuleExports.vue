<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, FileSpreadsheet } from 'lucide-vue-next';
import AppLayout from '@/components/layout/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface SystemModule {
    value: string;
    label: string;
    enabled: boolean;
}

const props = defineProps<{
    modules: SystemModule[];
}>();

function download(module: SystemModule) {
    window.location.href = `/module-exports/${module.value}/download`;
}
</script>

<template>
    <Head title="تصدير بيانات الوحدات" />

    <div class="mb-5">
        <h2 class="text-lg font-bold text-hospital-text">تصدير بيانات الوحدات</h2>
        <p class="text-sm text-hospital-muted">تصدير سجل واحد لكل وحدة (Excel) — يتم التصدير وفق البيانات المتاحة في النظام.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="module in props.modules"
            :key="module.value"
            class="flex items-center gap-3 rounded-xl border border-hospital-border bg-white p-4 shadow-sm"
        >
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-hospital-primary/10 text-hospital-primary">
                <FileSpreadsheet class="h-5 w-5" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-hospital-text">{{ module.label }}</p>
                <p class="text-xs text-hospital-muted">{{ module.enabled ? 'مفعلة' : 'معطلة' }}</p>
            </div>

            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-3 py-2 text-xs font-medium text-white shadow-sm transition-all hover:bg-hospital-primary-dark"
                @click="download(module)"
            >
                <Download class="h-3.5 w-3.5" /> تصدير
            </button>
        </div>
    </div>
</template>