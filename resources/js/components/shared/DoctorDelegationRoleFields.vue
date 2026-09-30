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
    /**
     * Lock delegation/anesthesia to the booking's own service: no service
     * picker, a single line on `bookingService` (which may have no id/name
     * when the booking itself has no service).
     */
    lockService?: boolean;
    bookingService?: { id: string | null; name: string | null } | null;
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

const lockedServiceId = computed(() => props.bookingService?.id ?? '');
const lockedServiceLabel = computed(() => props.bookingService?.name || 'لم تُحدَّد خدمة لهذا الحجز');

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

/**
 * Locked to the booking's service: a single row on that service, priced from
 * the selected doctor's fee (a manually typed amount is kept).
 */
function applyLockedService(repriceAmount: boolean) {
    if (!props.lockService) {
        return;
    }

    const serviceId = lockedServiceId.value;
    const row = section.rows[0] ?? { service_id: '', amount: null };
    const serviceChanged = row.service_id !== serviceId;

    row.service_id = serviceId;

    const shouldReprice =
        serviceId && section.doctor_id && (repriceAmount || serviceChanged || !row.amount);

    if (shouldReprice) {
        const fee = doctorFeeForService(serviceId);

        // A doctor with no configured rate has `fee === null`. Never blank an
        // amount the user already typed (or a saved one being re-opened) just
        // because no rate exists — keep it and let the inline warning ask for
        // a value, otherwise a filled-in form submits amount 0.
        if (fee !== null || !row.amount) {
            row.amount = fee ?? 0;
        }
    }

    section.rows.splice(0, section.rows.length, row);
}

// On mount keep any saved amount; re-price only when the booking (and so its service) changes.
watch(
    () => lockedServiceId.value,
    (_serviceId, previousServiceId) => applyLockedService(previousServiceId !== undefined),
    { immediate: true },
);

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

/**
 * A doctor with no amount is still a doctor the user picked, so the line is
 * kept (amount 0) rather than dropped — the picker would otherwise look
 * filled in while the submitted payload silently carried no delegation at
 * all. The save button refuses such a line (see delegationAmountError).
 */
function buildLines(): DelegationLine[] {
    if (!section.doctor_id) {
        return [];
    }

    const lines: DelegationLine[] = [];

    if (props.lockService) {
        const row = section.rows[0] ?? { service_id: '', amount: null };

        return [
            {
                doctor_id: section.doctor_id,
                role: props.role,
                service_id: lockedServiceId.value || null,
                service_name: props.bookingService?.name || 'خدمة الحجز',
                amount: Number(row.amount ?? 0),
            },
        ];
    }

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

/** Doctor picked but no amount — surfaced inline so it can't be missed on save. */
const amountMissing = computed(
    () =>
        props.lockService &&
        Boolean(section.doctor_id) &&
        Number(section.rows[0]?.amount ?? 0) <= 0,
);
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

        <div v-if="lockService" class="delegation-row delegation-row-locked">
            <input :value="lockedServiceLabel" type="text" class="field-input field-input-readonly" readonly title="خدمة الحجز" />
            <input
                v-model.number="section.rows[0].amount"
                type="number"
                min="0"
                step="0.01"
                placeholder="السعر"
                class="field-input delegation-amount"
            />
        </div>

        <p v-if="amountMissing" class="field-error">
            لا يوجد بدل مسجّل لهذا الطبيب — أدخل المبلغ يدوياً قبل الحفظ.
        </p>

        <div v-for="(row, index) in lockService ? [] : section.rows" :key="index" class="delegation-row">
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

        <button v-if="!lockService" type="button" class="delegation-add" @click="addRow">
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
.delegation-row-locked {
    grid-template-columns: 2fr 1fr;
}
.field-input-readonly {
    background: #f3f6fa;
    color: #4a5878;
    cursor: default;
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
.field-error {
    font-size: 11px;
    color: #e74c3c;
}
</style>
