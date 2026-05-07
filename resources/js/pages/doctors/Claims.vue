<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Calculator, CreditCard, X, FileText, ChevronLeft, ChevronRight, UserCircle, Printer, Search, PackageOpen } from 'lucide-vue-next';
import { ref, computed, onMounted, watch } from 'vue';
import Modal from '@/components/shared/Modal.vue';

interface DoctorSummary {
    doctor: { id: string; name: string; fee_type: string };
    total_claims: number;
    paid_amount: number;
    net_due: number;
}

interface SupplyItem {
    name: string;
    qty: number;
    unit_cost: number;
    total?: number;
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
    supplies?: SupplyItem[];
    supply_total?: number;
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
    period_from: string | null;
    period_to: string | null;
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

// ── Date filter ──
const fromFilter = ref(props.filters.from ?? '');
const toFilter = ref(props.filters.to ?? '');

function applyFilter() {
    router.get('/dr-claims', {
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
    }, { preserveState: true });
}

// ── Doctor table state ──
const search = ref('');
const currentPage = ref(1);
const perPage = 10;

const filteredSummaries = computed(() =>
    props.summaries.filter(s => s.doctor.name.includes(search.value)),
);

const totalPages = computed(() => Math.max(1, Math.ceil(filteredSummaries.value.length / perPage)));

const paginatedSummaries = computed(() => {
    const start = (currentPage.value - 1) * perPage;
    return filteredSummaries.value.slice(start, start + perPage);
});

watch(search, () => { currentPage.value = 1; });

// ── Doctor detail panel ──
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

// ── Row invoice modal ──
const selectedRow = ref<ClaimRow | null>(null);

// ── Pay modal ──
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

function openPay(summary?: DoctorSummary) {
    if (props.claims) {
        payForm.doctor_id = props.claims.doctor.id;
        payForm.amount = props.claims.net_due;
        payForm.period_from = props.claims.period_from;
        payForm.period_to = props.claims.period_to;
    } else if (summary) {
        payForm.doctor_id = summary.doctor.id;
        payForm.amount = summary.net_due;
        payForm.period_from = fromFilter.value;
        payForm.period_to = toFilter.value;
    }
    showPay.value = true;
}

function submitPay() {
    payForm.post('/dr-claims/pay', {
        preserveState: true,
        onSuccess: () => {
            showPay.value = false;
            if (props.claims) {
                loadDoctor(props.claims.doctor.id);
            }
        },
    });
}

// ── Totals ──
const grandTotal = computed(() => props.summaries.reduce((s, d) => s + d.total_claims, 0));
const grandPaid  = computed(() => props.summaries.reduce((s, d) => s + d.paid_amount, 0));
const grandDue   = computed(() => props.summaries.reduce((s, d) => s + d.net_due, 0));

const surgicalRows = computed(() =>
    (props.claims?.rows ?? []).filter(r => (r.supplies?.length ?? 0) > 0),
);

const deptLabels: Record<string, string> = {
    clinic: 'عيادة', labs: 'فحوصات', surgery: 'عمليات', lasik: 'ليزك', laser: 'ليزر',
};

const feeTypeLabel: Record<string, string> = {
    percentage: 'نسبة %',
    fixed: 'مبلغ ثابت',
    insurance: 'تأمين',
};

function fmt(n: number) {
    return Number(n ?? 0).toLocaleString('ar-EG', { minimumFractionDigits: 2 }) + ' ج.م';
}

function printInvoice() {
    window.print();
}
</script>

<template>
    <Head title="مستحقات الأطباء" />

    <!-- Page header + date filter -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">مستحقات الأطباء</h2>
            <p class="text-xs text-hospital-text-3">احتساب وصرف حصص الأطباء عن العمليات والكشوفات</p>
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

    <!-- Grand totals strip -->
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

