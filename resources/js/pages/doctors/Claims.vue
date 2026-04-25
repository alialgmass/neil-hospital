<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calculator, CreditCard, X, FileText, ChevronLeft, UserCircle, Printer } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface DoctorSummary {
    doctor: { id: string; name: string; fee_type: string };
    total_claims: number;
    paid_amount: number;
    net_due: number;
}

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

interface PaymentRecord {
    id: string;
    amount: number;
    paid_at: string;
    method: string;
    notes?: string;
}

interface Claims {
    doctor: { id: string; name: string; fee_type: string };
    period_from: string;
    period_to: string;
    total_claims: number;
    paid_amount: number;
    net_due: number;
    rows: ClaimRow[];
    payments: PaymentRecord[];
}

const props = defineProps<{
    summaries: DoctorSummary[];
    claims: Claims | null;
    filters: { doctor_id?: string; from?: string; to?: string };
}>();

const mounted = ref(false);
onMounted(() => { mounted.value = true; });

const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

function applyFilter() {
    router.get('/dr-claims', {
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true });
}

function loadDoctor(doctorId: string) {
    router.get('/dr-claims/calculate', {
        doctor_id: doctorId,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true });
}

function closePanel() {
    router.get('/dr-claims', {
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true });
}

/* ── Row invoice modal ── */
const selectedRow = ref<ClaimRow | null>(null);

/* ── Pay modal ── */
const showPay = ref(false);
const payForm = useForm({
    doctor_id: '',
    amount: 0 as number,
    period_from: '',
    period_to: '',
    paid_at: new Date().toISOString().slice(0, 10),
    method: 'cash' as 'cash' | 'transfer',
    notes: '',
});

function openPay() {
    if (props.claims) {
        payForm.doctor_id = props.claims.doctor.id;
        payForm.amount = props.claims.net_due;
        payForm.period_from = props.claims.period_from;
        payForm.period_to = props.claims.period_to;
    }

    showPay.value = true;
}

function submitPay() {
    payForm.post('/dr-claims/pay', {
        onSuccess: () => {
            showPay.value = false;
            if (props.claims) {
                loadDoctor(props.claims.doctor.id);
            }
        },
    });
}

function printInvoice() {
    window.print();
}

/* ── Totals across all doctors ── */
const grandTotal = computed(() => props.summaries.reduce((s, d) => s + d.total_claims, 0));
const grandPaid = computed(() => props.summaries.reduce((s, d) => s + d.paid_amount, 0));
const grandDue = computed(() => props.summaries.reduce((s, d) => s + d.net_due, 0));

const deptLabels: Record<string, string> = {
    clinic: 'عيادة', labs: 'فحوصات', surgery: 'عمليات', lasik: 'ليزك', laser: 'ليزر',
};

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 }) + ' ج.م';
}
</script>

