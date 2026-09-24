<script setup lang="ts">
import { computed, reactive, watch } from 'vue';

interface Doctor {
    id: string;
    name: string;
    delegation_services?: { id: string; pivot: { fee: number | string } }[];
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
    modelValue: DelegationLine[]; // only this role's lines
    role: 'delegate' | 'anesthesia';
    doctors: Doctor[];
    anesthesiologists: Doctor[];
    services: DelegationService[];
}>();

const emit = defineEmits<{
    'update:modelValue': [value: DelegationLine[]];
}>();

interface Row {
    service_id: string;
    amount: number | null;
}

function fromModelValue() {
    return {
        doctor_id: props.modelValue[0]?.doctor_id ?? '',
        rows: props.modelValue.length
            ? props.modelValue.map((l) => ({ service_id: l.service_id ?? '', amount: l.amount }))
            : [{ service_id: '', amount: null } as Row],
    };
}

const section = reactive(fromModelValue());

const doctorOptions = computed(() =>
    props.role === 'anesthesia' && props.anesthesiologists.length ? props.anesthesiologists : props.doctors,
);

// Any active service is a valid candidate for delegation/anesthesia — no
// special tagging required, only the doctor's own per-service fee matters.
const availableServices = computed(() => props.services);

const doctorLabel = computed(() => (props.role === 'anesthesia' ? 'طبيب التخدير' : 'الطبيب المفوَّض'));
const addLabel = computed(() => (props.role === 'anesthesia' ? '+ إضافة خدمة تخدير' : '+ إضافة خدمة تفويض'));

function addRow() {
    section.rows.push({ service_id: '', amount: null });
}

function removeRow(index: number) {
    section.rows.splice(index, 1);

    if (section.rows.length === 0) {
        section.rows.push({ service_id: '', amount: null });
    }
}

/**
 * The doctor's dedicated delegation/anesthesia fee for this service
 * (doctor_delegation_fees pivot — separate from their normal insurance/
 * contract fee), falling back to the service's default_dr_fee only when the
 * doctor has no delegation rate set for it.
 */
function doctorFeeForService(serviceId: string): number | null {
    const doctor = doctorOptions.value.find((d) => d.id === section.doctor_id);
    const pivotFee = doctor?.delegation_services?.find((s) => s.id === serviceId)?.pivot.fee;

    if (pivotFee !== undefined) {
        return Number(pivotFee);
    }

    const service = availableServices.value.find((s) => s.id === serviceId);

    return service?.default_dr_fee ?? null;
}

function onServiceChange(row: Row) {
    if (row.service_id && (row.amount === null || row.amount === 0)) {
        row.amount = doctorFeeForService(row.service_id) ?? 0;
    }
}

// Re-price rows that already have a service picked when the doctor changes
// (e.g. the user picks the service first, then the doctor).
watch(
    () => section.doctor_id,
    () => {
        for (const row of section.rows) {
            if (row.service_id && (row.amount === null || row.amount === 0)) {
                row.amount = doctorFeeForService(row.service_id) ?? 0;
            }
        }
    },
);

function buildLines(): DelegationLine[] {
    if (!section.doctor_id) {
        return [];
    }

    const lines: DelegationLine[] = [];

    for (const row of section.rows) {
        if (!row.service_id || !row.amount) {
            continue;
        }

        const service = availableServices.value.find((s) => s.id === row.service_id);

        lines.push({
            doctor_id: section.doctor_id,
            role: props.role,
            service_id: row.service_id,
            service_name: service?.name ?? '',
            amount: row.amount,
        });
    }

    return lines;
}

watch(section, () => emit('update:modelValue', buildLines()), { deep: true });
</script>

<template>
    <div class="delegation-fields">
        <div>
            <label class="field-label">{{ doctorLabel }}</label>
            <select v-model="section.doctor_id" class="field-input">
                <option value="">— اختر الطبيب —</option>
                <option v-for="doc in doctorOptions" :key="doc.id" :value="doc.id">
                    {{ doc.name }}
                </option>
            </select>
        </div>

        <div v-for="(row, index) in section.rows" :key="index" class="delegation-row">
            <select v-model="row.service_id" class="field-input" @change="onServiceChange(row)">
                <option value="">— اختر الخدمة —</option>
                <option v-for="svc in availableServices" :key="svc.id" :value="svc.id">
                    {{ svc.name }}
                </option>
            </select>
            <input
                v-model.number="row.amount"
                type="number"
                min="0"
                step="0.01"
                placeholder="السعر"
                class="field-input delegation-amount"
            />
            <button type="button" class="delegation-remove" title="حذف السطر" @click="removeRow(index)">×</button>
        </div>

        <button type="button" class="delegation-add" @click="addRow">
            {{ addLabel }}
        </button>
    </div>
</template>

<style scoped>
.delegation-fields {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.delegation-row {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 8px;
    align-items: center;
}
.delegation-amount {
    text-align: center;
}
.delegation-remove {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    color: #e74c3c;
    font-size: 16px;
    line-height: 1;
    cursor: pointer;
}
.delegation-add {
    align-self: flex-start;
    font-size: 12px;
    font-weight: 600;
    color: #7b2fa6;
    background: none;
    border: none;
    cursor: pointer;
    padding: 2px 0;
}
.field-label {
    display: block;
    font-size: 12px;
    font-weight: 500;
    color: #0d1f3c;
    margin-bottom: 4px;
}
.field-input {
    width: 100%;
    padding: 6px 9px;
    border: 1.5px solid #dde4ef;
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: #0d1f3c;
    background: #fff;
    direction: rtl;
}
.field-input:focus {
    outline: none;
    border-color: #7b2fa6;
    box-shadow: 0 0 0 3px rgba(123, 47, 166, 0.1);
}
</style>
