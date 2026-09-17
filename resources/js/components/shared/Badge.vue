<script setup lang="ts">
type Variant =
    | 'waiting'
    | 'confirmed'
    | 'in_progress'
    | 'completed'
    | 'completed_electronic'
    | 'cancelled'
    | 'paid'
    | 'partial'
    | 'unpaid'
    | 'active'
    | 'inactive'
    | 'draft'
    | 'info'
    | 'danger'
    | 'warning'
    | 'success'
    | 'scheduled'
    | 'prep';

interface Props {
    variant: Variant;
    label?: string;
    dot?: boolean;
}

withDefaults(defineProps<Props>(), {
    label: '',
    dot: true,
});

const variantConfig: Record<
    Variant,
    { classes: string; defaultLabel: string }
> = {
    waiting: {
        classes: 'bg-hospital-warning text-white',
        defaultLabel: 'انتظار',
    },
    confirmed: {
        classes: 'bg-hospital-primary text-white',
        defaultLabel: 'مؤكد',
    },
    in_progress: {
        classes: 'bg-hospital-accent text-white',
        defaultLabel: 'جارٍ',
    },
    completed: {
        classes: 'bg-hospital-success text-white',
        defaultLabel: 'مكتمل',
    },
    completed_electronic: {
        classes: 'bg-hospital-accent text-white',
        defaultLabel: 'مكتمل - إلكتروني',
    },
    cancelled: {
        classes: 'bg-hospital-danger text-white',
        defaultLabel: 'ملغي',
    },
    paid: {
        classes: 'bg-hospital-success text-white',
        defaultLabel: 'مسدد',
    },
    partial: {
        classes: 'bg-hospital-warning text-white',
        defaultLabel: 'جزئي',
    },
    unpaid: {
        classes: 'bg-hospital-danger text-white',
        defaultLabel: 'غير مسدد',
    },
    active: {
        classes: 'bg-hospital-success text-white',
        defaultLabel: 'نشط',
    },
    inactive: { classes: 'bg-hospital-text-3 text-white', defaultLabel: 'معطل' },
    draft: {
        classes: 'bg-hospital-text-2 text-white',
        defaultLabel: 'مسودة',
    },
    info: {
        classes: 'bg-hospital-primary text-white',
        defaultLabel: 'معلومات',
    },
    danger: {
        classes: 'bg-hospital-danger text-white',
        defaultLabel: 'خطر',
    },
    warning: {
        classes: 'bg-hospital-warning text-white',
        defaultLabel: 'تحذير',
    },
    success: {
        classes: 'bg-hospital-success text-white',
        defaultLabel: 'ناجح',
    },
    scheduled: {
        classes: 'bg-hospital-primary text-white',
        defaultLabel: 'مجدول',
    },
    prep: {
        classes: 'bg-hospital-warning text-white',
        defaultLabel: 'تحضير',
    },
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
