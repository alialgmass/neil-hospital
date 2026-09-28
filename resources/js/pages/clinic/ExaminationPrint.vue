<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import { usePrint } from '@/composables/usePrint';
import { formatDate } from '@/lib/date';
import {
    ANTERIOR_SEGMENT_ROWS,
    EYES,
    formatPower,
    FUNDUS_ROWS,
    GENDER_LABELS,
    IOP_ROWS,
    optionLabel,
    REFRACTION_ROWS,
    VISUAL_ACUITY_ROWS,
} from './Partials/examination';
import type { Eye, EyeRow, ExaminationEyeRecord, ExaminationOptions, ExaminationRecord, PatientSummary } from './Partials/examination';

const props = defineProps<{
    patient: PatientSummary;
    examination: ExaminationRecord;
    options: ExaminationOptions;
}>();

const { print } = usePrint();

const settings = usePage().props.settings as { hospital_name: string; hospital_specialty?: string; hospital_logo_url?: string | null };

const exam = props.examination;

const eyeRecords = computed<Record<Eye, Partial<ExaminationEyeRecord>>>(() => ({
    OD: exam.eyes.find((record) => record.eye === 'OD') ?? {},
    OS: exam.eyes.find((record) => record.eye === 'OS') ?? {},
}));

function display(row: EyeRow, eye: Eye): string {
    const value = eyeRecords.value[eye][row.key];

    if (value === null || value === undefined || value === '') {
        return '';
    }

    return row.signed ? formatPower(value) : String(Number.isNaN(Number(value)) || row.type !== 'number' ? value : Number(value));
}

/** Only print rows that were actually recorded for at least one eye. */
function filledRows(rows: EyeRow[]): EyeRow[] {
    return rows.filter((row) => EYES.some((eye) => display(row, eye) !== ''));
}

const eyeTables = computed(() => [
    { title: 'Visual Acuity', rows: filledRows(VISUAL_ACUITY_ROWS) },
    { title: 'Refraction', rows: filledRows(REFRACTION_ROWS) },
    { title: 'External / Anterior Segment', rows: filledRows(ANTERIOR_SEGMENT_ROWS) },
    {
        title: `Intraocular Pressure${exam.iop_method ? ` — ${optionLabel(props.options.iop_methods, exam.iop_method)}` : ''}`,
        rows: filledRows(IOP_ROWS),
    },
    { title: 'Fundus Examination', rows: filledRows(FUNDUS_ROWS) },
].filter((table) => table.rows.length > 0));

const historyItems = computed(() => [
    {
        label: 'Previous eye diseases',
        value: [...(exam.eye_disease_history ?? []).map((v) => optionLabel(props.options.eye_diseases, v)), exam.eye_disease_notes].filter(Boolean).join(' · '),
    },
    {
        label: 'Previous eye surgeries',
        value: [...(exam.eye_surgery_history ?? []).map((v) => optionLabel(props.options.eye_surgeries, v)), exam.eye_surgery_notes].filter(Boolean).join(' · '),
    },
    {
        label: 'Eye trauma',
        value: exam.eye_trauma === null ? '' : exam.eye_trauma ? `Yes${exam.eye_trauma_notes ? ` — ${exam.eye_trauma_notes}` : ''}` : 'No',
    },
    { label: 'Glasses', value: optionLabel(props.options.glasses_usage, exam.glasses_usage) },
    { label: 'Contact lenses', value: optionLabel(props.options.contact_lenses, exam.contact_lenses) },
    { label: 'Eye medications', value: exam.previous_eye_medications ?? '' },
    { label: 'Allergies', value: exam.allergies ?? '' },
    {
        label: 'Systemic diseases',
        value: (exam.systemic_diseases ?? []).map((v) => optionLabel(props.options.systemic_diseases, v)).join(' · '),
    },
    { label: 'Other notes', value: exam.history_notes ?? '' },
].filter((item) => item.value));

const medications = computed(() => (exam.medications ?? []).filter((row) => row.name));

const eyeHeader: Record<Eye, string> = { OD: 'OD — Right Eye', OS: 'OS — Left Eye' };
</script>

