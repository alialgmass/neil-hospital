<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    User,
    Phone,
    Calendar,
    Stethoscope,
    FlaskConical,
    Scissors,
    FileText,
    Paperclip,
    Printer,
    IdCard,
    ShieldCheck,
    ArrowLeftRight,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FileNoBarcode from '@/components/booking/FileNoBarcode.vue';
import Badge from '@/components/shared/Badge.vue';
import Modal from '@/components/shared/Modal.vue';
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions';
import { formatDate } from '@/lib/date';
import EyeSideSelector from '@/pages/booking/Partials/EyeSideSelector.vue';
import { archive } from '@/routes';
import booking from '@/routes/booking';

interface Patient {
    name: string;
    phone?: string;
    age?: number;
    file_no: string;
    national_id?: string;
    gender?: 'male' | 'female';
    kinship_degree?: string;
    kinship_degree_label?: string;
}

interface ClinicSheet {
    chief_complaint?: string;
    visual_acuity_od?: string;
    visual_acuity_os?: string;
    iop_od?: number;
    iop_os?: number;
    diagnosis?: string;
    plan?: string;
}

interface DiagnosticResult {
    id: string;
    test_name: string;
    eye?: string;
    result_text?: string;
    recorded_at: string;
}

interface Surgery {
    id: string;
    procedure: string;
    eye?: string;
    status: string;
    op_report?: string;
    scheduled_at?: string;
}

interface InsuranceClaim {
    id: string;
    invoice_amount: number;
    discount: number;
    patient_share: number;
    insurance_share: number;
    approved_amount: number;
    paid_amount: number;
    status: 'draft' | 'submitted' | 'approved' | 'rejected' | 'paid';
    claim_reference?: string;
    company?: { name: string };
}

interface Service {
    id: string;
    name: string;
    dept: string;
}

interface MediaFile {
    id: number;
    name: string;
    url: string;
    mime: string;
    size: string;
}

interface Booking {
    id: string;
    file_no: string;
    dept: string;
    service_name?: string;
    service?: Service;
    visit_date: string;
    visit_time?: string;
    price: number;
    discount: number;
    ins_amount: number;
    paid_amount: number;
    pay_method: string;
    pay_status: string;
    status: string;
    visit_note?: string;
    eye_side?: string;
    analysis_type?: string;
    analysis_notes?: string;
    cancel_reason?: string;
    doctor?: { name: string };
    clinic_sheet?: ClinicSheet;
    diagnostic_results?: DiagnosticResult[];
    surgery?: Surgery;
    insurance_claim?: InsuranceClaim;
    media_files: MediaFile[];
}

const props = defineProps<{
    file_no: string;
    patient: Patient | null;
    bookings: Booking[];
    transfer_services: { id: string; name: string; dept: string }[];
}>();

// Most recent visit — used to print the barcode label, same page/design as the booking barcode.
const latestBooking = computed(() => props.bookings[0] ?? null);

// ── Permissions ──
const { can } = usePermissions();
const canTransfer = computed(() => can('transfer_medical_record'));

// ── Transfer to Operation (Surgery/Lasik/Laser) ──
const showTransferModal = ref(false);
const transferringBooking = ref<Booking | null>(null);
const transferForm = ref({ service_id: '', dept: '', eye: '' });

function canTransferBooking(b: Booking): boolean {
    // Already an operation, or already converted — nothing to transfer.
    return !b.surgery && b.status !== 'cancelled';
}

function openTransfer(b: Booking) {
    if (!canTransfer.value) {
        return;
    }

    transferringBooking.value = b;
    const matchedService =
        props.transfer_services.find((s) => s.name === b.service_name) ??
        props.transfer_services[0] ??
        null;
    transferForm.value = {
        service_id: matchedService?.id ?? '',
        dept: matchedService?.dept ?? 'surgery',
        eye: b.eye_side ?? '',
    };
    showTransferModal.value = true;
}

const filteredTransferServices = computed(() =>
    props.transfer_services.filter(
        (s) => s.dept === transferForm.value.dept,
    ),
);

function onTransferDeptChange() {
    const stillValid = filteredTransferServices.value.some(
        (s) => s.id === transferForm.value.service_id,
    );

    if (!stillValid) {
        transferForm.value.service_id =
            filteredTransferServices.value[0]?.id ?? '';
    }
}

