<script setup lang="ts">
interface Props {
    modelValue: {
        patient_name: string;
        national_id: string;
        patient_phone: string;
        patient_age: string;
        gender: string;
        visit_date: string;
        visit_time: string;
    };
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
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
        <span class="bk-title bk-title-blue">بيانات المريض</span>
        <div class="bk-grid-2">
            <div class="col-span-2">
                <label class="bk-label">اسم المريض *</label>
                <input
                    :value="modelValue.patient_name"
                    type="text"
                    placeholder="الاسم الكامل للمريض"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.patient_name }"
                    @input="update('patient_name', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="errors.patient_name" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.patient_name }}
                </p>
            </div>
            <div>
                <label class="bk-label">الرقم القومي</label>
                <input
                    :value="modelValue.national_id"
                    type="text"
                    placeholder="14 رقم"
                    class="bk-input"
                    @input="update('national_id', ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div>
                <label class="bk-label">رقم الهاتف</label>
                <input
                    :value="modelValue.patient_phone"
                    type="tel"
                    placeholder="01xxxxxxxxx"
                    class="bk-input"
                    @input="update('patient_phone', ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div>
                <label class="bk-label">السن</label>
                <input
                    :value="modelValue.patient_age"
                    type="number"
                    min="0"
                    max="150"
                    placeholder="سنة"
                    class="bk-input"
                    @input="update('patient_age', ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div>
                <label class="bk-label">الجنس</label>
                <select
                    :value="modelValue.gender"
                    class="bk-input"
                    @change="update('gender', ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— اختر —</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
            <div>
                <label class="bk-label">التاريخ *</label>
                <input
                    :value="modelValue.visit_date"
                    type="date"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.visit_date }"
                    @input="update('visit_date', ($event.target as HTMLInputElement).value)"
                />
            </div>
            <div>
                <label class="bk-label">الوقت</label>
                <input
                    :value="modelValue.visit_time"
                    type="time"
                    class="bk-input"
                    @input="update('visit_time', ($event.target as HTMLInputElement).value)"
                />
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

.bk-title-blue {
    background: #0a4fa6;
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