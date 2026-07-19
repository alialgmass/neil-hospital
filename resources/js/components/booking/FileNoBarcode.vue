<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: string;
        label?: string;
    }>(),
    {
        label: 'رقم الملف الطبي',
    },
);

const bars = computed(() => {
    const bits = Array.from(`*${props.value}*`).flatMap((char) => {
        const code = char.charCodeAt(0);

        return Array.from(
            { length: 7 },
            (_, index) => ((code >> index) & 1) === 1,
        );
    });

    return bits.map((wide, index) => ({ index, wide }));
});
</script>

<template>
    <div
        class="inline-flex flex-col items-center gap-1 rounded-lg border border-hospital-border bg-white px-4 py-3 print:border-black"
    >
        <p
            class="text-[10px] font-bold tracking-[0.2em] text-hospital-muted uppercase print:text-black"
        >
            {{ label }}
        </p>
        <div
            class="flex h-14 items-stretch gap-px"
            dir="ltr"
            :aria-label="`Barcode ${value}`"
        >
            <span
                v-for="bar in bars"
                :key="bar.index"
                class="block bg-hospital-text print:bg-black"
                :style="{ width: bar.wide ? '3px' : '1px' }"
            />
        </div>
        <p
            class="font-mono text-sm font-bold tracking-[0.18em] text-hospital-text print:text-black"
        >
            {{ value }}
        </p>
    </div>
</template>
