<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import FileNoBarcode from '@/components/booking/FileNoBarcode.vue';
import { onMounted } from 'vue';

const props = defineProps<{
    booking: {
        file_no: string;
        patient_name: string;
        patient_age?: number;
        dept: string;
        visit_date: string;
        doctor?: { name: string };
    };
}>();

const hospitalName = usePage().props.settings.hospital_name;

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

onMounted(() => {
    // Short delay to ensure JsBarcode renders before print dialog
    setTimeout(() => window.print(), 400);
});
</script>

<template>
    <Head title="طباعة باركود" />

    <div class="flex min-h-screen items-center justify-center bg-white p-6 print:block print:min-h-0 print:p-0">
        <div class="label-box w-full max-w-[76mm] rounded-lg border border-gray-300 bg-white px-2 py-2 text-center print:max-w-none print:rounded-none print:border-0">
            <!-- Hospital name -->
            <p class="text-[9px] font-bold uppercase leading-tight tracking-widest text-gray-400 print:text-black">
                {{ hospitalName }}
            </p>

            <!-- Patient name -->
            <p class="truncate text-[13px] font-bold leading-tight text-gray-900 print:text-black">
                {{ booking.patient_name }}
            </p>
            <p class="mt-0.5 text-[10px] leading-tight text-gray-500 print:text-black">
                {{ deptLabels[booking.dept] ?? booking.dept }}
                <span v-if="booking.doctor"> · {{ booking.doctor.name }}</span>
                · {{ booking.visit_date }}
            </p>

            <!-- Barcode + its number -->
            <div class="mt-1.5 flex justify-center">
                <FileNoBarcode :value="booking.file_no" label="" flat />
            </div>
        </div>

        <!-- Print button (hidden in print) -->
        <div class="fixed bottom-6 left-1/2 -translate-x-1/2 print:hidden">
            <button
                type="button"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light"
                onclick="window.print()"
            >
                طباعة الباركود
            </button>
        </div>
    </div>
</template>

<style>
/* Fits a small thermal/label printer — width fixed to the label, height left to the
   content so the patient name and barcode number are never cut off. */
@media print {
    body { background: white; margin: 0; }
    .label-box { border: none !important; }
    @page { size: 80mm auto; margin: 2mm; }
}
</style>
