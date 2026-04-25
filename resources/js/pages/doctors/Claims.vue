<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calculator, CreditCard, X, FileText } from 'lucide-vue-next';
import { ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface Doctor { id: string; name: string; fee_type: string }
interface ClaimRow {
    booking_id: string;
    file_no: string;
    patient_name: string;
    date: string;
    dept: string;
    service: string;
    paid: number;
    ins_amount: number;
    dr_share: number;
}
interface Claims {
    doctor: Doctor;
    period_from: string;
    period_to: string;
    total_claims: number;
    paid_amount: number;
    net_due: number;
    rows: ClaimRow[];
}

const props = defineProps<{
    doctors: Doctor[];
    claims: Claims | null;
    filters: { doctor_id?: string; from?: string; to?: string };
}>();

const doctorFilter = ref(props.filters.doctor_id ?? '');
const fromFilter   = ref(props.filters.from      ?? '');
const toFilter     = ref(props.filters.to         ?? '');

function calculate() {
    if (!doctorFilter.value || !fromFilter.value || !toFilter.value) { return; }
    router.get('/dr-claims/calculate', {
        doctor_id: doctorFilter.value,
        from:      fromFilter.value,
        to:        toFilter.value,
    }, { preserveState: true });
}

/* ── Invoice row modal ── */
const selectedRow = ref<ClaimRow | null>(null);
function openInvoice(row: ClaimRow) { selectedRow.value = row; }
function closeInvoice() { selectedRow.value = null; }

/* ── Pay Modal ── */
const showPay = ref(false);
const payForm = useForm({
    doctor_id:   props.filters.doctor_id ?? '',
    amount:      0 as number,
    period_from: props.filters.from ?? '',
    period_to:   props.filters.to   ?? '',
    paid_at:     new Date().toISOString().slice(0, 10),
    method:      'cash' as 'cash' | 'transfer',
    notes:       '',
});

function openPay() {
    if (props.claims) {
        payForm.doctor_id   = props.claims.doctor.id;
        payForm.amount      = props.claims.net_due;
        payForm.period_from = props.claims.period_from;
        payForm.period_to   = props.claims.period_to;
    }
    showPay.value = true;
}

function submitPay() {
    payForm.post('/dr-claims/pay', { onSuccess: () => { showPay.value = false; } });
}

const deptLabels: Record<string, string> = {
    clinic: 'عيادة', labs: 'فحوصات', surgery: 'عمليات', lasik: 'ليزك', laser: 'ليزر',
};
function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 }) + ' ج.م';
}
</script>

