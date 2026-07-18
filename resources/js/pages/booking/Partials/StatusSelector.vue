<script setup lang="ts">
interface Props {
    modelValue: string;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const statusOptions = [
    { value: 'confirmed', label: 'مؤكد', icon: '✅' },
    { value: 'waiting', label: 'انتظار', icon: '⏳' },
    { value: 'in_progress', label: 'جارٍ', icon: '🔄' },
    { value: 'completed', label: 'مكتمل', icon: '🏁' },
    { value: 'cancelled', label: 'ملغي', icon: '❌' },
];

function selectStatus(value: string) {
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-orange">حالة الحجز</span>
        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="s in statusOptions"
                :key="s.value"
                type="button"
                :class="['status-opt', modelValue === s.value ? 'status-opt-selected' : '']"
                @click="selectStatus(s.value)"
            >
                <span>{{ s.icon }}</span> {{ s.label }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.bk-section {
    background: var(--color-hospital-bg, #f3f6fa);
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

.bk-title {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
    letter-spacing: 0.3px;
}

.bk-title-orange {
    background: #e07c10;
}

.status-opt {
    padding: 7px 6px;
    border-radius: 8px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
    color: #4a5878;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}

.status-opt:hover {
    border-color: #0a4fa6;
    background: #e8f1fb;
    color: #0a4fa6;
}

.status-opt-selected {
    border-color: #0a4fa6;
    background: #0a4fa6;
    color: #fff;
}
</style>