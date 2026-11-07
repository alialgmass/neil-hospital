<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { usePrint } from '@/composables/usePrint';
import { formatDate } from '@/lib/date';
import { GENDER_LABELS } from './Partials/examination';
import type { ExaminationOptions, ExaminationRecord, PatientSummary } from './Partials/examination';
import ExaminationReportBody from './Partials/ExaminationReportBody.vue';

const props = defineProps<{
    patient: PatientSummary;
    examination: ExaminationRecord;
    options: ExaminationOptions;
}>();

const { print } = usePrint();

const settings = usePage().props.settings as { hospital_name: string; hospital_specialty?: string; hospital_logo_url?: string | null };

const exam = props.examination;
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

        <ExaminationReportBody :examination="exam" :options="options" />

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