function confirmTransfer() {
    if (
        !transferringBooking.value ||
        !transferForm.value.service_id ||
        !transferForm.value.dept
    ) {
        return;
    }

    router.visit(`/${transferForm.value.dept}`, {
        method: 'get',
        data: {
            transfer_booking_id: transferringBooking.value.id,
            service_id: transferForm.value.service_id,
            eye: transferForm.value.eye || undefined,
        },
    });
}

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
    pentacam: 'البنتكام',
};

const deptIcons: Record<string, unknown> = {
    clinic: Stethoscope,
    labs: FlaskConical,
    surgery: Scissors,
    lasik: Scissors,
    laser: Scissors,
};

const payStatusColors: Record<string, string> = {
    paid: 'bg-hospital-success text-white',
    partial: 'bg-hospital-warning text-white',
    unpaid: 'bg-hospital-danger text-white',
};
const payStatusLabels: Record<string, string> = {
    paid: 'مسدد',
    partial: 'جزئي',
    unpaid: 'غير مسدد',
};

const payMethodLabels: Record<string, string> = {
    cash: 'نقدي',
    card: 'بطاقة',
    transfer: 'تحويل',
    insurance: 'تأمين',
    contract: 'تعاقد',
};

const eyeSideLabels: Record<string, string> = {
    OD: 'العين اليمنى',
    OS: 'العين اليسرى',
    OU: 'كلتا العينين',
};

const genderLabels: Record<string, string> = {
    male: 'ذكر',
    female: 'أنثى',
};

const claimStatusLabels: Record<string, string> = {
    draft: 'غير مسددة',
    submitted: 'مُرسلة',
    approved: 'معتمدة',
    rejected: 'مرفوضة',
    paid: 'مسددة',
};

const claimStatusVariants: Record<string, string> = {
    draft: 'draft',
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
    paid: 'paid',
};

function netAmount(b: Booking): number {
    return Math.max(0, Number(b.price) - Number(b.discount ?? 0));
}
function remainingAmount(b: Booking): number {
    return Math.max(0, netAmount(b) - Number(b.paid_amount ?? 0));
}

function fmt(n: number) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
}
function fmtDate(d: string) {
    return formatDate(d);
}
function isImage(mime: string): boolean {
    return mime.startsWith('image/');
}
</script>

