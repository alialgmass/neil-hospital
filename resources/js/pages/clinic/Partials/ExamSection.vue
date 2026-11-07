<script setup lang="ts">
defineProps<{
    id: string;
    number: number;
    title: string;
    subtitle?: string;
}>();
</script>

<template>
    <section :id="id" class="scroll-mt-28 rounded-xl border border-hospital-border bg-hospital-surface shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-hospital-border px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-hospital-primary-pale text-xs font-bold text-hospital-primary">
                    {{ number }}
                </span>
                <div>
                    <h3 class="text-sm font-bold text-hospital-text">{{ title }}</h3>
                    <p v-if="subtitle" class="text-[11px] text-hospital-text-3">{{ subtitle }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </header>
        <div class="p-4">
            <slot />
        </div>
    </section>
</template>
