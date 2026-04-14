<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: null }); // Print-only page — no sidebar/topbar

interface Booking {
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    patient_age?: number;
    dept: string;
    service_name?: string;
    visit_date: string;
    visit_time?: string;
    price: number;
    discount: number;
    ins_amount: number;
    paid_amount: number;
    pay_method: string;
    pay_status: string;
    doctor?: { name: string };
    created_at: string;
}

const props = defineProps<{
    booking: Booking;
}>();

const deptLabels: Record<string, string> = {
    clinic:  'العيادة',
    labs:    'الفحوصات',
    surgery: 'العمليات',
    lasik:   'الليزك',
    laser:   'الليزر',
};

const payMethodLabels: Record<string, string> = {
    cash:      'كاش',
    card:      'شبكة',
    transfer:  'تحويل بنكي',
    insurance: 'تأمين',
};

const net = Number(props.booking.price) - Number(props.booking.discount);
const remaining = net - Number(props.booking.paid_amount);
</script>

<template>
    <Head title="إيصال الدفع" />

    <div class="min-h-screen bg-white p-8 font-sans text-sm text-gray-800 print:p-4">
        <!-- Header -->
        <div class="mb-6 border-b-2 border-hospital-primary pb-4 text-center">
            <h1 class="text-2xl font-bold text-hospital-primary">مستشفى النور</h1>
            <p class="text-xs text-hospital-text-2">طب وجراحة العيون — المنيا، مصر</p>
        </div>

        <!-- Receipt Title -->
        <div class="mb-6 text-center">
            <span class="rounded-full border-2 border-hospital-primary px-6 py-1.5 text-base font-bold text-hospital-primary uppercase tracking-wide">
                إيصال استلام
            </span>
        </div>

        <!-- Details Grid -->
        <div class="mb-6 grid grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">رقم الملف:</span>
                <span class="font-bold text-hospital-primary">{{ booking.file_no }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">التاريخ:</span>
                <span>{{ booking.visit_date }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">اسم المريض:</span>
                <span>{{ booking.patient_name }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">الطبيب:</span>
                <span>{{ booking.doctor?.name ?? '—' }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">القسم:</span>
                <span>{{ deptLabels[booking.dept] ?? booking.dept }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">الخدمة:</span>
                <span>{{ booking.service_name ?? '—' }}</span>
            </div>
        </div>

        <!-- Financial Summary -->
        <table class="mb-6 w-full border-collapse text-sm">
            <thead>
                <tr class="bg-hospital-primary text-white">
                    <th class="p-2 text-right">البيان</th>
                    <th class="p-2 text-left">المبلغ (ج.م)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-100">
                    <td class="p-2">السعر الأصلي</td>
                    <td class="p-2 text-left font-medium">{{ Number(booking.price).toLocaleString('ar-EG') }}</td>
                </tr>
                <tr v-if="Number(booking.discount) > 0" class="border-b border-gray-100 text-hospital-success">
                    <td class="p-2">خصم</td>
                    <td class="p-2 text-left">— {{ Number(booking.discount).toLocaleString('ar-EG') }}</td>
                </tr>
                <tr v-if="Number(booking.ins_amount) > 0" class="border-b border-gray-100 text-hospital-accent">
                    <td class="p-2">تأمين</td>
                    <td class="p-2 text-left">— {{ Number(booking.ins_amount).toLocaleString('ar-EG') }}</td>
                </tr>
                <tr class="border-b border-gray-100 bg-hospital-primary-pale font-bold">
                    <td class="p-2">المبلغ المدفوع</td>
                    <td class="p-2 text-left text-hospital-primary">{{ Number(booking.paid_amount).toLocaleString('ar-EG') }}</td>
                </tr>
                <tr v-if="remaining > 0" class="text-hospital-danger">
                    <td class="p-2">المتبقي</td>
                    <td class="p-2 text-left">{{ remaining.toLocaleString('ar-EG') }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Pay Method -->
        <p class="mb-6 text-center text-xs text-hospital-text-2">
            طريقة الدفع: <strong>{{ payMethodLabels[booking.pay_method] ?? booking.pay_method }}</strong>
        </p>

        <!-- Signature -->
        <div class="mt-12 flex justify-between border-t border-dashed border-gray-300 pt-4 text-xs text-hospital-text-3">
            <span>توقيع المحاسب: _______________</span>
            <span>{{ booking.created_at }}</span>
        </div>

        <!-- Print Button (hidden in print) -->
        <div class="mt-8 text-center print:hidden">
            <button
                type="button"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light"
                onclick="window.print()"
            >
                طباعة الإيصال
            </button>
        </div>
    </div>
</template>

<style>
@media print {
    body { background: white; }
}
</style>
