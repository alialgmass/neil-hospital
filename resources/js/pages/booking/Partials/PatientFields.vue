<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

interface Props {
    modelValue: {
        patient_name: string;
        national_id: string;
        patient_phone: string;
        patient_age: string;
        gender: string;
        kinship_degree: string;
        visit_date: string;
        visit_time: string;
    };
    errors?: Record<string, string>;
}

interface PatientMatch {
    file_no: string;
    patient_name: string;
    national_id: string | null;
    patient_phone: string | null;
    patient_age: number | null;
    gender: string | null;
    kinship_degree: string | null;
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

const patientResults = ref<PatientMatch[]>([]);
const patientDropdownOpen = ref(false);
let debounceTimer: ReturnType<typeof setTimeout> | undefined;

function onPatientNameInput(value: string) {
    update('patient_name', value);

    clearTimeout(debounceTimer);

    if (!value.trim()) {
        patientResults.value = [];
        patientDropdownOpen.value = false;

        return;
    }

    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/booking/patients/search?q=${encodeURIComponent(value)}`, {
                headers: { Accept: 'application/json' },
            });
            patientResults.value = res.ok ? await res.json() : [];
            patientDropdownOpen.value = patientResults.value.length > 0;
        } catch {
            patientResults.value = [];
        }
    }, 300);
}

function selectPatient(patient: PatientMatch) {
    emit('update:modelValue', {
        ...props.modelValue,
        patient_name: patient.patient_name,
        national_id: patient.national_id ?? props.modelValue.national_id,
        patient_phone: patient.patient_phone ?? props.modelValue.patient_phone,
        patient_age: patient.patient_age ? String(patient.patient_age) : props.modelValue.patient_age,
        gender: patient.gender ?? props.modelValue.gender,
    });
    patientDropdownOpen.value = false;
}

function closePatientDropdown() {
    setTimeout(() => {
        patientDropdownOpen.value = false;
    }, 150);
}

onBeforeUnmount(() => clearTimeout(debounceTimer));
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-blue">بيانات المريض</span>
        <div class="bk-grid-2">
            <div class="relative col-span-2">
                <label class="bk-label">اسم المريض *</label>
                <input
                    :value="modelValue.patient_name"
                    type="text"
                    placeholder="الاسم الكامل للمريض"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.patient_name }"
                    autocomplete="off"
                    @input="onPatientNameInput(($event.target as HTMLInputElement).value)"
                    @focus="patientDropdownOpen = patientResults.length > 0"
                    @blur="closePatientDropdown"
                />
                <p v-if="errors.patient_name" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.patient_name }}
                </p>
                <ul
                    v-if="patientDropdownOpen && patientResults.length > 0"
                    class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-hospital-border bg-white shadow-lg"
                >
                    <li
                        v-for="patient in patientResults"
                        :key="patient.file_no"
                        class="cursor-pointer px-3 py-2 text-xs hover:bg-hospital-bg"
                        @mousedown.prevent="selectPatient(patient)"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-hospital-text">{{ patient.patient_name }}</span>
                            <span class="font-mono text-hospital-text-3">{{ patient.file_no }}</span>
                        </div>
                        <div v-if="patient.patient_phone || patient.national_id" class="mt-0.5 text-hospital-text-3">
                            <span v-if="patient.patient_phone">{{ patient.patient_phone }}</span>
                            <span v-if="patient.patient_phone && patient.national_id"> — </span>
                            <span v-if="patient.national_id">{{ patient.national_id }}</span>
                        </div>
                    </li>
                </ul>
            </div>
            <div>
                <label class="bk-label">الرقم القومي</label>
                <input
                    :value="modelValue.national_id"
                    type="text"
                    placeholder="14 رقم"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.national_id }"
                    @input="update('national_id', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="errors.national_id" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.national_id }}
                </p>
            </div>
            <div>
                <label class="bk-label">رقم الهاتف</label>
                <input
                    :value="modelValue.patient_phone"
                    type="tel"
                    placeholder="01xxxxxxxxx"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.patient_phone }"
                    @input="update('patient_phone', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="errors.patient_phone" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.patient_phone }}
                </p>
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
                    :class="{ 'border-hospital-danger': errors.patient_age }"
                    @input="update('patient_age', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="errors.patient_age" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.patient_age }}
                </p>
            </div>
            <div>
                <label class="bk-label">الجنس</label>
                <select
                    :value="modelValue.gender"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.gender }"
                    @change="update('gender', ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— اختر —</option>
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
                <p v-if="errors.gender" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.gender }}
                </p>
            </div>
            <div>
                <label class="bk-label">درجة القرابة</label>
                <select
                    :value="modelValue.kinship_degree"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.kinship_degree }"
                    @change="update('kinship_degree', ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— لا يوجد —</option>
                    <option value="father">الوالد</option>
                    <option value="mother">الوالدة</option>
                    <option value="son">الابن</option>
                    <option value="companion">مرافق</option>
                </select>
                <p v-if="errors.kinship_degree" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.kinship_degree }}
                </p>
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
                <p v-if="errors.visit_date" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.visit_date }}
                </p>
            </div>
            <div>
                <label class="bk-label">الوقت</label>
                <input
                    :value="modelValue.visit_time"
                    type="time"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.visit_time }"
                    @input="update('visit_time', ($event.target as HTMLInputElement).value)"
                />
                <p v-if="errors.visit_time" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.visit_time }}
                </p>
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