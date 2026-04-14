<script setup lang="ts">
import { ref } from 'vue';

interface Props {
    from?: string;
    to?: string;
}

const props = withDefaults(defineProps<Props>(), {
    from: '',
    to: '',
});

const emit = defineEmits<{
    (e: 'apply', from: string, to: string): void;
    (e: 'clear'): void;
}>();

const localFrom = ref(props.from);
const localTo = ref(props.to);

function apply() {
    emit('apply', localFrom.value, localTo.value);
}

function clear() {
    localFrom.value = '';
    localTo.value = '';
    emit('clear');
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-hospital-text-2">من</label>
            <input
                v-model="localFrom"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
            />
        </div>
        <div class="flex flex-col gap-1">
            <label class="text-xs font-medium text-hospital-text-2">إلى</label>
            <input
                v-model="localTo"
                type="date"
                class="rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
            />
        </div>
        <button
            type="button"
            class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary-light transition-colors"
            @click="apply"
        >
            تطبيق
        </button>
        <button
            v-if="localFrom || localTo"
            type="button"
            class="rounded-lg border border-hospital-border px-4 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg transition-colors"
            @click="clear"
        >
            مسح
        </button>
    </div>
</template>
