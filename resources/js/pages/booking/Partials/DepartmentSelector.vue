<script setup lang="ts">
interface Props {
    modelValue: string;
    error?: string;
}

withDefaults(defineProps<Props>(), {
    error: '',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const deptOptions = [
    { value: 'clinic', label: 'العيادة', icon: '🏥', cap: 'فحص عام' },
    { value: 'labs', label: 'الفحوصات', icon: '🔬', cap: 'تحاليل وأشعة' },
    { value: 'laser', label: 'الليزر', icon: '💡', cap: 'ليزر علاجي' },
    { value: 'lasik', label: 'الليزك', icon: '👁️', cap: 'تصحيح النظر' },
    { value: 'surgery', label: 'العمليات', icon: '⚕️', cap: 'جراحة عيون' },
];

function selectDept(value: string) {
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-purple">التوجيه إلى قسم</span>
        <div class="grid grid-cols-3 gap-2">
            <button
                v-for="dept in deptOptions"
                :key="dept.value"
                type="button"
                :class="['dept-btn', modelValue === dept.value ? 'dept-btn-selected' : '']"
                @click="selectDept(dept.value)"
            >
                <div
                    :class="['dept-btn-icon', modelValue === dept.value ? 'dept-btn-icon-selected' : '']"
                >
                    {{ dept.icon }}
                </div>
                <p :class="['dept-btn-name', modelValue === dept.value ? 'text-hospital-primary' : '']">
                    {{ dept.label }}
                </p>
                <p class="dept-btn-cap">{{ dept.cap }}</p>
            </button>
        </div>
        <p v-if="error" class="mt-2 text-xs text-hospital-danger">
            {{ error }}
        </p>
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

.bk-title-purple {
    background: #7b2fa6;
}

.dept-btn {
    border: 2px solid #dde4ef;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.18s;
    background: #fff;
}

.dept-btn:hover {
    border-color: #0a4fa6;
    background: #e8f1fb;
}

.dept-btn-selected {
    border-color: #0a4fa6;
    background: #e8f1fb;
}

.dept-btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 6px;
    background: #f3f6fa;
    font-size: 16px;
    transition: all 0.18s;
}

.dept-btn-icon-selected {
    background: #0a4fa6;
}

.dept-btn-name {
    font-size: 11px;
    font-weight: 700;
    color: #0d1f3c;
}

.dept-btn-cap {
    font-size: 9px;
    color: #8a96ae;
    margin-top: 2px;
}
</style>