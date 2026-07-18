<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FileNoBarcode from '@/components/booking/FileNoBarcode.vue';
import {
    User,
    Phone,
    Calendar,
    Stethoscope,
    FlaskConical,
    Scissors,
    FileText,
    Paperclip,
} from 'lucide-vue-next';
import { archive } from '@/routes';

interface Patient {
    name: string;
    phone?: string;
    age?: number;
    file_no: string;
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
    visit_date: string;
    visit_time?: string;
    price: number;
    pay_status: string;
    status: string;
    visit_note?: string;
    doctor?: { name: string };
    clinic_sheet?: ClinicSheet;
    diagnostic_results?: DiagnosticResult[];
    surgery?: Surgery;
    media_files: MediaFile[];
}

defineProps<{
    file_no: string;
    patient: Patient | null;
    bookings: Booking[];
}>();

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const deptIcons: Record<string, unknown> = {
    clinic: Stethoscope,
    labs: FlaskConical,
    surgery: Scissors,
    lasik: Scissors,
    laser: Scissors,
};

const payStatusColors: Record<string, string> = {
    paid: 'bg-hospital-success/10 text-hospital-success',
    partial: 'bg-yellow-50 text-yellow-600',
    unpaid: 'bg-hospital-danger/10 text-hospital-danger',
};
const payStatusLabels: Record<string, string> = {
    paid: 'مسدد',
    partial: 'جزئي',
    unpaid: 'غير مسدد',
};

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
function fmtDate(d: string) {
    return new Date(d).toLocaleDateString('ar-EG', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}
function isImage(mime: string): boolean {
    return mime.startsWith('image/');
}
</script>

<template>
    <Head :title="`ملف المريض — ${file_no}`" />

    <!-- Header -->
    <div class="mb-5 flex items-center justify-between">
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
                <p class="text-sm text-hospital-muted">
                    رقم الملف: {{ file_no }}
                </p>
                <div class="mt-3">
                    <FileNoBarcode :value="file_no" />
                </div>
            </div>
        </div>
        <Link
            :href="archive().url"
            class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text transition-colors hover:bg-hospital-bg"
        >
            العودة للأرشيف
        </Link>
    </div>

    <!-- Patient Info Card -->
    <div v-if="patient" class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <User class="h-4 w-4 text-hospital-muted" />
            <div>
                <p class="text-xs text-hospital-muted">الاسم</p>
                <p class="text-sm font-medium">{{ patient.name }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <Phone class="h-4 w-4 text-hospital-muted" />
            <div>
                <p class="text-xs text-hospital-muted">الهاتف</p>
                <p class="text-sm font-medium">{{ patient.phone ?? '—' }}</p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <Calendar class="h-4 w-4 text-hospital-muted" />
            <div>
                <p class="text-xs text-hospital-muted">العمر</p>
                <p class="text-sm font-medium">
                    {{ patient.age ? `${patient.age} سنة` : '—' }}
                </p>
            </div>
        </div>
        <div
            class="flex items-center gap-2 rounded-lg border border-hospital-border bg-white p-3"
        >
            <FileText class="h-4 w-4 text-hospital-muted" />
            <div>
                <p class="text-xs text-hospital-muted">عدد الزيارات</p>
                <p class="text-sm font-medium">{{ bookings.length }} زيارة</p>
            </div>
        </div>
    </div>

    <!-- No bookings -->
    <div
        v-if="bookings.length === 0"
        class="rounded-xl border border-hospital-border bg-white p-8 text-center text-hospital-muted"
    >
        لا توجد زيارات مسجلة لهذا الملف
    </div>

    <!-- Visit Timeline -->
    <div class="space-y-4">
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
                    <span class="text-sm text-hospital-muted"
                        >— {{ booking.service_name ?? '—' }}</span
                    >
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="
                            payStatusColors[booking.pay_status] ??
                            'bg-hospital-muted/20 text-hospital-muted'
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
                    <span class="text-xs text-hospital-muted">{{
                        fmtDate(booking.visit_date)
                    }}</span>
                </div>
            </div>

            <div class="space-y-3 p-4">
                <!-- Doctor -->
                <div v-if="booking.doctor" class="text-sm">
                    <span class="text-hospital-muted">الطبيب: </span>
                    <span class="font-medium">{{ booking.doctor.name }}</span>
                </div>

                <!-- Visit note -->
                <div v-if="booking.visit_note" class="text-sm">
                    <span class="text-hospital-muted">ملاحظات: </span>
                    <span>{{ booking.visit_note }}</span>
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
                        <span class="text-hospital-muted">الشكوى: </span
                        >{{ booking.clinic_sheet.chief_complaint }}
                    </div>
                    <div
                        v-if="
                            booking.clinic_sheet.visual_acuity_od ||
                            booking.clinic_sheet.visual_acuity_os
                        "
                    >
                        <span class="text-hospital-muted">حدة الإبصار: </span>
                        OD {{ booking.clinic_sheet.visual_acuity_od ?? '—' }} /
                        OS {{ booking.clinic_sheet.visual_acuity_os ?? '—' }}
                    </div>
                    <div v-if="booking.clinic_sheet.diagnosis">
                        <span class="text-hospital-muted">التشخيص: </span
                        >{{ booking.clinic_sheet.diagnosis }}
                    </div>
                    <div v-if="booking.clinic_sheet.plan">
                        <span class="text-hospital-muted">خطة العلاج: </span
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
                            <span class="text-xs text-hospital-muted">{{
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
                        <span class="text-hospital-muted">الإجراء: </span
                        >{{ booking.surgery.procedure }}
                        <span
                            v-if="booking.surgery.eye"
                            class="text-hospital-muted"
                        >
                            ({{ booking.surgery.eye }})</span
                        >
                    </div>
                    <div v-if="booking.surgery.op_report">
                        <span class="text-hospital-muted">تقرير العملية: </span
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
                                        class="h-5 w-5 text-hospital-muted"
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
                                <p class="text-xs text-hospital-muted">
                                    {{ file.size }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
