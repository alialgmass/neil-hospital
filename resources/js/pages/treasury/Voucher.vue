<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';

interface Voucher {
    voucher_no: string;
    type: 'in' | 'out';
    title: string;
    party_label: string;
    party: string | null;
    amount: number;
    date: string;
    description: string;
    reference_no: string | null;
    created_by: string | null;
    is_reversed: boolean;
}

defineProps<{
    voucher: Voucher;
}>();

function money(value: number): string {
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const hospitalName = usePage().props.settings.hospital_name;
</script>

<template>
    <Head :title="`${voucher.title} ${voucher.voucher_no}`" />

    <div class="min-h-screen bg-white p-8 font-sans text-sm text-gray-800 print:p-4">
        <div class="mb-6 border-b-2 border-hospital-primary pb-4 text-center">
            <h1 class="text-2xl font-bold text-hospital-primary">{{ hospitalName }}</h1>
        </div>

        <div class="mb-6 text-center">
            <span
                class="rounded-full border-2 px-6 py-1.5 text-base font-bold"
                :class="voucher.type === 'in' ? 'border-hospital-success text-hospital-success' : 'border-hospital-danger text-hospital-danger'"
            >
                {{ voucher.title }}
            </span>
            <p v-if="voucher.is_reversed" class="mt-2 text-xs font-bold text-hospital-danger">هذه الحركة معكوسة (ملغاة)</p>
        </div>

        <div class="mb-6 grid grid-cols-2 gap-x-8 gap-y-3">
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">رقم الإيصال:</span>
                <span class="font-mono font-bold text-hospital-primary">{{ voucher.voucher_no }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">التاريخ:</span>
                <span>{{ voucher.date }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">{{ voucher.party_label }}:</span>
                <span>{{ voucher.party || '—' }}</span>
            </div>
            <div class="flex justify-between border-b border-dashed border-gray-200 pb-2">
                <span class="font-semibold text-hospital-text-2">المرجع:</span>
                <span>{{ voucher.reference_no || '—' }}</span>
            </div>
        </div>

        <div class="mb-6 rounded-lg border-2 border-gray-200 p-4 text-center">
            <p class="text-xs text-hospital-text-2">مبلغ وقدره</p>
            <p class="mt-1 text-3xl font-bold text-hospital-primary">{{ money(voucher.amount) }} ج.م</p>
        </div>

        <div class="mb-6 border-b border-dashed border-gray-200 pb-2">
            <span class="font-semibold text-hospital-text-2">وذلك عن (البيان): </span>
            <span>{{ voucher.description }}</span>
        </div>

        <div class="mt-12 grid grid-cols-3 gap-4 border-t border-dashed border-gray-300 pt-4 text-center text-xs text-hospital-text-3">
            <span>المحاسب: {{ voucher.created_by ?? '—' }}</span>
            <span>{{ voucher.type === 'in' ? 'توقيع المُسلِّم' : 'توقيع المستلم' }}: _____________</span>
            <span>اعتماد المدير المالي: _____________</span>
        </div>

        <div class="mt-8 flex justify-center gap-3 print:hidden">
            <button
                type="button"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light"
                onclick="window.print()"
            >
                طباعة الإيصال
            </button>
            <Link
                href="/treasury"
                class="rounded-lg border border-gray-300 px-6 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
            >
                رجوع للخزنة
            </Link>
        </div>
    </div>
</template>
