<script setup lang="ts">
type Variant = 'waiting' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled' | 'paid' | 'partial' | 'unpaid' | 'active' | 'inactive' | 'info' | 'danger' | 'warning' | 'success';

interface Props {
    variant: Variant;
    label?: string;
    dot?: boolean;
}

withDefaults(defineProps<Props>(), {
    label: '',
    dot: true,
});

const variantConfig: Record<Variant, { classes: string; defaultLabel: string }> = {
    waiting:     { classes: 'bg-hospital-warning-pale text-hospital-warning',         defaultLabel: 'انتظار' },
    confirmed:   { classes: 'bg-hospital-primary-pale text-hospital-primary',          defaultLabel: 'مؤكد' },
    in_progress: { classes: 'bg-hospital-accent-pale text-hospital-accent',            defaultLabel: 'جارٍ' },
    completed:   { classes: 'bg-hospital-success-pale text-hospital-success',          defaultLabel: 'مكتمل' },
    cancelled:   { classes: 'bg-hospital-danger-pale text-hospital-danger',            defaultLabel: 'ملغي' },
    paid:        { classes: 'bg-hospital-success-pale text-hospital-success',          defaultLabel: 'مسدد' },
    partial:     { classes: 'bg-hospital-warning-pale text-hospital-warning',          defaultLabel: 'جزئي' },
    unpaid:      { classes: 'bg-hospital-danger-pale text-hospital-danger',            defaultLabel: 'غير مسدد' },
    active:      { classes: 'bg-hospital-success-pale text-hospital-success',          defaultLabel: 'نشط' },
    inactive:    { classes: 'bg-gray-100 text-gray-500',                               defaultLabel: 'معطل' },
    info:        { classes: 'bg-hospital-primary-pale text-hospital-primary',          defaultLabel: 'معلومات' },
    danger:      { classes: 'bg-hospital-danger-pale text-hospital-danger',            defaultLabel: 'خطر' },
    warning:     { classes: 'bg-hospital-warning-pale text-hospital-warning',          defaultLabel: 'تحذير' },
    success:     { classes: 'bg-hospital-success-pale text-hospital-success',          defaultLabel: 'ناجح' },
};
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
            variantConfig[variant].classes,
        ]"
    >
        <span v-if="dot" class="h-1.5 w-1.5 rounded-full bg-current" />
        {{ label || variantConfig[variant].defaultLabel }}
    </span>
</template>
