<script setup lang="ts">
import { reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Printer, ChevronLeft } from 'lucide-vue-next';
import Badge from '@/components/shared/Badge.vue';
import { usePrint } from '@/composables/usePrint';

interface ClinicSheet {
    id: string;
    chief_complaint?: string;
    visual_acuity_od?: string;
    visual_acuity_os?: string;
    iop_od?: number;
    iop_os?: number;
    anterior_segment?: string;
    posterior_segment?: string;
    diagnosis?: string;
    plan?: string;
    referral_to?: string;
    notes?: string;
    recorded_at?: string;
}

interface HistoryItem {
    id: string;
    booking: { file_no: string; visit_date: string; dept: string };
    doctor?: { name: string };
    diagnosis?: string;
    plan?: string;
    recorded_at: string;
}

interface Booking {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    patient_age?: number;
    dept: string;
    visit_date: string;
    status: string;
    pay_status: string;
    doctor?: { name: string };
    clinic_sheet?: ClinicSheet | null;
}

const props = defineProps<{
    booking: Booking;
    history: HistoryItem[];
}>();

const { print } = usePrint();

const form = reactive<Record<string, string | number | null>>({
    booking_id:        props.booking.id,
    doctor_id:         props.booking.doctor?.id ?? null,
    chief_complaint:   props.booking.clinic_sheet?.chief_complaint ?? '',
    visual_acuity_od:  props.booking.clinic_sheet?.visual_acuity_od ?? '',
    visual_acuity_os:  props.booking.clinic_sheet?.visual_acuity_os ?? '',
    iop_od:            props.booking.clinic_sheet?.iop_od ?? '',
    iop_os:            props.booking.clinic_sheet?.iop_os ?? '',
    anterior_segment:  props.booking.clinic_sheet?.anterior_segment ?? '',
    posterior_segment: props.booking.clinic_sheet?.posterior_segment ?? '',
    diagnosis:         props.booking.clinic_sheet?.diagnosis ?? '',
    plan:              props.booking.clinic_sheet?.plan ?? '',
    referral_to:       props.booking.clinic_sheet?.referral_to ?? '',
    notes:             props.booking.clinic_sheet?.notes ?? '',
});

const saving = ref(false);

function saveSheet() {
    saving.value = true;
    router.post(`/clinic/${props.booking.id}/sheet`, form as Record<string, unknown>, {
        onFinish: () => { saving.value = false; },
    });
}

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات', lasik: 'الليزك', laser: 'الليزر',
};
</script>

<template>
    <Head :title="`ملف ${booking.patient_name}`" />

    <!-- Back + Print -->
    <div class="mb-4 flex items-center justify-between">
        <a href="/clinic" class="flex items-center gap-1 text-sm font-medium text-hospital-primary hover:underline">
            <ChevronLeft class="h-4 w-4" />
            العودة للقائمة
        </a>
        <button type="button" class="flex items-center gap-2 rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg" @click="print">
            <Printer class="h-4 w-4" />
            طباعة الكشف
        </button>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Patient Summary Card -->
        <div class="rounded-xl border border-hospital-border bg-hospital-surface p-5 shadow-sm">
            <h3 class="mb-3 font-bold text-hospital-primary">بيانات المريض</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-hospital-text-2">الاسم:</dt>
                    <dd class="font-medium text-hospital-text">{{ booking.patient_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-hospital-text-2">رقم الملف:</dt>
                    <dd class="font-bold text-hospital-primary">{{ booking.file_no }}</dd>
                </div>
                <div v-if="booking.patient_phone" class="flex justify-between">
                    <dt class="text-hospital-text-2">الهاتف:</dt>
                    <dd>{{ booking.patient_phone }}</dd>
                </div>
                <div v-if="booking.patient_age" class="flex justify-between">
                    <dt class="text-hospital-text-2">العمر:</dt>
                    <dd>{{ booking.patient_age }} سنة</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-hospital-text-2">الحالة:</dt>
                    <dd><Badge :variant="booking.status as any" /></dd>
                </div>
            </dl>

            <!-- Visit History -->
            <h4 class="mb-2 mt-5 font-bold text-hospital-text">سجل الزيارات السابقة</h4>
            <div v-if="history.length === 0" class="text-xs text-hospital-text-3">لا توجد زيارات سابقة</div>
            <div v-else class="space-y-2">
                <div
                    v-for="h in history"
                    :key="h.id"
                    class="rounded-lg border border-hospital-border p-3 text-xs hover:bg-hospital-bg"
                >
                    <div class="flex justify-between font-semibold text-hospital-primary">
                        <span>{{ h.booking?.visit_date }}</span>
                        <span>{{ deptLabels[h.booking?.dept] ?? h.booking?.dept }}</span>
                    </div>
                    <p v-if="h.diagnosis" class="mt-1 line-clamp-2 text-hospital-text-2">{{ h.diagnosis }}</p>
                </div>
            </div>
        </div>

        <!-- Clinic Sheet Form -->
        <div class="lg:col-span-2 rounded-xl border border-hospital-border bg-hospital-surface p-5 shadow-sm">
            <h3 class="mb-5 font-bold text-hospital-primary">ورقة الكشف الطبي</h3>

            <form class="space-y-5" @submit.prevent="saveSheet">
                <!-- Chief Complaint -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الشكوى الرئيسية</label>
                    <textarea v-model="form.chief_complaint" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none resize-none" />
                </div>

                <!-- Visual Acuity + IOP -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">حدة الإبصار OD</label>
                        <input v-model="form.visual_acuity_od" type="text" placeholder="6/60" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">حدة الإبصار OS</label>
                        <input v-model="form.visual_acuity_os" type="text" placeholder="6/60" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">ضغط العين OD (mmHg)</label>
                        <input v-model="form.iop_od" type="number" step="0.1" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">ضغط العين OS (mmHg)</label>
                        <input v-model="form.iop_os" type="number" step="0.1" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    </div>
                </div>

                <!-- Segments -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">فحص المصباح الشقي</label>
                        <textarea v-model="form.anterior_segment" rows="3" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">فحص قاع العين</label>
                        <textarea v-model="form.posterior_segment" rows="3" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                </div>

                <!-- Diagnosis + Plan -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">التشخيص</label>
                        <textarea v-model="form.diagnosis" rows="3" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">الخطة العلاجية</label>
                        <textarea v-model="form.plan" rows="3" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                </div>

                <!-- Referral + Notes -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">إحالة إلى</label>
                        <select v-model="form.referral_to" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                            <option value="">— بدون إحالة —</option>
                            <option value="labs">الفحوصات</option>
                            <option value="surgery">العمليات</option>
                            <option value="lasik">الليزك</option>
                            <option value="laser">الليزر</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">ملاحظات</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-lg bg-hospital-primary px-6 py-2.5 text-sm font-semibold text-white hover:bg-hospital-primary-light disabled:opacity-60 transition-colors"
                    >
                        {{ saving ? 'جارٍ الحفظ…' : 'حفظ الكشف الطبي' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
