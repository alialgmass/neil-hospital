<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { formatDate } from '@/lib/date';

interface DiagnosticResult {
    id: string;
    test_name: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    result_text: string | null;
    recorded_at: string;
    booking: { file_no: string; patient_name: string };
    technician: { name: string } | null;
}

defineProps<{
    result: DiagnosticResult;
}>();

const eyePhrase: Record<string, string> = {
    OD: 'the RT',
    OS: 'the LT',
    OU: 'both',
};

const hospitalName = usePage().props.settings.hospital_name;
</script>

<template>
    <Head title="خطاب نتيجة الفحص" />

    <div class="min-h-screen bg-white p-8 font-sans text-sm text-gray-800 print:p-4" dir="ltr">
        <!-- Letterhead -->
        <div class="mb-6 border-b-2 border-hospital-primary pb-4 text-center">
            <h1 class="text-2xl font-bold text-hospital-primary">{{ hospitalName }}</h1>
            <p class="text-xs text-hospital-text-2">Investigative Unit Report</p>
        </div>

        <!-- Patient / Eye / Date table -->
        <table class="mb-6 w-full border-collapse border border-gray-300 text-sm">
            <tbody>
                <tr>
                    <td class="w-1/3 border border-gray-300 bg-gray-50 p-2 font-semibold">Patient's name</td>
                    <td class="border border-gray-300 p-2">{{ result.booking.patient_name }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-300 bg-gray-50 p-2 font-semibold">EYE</td>
                    <td class="border border-gray-300 p-2">{{ result.eye ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="border border-gray-300 bg-gray-50 p-2 font-semibold">Date</td>
                    <td class="border border-gray-300 p-2">{{ formatDate(result.recorded_at) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Letter body -->
        <p class="mb-10 leading-8">
            Dear Prof. Dr.
            <br />
            Thank you very much for referring your patient. {{ result.test_name }} of
            {{ result.eye ? eyePhrase[result.eye] : '' }} eye{{ result.eye === 'OU' ? 's' : '' }} revealed:
            <br /><br />
            <span class="whitespace-pre-line">{{ result.result_text || '—' }}</span>
        </p>

        <p class="mb-16 text-right">
            Sincerely,
            <br />
            <strong>{{ result.technician?.name ?? '—' }}</strong>
        </p>

        <div class="mt-8 text-center print:hidden">
            <button
                type="button"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light"
                onclick="window.print()"
            >
                طباعة الخطاب
            </button>
        </div>
    </div>
</template>

<style>
@media print {
    body {
        background: white;
    }
}
</style>
