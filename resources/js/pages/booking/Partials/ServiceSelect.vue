<script setup lang="ts">
interface Service {
    id: string;
    name: string;
}

interface Doctor {
    id: string;
    name: string;
}

interface Props {
    modelValue: {
        service_id: string;
        doctor_id: string;
    };
    services: Service[];
    doctors: Doctor[];
    isEditMode?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isEditMode: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Props['modelValue']): void;
}>();

function update(field: keyof Props['modelValue'], value: string) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-teal">{{ isEditMode ? 'الخدمة والدفع' : 'الخدمة' }}</span>
        <div class="bk-grid-2">
            <div>
                <label class="bk-label">الخدمة</label>
                <select
                    :value="modelValue.service_id"
                    class="bk-input"
                    @change="update('service_id', ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— اختر الخدمة —</option>
                    <option v-for="svc in services" :key="svc.id" :value="svc.id">
                        {{ svc.name }}
                    </option>
                </select>
            </div>
            <div>
                <label class="bk-label">الطبيب</label>
                <select
                    :value="modelValue.doctor_id"
                    class="bk-input"
                    @change="update('doctor_id', ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— اختر الطبيب —</option>
                    <option v-for="dr in doctors" :key="dr.id" :value="dr.id">
                        {{ dr.name }}
                    </option>
                </select>
            </div>
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

.bk-title-teal {
    background: #00b5a4;
}

.bk-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.bk-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

.bk-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid #dde4ef;
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: #0d1f3c;
    background: #fff;
    direction: rtl;
    transition: border-color 0.15s;
}

.bk-input:focus {
    outline: none;
    border-color: #0a4fa6;
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.1);
}
</style>