<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

interface InvoiceItem {
    id: number;
    item_name: string;
    qty: string;
    unit_price: string;
    line_total: string;
}

interface Invoice {
    id: string;
    invoice_no: string;
    invoice_date: string;
    customer_name: string;
    customer_phone: string | null;
    file_no: string | null;
    pay_method: string;
    subtotal: string;
    discount: string;
    total: string;
    notes: string | null;
    created_at: string;
    items: InvoiceItem[];
    creator?: { id: number; name: string } | null;
}

defineProps<{
    invoice: Invoice;
}>();

const payMethodLabels: Record<string, string> = {
    cash: 'نقدي',
    card: 'بطاقة',
    transfer: 'تحويل',
};

function money(value: number | string): string {
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const hospitalName = usePage().props.settings.hospital_name;
</script>

<template>
    <Head :title="`فاتورة بيع ${invoice.invoice_no}`" />

    <div class="min-h-screen bg-white p-8 font-sans text-sm text-gray-800 print:p-4">
        <!-- Header -->
        <div class="mb-6 border-b-2 border-hospital-primary pb-4 text-center">
            <h1 class="text-2xl font-bold text-hospital-primary">{{ hospitalName }}</h1>
        </div>

        <div class="mb-6 text-center">
            <span class="rounded-full border-2 border-hospital-primary px-6 py-1.5 text-base font-bold text-hospital-primary">
                فاتورة بيع
            </span>
        </div>

        <!-- Details -->
        <div class="mb-6 grid grid-cols-2 gap-x-8 gap-y-3">
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">رقم الفاتورة:</span>
                <span class="font-mono font-bold text-hospital-primary">{{ invoice.invoice_no }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">التاريخ:</span>
                <span>{{ invoice.invoice_date.slice(0, 10) }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">العميل:</span>
                <span>{{ invoice.customer_name }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">رقم الملف / الهاتف:</span>
                <span>{{ [invoice.file_no, invoice.customer_phone].filter(Boolean).join(' — ') || '—' }}</span>
            </div>
        </div>

        <!-- Items -->
        <table class="mb-6 w-full border-collapse text-sm">
            <thead>
                <tr class="bg-hospital-primary text-white">
                    <th class="p-2 text-right">الصنف</th>
                    <th class="p-2 text-right">الكمية</th>
                    <th class="p-2 text-right">السعر</th>
                    <th class="p-2 text-left">الإجمالي (ج.م)</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="item in invoice.items" :key="item.id" class="border-b border-gray-100">
                    <td class="p-2">{{ item.item_name }}</td>
                    <td class="p-2">{{ Number(item.qty) }}</td>
                    <td class="p-2">{{ money(item.unit_price) }}</td>
                    <td class="p-2 text-left font-medium">{{ money(item.line_total) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr v-if="Number(invoice.discount) > 0" class="border-b border-gray-100">
                    <td class="p-2" colspan="3">الإجمالي قبل الخصم</td>
                    <td class="p-2 text-left">{{ money(invoice.subtotal) }}</td>
                </tr>
                <tr v-if="Number(invoice.discount) > 0" class="border-b border-gray-100 text-hospital-success">
                    <td class="p-2" colspan="3">خصم</td>
                    <td class="p-2 text-left">— {{ money(invoice.discount) }}</td>
                </tr>
                <tr class="bg-hospital-primary-pale font-bold">
                    <td class="p-2" colspan="3">الصافي المدفوع</td>
                    <td class="p-2 text-left text-hospital-primary">{{ money(invoice.total) }}</td>
                </tr>
            </tfoot>
        </table>

        <p class="mb-2 text-center text-xs text-hospital-text-2">
            طريقة الدفع: <strong>{{ payMethodLabels[invoice.pay_method] ?? invoice.pay_method }}</strong>
        </p>
        <p v-if="invoice.notes" class="mb-6 text-center text-xs text-hospital-text-2">{{ invoice.notes }}</p>

        <div class="mt-12 flex justify-between border-t border-dashed border-gray-300 pt-4 text-xs text-hospital-text-3">
            <span>بواسطة: {{ invoice.creator?.name ?? '—' }}</span>
            <span>توقيع المحاسب: _______________</span>
        </div>

        <div class="mt-8 flex justify-center gap-3 print:hidden">
            <button
                type="button"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light"
                onclick="window.print()"
            >
                طباعة الفاتورة
            </button>
            <Link
                href="/item-sales"
                class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
                رجوع لفواتير البيع
            </Link>
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
