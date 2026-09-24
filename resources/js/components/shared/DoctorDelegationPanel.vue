<script setup lang="ts">
import { computed, reactive } from 'vue';
import DoctorDelegationRoleFields from './DoctorDelegationRoleFields.vue';

interface Doctor {
    id: string;
    name: string;
}

interface DelegationService {
    id: string;
    name: string;
    default_dr_fee: number | null;
}

interface DelegationLine {
    doctor_id: string;
    role: 'delegate' | 'anesthesia';
    service_id: string | null;
    service_name: string;
    amount: number;
}

const props = defineProps<{
    modelValue: DelegationLine[];
    doctors: Doctor[];
    anesthesiologists: Doctor[];
    services: DelegationService[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: DelegationLine[]];
}>();

const toggles = reactive({
    delegate: props.modelValue.some((l) => l.role === 'delegate'),
    anesthesia: props.modelValue.some((l) => l.role === 'anesthesia'),
});

const delegateLines = computed(() => props.modelValue.filter((l) => l.role === 'delegate'));
const anesthesiaLines = computed(() => props.modelValue.filter((l) => l.role === 'anesthesia'));

function updateRole(role: 'delegate' | 'anesthesia', lines: DelegationLine[]) {
    emit('update:modelValue', [...props.modelValue.filter((l) => l.role !== role), ...lines]);
}
</script>

<template>
    <div class="delegation-panel">
        <div class="delegation-section">
            <label class="delegation-toggle">
                <input v-model="toggles.delegate" type="checkbox" />
                <span>تفويض دكتور آخر</span>
            </label>

            <div v-if="toggles.delegate" class="delegation-body">
                <DoctorDelegationRoleFields
                    :model-value="delegateLines"
                    role="delegate"
                    :doctors="doctors"
                    :anesthesiologists="anesthesiologists"
                    :services="services"
                    @update:model-value="(lines) => updateRole('delegate', lines)"
                />
            </div>
        </div>

        <div class="delegation-section">
            <label class="delegation-toggle">
                <input v-model="toggles.anesthesia" type="checkbox" />
                <span>دكتور التخدير</span>
            </label>

            <div v-if="toggles.anesthesia" class="delegation-body">
                <DoctorDelegationRoleFields
                    :model-value="anesthesiaLines"
                    role="anesthesia"
                    :doctors="doctors"
                    :anesthesiologists="anesthesiologists"
                    :services="services"
                    @update:model-value="(lines) => updateRole('anesthesia', lines)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
.delegation-panel {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.delegation-section {
    background: #f3f6fa;
    border: 1.5px solid #dde4ef;
    border-radius: 10px;
    padding: 10px 14px;
}
.delegation-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #0d1f3c;
    cursor: pointer;
}
.delegation-body {
    margin-top: 10px;
}
</style>
