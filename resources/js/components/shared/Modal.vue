<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onMounted, onUnmounted } from 'vue';

interface Props {
    modelValue: boolean;
    title?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    closeable?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    size: 'md',
    closeable: true,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void;
    (e: 'close'): void;
}>();

function close() {
    if (!props.closeable) {
return;
}

    emit('update:modelValue', false);
    emit('close');
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') {
close();
}
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

const sizeClasses: Record<string, string> = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                @click.self="close"
            >
                <!-- Overlay -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="close" />

                <!-- Panel -->
                <div
                    :class="[
                        'relative z-10 flex w-full flex-col rounded-2xl bg-hospital-surface shadow-2xl max-h-[90vh]',
                        sizeClasses[size],
                    ]"
                    role="dialog"
                    aria-modal="true"
                >
                    <!-- Header (replaceable via #header slot) -->
                    <slot name="header" :close="close">
                        <div class="flex items-center justify-between border-b border-hospital-border px-6 py-4">
                            <h2 class="text-base font-bold text-hospital-text">
                                <slot name="title">{{ title }}</slot>
                            </h2>
                            <button
                                v-if="closeable"
                                type="button"
                                class="rounded-lg p-1.5 text-hospital-text-3 hover:bg-hospital-bg hover:text-hospital-text transition-colors"
                                @click="close"
                            >
                                <X class="h-5 w-5" />
                            </button>
                        </div>
                    </slot>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="flex items-center justify-end gap-3 border-t border-hospital-border px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
.modal-enter-active .relative,
.modal-leave-active .relative {
    transition: transform 0.2s ease;
}
.modal-enter-from .relative,
.modal-leave-to .relative {
    transform: scale(0.95);
}
</style>