<template>
    <Head title="مستحقات الأطباء" />

    <!-- Page header + date filter -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">مستحقات الأطباء</h2>
            <p class="text-xs text-hospital-muted">اضغط على طبيب لعرض تفاصيل مستحقاته</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1">
                <label class="text-xs text-hospital-text-2">من</label>
                <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-1.5 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <div class="flex items-center gap-1">
                <label class="text-xs text-hospital-text-2">إلى</label>
                <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-1.5 text-sm focus:border-hospital-primary focus:outline-none" />
            </div>
            <button
                class="flex items-center gap-1.5 rounded-lg bg-hospital-primary px-4 py-1.5 text-sm font-medium text-white hover:bg-hospital-primary/90"
                @click="applyFilter"
            >
                <Calculator class="h-4 w-4" /> عرض
            </button>
        </div>
    </div>

    <!-- Grand totals row -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
            <p class="text-xs text-hospital-text-2">إجمالي المستحقات</p>
            <p class="mt-1 font-mono text-xl font-bold text-hospital-primary">{{ fmt(grandTotal) }}</p>
        </div>
        <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
            <p class="text-xs text-hospital-text-2">إجمالي المدفوع</p>
            <p class="mt-1 font-mono text-xl font-bold text-hospital-success">{{ fmt(grandPaid) }}</p>
        </div>
        <div class="rounded-xl border border-hospital-border bg-white p-4 shadow-sm">
            <p class="text-xs text-hospital-text-2">إجمالي المتبقي</p>
            <p class="mt-1 font-mono text-xl font-bold" :class="grandDue > 0 ? 'text-hospital-danger' : 'text-hospital-success'">{{ fmt(grandDue) }}</p>
        </div>
    </div>

    <!-- Doctors grid -->
    <div v-if="summaries.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <button
            v-for="s in summaries"
            :key="s.doctor.id"
            class="group rounded-xl border border-hospital-border bg-white p-4 text-right shadow-sm transition-all hover:border-hospital-primary hover:shadow-md"
            :class="{ 'ring-2 ring-hospital-primary border-hospital-primary': claims?.doctor.id === s.doctor.id }"
            @click="loadDoctor(s.doctor.id)"
        >
            <div class="mb-3 flex items-center justify-between">
                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-blue-700">
                    <UserCircle class="h-5 w-5" />
                </div>
                <ChevronLeft class="h-4 w-4 text-hospital-text-2 transition-transform group-hover:-translate-x-1" />
            </div>
            <p class="mb-3 text-sm font-bold text-hospital-text">{{ s.doctor.name }}</p>
            <div class="grid grid-cols-3 gap-1 text-center">
                <div>
                    <p class="text-[10px] text-hospital-text-2">مستحق</p>
                    <p class="font-mono text-xs font-bold text-hospital-primary">{{ fmt(s.total_claims) }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-hospital-text-2">مدفوع</p>
                    <p class="font-mono text-xs font-bold text-hospital-success">{{ fmt(s.paid_amount) }}</p>
                </div>
                <div>
                    <p class="text-[10px] text-hospital-text-2">متبقي</p>
                    <p class="font-mono text-xs font-bold" :class="s.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-success'">{{ fmt(s.net_due) }}</p>
                </div>
            </div>
        </button>
    </div>
    <div v-else class="rounded-xl border border-hospital-border bg-white p-12 text-center text-hospital-text-2">
        لا يوجد أطباء نشطون
    </div>

    <!-- Right slide-over: doctor detail -->
    <Teleport v-if="mounted" to="body">
        <Transition name="claims-fade">
            <div v-if="claims" class="fixed inset-0 z-30 bg-black/30" @click="closePanel" />
        </Transition>

        <Transition name="claims-slide">
            <div v-if="claims" class="fixed inset-y-0 left-0 z-40 flex w-full max-w-2xl flex-col bg-white shadow-2xl" dir="rtl">
                <!-- Panel header -->
                <div class="flex shrink-0 items-center justify-between bg-linear-to-l from-blue-700 to-blue-900 px-5 py-4 text-white">
                    <div>
                        <p class="text-base font-bold">{{ claims.doctor.name }}</p>
                        <p class="text-xs opacity-75">{{ claims.period_from }} — {{ claims.period_to }}</p>
                    </div>
                    <button class="rounded-full p-1 hover:bg-white/20" @click="closePanel">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Summary cards -->
                <div class="grid shrink-0 grid-cols-3 gap-3 border-b border-hospital-border bg-hospital-bg p-4">
                    <div class="rounded-lg bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] text-hospital-text-2">إجمالي المستحقات</p>
                        <p class="font-mono text-sm font-bold text-hospital-primary">{{ fmt(claims.total_claims) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] text-hospital-text-2">المدفوع للطبيب</p>
                        <p class="font-mono text-sm font-bold text-hospital-success">{{ fmt(claims.paid_amount) }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-3 text-center shadow-sm">
                        <p class="text-[10px] text-hospital-text-2">المتبقي</p>
                        <p class="font-mono text-sm font-bold" :class="claims.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-success'">{{ fmt(claims.net_due) }}</p>
                    </div>
                </div>

                <!-- Payments made to doctor -->
                <div v-if="claims.payments.length" class="shrink-0 border-b border-hospital-border bg-white px-4 py-3">
                    <p class="mb-2 text-xs font-bold text-hospital-text-2">الدفعات المسددة للطبيب</p>
                    <div class="space-y-1.5">
                        <div
                            v-for="p in claims.payments"
                            :key="p.id"
                            class="flex items-center justify-between rounded-lg bg-hospital-bg px-3 py-2 text-xs"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-hospital-text-2">{{ p.paid_at }}</span>
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-medium" :class="p.method === 'cash' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'">
                                    {{ p.method === 'cash' ? 'نقدي' : 'تحويل' }}
                                </span>
                                <span v-if="p.notes" class="text-hospital-muted">{{ p.notes }}</span>
                            </div>
                            <span class="font-mono font-semibold text-hospital-success">{{ fmt(p.amount) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Rows table (scrollable) -->
                <div class="flex-1 overflow-y-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 border-b border-hospital-border bg-hospital-bg">
                            <tr>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold">التاريخ</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold">المريض</th>
                                <th class="px-4 py-2.5 text-right text-xs font-semibold">القسم</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold">مدفوع</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold">مستحق</th>
                                <th class="px-4 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in claims.rows"
                                :key="row.booking_id"
                                class="cursor-pointer border-b border-hospital-border/40 hover:bg-blue-50/50"
                                @click="selectedRow = row"
                            >
                                <td class="px-4 py-2.5 text-xs">{{ row.date }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="block text-xs font-medium">{{ row.patient_name }}</span>
                                    <span class="block font-mono text-[10px] text-hospital-text-2">{{ row.file_no }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs">{{ deptLabels[row.dept] ?? row.dept }}</td>
                                <td class="px-4 py-2.5 text-left font-mono text-xs">{{ fmt(row.paid) }}</td>
                                <td class="px-4 py-2.5 text-left font-mono text-xs font-semibold text-hospital-primary">{{ fmt(row.dr_share) }}</td>
                                <td class="px-4 py-2.5">
                                    <FileText class="h-3.5 w-3.5 text-hospital-text-2" />
                                </td>
                            </tr>
                            <tr v-if="claims.rows.length === 0">
                                <td colspan="6" class="p-10 text-center text-sm text-hospital-text-2">
                                    لا توجد حالات مسددة في هذه الفترة
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer action -->
                <div class="flex shrink-0 items-center justify-between border-t border-hospital-border p-4">
                    <button
                        class="flex items-center gap-1.5 rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg"
                        @click="printInvoice"
                    >
                        <Printer class="h-4 w-4" />
                        طباعة
                    </button>
                    <div class="flex gap-2">
                        <button class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="closePanel">إغلاق</button>
                        <button
                            v-if="claims.net_due > 0"
                            class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white hover:bg-hospital-success/90"
                            @click="openPay"
                        >
                            <CreditCard class="h-4 w-4" />
                            تسجيل دفعة ({{ fmt(claims.net_due) }})
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Row invoice modal -->
    <Teleport v-if="mounted" to="body">
        <Transition name="claims-fade">
            <div
                v-if="selectedRow && claims"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="selectedRow = null"
            >
                <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl" dir="rtl">
                    <div class="flex items-center justify-between rounded-t-2xl bg-linear-to-l from-blue-700 to-blue-900 px-5 py-4 text-white">
                        <div>
                            <p class="text-xs opacity-75">{{ claims.doctor.name }}</p>
                            <p class="text-base font-bold">إيصال حالة — {{ selectedRow.file_no }}</p>
                            <p class="text-xs opacity-75">{{ selectedRow.date }}</p>
                        </div>
                        <button class="rounded-full p-1 hover:bg-white/20" @click="selectedRow = null">
                            <X class="h-5 w-5" />
                        </button>
                    </div>

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

                        <div class="rounded-xl bg-blue-50 p-3">
                            <p class="mb-2 text-xs font-bold text-blue-700">ملخص الفترة</p>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <p class="text-[10px] text-hospital-text-2">إجمالي مستحق</p>
                                    <p class="font-mono text-xs font-bold text-blue-700">{{ fmt(claims.total_claims) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-hospital-text-2">مدفوع للطبيب</p>
                                    <p class="font-mono text-xs font-bold text-hospital-success">{{ fmt(claims.paid_amount) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] text-hospital-text-2">المتبقي</p>
                                    <p class="font-mono text-xs font-bold" :class="claims.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-success'">{{ fmt(claims.net_due) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-hospital-border p-4">
                        <button class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="selectedRow = null">إغلاق</button>
                        <button
                            v-if="claims.net_due > 0"
                            class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white"
                            @click="selectedRow = null; openPay();"
                        >
                            <CreditCard class="h-4 w-4" /> تسجيل دفعة
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Pay modal -->
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
    <!-- Print invoice (hidden, rendered to body, visible only on print) -->
    <Teleport v-if="mounted && claims" to="body">
        <div id="dr-claims-print" dir="rtl">
            <!-- Header -->
            <div class="print-header">
                <div>
                    <div class="print-title">كشف مستحقات الطبيب</div>
                    <div class="print-subtitle">{{ claims.doctor.name }}</div>
                </div>
                <div class="print-period">
                    <div>الفترة</div>
                    <div>{{ claims.period_from }} — {{ claims.period_to }}</div>
                </div>
            </div>

            <!-- Summary -->
            <div class="print-summary">
                <div class="print-summary-cell">
                    <div class="print-summary-label">إجمالي المستحقات</div>
                    <div class="print-summary-value primary">{{ fmt(claims.total_claims) }}</div>
                </div>
                <div class="print-summary-cell">
                    <div class="print-summary-label">إجمالي المدفوع</div>
                    <div class="print-summary-value success">{{ fmt(claims.paid_amount) }}</div>
                </div>
                <div class="print-summary-cell">
                    <div class="print-summary-label">الصافي المتبقي</div>
                    <div class="print-summary-value" :class="claims.net_due > 0 ? 'danger' : 'success'">{{ fmt(claims.net_due) }}</div>
                </div>
            </div>

            <!-- Payments section -->
            <template v-if="claims.payments.length">
                <div class="print-section-title">الدفعات المسددة للطبيب</div>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th>تاريخ الدفع</th>
                            <th>الطريقة</th>
                            <th>ملاحظات</th>
                            <th class="ltr">المبلغ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in claims.payments" :key="p.id">
                            <td>{{ p.paid_at }}</td>
                            <td>{{ p.method === 'cash' ? 'نقدي' : 'تحويل بنكي' }}</td>
                            <td>{{ p.notes ?? '—' }}</td>
                            <td class="ltr amount">{{ fmt(p.amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- Booking rows -->
            <div class="print-section-title">تفاصيل الحالات المسددة</div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>رقم الملف</th>
                        <th>المريض</th>
                        <th>القسم</th>
                        <th>الخدمة</th>
                        <th class="ltr">المدفوع</th>
                        <th class="ltr">مستحق الطبيب</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in claims.rows" :key="row.booking_id">
                        <td>{{ row.date }}</td>
                        <td class="mono">{{ row.file_no }}</td>
                        <td>{{ row.patient_name }}</td>
                        <td>{{ deptLabels[row.dept] ?? row.dept }}</td>
                        <td>{{ row.service }}</td>
                        <td class="ltr amount">{{ fmt(row.paid) }}</td>
                        <td class="ltr amount bold">{{ fmt(row.dr_share) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="tfoot-sub">
                        <td colspan="6">إجمالي المستحقات</td>
                        <td class="ltr amount">{{ fmt(claims.total_claims) }}</td>
                    </tr>
                    <tr class="tfoot-sub">
                        <td colspan="6">إجمالي المدفوع للطبيب</td>
                        <td class="ltr amount deduct">− {{ fmt(claims.paid_amount) }}</td>
                    </tr>
                    <tr class="tfoot-net">
                        <td colspan="6" class="bold">الصافي المتبقي</td>
                        <td class="ltr amount bold">{{ fmt(claims.net_due) }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="print-footer">
                طُبع بتاريخ {{ new Date().toLocaleDateString('ar-EG') }}
            </div>
        </div>
    </Teleport>
</template>

<style>
.claims-fade-enter-active,
.claims-fade-leave-active {
    transition: opacity 0.2s ease;
}
.claims-fade-enter-from,
.claims-fade-leave-to {
    opacity: 0;
}

.claims-slide-enter-active,
.claims-slide-leave-active {
    transition: transform 0.25s ease;
}
.claims-slide-enter-from,
.claims-slide-leave-to {
    transform: translateX(-100%);
}

/* ── Print invoice ── */
#dr-claims-print {
    display: none;
}

@media print {
    body > *:not(#dr-claims-print) { display: none !important; }
    #dr-claims-print {
        display: block;
        font-family: 'Cairo', 'Segoe UI', sans-serif;
        font-size: 12px;
        color: #111;
        direction: rtl;
        padding: 24px;
    }

    .print-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid #1e3a5f;
        padding-bottom: 12px;
        margin-bottom: 16px;
    }
    .print-title  { font-size: 18px; font-weight: 700; color: #1e3a5f; }
    .print-subtitle { font-size: 14px; font-weight: 600; margin-top: 4px; }
    .print-period { text-align: left; font-size: 11px; color: #555; }
    .print-period div:last-child { font-weight: 700; font-size: 13px; color: #111; margin-top: 2px; }

    .print-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    .print-summary-cell {
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 10px 14px;
        text-align: center;
    }
    .print-summary-label { font-size: 10px; color: #666; margin-bottom: 4px; }
    .print-summary-value { font-size: 15px; font-weight: 800; font-family: monospace; }
    .print-summary-value.primary { color: #1e3a5f; }
    .print-summary-value.success { color: #16a34a; }
    .print-summary-value.danger  { color: #dc2626; }

    .print-section-title {
        background: #1e3a5f;
        color: #fff;
        padding: 5px 12px;
        font-size: 11px;
        font-weight: 700;
        margin: 16px 0 6px;
        border-radius: 4px;
    }

    .print-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
        margin-bottom: 8px;
    }
    .print-table th {
        background: #e8f0fe;
        color: #1e3a5f;
        padding: 7px 10px;
        text-align: right;
        font-weight: 700;
        border: 1px solid #c8d8f0;
    }
    .print-table td {
        padding: 6px 10px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }
    .print-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .print-table tfoot .tfoot-sub td {
        background: #f1f5f9;
        color: #374151;
        font-size: 11px;
        border-color: #e2e8f0;
    }
    .print-table tfoot .tfoot-sub .deduct {
        color: #16a34a;
        font-weight: 700;
    }
    .print-table tfoot .tfoot-net td {
        background: #1e3a5f;
        color: #fff;
        font-weight: 700;
        font-size: 13px;
        border-color: #1e3a5f;
    }
    .print-table .ltr { text-align: left; direction: ltr; }
    .print-table .mono { font-family: monospace; font-size: 10px; }
    .print-table .amount { font-family: monospace; }
    .print-table .bold { font-weight: 700; }

    .print-footer {
        margin-top: 24px;
        text-align: center;
        font-size: 10px;
        color: #888;
        border-top: 1px solid #eee;
        padding-top: 10px;
    }
}
</style>
