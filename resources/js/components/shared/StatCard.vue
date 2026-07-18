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
    <div class="stat relative overflow-hidden rounded-[var(--rl)] border border-hospital-border bg-hospital-surface p-[14px] [box-shadow:var(--sh)]">
        <!-- Color Indicator Bar (Right side in RTL) -->
        <div 
            :class="[
                'absolute top-0 right-0 h-full w-[4px]',
                colorMap[color].bar
            ]" 
        />

        <div class="flex flex-col">
            <!-- Label -->
            <p class="stat-lbl mb-[5px] text-[10px] font-bold text-hospital-text-3">
                {{ label }}
            </p>

            <!-- Value & Icon Row -->
            <div class="flex items-end justify-between">
                <div class="stat-val font-sans text-[24px] font-bold leading-none text-hospital-text">
                    {{ value }}
                </div>
                
                <div v-if="$slots.icon" class="text-hospital-primary opacity-20">
                    <slot name="icon" />
                </div>
            </div>

            <!-- Change Indicator -->
            <p
                v-if="change"
                :class="[
                    'stat-ch mt-1 text-[10px]',
                    changePositive ? 'text-hospital-success' : 'text-hospital-danger'
                ]"
            >
                {{ change }}
            </p>
        </div>
    </div>
</template>
