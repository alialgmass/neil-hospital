<script setup lang="ts">
interface Props {
    processing: boolean;
    isEditMode: boolean;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'cancel'): void;
    (e: 'submit'): void;
}>();
</script>

<template>
    <div class="mt-2 flex items-center justify-between border-t border-hospital-border pt-4">
        <div class="flex items-center gap-2">
            <div class="h-2 w-2 rounded-full bg-hospital-success" />
            <span class="text-xs text-hospital-text-3">
                عند السداد سيتم إنشاء الفاتورة أوتوماتيكياً
            </span>
        </div>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-lg border border-hospital-border px-4 py-2 text-sm font-medium text-hospital-text-2 transition-colors hover:bg-hospital-bg"
                @click="emit('cancel')"
            >
                إلغاء
            </button>
            <button
                type="submit"
                :disabled="processing"
                class="rounded-lg bg-hospital-primary px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light disabled:opacity-60"
                @click="emit('submit')"
            >
                {{ processing ? 'جارٍ الحفظ…' : '💾 حفظ الحجز' }}
            </button>
        </div>
    </div>
</template>