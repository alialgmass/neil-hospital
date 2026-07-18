<template>
    <Head title="فاتورة المريض" />

    <div class="max-w-[800px] mx-auto space-y-6 pb-12">
        <!-- Action Header -->
        <div class="flex items-center justify-between no-print">
            <div>
                <h1 class="text-[18px] font-bold text-hospital-text">فاتورة المبيعات</h1>
                <p class="text-[10px] text-hospital-text-3">إدارة المدفوعات والتحصيل المالي</p>
            </div>
            <div class="flex gap-2">
                <button 
                    class="btn btn-icon flex h-9 w-9 items-center justify-center rounded-lg border border-hospital-border bg-white text-hospital-text-2 transition-all hover:bg-hospital-bg hover:text-hospital-primary"
                    title="طباعة الفاتورة"
                    @click="printPage"
                >
                    <Printer class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
            <!-- Left: Invoice Details (3/5) -->
            <div class="lg:col-span-3">
                <div class="card rounded-[var(--rl)] border border-hospital-border bg-white overflow-hidden [box-shadow:var(--sh)]">
                    <div class="card-hd flex items-center justify-between border-b border-hospital-border bg-hospital-surface-2 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-hospital-primary-pale text-hospital-primary">
                                <Receipt class="h-5 w-5" />
                            </div>
                            <h2 class="text-[14px] font-bold text-hospital-text">تفاصيل الزيارة</h2>
                        </div>
                        <span class="text-[10px] font-bold text-hospital-primary bg-hospital-primary-pale px-2.5 py-1 rounded-full uppercase">مسودة</span>
                    </div>
                    
                    <div class="card-bd p-6 space-y-5">
                        <!-- Patient Info Grid -->
                        <div class="grid grid-cols-2 gap-y-4 gap-x-8">
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-hospital-text-3 uppercase tracking-wider">رقم الملف</p>
                                <p class="text-[13px] font-mono font-bold text-hospital-text">{{ booking.file_no }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-hospital-text-3 uppercase tracking-wider">اسم المريض</p>
                                <p class="text-[13px] font-bold text-hospital-text">{{ booking.patient_name }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-hospital-text-3 uppercase tracking-wider">القسم</p>
                                <p class="text-[13px] font-bold text-hospital-text">{{ booking.dept }}</p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] font-bold text-hospital-text-3 uppercase tracking-wider">الطبيب المعالج</p>
                                <p class="text-[13px] font-bold text-hospital-text">{{ booking.doctor?.name || '—' }}</p>
                            </div>
                        </div>

                        <div class="border-t border-hospital-border/50 pt-5">
                            <h3 class="text-[11px] font-bold text-hospital-text-3 mb-3 uppercase">الخدمات والرسوم</h3>
                            <div class="flex items-center justify-between py-2 border-b border-hospital-border/40">
                                <span class="text-[12px] font-bold text-hospital-text">{{ booking.service_name }}</span>
                                <span class="text-[12px] font-mono font-bold text-hospital-text">{{ booking.price.toFixed(2) }} ج</span>
                            </div>

                            <div class="mt-4 space-y-2 bg-hospital-bg/30 p-4 rounded-xl">
                                <div class="flex justify-between items-center text-[12px]">
                                    <span class="text-hospital-text-3">المجموع الفرعي</span>
                                    <span class="font-bold text-hospital-text">{{ booking.price.toFixed(2) }} ج</span>
                                </div>
                                <div v-if="form.discount > 0" class="flex justify-between items-center text-[12px]">
                                    <span class="text-hospital-text-3">خصم نقدي</span>
                                    <span class="font-bold text-hospital-danger">- {{ form.discount.toFixed(2) }} ج</span>
                                </div>
                                <div v-if="isInsurance && form.ins_amount > 0" class="flex justify-between items-center text-[12px]">
                                    <span class="text-hospital-text-3">تحمل شركة التأمين</span>
                                    <span class="font-bold text-hospital-danger">- {{ form.ins_amount.toFixed(2) }} ج</span>
                                </div>
                                <div class="flex justify-between items-center pt-2 border-t border-hospital-border mt-2">
                                    <span class="text-[13px] font-bold text-hospital-text">الصافي المستحق على المريض</span>
                                    <span class="text-[16px] font-bold text-hospital-primary">{{ netDue.toFixed(2) }} ج</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Payment Form (2/5) -->
            <div class="lg:col-span-2">
                <div class="card rounded-[var(--rl)] border border-hospital-border bg-white overflow-hidden [box-shadow:var(--sh)]">
                    <div class="card-hd border-b border-hospital-border bg-hospital-surface-2 px-5 py-4">
                        <h2 class="text-[14px] font-bold text-hospital-text">تحصيل المبلغ</h2>
                    </div>
                    
                    <form class="card-bd p-5 space-y-5" @submit.prevent="submit">
                        <div class="fg">
                            <label class="text-[11px] font-bold text-hospital-text-3 mb-1.5 block">طريقة التحصيل</label>
                            <select v-model="form.pay_method" class="w-full rounded-[8px] border border-hospital-border bg-white px-3 py-2 text-[12px] font-bold text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-4 focus:ring-hospital-primary/5 transition-all">
                                <option value="cash">نقداً (كاش)</option>
                                <option value="card">مدى / بطاقة</option>
                                <option value="transfer">تحويل بنكي</option>
                                <option value="insurance">مطالبة تأمينية</option>
                            </select>
                        </div>

                        <div class="fg">
                            <label class="text-[11px] font-bold text-hospital-text-3 mb-1.5 block">المبلغ المحصل</label>
                            <div class="relative flex items-center">
                                <input v-model.number="form.amount_paid" class="w-full rounded-[8px] border border-hospital-border bg-white px-3 py-2 text-[13px] font-bold text-hospital-text focus:border-hospital-primary focus:outline-none transition-all pl-10" type="number" min="0" step="0.01" />
                                <span class="absolute left-3 text-[10px] font-bold text-hospital-text-3">ج.م</span>
                            </div>
                        </div>

                        <template v-if="form.pay_method === 'insurance'">
                            <div class="fg animate-fade-in">
                                <label class="text-[11px] font-bold text-hospital-text-3 mb-1.5 block">شركة التأمين</label>
                                <select
                                    v-model="form.ins_company_id"
                                    class="w-full rounded-[8px] border border-hospital-border bg-white px-3 py-2 text-[12px] font-bold text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-4 focus:ring-hospital-primary/5 transition-all"
                                    :class="{ 'border-hospital-danger': form.errors.ins_company_id }"
                                >
                                    <option value="">— اختر الشركة —</option>
                                    <option v-for="ins in insuranceCompanies" :key="ins.id" :value="ins.id">
                                        {{ ins.name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.ins_company_id" class="mt-1 text-[11px] text-hospital-danger">{{ form.errors.ins_company_id }}</p>
                            </div>

                            <div class="fg animate-fade-in">
                                <label class="text-[11px] font-bold text-hospital-text-3 mb-1.5 block">مبلغ التحمل التأميني</label>
                                <input v-model.number="form.ins_amount" class="w-full rounded-[8px] border border-hospital-border bg-white px-3 py-2 text-[13px] font-bold text-hospital-text focus:border-hospital-primary focus:outline-none transition-all" type="number" min="0" step="0.01" readonly />
                            </div>
                        </template>

                        <div class="fg">
                            <label class="text-[11px] font-bold text-hospital-text-3 mb-1.5 block">الخصم المسموح به</label>
                            <input v-model.number="form.discount" class="w-full rounded-[8px] border border-hospital-border bg-white px-3 py-2 text-[13px] font-bold text-hospital-text focus:border-hospital-primary focus:outline-none transition-all" type="number" min="0" step="0.01" />
                        </div>

                        <button 
                            type="submit" 
                            class="w-full rounded-[8px] bg-hospital-primary py-3 text-[13px] font-bold text-white shadow-lg shadow-hospital-primary/20 transition-all hover:bg-hospital-primary-dark hover:scale-[1.02] active:scale-[0.98] disabled:opacity-60" 
                            :disabled="form.processing"
                        >
                            <div class="flex items-center justify-center gap-2">
                                <Save class="h-4 w-4" v-if="!form.processing" />
                                <span>{{ form.processing ? 'جارٍ تسجيل الدفعة...' : 'اعتماد وحفظ الفاتورة' }}</span>
                            </div>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Printer, Receipt, Save } from 'lucide-vue-next'
import { computed, watch } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

interface Doctor {
    id: string
    name: string
}

interface InsuranceCompany {
    id: string
    name: string
    coverage_pct: number
}

interface Booking {
    id: string
    file_no: string
    patient_name: string
    dept: string
    service_name: string
    price: number
    pay_status: string
    pay_method: string
    ins_company_id?: string | null
    ins_amount: number
    discount: number
    doctor?: Doctor
}

const props = defineProps<{ booking: Booking; insuranceCompanies: InsuranceCompany[] }>()

const form = useForm({
    booking_id: props.booking.id,
    amount_paid: props.booking.price - (props.booking.discount || 0),
    pay_method: props.booking.pay_method || 'cash',
    ins_company_id: props.booking.ins_company_id ?? '',
    ins_amount: props.booking.ins_amount || 0,
    discount: props.booking.discount || 0,
})

const isInsurance = computed(() => form.pay_method === 'insurance')

const netDue = computed(() =>
    Math.max(0, props.booking.price - (form.discount || 0) - (isInsurance.value ? form.ins_amount || 0 : 0)),
)

function recalcInsuranceShare() {
    if (!isInsurance.value || !form.ins_company_id) {
        form.ins_amount = 0

        return
    }

    const company = props.insuranceCompanies.find((c) => c.id === form.ins_company_id)
    const base = props.booking.price - (form.discount || 0)
    form.ins_amount = company ? Math.round(((base * company.coverage_pct) / 100) * 100) / 100 : 0
}

watch(
    () => form.pay_method,
    () => {
        if (!isInsurance.value) {
            form.ins_company_id = ''
        }

        recalcInsuranceShare()
        form.amount_paid = netDue.value
    },
)
watch([() => form.ins_company_id, () => form.discount], () => {
    recalcInsuranceShare()
    form.amount_paid = netDue.value
})

function submit() {
    form.post('/sales-invoices')
}

function printPage() {
    window.print()
}
</script>

<style scoped>
@media print {
    .no-print {
        display: none !important;
    }
}
</style>

