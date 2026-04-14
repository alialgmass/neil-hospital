<script setup lang="ts">
interface Props {
    label: string;
    value: string | number;
    /** Optional trend: positive = green, negative = red */
    change?: string;
    changePositive?: boolean;
    /** Color accent: primary | accent | success | warning | danger */
    color?: 'primary' | 'accent' | 'success' | 'warning' | 'danger';
    icon?: unknown;
}

withDefaults(defineProps<Props>(), {
    change: '',
    changePositive: true,
    color: 'primary',
    icon: null,
});

const colorMap: Record<string, { bar: string; icon: string; bg: string }> = {
    primary: { bar: 'bg-hospital-primary', icon: 'text-hospital-primary', bg: 'bg-hospital-primary-pale' },
    accent:  { bar: 'bg-hospital-accent',  icon: 'text-hospital-accent',  bg: 'bg-hospital-accent-pale' },
    success: { bar: 'bg-hospital-success', icon: 'text-hospital-success', bg: 'bg-hospital-success-pale' },
    warning: { bar: 'bg-hospital-warning', icon: 'text-hospital-warning', bg: 'bg-hospital-warning-pale' },
    danger:  { bar: 'bg-hospital-danger',  icon: 'text-hospital-danger',  bg: 'bg-hospital-danger-pale' },
};
</script>

<template>
    <div class="relative flex items-stretch overflow-hidden rounded-xl border border-hospital-border bg-hospital-surface shadow-sm">
        <!-- Color bar on the right (RTL) -->
        <div :class="['w-1 shrink-0', colorMap[color].bar]" />

        <div class="flex flex-1 items-center gap-4 px-5 py-4">
            <!-- Icon -->
            <div
                v-if="icon"
                :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-lg', colorMap[color].bg]"
            >
                <component :is="icon" :class="['h-5 w-5', colorMap[color].icon]" />
            </div>

            <!-- Text -->
            <div class="min-w-0 flex-1 text-right">
                <p class="truncate text-xs text-hospital-text-3">{{ label }}</p>
                <p class="mt-0.5 text-2xl font-bold tabular-nums text-hospital-text">{{ value }}</p>
                <p
                    v-if="change"
                    :class="['mt-0.5 text-xs font-medium', changePositive ? 'text-hospital-success' : 'text-hospital-danger']"
                >
                    {{ change }}
                </p>
            </div>
        </div>
    </div>
</template>
