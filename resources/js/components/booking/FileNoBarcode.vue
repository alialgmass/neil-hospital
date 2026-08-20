<script setup lang="ts">
import JsBarcode from 'jsbarcode';
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        value: string;
        label?: string;
    }>(),
    {
        label: 'رقم الملف الطبي',
    },
);

const svgEl = ref<SVGElement | null>(null);

function draw() {
    if (!svgEl.value || !props.value) {
        return;
    }
    JsBarcode(svgEl.value, props.value, {
        format: 'CODE39',
        width: 2,
        height: 56,
        displayValue: true,
        font: 'monospace',
        fontSize: 13,
        margin: 6,
        background: '#ffffff',
        lineColor: '#000000',
    });
}

onMounted(draw);
watch(() => props.value, draw);
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
        <svg ref="svgEl" />
    </div>
</template>