<template>
    <Head title="مستحقات الأطباء" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-lg font-bold text-hospital-text">مستحقات الأطباء</h2>
    </div>

    <!-- Filter bar -->
    <div class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-hospital-border bg-hospital-bg p-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">الطبيب</label>
            <select v-model="doctorFilter" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                <option value="">— اختر الطبيب —</option>
                <option v-for="d in doctors" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">من</label>
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">إلى</label>
            <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
        </div>
        <button
            class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90"
            @click="calculate"
        >
            <Calculator class="h-4 w-4" />
            احسب المستحقات
        </button>
    </div>

    <!-- Claims result -->
    <div v-if="claims">
        <!-- Summary cards -->
        <div class="mb-5 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
                <p class="text-xs text-hospital-text-2">إجمالي المستحقات</p>
                <p class="mt-1 font-mono text-2xl font-bold text-hospital-primary">{{ fmt(claims.total_claims) }}</p>
            </div>
            <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
                <p class="text-xs text-hospital-text-2">المدفوع</p>
                <p class="mt-1 font-mono text-2xl font-bold text-hospital-success">{{ fmt(claims.paid_amount) }}</p>
            </div>
            <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
                <p class="text-xs text-hospital-text-2">الصافي المستحق</p>
                <p class="mt-1 font-mono text-2xl font-bold" :class="claims.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-success'">
                    {{ fmt(claims.net_due) }}
                </p>
            </div>
        </div>

        <!-- Pay button -->
        <div class="mb-4 flex justify-end">
            <button
                v-if="claims.net_due > 0"
                class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white hover:bg-hospital-success/90"
                @click="openPay"
            >
                <CreditCard class="h-4 w-4" />
                تسجيل دفعة
            </button>
        </div>

        <!-- Details table — click a row to see invoice -->
        <div class="overflow-x-auto rounded-xl border border-hospital-border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="border-b border-hospital-border bg-hospital-bg">
                    <tr>
                        <th class="px-4 py-3 text-right font-semibold">التاريخ</th>
                        <th class="px-4 py-3 text-right font-semibold">رقم الملف</th>
                        <th class="px-4 py-3 text-right font-semibold">المريض</th>
                        <th class="px-4 py-3 text-right font-semibold">القسم</th>
                        <th class="px-4 py-3 text-right font-semibold">الخدمة</th>
                        <th class="px-4 py-3 text-left font-semibold">المدفوع</th>
                        <th class="px-4 py-3 text-left font-semibold">مستحق الطبيب</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in claims.rows"
                        :key="row.booking_id"
                        class="cursor-pointer border-b border-hospital-border/50 hover:bg-blue-50/60"
                        @click="openInvoice(row)"
                    >
                        <td class="px-4 py-3">{{ row.date }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ row.file_no }}</td>
                        <td class="px-4 py-3">{{ row.patient_name }}</td>
                        <td class="px-4 py-3">{{ deptLabels[row.dept] ?? row.dept }}</td>
                        <td class="px-4 py-3 max-w-[200px] truncate">{{ row.service }}</td>
                        <td class="px-4 py-3 text-left font-mono">{{ fmt(row.paid) }}</td>
                        <td class="px-4 py-3 text-left font-mono font-semibold text-hospital-primary">{{ fmt(row.dr_share) }}</td>
                        <td class="px-4 py-3 text-hospital-text-2">
                            <FileText class="h-4 w-4" />
                        </td>
                    </tr>
                </tbody>
            </table>
            <div v-if="claims.rows.length === 0" class="p-8 text-center text-hospital-text-2">
                لا توجد حالات مسددة في هذه الفترة
            </div>
        </div>
    </div>
    <div v-else class="rounded-xl border border-hospital-border bg-white p-12 text-center text-hospital-text-2">
        اختر الطبيب والفترة الزمنية ثم اضغط "احسب المستحقات"
    </div>

    <!-- Pay Modal -->
    <Modal v-model="showPay" title="تسجيل دفعة للطبيب" size="md">
        <form class="space-y-4" @submit.prevent="submitPay">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">المبلغ (ج.م)</label>
                    <input v-model.number="payForm.amount" type="number" min="0.01" step="0.01" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    <p v-if="payForm.errors.amount" class="mt-1 text-xs text-hospital-danger">{{ payForm.errors.amount }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">تاريخ الدفع</label>
                    <input v-model="payForm.paid_at" type="date" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">طريقة الدفع</label>
                    <select v-model="payForm.method" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                        <option value="cash">نقدي</option>
                        <option value="transfer">تحويل بنكي</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                    <textarea v-model="payForm.notes" rows="2" class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showPay = false">إلغاء</button>
                <button type="submit" :disabled="payForm.processing" class="rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white disabled:opacity-60">تسجيل الدفعة</button>
            </div>
        </form>
    </Modal>

    <!-- Invoice Row Modal -->
    <Teleport to="body">
        <Transition name="fade">
            <div v-if="selectedRow && claims" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeInvoice">
                <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" dir="rtl">
                    <!-- Header -->
                    <div class="flex items-center justify-between rounded-t-2xl bg-gradient-to-l from-blue-700 to-blue-900 px-5 py-4 text-white">
                        <div>
                            <p class="text-xs opacity-75">{{ claims.doctor.name }}</p>
                            <p class="text-base font-bold">إيصال مستحق — {{ selectedRow.file_no }}</p>
                            <p class="text-xs opacity-75">{{ selectedRow.date }}</p>
                        </div>
                        <button class="rounded-full p-1 hover:bg-white/20" @click="closeInvoice">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <!-- Booking details -->
                    <div class="space-y-3 p-5">
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-lg bg-hospital-bg p-3">
                                <p class="text-xs text-hospital-text-2">المريض</p>
                                <p class="font-semibold">{{ selectedRow.patient_name }}</p>
                            </div>
                            <div class="rounded-lg bg-hospital-bg p-3">
                                <p class="text-xs text-hospital-text-2">القسم</p>
                                <p class="font-semibold">{{ deptLabels[selectedRow.dept] ?? selectedRow.dept }}</p>
                            </div>
                            <div class="col-span-2 rounded-lg bg-hospital-bg p-3">
                                <p class="text-xs text-hospital-text-2">الخدمة</p>
                                <p class="font-semibold">{{ selectedRow.service }}</p>
                            </div>
                        </div>

                        <!-- Amounts breakdown -->
                        <div class="rounded-lg border border-hospital-border p-4">
                            <div class="flex items-center justify-between border-b border-dashed border-hospital-border pb-2 text-sm">
                                <span class="text-hospital-text-2">المبلغ المدفوع من المريض</span>
                                <span class="font-mono">{{ fmt(selectedRow.paid) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-2 text-base font-bold text-hospital-primary">
                                <span>مستحق الطبيب (هذه الحالة)</span>
                                <span class="font-mono">{{ fmt(selectedRow.dr_share) }}</span>
                            </div>
                        </div>

                        <!-- Period summary -->
                        <div class="rounded-xl bg-blue-50 p-4">
                            <p class="mb-3 text-xs font-bold text-blue-700">ملخص الفترة كاملة</p>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-xs text-hospital-text-2">إجمالي المستحقات</p>
                                    <p class="font-mono text-sm font-bold text-blue-700">{{ fmt(claims.total_claims) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-hospital-text-2">المدفوع للطبيب</p>
                                    <p class="font-mono text-sm font-bold text-hospital-success">{{ fmt(claims.paid_amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-hospital-text-2">المتبقي</p>
                                    <p class="font-mono text-sm font-bold" :class="claims.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-success'">
                                        {{ fmt(claims.net_due) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-hospital-border p-4">
                        <button class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="closeInvoice">إغلاق</button>
                        <button
                            v-if="claims.net_due > 0"
                            class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white"
                            @click="closeInvoice(); openPay();"
                        >
                            <CreditCard class="h-4 w-4" />
                            تسجيل دفعة
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
