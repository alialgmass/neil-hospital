<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
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

    <div class="flex min-h-screen items-center justify-center bg-white p-6 print:p-2">
        <div class="w-full max-w-xs rounded-xl border-2 border-gray-300 bg-white p-4 text-center shadow-sm print:border-black print:shadow-none">
            <!-- Hospital name -->
            <p class="mb-1 text-[11px] font-bold uppercase tracking-widest text-gray-500 print:text-black">
                مستشفى النور
            </p>

            <!-- Barcode -->
            <div class="my-3 flex justify-center">
                <FileNoBarcode :value="booking.file_no" label="" />
            </div>

            <!-- Patient info -->
            <div class="mt-3 space-y-1 border-t border-dashed border-gray-200 pt-3 text-right print:border-black">
                <p class="text-[12px] font-bold text-gray-800">{{ booking.patient_name }}</p>
                <p class="text-[11px] text-gray-500">
                    {{ deptLabels[booking.dept] ?? booking.dept }}
                    <span v-if="booking.doctor"> · {{ booking.doctor.name }}</span>
                </p>
                <p class="text-[11px] text-gray-400">{{ booking.visit_date }}</p>
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
@media print {
    body { background: white; }
    @page { size: 80mm 60mm; margin: 4mm; }
}
</style>