<template>
    <Head :title="`ملف المريض — ${file_no}`" />

    <!-- Header -->
    <div class="no-print mb-5 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div
                class="flex h-12 w-12 items-center justify-center rounded-full bg-hospital-primary/10 text-hospital-primary"
            >
                <User class="h-6 w-6" />
            </div>
            <div>
                <h2 class="text-lg font-bold text-hospital-text">
                    {{ patient?.name ?? 'مريض غير معروف' }}
                </h2>
                <p class="text-sm text-hospital-text-3">
                    رقم الملف: {{ file_no }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a
                v-if="latestBooking"
                :href="booking.barcode(latestBooking.id).url"
                target="_blank"
                class="flex items-center gap-2 rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text-2 transition-colors hover:bg-hospital-bg"
            >
                <Printer class="h-4 w-4" />
                طباعة الباركود
            </a>
            <Link
                :href="archive().url"
                class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text transition-colors hover:bg-hospital-bg"
            >
                العودة للأرشيف
            </Link>
        </div>
    </div>

    <!-- Barcode (reference view — printing uses the same label page as booking barcodes) -->
    <div class="no-print mb-6">
        <FileNoBarcode :value="file_no" />
    </div>

    <!-- Patient Info Card -->
    <div
        v-if="patient"
        class="no-print mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4"
    >
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <User class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">الاسم</p>
                <p class="text-sm font-medium">{{ patient.name }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <Phone class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">الهاتف</p>
                <p class="text-sm font-medium">{{ patient.phone ?? '—' }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <Calendar class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">العمر</p>
                <p class="text-sm font-medium">
                    {{ patient.age ? `${patient.age} سنة` : '—' }}
                </p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <FileText class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">عدد الزيارات</p>
                <p class="text-sm font-medium">{{ bookings.length }} زيارة</p>
            </div>
        </div>
        <div
            v-if="patient.national_id"
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <IdCard class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">الرقم القومي</p>
                <p class="text-sm font-medium">{{ patient.national_id }}</p>
            </div>
        </div>
        <div
            v-if="patient.gender"
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <User class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">النوع</p>
                <p class="text-sm font-medium">
                    {{ genderLabels[patient.gender] ?? patient.gender }}
                </p>
            </div>
        </div>
        <div
            v-if="patient.kinship_degree_label"
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <User class="h-4 w-4 text-hospital-text-3" />
            <div>
                <p class="text-xs text-hospital-text-3">
                    صلة القرابة (لمرافق الحجز)
                </p>
                <p class="text-sm font-medium">
                    {{ patient.kinship_degree_label }}
                </p>
            </div>
        </div>
    </div>

    <!-- No bookings -->
    <div
        v-if="bookings.length === 0"
        class="no-print rounded-xl border border-hospital-border bg-white p-8 text-center text-hospital-text-3"
    >
        لا توجد زيارات مسجلة لهذا الملف
    </div>

    <!-- Visit Timeline -->
    <div class="no-print space-y-4">
        <div
            v-for="booking in bookings"
            :key="booking.id"
            class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm"
        >
            <!-- Visit Header -->
            <div
                class="flex items-center justify-between border-b border-hospital-border bg-hospital-bg px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <component
                        :is="deptIcons[booking.dept] ?? Stethoscope"
                        class="h-4 w-4 text-hospital-primary"
                    />
                    <span class="font-semibold text-hospital-text">{{
                        deptLabels[booking.dept] ?? booking.dept
                    }}</span>
                    <span class="text-sm text-hospital-text-3"
                        >— {{ booking.service_name ?? '—' }}</span
                    >
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            payStatusColors[booking.pay_status] ??
                            'bg-hospital-text-3 text-white'
                        "
                    >
                        {{
                            payStatusLabels[booking.pay_status] ??
                            booking.pay_status
                        }}
                    </span>
                    <span class="text-sm font-medium text-hospital-text"
                        >{{ fmt(booking.price) }} ج.م</span
                    >
                    <span class="text-xs text-hospital-text-3">{{
                        fmtDate(booking.visit_date)
                    }}</span>
                    <button
                        v-if="canTransferBooking(booking)"
                        type="button"
                        class="flex items-center gap-1 rounded-lg border border-hospital-border px-2 py-1 text-xs font-medium text-hospital-primary transition-colors hover:bg-hospital-primary/10 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                        :disabled="!canTransfer"
                        :title="canTransfer ? 'تحويل إلى عملية' : NO_PERMISSION_TITLE"
                        @click="openTransfer(booking)"
                    >
                        <ArrowLeftRight class="h-3.5 w-3.5" />
                        تحويل
                    </button>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <!-- Doctor -->
                <div v-if="booking.doctor" class="text-sm">
                    <span class="text-hospital-text-3">الطبيب: </span>
                    <span class="font-medium">{{ booking.doctor.name }}</span>
                </div>

                <!-- Eye side / analysis type -->
                <div v-if="booking.eye_side" class="text-sm">
                    <span class="text-hospital-text-3">العين: </span>
                    <span class="font-medium">{{
                        eyeSideLabels[booking.eye_side] ?? booking.eye_side
                    }}</span>
                </div>
                <div v-if="booking.analysis_type" class="text-sm">
                    <span class="text-hospital-text-3">نوع التحليل: </span>
                    <span class="font-medium">{{ booking.analysis_type }}</span>
                </div>
                <div v-if="booking.analysis_notes" class="text-sm">
                    <span class="text-hospital-text-3">ملاحظات التحليل: </span>
                    <span>{{ booking.analysis_notes }}</span>
                </div>

                <!-- Visit note -->
                <div v-if="booking.visit_note" class="text-sm">
                    <span class="text-hospital-text-3">ملاحظات: </span>
                    <span>{{ booking.visit_note }}</span>
                </div>

                <!-- Cancellation reason -->
                <div
                    v-if="booking.cancel_reason"
                    class="text-sm text-hospital-danger"
                >
                    <span class="text-hospital-text-3">سبب الإلغاء: </span>
                    <span>{{ booking.cancel_reason }}</span>
                </div>

                <!-- Financial breakdown -->
                <div
                    class="grid grid-cols-2 gap-2 rounded-lg border border-hospital-border bg-hospital-bg/50 p-3 text-sm sm:grid-cols-4"
                >
                    <div>
                        <p class="text-xs text-hospital-text-3">طريقة الدفع</p>
                        <p class="font-medium">
                            {{
                                payMethodLabels[booking.pay_method] ??
                                booking.pay_method
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-hospital-text-3">الخصم</p>
                        <p class="font-medium">
                            {{ fmt(booking.discount ?? 0) }} ج
                        </p>
                    </div>
                    <div v-if="Number(booking.ins_amount) > 0">
                        <p class="text-xs text-hospital-text-3">حصة التأمين</p>
                        <p class="font-medium">
                            {{ fmt(booking.ins_amount) }} ج
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-hospital-text-3">المدفوع</p>
                        <p class="font-medium">
                            {{ fmt(booking.paid_amount ?? 0) }} ج
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-hospital-text-3">
                            الصافي المستحق
                        </p>
                        <p class="font-medium">
                            {{ fmt(netAmount(booking)) }} ج
                        </p>
                    </div>
                    <div v-if="remainingAmount(booking) > 0">
                        <p class="text-xs text-hospital-danger">المتبقي</p>
                        <p class="font-medium text-hospital-danger">
                            {{ fmt(remainingAmount(booking)) }} ج
                        </p>
                    </div>
                </div>

                <!-- Insurance claim -->
                <div
                    v-if="booking.insurance_claim"
                    class="space-y-1 rounded-lg border border-hospital-border bg-hospital-bg/50 p-3 text-sm"
                >
                    <p
                        class="flex items-center gap-1.5 font-medium text-hospital-primary"
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        مطالبة التأمين
                        <Badge
                            :variant="
                                claimStatusVariants[
                                    booking.insurance_claim.status
                                ] as any
                            "
                        >
                            {{
                                claimStatusLabels[
                                    booking.insurance_claim.status
                                ]
                            }}
                        </Badge>
                    </p>
                    <div v-if="booking.insurance_claim.company">
                        <span class="text-hospital-text-3">شركة التأمين: </span
                        >{{ booking.insurance_claim.company.name }}
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div>
                            <span class="text-hospital-text-3"
                                >قيمة الفاتورة: </span
                            >{{ fmt(booking.insurance_claim.invoice_amount) }} ج
                        </div>
                        <div>
                            <span class="text-hospital-text-3"
                                >حصة التأمين: </span
                            >{{
                                fmt(booking.insurance_claim.insurance_share)
                            }}
                            ج
                        </div>
                        <div>
                            <span class="text-hospital-text-3">حصة المريض: </span
                            >{{ fmt(booking.insurance_claim.patient_share) }} ج
                        </div>
                        <div>
                            <span class="text-hospital-text-3">المحصّل: </span
                            >{{ fmt(booking.insurance_claim.paid_amount) }} ج
                        </div>
                    </div>
                    <div v-if="booking.insurance_claim.claim_reference">
                        <span class="text-hospital-text-3">مرجع المطالبة: </span
                        >{{ booking.insurance_claim.claim_reference }}
                    </div>
                </div>

                <!-- Clinic Sheet -->
                <div
                    v-if="booking.clinic_sheet"
                    class="space-y-1 rounded-lg border border-hospital-border bg-hospital-bg/50 p-3 text-sm"
                >
                    <p class="font-medium text-hospital-primary">
                        ورقة الكشف الطبي
                    </p>
                    <div v-if="booking.clinic_sheet.chief_complaint">
                        <span class="text-hospital-text-3">الشكوى: </span
                        >{{ booking.clinic_sheet.chief_complaint }}
                    </div>
                    <div
                        v-if="
                            booking.clinic_sheet.visual_acuity_od ||
                            booking.clinic_sheet.visual_acuity_os
                        "
                    >
                        <span class="text-hospital-text-3">حدة الإبصار: </span>
                        OD {{ booking.clinic_sheet.visual_acuity_od ?? '—' }} /
                        OS {{ booking.clinic_sheet.visual_acuity_os ?? '—' }}
                    </div>
                    <div v-if="booking.clinic_sheet.diagnosis">
                        <span class="text-hospital-text-3">التشخيص: </span
                        >{{ booking.clinic_sheet.diagnosis }}
                    </div>
                    <div v-if="booking.clinic_sheet.plan">
                        <span class="text-hospital-text-3">خطة العلاج: </span
                        >{{ booking.clinic_sheet.plan }}
                    </div>
                </div>

                <!-- Diagnostic Results -->
                <div
                    v-if="
                        booking.diagnostic_results &&
                        booking.diagnostic_results.length > 0
                    "
                    class="space-y-1"
                >
                    <p class="text-sm font-medium text-hospital-primary">
                        نتائج الفحوصات
                    </p>
                    <div
                        v-for="result in booking.diagnostic_results"
                        :key="result.id"
                        class="rounded-lg border border-hospital-border bg-hospital-bg/50 p-2.5 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{
                                result.test_name
                            }}</span>
                            <span class="text-xs text-hospital-text-3">{{
                                result.eye ?? ''
                            }}</span>
                        </div>
                        <p
                            v-if="result.result_text"
                            class="mt-1 text-hospital-text"
                        >
                            {{ result.result_text }}
                        </p>
                    </div>
                </div>

                <!-- Surgery -->
                <div
                    v-if="booking.surgery"
                    class="space-y-1 rounded-lg border border-hospital-border bg-hospital-bg/50 p-3 text-sm"
                >
                    <p class="font-medium text-hospital-primary">
                        العملية الجراحية
                    </p>
                    <div>
                        <span class="text-hospital-text-3">الإجراء: </span
                        >{{ booking.surgery.procedure }}
                        <span
                            v-if="booking.surgery.eye"
                            class="text-hospital-text-3"
                        >
                            ({{ booking.surgery.eye }})</span
                        >
                    </div>
                    <div v-if="booking.surgery.op_report">
                        <span class="text-hospital-text-3">تقرير العملية: </span
                        >{{ booking.surgery.op_report }}
                    </div>
                </div>

                <!-- Archived Files -->
                <div v-if="booking.media_files.length > 0" class="space-y-1.5">
                    <p
                        class="flex items-center gap-1.5 text-sm font-medium text-hospital-primary"
                    >
                        <Paperclip class="h-3.5 w-3.5" />
                        الملفات المرفقة ({{ booking.media_files.length }})
                    </p>
                    <div
                        class="divide-y divide-hospital-border rounded-lg border border-hospital-border"
                    >
                        <div
                            v-for="file in booking.media_files"
                            :key="file.id"
                            class="flex items-center gap-3 px-3 py-2"
                        >
                            <div
                                class="h-10 w-10 shrink-0 overflow-hidden rounded"
                            >
                                <img
                                    v-if="isImage(file.mime)"
                                    :src="file.url"
                                    :alt="file.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-hospital-bg"
                                >
                                    <FileText
                                        class="h-5 w-5 text-hospital-text-3"
                                    />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <a
                                    :href="file.url"
                                    target="_blank"
                                    class="block truncate text-sm font-medium text-hospital-primary hover:underline"
                                >
                                    {{ file.name }}
                                </a>
                                <p class="text-xs text-hospital-text-3">
                                    {{ file.size }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transfer to Operation Modal -->
    <Modal v-model="showTransferModal" title="تحويل إلى عملية" size="md">
        <div v-if="transferringBooking" class="space-y-4">
            <p class="text-sm text-hospital-text-3">
                تحويل زيارة
                {{
                    deptLabels[transferringBooking.dept] ??
                    transferringBooking.dept
                }}
                — {{ transferringBooking.service_name }} إلى عملية.
            </p>
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-hospital-text-2"
                    >القسم</label
                >
                <select
                    v-model="transferForm.dept"
                    class="input-field w-full"
                    @change="onTransferDeptChange"
                >
                    <option value="surgery">العمليات</option>
                    <option value="lasik">الليزك</option>
                    <option value="laser">الليزر</option>
                </select>
            </div>
            <div>
                <label
                    class="mb-1 block text-xs font-semibold text-hospital-text-2"
                    >الخدمة</label
                >
                <select
                    v-model="transferForm.service_id"
                    class="input-field w-full"
                    :disabled="filteredTransferServices.length === 0"
                >
                    <option value="">— اختر الخدمة —</option>
                    <option
                        v-for="s in filteredTransferServices"
                        :key="s.id"
                        :value="s.id"
                    >
                        {{ s.name }}
                    </option>
                </select>
                <p
                    v-if="filteredTransferServices.length === 0"
                    class="mt-1 text-xs text-hospital-danger"
                >
                    لا توجد خدمات متاحة لهذا القسم
                </p>
            </div>
            <EyeSideSelector v-model="transferForm.eye" />
        </div>
        <template #footer>
            <button
                type="button"
                class="btn-secondary"
                @click="showTransferModal = false"
            >
                إلغاء
            </button>
            <button
                type="button"
                class="btn-primary"
                :disabled="!transferForm.service_id"
                @click="confirmTransfer"
            >
                متابعة إلى صفحة العمليات
            </button>
        </template>
    </Modal>
</template>
