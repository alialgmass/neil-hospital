<script setup lang="ts">
interface Props {
    modelValue: {
        analysis_type: string;
        analysis_notes: string;
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Props['modelValue']): void;
}>();

const analysisOptions = [
    'تحاليل ما قبل العملية (روتين)',
    'تحاليل دم كاملة CBC',
    'تحاليل كيمياء الدم',
    'OCT شبكية',
    'تصوير قرنية Topography',
    'A-Scan قياسات',
    'فحص مجال بصري',
    'أنجيوغرافيا',
    'أشعة صدر',
    'رسم قلب ECG',
];

function update(field: keyof Props['modelValue'], value: string) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}
</script>

<template>
    <div class="col-span-2">
        <label class="bk-label">نوع التحاليل / الفحص المطلوب</label>
        <select
            :value="modelValue.analysis_type"
            class="bk-input"
            @change="update('analysis_type', ($event.target as HTMLSelectElement).value)"
        >
            <option value="">— بدون —</option>
            <option v-for="opt in analysisOptions" :key="opt" :value="opt">
                {{ opt }}
            </option>
        </select>
    </div>
    <div class="col-span-2">
        <label class="bk-label">ملاحظات التحاليل</label>
        <input
            :value="modelValue.analysis_notes"
            type="text"
            placeholder="تفاصيل إضافية على التحاليل..."
            class="bk-input"
            @input="update('analysis_notes', ($event.target as HTMLInputElement).value)"
        />
    </div>
</template>

<style scoped>
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