<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Doctor {
    id: string
    name: string
}

interface Booking {
    id: string
    file_no: string
    patient_name: string
    dept: string
    service: string
    price: number
    pay_status: string
    pay_method: string
    ins_amount: number
    discount: number
    doctor?: Doctor
}

const props = defineProps<{ booking: Booking }>()

const form = useForm({
    booking_id: props.booking.id,
    amount_paid: props.booking.price - props.booking.discount,
    pay_method: props.booking.pay_method || 'cash',
    ins_amount: props.booking.ins_amount || 0,
    discount: props.booking.discount || 0,
})

const netDue = props.booking.price - (props.booking.discount ?? 0)

function submit() {
    form.post('/sales-invoices')
}

function printPage() {
    window.print()
}
</script>

<template>
    <div class="p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">فاتورة المريض</h1>
            <button class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50" @click="printPage">
                طباعة الفاتورة
            </button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Invoice Details -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">تفاصيل الزيارة</h2>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">رقم الملف</dt>
                        <dd class="font-mono font-medium">{{ booking.file_no }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">اسم المريض</dt>
                        <dd class="font-medium">{{ booking.patient_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">الخدمة</dt>
                        <dd>{{ booking.service }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">الطبيب</dt>
                        <dd>{{ booking.doctor?.name || '—' }}</dd>
                    </div>
                    <div class="border-t border-gray-100 pt-3">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">السعر</dt>
                            <dd>{{ booking.price.toFixed(2) }} ج</dd>
                        </div>
                        <div v-if="form.discount > 0" class="flex justify-between text-red-600">
                            <dt>خصم</dt>
                            <dd>- {{ form.discount.toFixed(2) }} ج</dd>
                        </div>
                        <div class="flex justify-between font-bold text-lg mt-2">
                            <dt>الإجمالي المستحق</dt>
                            <dd>{{ netDue.toFixed(2) }} ج</dd>
                        </div>
                    </div>
                </dl>
            </div>

            <!-- Payment Form -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold text-gray-800">تسجيل الدفع</h2>
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium">طريقة الدفع</label>
                        <select v-model="form.pay_method" class="input-field">
                            <option value="cash">نقداً</option>
                            <option value="card">شبكة</option>
                            <option value="transfer">تحويل</option>
                            <option value="insurance">تأمين</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">المبلغ المدفوع</label>
                        <input v-model.number="form.amount_paid" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="form.pay_method === 'insurance'">
                        <label class="mb-1 block text-sm font-medium">مبلغ التأمين</label>
                        <input v-model.number="form.ins_amount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">خصم</label>
                        <input v-model.number="form.discount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'تسجيل الدفع وإصدار الفاتورة' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