    <!-- Doctors table card -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-hospital-border bg-white [box-shadow:var(--sh)]">
        <!-- Table toolbar -->
        <div class="flex items-center justify-between border-b border-hospital-border bg-hospital-surface-2 px-4 py-3">
            <p class="text-[13px] font-bold text-hospital-text">
                قائمة الأطباء
                <span class="ml-1 text-[11px] font-normal text-hospital-text-3">({{ filteredSummaries.length }} طبيب)</span>
            </p>
            <div class="relative">
                <Search class="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-hospital-text-3" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="ابحث باسم الطبيب..."
                    class="h-8 w-[200px] rounded-[7px] border border-hospital-border bg-white pr-9 pl-3 text-[12px] focus:border-hospital-primary focus:outline-none"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-right">
                <thead>
                    <tr class="border-b border-hospital-border bg-hospital-bg/50">
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3 w-8">#</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3">الطبيب</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3">نوع الحساب</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3 text-left">إجمالي المستحق</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3 text-left">المدفوع</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3 text-left">المتبقي</th>
                        <th class="px-4 py-3 text-[10px] font-bold text-hospital-text-3 text-center w-28">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border/60">
                    <tr
                        v-for="(s, index) in paginatedSummaries"
                        :key="s.doctor.id"
                        class="cursor-pointer transition-colors hover:bg-hospital-primary-pale/30"
                        :class="{ 'bg-hospital-primary-pale/40': claims?.doctor.id === s.doctor.id }"
                        @click="loadDoctor(s.doctor.id)"
                    >
                        <td class="px-4 py-3 text-[11px] text-hospital-text-3">
                            {{ (currentPage - 1) * perPage + index + 1 }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-hospital-primary-pale text-hospital-primary">
                                    <UserCircle class="h-4 w-4" />
                                </div>
                                <span class="text-[12px] font-bold text-hospital-text">{{ s.doctor.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-md bg-hospital-bg px-2 py-1 text-[10px] font-bold text-hospital-text-2">
                                {{ feeTypeLabel[s.doctor.fee_type] ?? s.doctor.fee_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-left font-mono text-[12px] font-bold text-hospital-primary">
                            {{ fmt(s.total_claims) }}
                        </td>
                        <td class="px-4 py-3 text-left font-mono text-[12px] font-bold text-hospital-success">
                            {{ fmt(s.paid_amount) }}
                        </td>
                        <td class="px-4 py-3 text-left font-mono text-[12px] font-bold" :class="s.net_due > 0 ? 'text-hospital-danger' : 'text-hospital-text-3'">
                            {{ fmt(s.net_due) }}
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <div class="flex items-center justify-center gap-1.5">
                                <button
                                    class="rounded-[6px] border border-hospital-border px-2.5 py-1 text-[10px] font-bold text-hospital-text-2 transition-all hover:bg-hospital-bg"
                                    @click="loadDoctor(s.doctor.id)"
                                >
                                    تفاصيل
                                </button>
                                <button
                                    v-if="s.net_due > 0"
                                    class="rounded-[6px] bg-hospital-success px-2.5 py-1 text-[10px] font-bold text-white transition-all hover:bg-hospital-success/90"
                                    @click="openPay(s)"
                                >
                                    صرف
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="paginatedSummaries.length === 0">
                        <td colspan="7" class="py-12 text-center text-[12px] text-hospital-text-3">
                            {{ summaries.length === 0 ? 'لا يوجد أطباء نشطون' : 'لا توجد نتائج مطابقة للبحث' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-hospital-border px-5 py-3">
            <p class="text-[11px] text-hospital-text-3">صفحة {{ currentPage }} من {{ totalPages }}</p>
            <div class="flex items-center gap-1">
                <button
                    :disabled="currentPage === 1"
                    class="flex h-7 w-7 items-center justify-center rounded-[6px] border border-hospital-border text-hospital-text-2 hover:bg-hospital-bg disabled:opacity-40"
                    @click="currentPage--"
                >
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
                <template v-for="p in totalPages" :key="p">
                    <button
                        v-if="Math.abs(p - currentPage) <= 2 || p === 1 || p === totalPages"
                        class="flex h-7 w-7 items-center justify-center rounded-[6px] text-[11px] font-bold transition-all"
                        :class="p === currentPage ? 'bg-hospital-primary text-white' : 'border border-hospital-border text-hospital-text-2 hover:bg-hospital-bg'"
                        @click="currentPage = p"
                    >{{ p }}</button>
                    <span v-else-if="Math.abs(p - currentPage) === 3" class="px-1 text-[11px] text-hospital-text-3">…</span>
                </template>
                <button
                    :disabled="currentPage === totalPages"
                    class="flex h-7 w-7 items-center justify-center rounded-[6px] border border-hospital-border text-hospital-text-2 hover:bg-hospital-bg disabled:opacity-40"
                    @click="currentPage++"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════ Right slide-over: doctor detail ════════════════ -->
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
                                <span v-if="p.notes" class="text-hospital-text-3">{{ p.notes }}</span>
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
                                <th class="px-4 py-2.5 text-left text-xs font-semibold">مستلزمات</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold">مستحق</th>
                                <th class="px-4 py-2.5 w-6" />
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="row in claims.rows" :key="row.booking_id">
                                <tr
                                    class="cursor-pointer border-b border-hospital-border/40 hover:bg-blue-50/50"
                                    @click="selectedRow = row"
                                >
                                    <td class="px-4 py-2.5 text-xs">{{ row.date }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="block text-xs font-medium">{{ row.patient_name }}</span>
                                        <span class="block font-mono text-[10px] text-hospital-text-3">{{ row.file_no }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs">{{ deptLabels[row.dept] ?? row.dept }}</td>
                                    <td class="px-4 py-2.5 text-left font-mono text-xs">{{ fmt(row.paid) }}</td>
                                    <td class="px-4 py-2.5 text-left font-mono text-xs" :class="(row.supply_total ?? 0) > 0 ? 'font-semibold text-hospital-warning' : 'text-hospital-text-3'">
                                        {{ (row.supply_total ?? 0) > 0 ? fmt(row.supply_total!) : '—' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-left font-mono text-xs font-semibold text-hospital-primary">{{ fmt(row.dr_share) }}</td>
                                    <td class="px-4 py-2.5">
                                        <FileText class="h-3.5 w-3.5 text-hospital-text-3" />
                                    </td>
                                </tr>
                                <!-- Supply items sub-row -->
                                <tr v-if="row.supplies && row.supplies.length > 0" class="border-b border-hospital-border/40 bg-amber-50/40">
                                    <td colspan="7" class="px-6 pb-2.5 pt-1">
                                        <div class="flex items-start gap-2">
                                            <PackageOpen class="mt-0.5 h-3.5 w-3.5 shrink-0 text-hospital-warning" />
                                            <div>
                                                <p class="mb-1 text-[9px] font-bold uppercase text-hospital-warning">مستلزمات جراحية</p>
                                                <div class="flex flex-wrap gap-2">
                                                    <span
                                                        v-for="(item, i) in row.supplies"
                                                        :key="i"
                                                        class="rounded bg-white px-2 py-0.5 text-[10px] text-hospital-text-2 shadow-sm"
                                                    >
                                                        {{ item.name }} × {{ item.qty }} = <strong>{{ fmt(item.total ?? item.qty * item.unit_cost) }}</strong>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="claims.rows.length === 0">
                                <td colspan="7" class="p-10 text-center text-sm text-hospital-text-2">
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
                            @click="openPay()"
                        >
                            <CreditCard class="h-4 w-4" />
                            تسجيل دفعة ({{ fmt(claims.net_due) }})
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ════════════════ Row invoice modal ════════════════ -->
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

                        <!-- Surgery supplies in row modal -->
                        <div v-if="selectedRow.supplies && selectedRow.supplies.length > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-3">
                            <p class="mb-2 flex items-center gap-1.5 text-xs font-bold text-amber-700">
                                <PackageOpen class="h-3.5 w-3.5" /> مستلزمات جراحية
                            </p>
                            <div class="space-y-1">
                                <div v-for="(item, i) in selectedRow.supplies" :key="i" class="flex justify-between text-xs">
                                    <span class="text-amber-800">{{ item.name }} (× {{ item.qty }})</span>
                                    <span class="font-mono font-semibold text-amber-900">{{ fmt(item.total ?? item.qty * item.unit_cost) }}</span>
                                </div>
                                <div class="mt-1 flex justify-between border-t border-amber-200 pt-1 text-xs font-bold text-amber-800">
                                    <span>إجمالي المستلزمات</span>
                                    <span class="font-mono">{{ fmt(selectedRow.supply_total ?? 0) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-hospital-border p-4">
                            <div class="flex items-center justify-between border-b border-dashed border-hospital-border pb-2 text-sm">
                                <span class="text-hospital-text-2">المبلغ المدفوع من المريض</span>
                                <span class="font-mono">{{ fmt(selectedRow.paid) }}</span>
                            </div>
                            <div v-if="(selectedRow.supply_total ?? 0) > 0" class="flex items-center justify-between border-b border-dashed border-hospital-border py-2 text-sm">
                                <span class="text-hospital-warning">خصم المستلزمات</span>
                                <span class="font-mono text-hospital-warning">− {{ fmt(selectedRow.supply_total!) }}</span>
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

    <!-- ════════════════ Pay modal ════════════════ -->
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
                    <p v-if="payForm.errors.paid_at" class="mt-1 text-xs text-hospital-danger">{{ payForm.errors.paid_at }}</p>
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

    <!-- ════════════════ Print invoice ════════════════ -->
    <Teleport v-if="mounted && claims" to="body">
        <div id="dr-claims-print" dir="rtl">

            <!-- Hospital Header -->
            <div class="ph-header">
                <div class="ph-logo">👁</div>
                <div class="ph-hospital">
                    <div class="ph-hospital-name">مستشفى النور لطب وجراحة العيون</div>
                    <div class="ph-hospital-sub">Al-Nour Eye Hospital</div>
                </div>
                <div class="ph-doc-info">
                    <div class="ph-doc-label">كشف مستحقات الطبيب</div>
                    <div class="ph-doc-name">{{ claims.doctor.name }}</div>
                    <div class="ph-doc-period">
                        الفترة:
                        <span v-if="claims.period_from && claims.period_to">{{ claims.period_from }} — {{ claims.period_to }}</span>
                        <span v-else>كل الفترات</span>
                    </div>
                    <div class="ph-doc-date">تاريخ الطباعة: {{ new Date().toLocaleDateString('ar-EG') }}</div>
                </div>
            </div>

            <!-- Summary Strip -->
            <div class="ph-summary">
                <div class="ph-sum-card ph-sum-primary">
                    <div class="ph-sum-label">إجمالي المستحقات</div>
                    <div class="ph-sum-value">{{ fmt(claims.total_claims) }}</div>
                </div>
                <div class="ph-sum-card ph-sum-success">
                    <div class="ph-sum-label">إجمالي المدفوع</div>
                    <div class="ph-sum-value">{{ fmt(claims.paid_amount) }}</div>
                </div>
                <div class="ph-sum-card" :class="claims.net_due > 0 ? 'ph-sum-danger' : 'ph-sum-success'">
                    <div class="ph-sum-label">الصافي المتبقي</div>
                    <div class="ph-sum-value">{{ fmt(claims.net_due) }}</div>
                </div>
            </div>

            <!-- Payments Table -->
            <template v-if="claims.payments.length">
                <div class="ph-section-title">الدفعات المسددة للطبيب</div>
                <table class="ph-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>تاريخ الدفع</th>
                            <th>الطريقة</th>
                            <th>ملاحظات</th>
                            <th class="num">المبلغ (ج.م)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in claims.payments" :key="p.id">
                            <td class="center muted">{{ i + 1 }}</td>
                            <td>{{ p.paid_at }}</td>
                            <td><span class="ph-badge" :class="p.method === 'cash' ? 'ph-badge-green' : 'ph-badge-blue'">{{ p.method === 'cash' ? 'نقدي' : 'تحويل بنكي' }}</span></td>
                            <td class="muted">{{ p.notes ?? '—' }}</td>
                            <td class="num green bold">{{ fmt(p.amount) }}</td>
                        </tr>
                        <tr class="ph-tfoot-sub">
                            <td colspan="4" class="bold">إجمالي الدفعات</td>
                            <td class="num green bold">{{ fmt(claims.paid_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <!-- Claims Detail Table -->
            <div class="ph-section-title">تفاصيل الحالات</div>
            <table class="ph-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>التاريخ</th>
                        <th>رقم الملف</th>
                        <th>المريض</th>
                        <th>القسم</th>
                        <th>الخدمة</th>
                        <th class="num">المدفوع</th>
                        <th class="num">مستلزمات</th>
                        <th class="num">مستحق الطبيب</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in claims.rows" :key="row.booking_id">
                        <tr>
                            <td class="center muted">{{ idx + 1 }}</td>
                            <td class="mono">{{ row.date }}</td>
                            <td class="mono muted">{{ row.file_no }}</td>
                            <td class="bold">{{ row.patient_name }}</td>
                            <td><span class="ph-dept">{{ deptLabels[row.dept] ?? row.dept }}</span></td>
                            <td class="muted">{{ row.service }}</td>
                            <td class="num">{{ fmt(row.paid) }}</td>
                            <td class="num" :class="(row.supply_total ?? 0) > 0 ? 'orange bold' : 'muted'">
                                {{ (row.supply_total ?? 0) > 0 ? fmt(row.supply_total!) : '—' }}
                            </td>
                            <td class="num primary bold">{{ fmt(row.dr_share) }}</td>
                        </tr>
                        <!-- Supply sub-rows -->
                        <tr v-if="row.supplies && row.supplies.length > 0" class="ph-supply-row">
                            <td colspan="9" class="ph-supply-cell">
                                <span class="ph-supply-title">مستلزمات جراحية: </span>
                                <span v-for="(item, i) in row.supplies" :key="i" class="ph-supply-item">
                                    {{ item.name }} × {{ item.qty }}
                                    <span class="ph-supply-cost">{{ fmt(item.total ?? item.qty * item.unit_cost) }}</span>
                                    <span v-if="i < (row.supplies?.length ?? 0) - 1"> — </span>
                                </span>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="claims.rows.length === 0">
                        <td colspan="9" class="center muted" style="padding:16px">لا توجد حالات في هذه الفترة</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr v-if="surgicalRows.length > 0" class="ph-tfoot-sub">
                        <td colspan="8">إجمالي تكاليف المستلزمات الجراحية</td>
                        <td class="num orange bold">{{ fmt(surgicalRows.reduce((s, r) => s + (r.supply_total ?? 0), 0)) }}</td>
                    </tr>
                    <tr class="ph-tfoot-sub">
                        <td colspan="8">إجمالي مستحقات الطبيب</td>
                        <td class="num primary bold">{{ fmt(claims.total_claims) }}</td>
                    </tr>
                    <tr class="ph-tfoot-sub">
                        <td colspan="8">إجمالي الدفعات المسددة</td>
                        <td class="num green bold">− {{ fmt(claims.paid_amount) }}</td>
                    </tr>
                    <tr class="ph-tfoot-net">
                        <td colspan="8" class="bold">الصافي المستحق للطبيب</td>
                        <td class="num bold">{{ fmt(claims.net_due) }}</td>
                    </tr>
                </tfoot>
            </table>

            <!-- Signatures -->
            <div class="ph-sigs">
                <div class="ph-sig">
                    <div class="ph-sig-line" />
                    <div class="ph-sig-label">توقيع الطبيب</div>
                    <div class="ph-sig-name">{{ claims.doctor.name }}</div>
                </div>
                <div class="ph-sig">
                    <div class="ph-sig-line" />
                    <div class="ph-sig-label">توقيع المحاسب</div>
                </div>
                <div class="ph-sig">
                    <div class="ph-sig-line" />
                    <div class="ph-sig-label">توقيع المدير</div>
                </div>
            </div>

            <div class="ph-footer">
                مستشفى النور لطب وجراحة العيون &nbsp;|&nbsp; طُبع بتاريخ {{ new Date().toLocaleDateString('ar-EG') }}
            </div>

        </div>
    </Teleport>
</template>

<style>
.claims-fade-enter-active,
.claims-fade-leave-active { transition: opacity 0.2s ease; }
.claims-fade-enter-from,
.claims-fade-leave-to { opacity: 0; }

.claims-slide-enter-active,
.claims-slide-leave-active { transition: transform 0.25s ease; }
.claims-slide-enter-from,
.claims-slide-leave-to { transform: translateX(-100%); }

/* ── Print: hidden on screen ── */
#dr-claims-print { display: none; }

@media print {
    @page { size: A4 portrait; margin: 14mm 16mm; }

    body > *:not(#dr-claims-print) { display: none !important; }

    #dr-claims-print {
        display: block;
        font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
        font-size: 11.5px;
        color: #0D1F3C;
        direction: rtl;
        background: #fff;
    }

    /* ── Hospital header ── */
    .ph-header {
        display: flex;
        align-items: center;
        gap: 14px;
        padding-bottom: 12px;
        margin-bottom: 14px;
        border-bottom: 3px solid #0A4FA6;
    }
    .ph-logo {
        width: 48px; height: 48px;
        background: #0A4FA6;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .ph-hospital { flex: 1; }
    .ph-hospital-name { font-size: 15px; font-weight: 800; color: #072E63; }
    .ph-hospital-sub  { font-size: 10px; color: #4A5878; margin-top: 2px; }
    .ph-doc-info { text-align: left; }
    .ph-doc-label { font-size: 10px; color: #8A96AE; text-transform: uppercase; letter-spacing: .5px; }
    .ph-doc-name  { font-size: 15px; font-weight: 700; color: #0A4FA6; margin-top: 2px; }
    .ph-doc-period { font-size: 11px; color: #0D1F3C; margin-top: 3px; }
    .ph-doc-date  { font-size: 10px; color: #8A96AE; margin-top: 2px; }

    /* ── Summary strip ── */
    .ph-summary {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 16px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .ph-sum-card {
        border-radius: 8px;
        padding: 10px 14px;
        text-align: center;
        border-right: 4px solid transparent;
    }
    .ph-sum-primary { background: #E8F1FB; border-right-color: #0A4FA6; }
    .ph-sum-success { background: #E2F5EC; border-right-color: #1A8C5B; }
    .ph-sum-danger  { background: #FDEAEA; border-right-color: #D63B3B; }
    .ph-sum-label { font-size: 9.5px; color: #4A5878; margin-bottom: 4px; }
    .ph-sum-value { font-size: 14px; font-weight: 800; font-family: monospace; }
    .ph-sum-primary .ph-sum-value { color: #0A4FA6; }
    .ph-sum-success .ph-sum-value { color: #1A8C5B; }
    .ph-sum-danger  .ph-sum-value { color: #D63B3B; }

    /* ── Section title ── */
    .ph-section-title {
        background: #072E63;
        color: #fff;
        padding: 5px 12px;
        font-size: 11px;
        font-weight: 700;
        margin: 14px 0 5px;
        border-radius: 4px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ── Tables ── */
    .ph-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10.5px;
        margin-bottom: 6px;
    }
    .ph-table th {
        background: #E8F1FB;
        color: #072E63;
        padding: 6px 8px;
        text-align: right;
        font-weight: 700;
        border: 1px solid #C8D8F0;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .ph-table td {
        padding: 5px 8px;
        border: 1px solid #DDE4EF;
        vertical-align: middle;
    }
    .ph-table tbody tr:nth-child(even) td { background: #F8FAFD; }
    .ph-table tbody tr { page-break-inside: avoid; }

    /* supply sub-row */
    .ph-supply-row td { background: #FFFBEB !important; }
    .ph-supply-cell { padding: 4px 12px !important; font-size: 10px; }
    .ph-supply-title { font-weight: 700; color: #B45309; }
    .ph-supply-item { color: #78350F; }
    .ph-supply-cost { font-weight: 700; margin-right: 3px; }

    /* tfoot */
    .ph-tfoot-sub td { background: #F1F5F9; color: #374151; font-size: 10.5px; border-color: #E2E8F0; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ph-tfoot-net td { background: #0A4FA6; color: #fff; font-weight: 700; font-size: 12px; border-color: #0A4FA6; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* utilities */
    .ph-table .num   { text-align: left; direction: ltr; font-family: monospace; }
    .ph-table .mono  { font-family: monospace; font-size: 10px; }
    .ph-table .bold  { font-weight: 700; }
    .ph-table .muted { color: #6B7280; }
    .ph-table .center { text-align: center; }
    .ph-table .primary { color: #0A4FA6; }
    .ph-table .green   { color: #1A8C5B; }
    .ph-table .orange  { color: #B45309; }

    /* dept badge */
    .ph-dept { background: #E8F1FB; color: #072E63; border-radius: 4px; padding: 1px 6px; font-size: 10px; font-weight: 600; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

    /* payment method badge */
    .ph-badge { border-radius: 4px; padding: 1px 6px; font-size: 10px; font-weight: 600; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ph-badge-green { background: #E2F5EC; color: #1A8C5B; }
    .ph-badge-blue  { background: #E8F1FB; color: #0A4FA6; }

    /* ── Signatures ── */
    .ph-sigs {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        margin-top: 32px;
        padding-top: 16px;
        border-top: 1px dashed #DDE4EF;
    }
    .ph-sig { text-align: center; }
    .ph-sig-line { height: 1px; background: #4A5878; margin-bottom: 6px; }
    .ph-sig-label { font-size: 10px; font-weight: 700; color: #4A5878; }
    .ph-sig-name  { font-size: 10px; color: #0A4FA6; margin-top: 2px; }

    /* ── Footer ── */
    .ph-footer {
        margin-top: 16px;
        text-align: center;
        font-size: 9.5px;
        color: #8A96AE;
        border-top: 1px solid #EEF0F4;
        padding-top: 8px;
    }
}
</style>