<template>
    <div class="exam-report min-h-screen bg-white p-8 font-sans text-[13px] text-gray-800 print:p-0" dir="ltr">
        <Head :title="`تقرير الفحص — ${patient.patient_name}`" />

        <div class="mb-4 flex justify-end print:hidden">
            <button type="button" class="btn-primary gap-2" @click="print">
                <Printer class="h-4 w-4" />
                طباعة
            </button>
        </div>

        <div v-if="exam.status === 'draft'" class="mb-3 rounded border border-amber-400 bg-amber-50 px-3 py-1.5 text-center text-xs font-bold uppercase tracking-widest text-amber-700">
            Draft — not finalized
        </div>

        <!-- Letterhead -->
        <header class="mb-4 flex items-center gap-4 border-b-2 border-hospital-primary pb-3">
            <img v-if="settings.hospital_logo_url" :src="settings.hospital_logo_url" alt="" class="h-16 w-16 object-contain" />
            <div class="flex-1">
                <h1 class="text-xl font-bold text-hospital-primary">{{ settings.hospital_name }}</h1>
                <p v-if="settings.hospital_specialty" class="text-xs text-gray-500">{{ settings.hospital_specialty }}</p>
            </div>
            <div class="text-right">
                <p class="text-base font-bold tracking-wide">OPHTHALMIC EXAMINATION</p>
                <p class="text-xs text-gray-500">Date: {{ formatDate(exam.examined_at) }}</p>
            </div>
        </header>

        <!-- Patient information -->
        <table class="report-table mb-4">
            <tbody>
                <tr>
                    <th>Patient</th>
                    <td class="font-semibold" dir="auto">{{ patient.patient_name }}</td>
                    <th>File No.</th>
                    <td class="font-semibold">{{ patient.file_no }}</td>
                </tr>
                <tr>
                    <th>Age / Gender</th>
                    <td>{{ patient.patient_age ?? '—' }}{{ patient.gender ? ` / ${GENDER_LABELS[patient.gender] ?? patient.gender}` : '' }}</td>
                    <th>Phone</th>
                    <td>{{ patient.patient_phone || '—' }}</td>
                </tr>
                <tr>
                    <th>Visit Date</th>
                    <td>{{ patient.visit_date ? formatDate(patient.visit_date) : '—' }}</td>
                    <th>Doctor</th>
                    <td dir="auto">{{ exam.doctor?.name ?? patient.doctor?.name ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Chief complaint -->
        <section v-if="exam.chief_complaint" class="report-section">
            <h2>Chief Complaint</h2>
            <p dir="auto" class="whitespace-pre-line">
                {{ exam.chief_complaint }}
                <span v-if="exam.complaint_duration" class="text-gray-500"> — Duration: {{ exam.complaint_duration }}</span>
                <span v-if="exam.affected_eye" class="text-gray-500"> — Eye: {{ exam.affected_eye }}</span>
            </p>
        </section>

        <!-- History -->
        <section v-if="historyItems.length" class="report-section">
            <h2>Ophthalmic History</h2>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-1">
                <div v-for="item in historyItems" :key="item.label" class="flex gap-2">
                    <dt class="shrink-0 font-semibold text-gray-600">{{ item.label }}:</dt>
                    <dd dir="auto">{{ item.value }}</dd>
                </div>
            </dl>
        </section>

        <!-- OD / OS tables -->
        <section v-for="table in eyeTables" :key="table.title" class="report-section">
            <h2>{{ table.title }}</h2>
            <table class="report-table eye-table">
                <thead>
                    <tr>
                        <th class="w-1/3"></th>
                        <th v-for="eye in EYES" :key="eye" class="text-center">{{ eyeHeader[eye] }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in table.rows" :key="row.key">
                        <th>{{ row.label }}</th>
                        <td v-for="eye in EYES" :key="eye" class="text-center">{{ display(row, eye) || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Diagnosis -->
        <section v-if="exam.diagnoses.length" class="report-section">
            <h2>Diagnosis</h2>
            <ol class="list-decimal space-y-0.5 ps-5">
                <li v-for="row in exam.diagnoses" :key="row.id">
                    <strong>{{ row.diagnosis?.name }}</strong>
                    <span v-if="row.diagnosis?.code" class="text-gray-500"> [{{ row.diagnosis.code }}]</span>
                    <span v-if="row.eye"> — {{ row.eye }}</span>
                    <span v-if="row.notes" class="text-gray-600" dir="auto"> — {{ row.notes }}</span>
                </li>
            </ol>
        </section>

        <!-- Investigations -->
        <section v-if="exam.investigations.length" class="report-section">
            <h2>Investigations Requested</h2>
            <ul class="list-disc space-y-0.5 ps-5">
                <li v-for="row in exam.investigations" :key="row.id">
                    {{ row.name }}<span v-if="row.eye"> — {{ row.eye }}</span>
                    <span v-if="row.notes" class="text-gray-600" dir="auto"> — {{ row.notes }}</span>
                </li>
            </ul>
        </section>

        <!-- Assessment & plan -->
        <section v-if="exam.assessment || exam.treatment_plan || exam.recommendations" class="report-section">
            <h2>Assessment &amp; Treatment Plan</h2>
            <p v-if="exam.assessment" dir="auto" class="whitespace-pre-line"><strong>Assessment:</strong> {{ exam.assessment }}</p>
            <p v-if="exam.treatment_plan" dir="auto" class="mt-1 whitespace-pre-line"><strong>Plan:</strong> {{ exam.treatment_plan }}</p>
            <p v-if="exam.recommendations" dir="auto" class="mt-1 whitespace-pre-line"><strong>Recommendations:</strong> {{ exam.recommendations }}</p>
        </section>

        <section v-if="medications.length" class="report-section">
            <h2>Medications</h2>
            <table class="report-table">
                <thead>
                    <tr><th>Medication</th><th>Dose</th><th>Route</th><th>Frequency</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in medications" :key="index">
                        <td class="font-semibold" dir="auto">{{ row.name }}</td>
                        <td dir="auto">{{ row.dose }}</td>
                        <td dir="auto">{{ row.route }}</td>
                        <td dir="auto">{{ row.frequency }}</td>
                        <td dir="auto">{{ row.remarks }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Follow-up -->
        <section v-if="exam.follow_up || exam.next_visit_date" class="report-section">
            <h2>Follow-up</h2>
            <p>
                <span v-if="exam.follow_up" dir="auto">{{ exam.follow_up }}</span>
                <span v-if="exam.next_visit_date"> — Next visit: <strong>{{ formatDate(exam.next_visit_date) }}</strong></span>
            </p>
        </section>

        <!-- Signature -->
        <footer class="mt-10 flex items-end justify-between text-xs">
            <p class="text-gray-500">
                <template v-if="exam.finalized_at">Finalized {{ formatDate(exam.finalized_at) }}</template>
                <template v-else>Draft</template>
            </p>
            <div class="w-56 text-center">
                <div class="mb-1 h-10 border-b border-gray-400"></div>
                <p class="font-semibold" dir="auto">{{ exam.doctor?.name ?? patient.doctor?.name ?? '' }}</p>
                <p class="text-gray-500">Signature</p>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.report-section {
    margin-bottom: 0.9rem;
    break-inside: avoid;
}

.report-section h2 {
    margin-bottom: 0.35rem;
    border-bottom: 1px solid #d1d5db;
    padding-bottom: 0.15rem;
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-hospital-primary);
}

.report-table {
    width: 100%;
    border-collapse: collapse;
}

.report-table th,
.report-table td {
    border: 1px solid #d1d5db;
    padding: 0.3rem 0.5rem;
    text-align: left;
    vertical-align: top;
}

.report-table th {
    background: #f9fafb;
    font-weight: 600;
    color: #4b5563;
    white-space: nowrap;
}

.eye-table thead th {
    text-align: center;
    color: #111827;
}

@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    .exam-report {
        font-size: 11px;
    }
}
</style>
