<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Printer, ChevronLeft } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import Badge from '@/components/shared/Badge.vue';
import { usePrint } from '@/composables/usePrint';
import MedicationsTable from './Partials/MedicationsTable.vue';

interface MedicationRow {
    name: string;
    dose: string;
    route: string;
    frequency: string;
    remarks: string;
}

interface EyeSideValue {
    right: string;
    left: string;
}

interface NursingHistoryAnswer {
    key: string;
    answer: string;
    specify: string;
}

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
    patient_contact?: string;
    allergies_status?: 'none' | 'yes' | null;
    allergies_specify?: string;
    current_medications?: MedicationRow[];
    plan_medications?: MedicationRow[];
    visual_exam?: { uncorrected?: EyeSideValue; correction?: EyeSideValue };
    eye_exam_grid?: Record<string, EyeSideValue>;
    plan_education?: boolean | null;
    plan_followup?: string;
    nursing_assessment?: {
        bp_history?: string;
        bp_current?: string;
        diabetes_history?: string;
        diabetes_current?: string;
        diet_type?: string;
        diet_compliant?: string;
        smoking_status?: string;
        pain_severity?: string;
        eye_pain_nature?: string;
        eye_pain_frequency?: string;
        eye_pain_continuity?: string;
    };
    nursing_history_answers?: NursingHistoryAnswer[];
    fall_screening?: { dizziness?: number; pain_meds_today?: number; diabetes_meds?: number; gait?: number; walking_aid?: number };
    drops_given?: { drop?: string; time?: string }[];
    critical_results?: { result?: string; time?: string; amount?: string; received_by?: string }[];
    nursing_notes?: string;
    nurse_signature_name?: string;
    evaluator_name?: string;
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
    eye_side?: string;
    doctor?: { id: string; name: string };
    clinic_sheet?: ClinicSheet | null;
}

interface ReferralService {
    id: string;
    name: string;
    dept: string;
    one_eye_price?: string | number | null;
    both_eyes_price?: string | number | null;
}

const props = defineProps<{
    booking: Booking;
    history: HistoryItem[];
    referral_services: ReferralService[];
}>();

const { print } = usePrint();

const page = usePage<{ moduleStatus?: Record<string, boolean> }>();
const referralOptions: Record<string, string> = {
    labs: 'الفحوصات', surgery: 'العمليات', lasik: 'الليزك', laser: 'الليزر', pentacam: 'البنتكام',
};
const availableReferralOptions = computed(() => {
    const moduleStatus = (page.props.moduleStatus as Record<string, boolean>) ?? {};

    return Object.fromEntries(Object.entries(referralOptions).filter(([key]) => moduleStatus[key] !== false));
});

const sheet = props.booking.clinic_sheet;

function emptyMedicationRows(rows?: MedicationRow[]): MedicationRow[] {
    return rows && rows.length > 0 ? rows : [{ name: '', dose: '', route: '', frequency: '', remarks: '' }];
}

const eyeExamRows = [
    { key: 'cornea', label: 'القرنية (Cornea)' },
    { key: 'iris', label: 'القزحية (Iris)' },
    { key: 'lens', label: 'العدسة (Lens)' },
    { key: 'vitreous', label: 'الجسم الزجاجي (Vitreous)' },
    { key: 'retina', label: 'الشبكية (Retina)' },
];

const nursingHistoryQuestions = [
    { key: 'fever_sweating_weight_loss', label: 'ارتفاع مزمن بدرجات الحرارة – العرق – فقد الوزن؟', specify: false },
    { key: 'drug_food_allergy', label: 'حساسية من أي عقار طبي أو أي غذاء؟', specify: true },
    { key: 'gi_disorders', label: 'اضطرابات مزمنة بالجهاز الهضمي أو فقدان/زيادة وزن في وقت قصير؟', specify: false },
    { key: 'thyroid_pituitary', label: 'اختلال بالغدة الدرقية أو الغدة النخامية؟', specify: false },
    { key: 'heart_disease', label: 'أمراض القلب؟', specify: true },
    { key: 'chronic_skin_disease', label: 'أمراض جلدية مزمنة (صدفية - إكزيما - غيرها)؟', specify: true },
    { key: 'joint_muscle_pain', label: 'آلام بالمفاصل أو بالعضلات؟', specify: false },
    { key: 'liver_disease', label: 'أمراض بالكبد أو علاج سابق بالإنترفيرون؟', specify: false },
    { key: 'hearing_tinnitus', label: 'ضعف بالسمع أو طنين مزمن بالأذن؟', specify: false },
    { key: 'kidney_disease', label: 'أمراض بالكلى أو غسيل كلوي سابق؟', specify: false },
    { key: 'hospital_quarantine', label: 'حجز سابق بأي من مستشفيات الحميات؟', specify: true },
    { key: 'chest_disease', label: 'أمراض الصدر؟', specify: false },
    { key: 'radio_chemo_therapy', label: 'علاج إشعاعي أو كيماوي سابق؟', specify: true },
    { key: 'animal_contact', label: 'تربية حيوانات بالمنزل أو اختلاط مباشر بالعمل؟', specify: false },
    { key: 'anemia', label: 'أنيميا؟', specify: false },
    { key: 'cortisone_treatment', label: 'علاج بالكورتيزون لفترات طويلة؟', specify: true },
    { key: 'nervous_spinal', label: 'التهابات بالأعصاب أو الفقرات؟', specify: false },
    { key: 'respiratory_symptoms', label: 'سعال وبلغم - طفح جلدي - إفرازات بالعين؟', specify: false },
];

interface FormShape {
    booking_id: string;
    doctor_id: string | null;
    chief_complaint: string;
    visual_acuity_od: string;
    visual_acuity_os: string;
    iop_od: number | string;
    iop_os: number | string;
    anterior_segment: string;
    posterior_segment: string;
    diagnosis: string;
    plan: string;
    referral_to: string;
    notes: string;
    patient_contact: string;
    allergies_status: string;
    allergies_specify: string;
    current_medications: MedicationRow[];
    plan_medications: MedicationRow[];
    visual_exam: { uncorrected: EyeSideValue; correction: EyeSideValue };
    eye_exam_grid: Record<string, EyeSideValue>;
    plan_education: boolean | null;
    plan_followup: string;
    nursing_assessment: {
        bp_history: string;
        bp_current: string;
        diabetes_history: string;
        diabetes_current: string;
        diet_type: string;
        diet_compliant: string;
        smoking_status: string;
        pain_severity: string;
        eye_pain_nature: string;
        eye_pain_frequency: string;
        eye_pain_continuity: string;
    };
    nursing_history_answers: NursingHistoryAnswer[];
    fall_screening: Record<string, number>;
    drops_given: { drop?: string; time?: string }[];
    critical_results: { result?: string; time?: string; amount?: string; received_by?: string }[];
    nursing_notes: string;
    nurse_signature_name: string;
    evaluator_name: string;
}

const form = reactive<FormShape>({
    booking_id:        props.booking.id,
    doctor_id:         props.booking.doctor?.id ?? null,
    chief_complaint:   sheet?.chief_complaint ?? '',
    visual_acuity_od:  sheet?.visual_acuity_od ?? '',
    visual_acuity_os:  sheet?.visual_acuity_os ?? '',
    iop_od:            sheet?.iop_od ?? '',
    iop_os:            sheet?.iop_os ?? '',
    anterior_segment:  sheet?.anterior_segment ?? '',
    posterior_segment: sheet?.posterior_segment ?? '',
    diagnosis:         sheet?.diagnosis ?? '',
    plan:              sheet?.plan ?? '',
    referral_to:       sheet?.referral_to ?? '',
    notes:             sheet?.notes ?? '',

    // Initial Medical Assessment
    patient_contact:   sheet?.patient_contact ?? props.booking.patient_phone ?? '',
    allergies_status:  sheet?.allergies_status ?? '',
    allergies_specify: sheet?.allergies_specify ?? '',
    current_medications: emptyMedicationRows(sheet?.current_medications),
    plan_medications:    emptyMedicationRows(sheet?.plan_medications),
    visual_exam: {
        uncorrected: { right: sheet?.visual_exam?.uncorrected?.right ?? '', left: sheet?.visual_exam?.uncorrected?.left ?? '' },
        correction:  { right: sheet?.visual_exam?.correction?.right ?? '', left: sheet?.visual_exam?.correction?.left ?? '' },
    },
    eye_exam_grid: Object.fromEntries(
        eyeExamRows.map((row) => [row.key, {
            right: sheet?.eye_exam_grid?.[row.key]?.right ?? '',
            left: sheet?.eye_exam_grid?.[row.key]?.left ?? '',
        }]),
    ),
    plan_education: sheet?.plan_education ?? null,
    plan_followup:  sheet?.plan_followup ?? '',

    // Initial Nursing Assessment
    nursing_assessment: {
        bp_history: sheet?.nursing_assessment?.bp_history ?? '',
        bp_current: sheet?.nursing_assessment?.bp_current ?? '',
        diabetes_history: sheet?.nursing_assessment?.diabetes_history ?? '',
        diabetes_current: sheet?.nursing_assessment?.diabetes_current ?? '',
        diet_type: sheet?.nursing_assessment?.diet_type ?? '',
        diet_compliant: sheet?.nursing_assessment?.diet_compliant ?? '',
        smoking_status: sheet?.nursing_assessment?.smoking_status ?? '',
        pain_severity: sheet?.nursing_assessment?.pain_severity ?? '',
        eye_pain_nature: sheet?.nursing_assessment?.eye_pain_nature ?? '',
        eye_pain_frequency: sheet?.nursing_assessment?.eye_pain_frequency ?? '',
        eye_pain_continuity: sheet?.nursing_assessment?.eye_pain_continuity ?? '',
    },
    nursing_history_answers: nursingHistoryQuestions.map((q) => {
        const existing = sheet?.nursing_history_answers?.find((a) => a.key === q.key);

        return { key: q.key, answer: existing?.answer ?? '', specify: existing?.specify ?? '' };
    }),
    fall_screening: {
        dizziness: sheet?.fall_screening?.dizziness ?? 0,
        pain_meds_today: sheet?.fall_screening?.pain_meds_today ?? 0,
        diabetes_meds: sheet?.fall_screening?.diabetes_meds ?? 0,
        gait: sheet?.fall_screening?.gait ?? 0,
        walking_aid: sheet?.fall_screening?.walking_aid ?? 0,
    },
    drops_given: sheet?.drops_given ?? [],
    critical_results: sheet?.critical_results ?? [],
    nursing_notes: sheet?.nursing_notes ?? '',
    nurse_signature_name: sheet?.nurse_signature_name ?? '',
    evaluator_name: sheet?.evaluator_name ?? '',
});

const fallScreeningTotal = computed(() =>
    Object.values(form.fall_screening as Record<string, number>).reduce((sum, v) => sum + Number(v || 0), 0),
);

const activeSection = ref<'medical' | 'nursing'>('medical');

const saving = ref(false);

function saveSheet() {
    saving.value = true;
    router.post(`/clinic/${props.booking.id}/sheet`, form as unknown as Record<string, string>, {
        onFinish: () => {
 saving.value = false;
},
    });
}

// ── Patient routing (triage) ──
const routingTarget = ref('');
const routingServiceId = ref('');
const routingEye = ref(props.booking.eye_side ?? '');
const createFollowUp = ref(true);
const routing = ref(false);

const routingOptions = computed(() =>
    Object.fromEntries(Object.entries(availableReferralOptions.value).filter(([key]) => key !== props.booking.dept)),
);

const eyeOptions: Record<string, string> = {
    OD: 'العين اليمنى', OS: 'العين اليسرى', OU: 'كلتا العينين',
};

/** Services of the chosen destination only — a service can't cross departments. */
const routingServices = computed(() =>
    props.referral_services.filter((s) => s.dept === routingTarget.value),
);

const operationDepts = ['surgery', 'lasik', 'laser'];

/** Clear a service that no longer belongs to the newly chosen destination. */
function onRoutingTargetChange() {
    if (!routingServices.value.some((s) => s.id === routingServiceId.value)) {
        routingServiceId.value = '';
    }
}

/** Operations are priced per eye, so the side is mandatory for them. */
const eyeRequired = computed(
    () => !!routingServiceId.value && operationDepts.includes(routingTarget.value),
);

const canRoute = computed(
    () => !!routingTarget.value && !routing.value && (!eyeRequired.value || !!routingEye.value),
);

/**
 * Mirrors the server-side pricing rule (ServicePricingService): both-eyes
 * price when OU, one-eye price otherwise — shown so the doctor sees what the
 * referral will cost before creating the follow-up booking.
 */
const routingPrice = computed<number | null>(() => {
    const service = routingServices.value.find((s) => s.id === routingServiceId.value);

    if (!service) {
        return null;
    }

    const oneEye = service.one_eye_price != null ? Number(service.one_eye_price) : null;

    if (routingEye.value === 'OU') {
        return service.both_eyes_price != null
            ? Number(service.both_eyes_price)
            : oneEye != null ? oneEye * 2 : null;
    }

    return oneEye;
});

function routePatient() {
    if (!routingTarget.value) {
        return;
    }

    routing.value = true;
    router.post(
        `/clinic/${props.booking.id}/refer`,
        {
            referral_to: routingTarget.value,
            create_follow_up: createFollowUp.value,
            service_id: routingServiceId.value || null,
            eye_side: routingEye.value || null,
        },
        {
            onSuccess: () => {
                form.referral_to = routingTarget.value;
                routingTarget.value = '';
                routingServiceId.value = '';
                routingEye.value = props.booking.eye_side ?? '';
            },
            onFinish: () => {
                routing.value = false;
            },
        },
    );
}

const deptLabels: Record<string, string> = {
    clinic: 'العيادة', labs: 'الفحوصات', surgery: 'العمليات', lasik: 'الليزك', laser: 'الليزر', pentacam: 'البنتكام',
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
            <h3 class="mb-3 font-bold text-hospital-primary">ورقة الكشف الطبي</h3>

            <!-- Section Tabs -->
            <div class="mb-5 flex gap-2 border-b border-hospital-border">
                <button
                    type="button"
                    class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                    :class="activeSection === 'medical' ? 'border-hospital-primary text-hospital-primary' : 'border-transparent text-hospital-text-2 hover:text-hospital-text'"
                    @click="activeSection = 'medical'"
                >
                    تقييم طبي أولي
                </button>
                <button
                    type="button"
                    class="border-b-2 px-3 py-2 text-sm font-medium transition-colors"
                    :class="activeSection === 'nursing' ? 'border-hospital-primary text-hospital-primary' : 'border-transparent text-hospital-text-2 hover:text-hospital-text'"
                    @click="activeSection = 'nursing'"
                >
                    تقييم تمريضي أولي
                </button>
            </div>

            <form class="space-y-5" @submit.prevent="saveSheet">
                <div v-show="activeSection === 'medical'" class="space-y-5">
                <!-- Contact + Allergies -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">رقم التواصل</label>
                        <input v-model="form.patient_contact" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">الحساسية (Allergies)</label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 text-xs">
                                <input v-model="form.allergies_status" type="radio" value="none" />
                                لا يوجد
                            </label>
                            <label class="flex items-center gap-1.5 text-xs">
                                <input v-model="form.allergies_status" type="radio" value="yes" />
                                يوجد
                            </label>
                            <input
                                v-if="form.allergies_status === 'yes'"
                                v-model="form.allergies_specify"
                                type="text"
                                placeholder="حدد النوع"
                                class="flex-1 rounded-lg border border-hospital-border bg-hospital-bg px-3 py-1.5 text-sm focus:border-hospital-primary focus:outline-none"
                            />
                        </div>
                    </div>
                </div>

                <!-- Chief Complaint -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الشكوى الرئيسية</label>
                    <textarea v-model="form.chief_complaint" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none resize-none" />
                </div>

                <!-- Current Medications -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الأدوية الحالية (Current Medications)</label>
                    <MedicationsTable v-model="form.current_medications" />
                </div>

                <!-- Visual Examination -->
                <div>
                    <label class="mb-2 block text-xs font-medium text-hospital-text-2">Visual Examination</label>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-[11px] text-hospital-text-3">Uncorrected — Right</label>
                            <input v-model="form.visual_exam.uncorrected.right" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] text-hospital-text-3">Uncorrected — Left</label>
                            <input v-model="form.visual_exam.uncorrected.left" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] text-hospital-text-3">Correction — Right</label>
                            <input v-model="form.visual_exam.correction.right" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-[11px] text-hospital-text-3">Correction — Left</label>
                            <input v-model="form.visual_exam.correction.left" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                    </div>
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

                <!-- Eye Examination Grid -->
                <div>
                    <label class="mb-2 block text-xs font-medium text-hospital-text-2">Eye Examination</label>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[420px] border-collapse text-xs">
                            <thead>
                                <tr class="text-hospital-text-2">
                                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right"></th>
                                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">Right</th>
                                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">Left</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in eyeExamRows" :key="row.key">
                                    <td class="border border-hospital-border p-1.5 font-medium text-hospital-text-2">{{ row.label }}</td>
                                    <td class="border border-hospital-border p-1">
                                        <input v-model="form.eye_exam_grid[row.key].right" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" />
                                    </td>
                                    <td class="border border-hospital-border p-1">
                                        <input v-model="form.eye_exam_grid[row.key].left" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Plan Medications -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">أدوية الخطة العلاجية (Medications)</label>
                    <MedicationsTable v-model="form.plan_medications" />
                </div>

                <!-- Plan: Education / Follow-up -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">تثقيف المريض (Education)</label>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 text-xs">
                                <input :checked="form.plan_education === false" type="radio" @change="form.plan_education = false" />
                                لا
                            </label>
                            <label class="flex items-center gap-1.5 text-xs">
                                <input :checked="form.plan_education === true" type="radio" @change="form.plan_education = true" />
                                نعم
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">متابعة (Follow-up)</label>
                        <input v-model="form.plan_followup" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
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
                            <option v-for="(label, key) in availableReferralOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">ملاحظات</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                    </div>
                </div>
                </div>

                <!-- ═══════════ Nursing Section ═══════════ -->
                <div v-show="activeSection === 'nursing'" class="space-y-5">
                    <!-- Vitals / History -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-hospital-border p-3">
                            <p class="mb-2 text-xs font-semibold text-hospital-text">ضغط الدم</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="form.nursing_assessment.bp_history" type="text" placeholder="مدة المرض (عام)" class="rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1.5 text-xs focus:border-hospital-primary focus:outline-none" />
                                <input v-model="form.nursing_assessment.bp_current" type="text" placeholder="القياس الحالي" class="rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1.5 text-xs focus:border-hospital-primary focus:outline-none" />
                            </div>
                        </div>
                        <div class="rounded-lg border border-hospital-border p-3">
                            <p class="mb-2 text-xs font-semibold text-hospital-text">السكر</p>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="form.nursing_assessment.diabetes_history" type="text" placeholder="مدة المرض (عام)" class="rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1.5 text-xs focus:border-hospital-primary focus:outline-none" />
                                <input v-model="form.nursing_assessment.diabetes_current" type="text" placeholder="القياس الحالي" class="rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1.5 text-xs focus:border-hospital-primary focus:outline-none" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">نظام غذائي معين</label>
                            <select v-model="form.nursing_assessment.diet_type" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                                <option value="">— غير محدد —</option>
                                <option value="normal">طبيعي</option>
                                <option value="diabetic">سكري</option>
                                <option value="low_salt">قليل الملح</option>
                                <option value="low_fat">قليل الدهون</option>
                                <option value="renal">خاص بمرضى الكلى</option>
                                <option value="other">أخرى</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">منتظم على النظام الغذائي</label>
                            <div class="flex items-center gap-4 pt-2">
                                <label class="flex items-center gap-1.5 text-xs">
                                    <input v-model="form.nursing_assessment.diet_compliant" type="radio" value="yes" />
                                    نعم
                                </label>
                                <label class="flex items-center gap-1.5 text-xs">
                                    <input v-model="form.nursing_assessment.diet_compliant" type="radio" value="no" />
                                    لا
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">التدخين</label>
                            <select v-model="form.nursing_assessment.smoking_status" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                                <option value="">— غير محدد —</option>
                                <option value="no">لا</option>
                                <option value="yes">نعم</option>
                                <option value="quit">أقلعت عنها</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">تقييم الألم</label>
                            <select v-model="form.nursing_assessment.pain_severity" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                                <option value="">— غير محدد —</option>
                                <option value="none">لا يوجد</option>
                                <option value="mild">بسيط</option>
                                <option value="moderate">متوسط</option>
                                <option value="severe">شديد</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.nursing_assessment.pain_severity && form.nursing_assessment.pain_severity !== 'none'" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">طبيعة ألم العين</label>
                            <input v-model="form.nursing_assessment.eye_pain_nature" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">التكرار</label>
                            <input v-model="form.nursing_assessment.eye_pain_frequency" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">الاستمرارية</label>
                            <input v-model="form.nursing_assessment.eye_pain_continuity" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                        </div>
                    </div>

                    <!-- History Questionnaire -->
                    <div>
                        <label class="mb-2 block text-xs font-medium text-hospital-text-2">التاريخ المرضي</label>
                        <div class="divide-y divide-hospital-border rounded-lg border border-hospital-border">
                            <div
                                v-for="(q, index) in nursingHistoryQuestions"
                                :key="q.key"
                                class="flex flex-wrap items-center gap-3 p-2.5"
                            >
                                <span class="min-w-0 flex-1 text-xs text-hospital-text">{{ q.label }}</span>
                                <label class="flex items-center gap-1 text-xs">
                                    <input v-model="(form.nursing_history_answers as NursingHistoryAnswer[])[index].answer" type="radio" value="yes" />
                                    نعم
                                </label>
                                <label class="flex items-center gap-1 text-xs">
                                    <input v-model="(form.nursing_history_answers as NursingHistoryAnswer[])[index].answer" type="radio" value="no" />
                                    لا
                                </label>
                                <input
                                    v-if="q.specify && (form.nursing_history_answers as NursingHistoryAnswer[])[index].answer === 'yes'"
                                    v-model="(form.nursing_history_answers as NursingHistoryAnswer[])[index].specify"
                                    type="text"
                                    placeholder="اذكرها"
                                    class="w-40 rounded-lg border border-hospital-border bg-hospital-bg px-2 py-1 text-xs focus:border-hospital-primary focus:outline-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Fall Screening -->
                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <label class="block text-xs font-medium text-hospital-text-2">Fall Screening</label>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                                :class="fallScreeningTotal >= 25 ? 'bg-hospital-danger/15 text-hospital-danger' : fallScreeningTotal >= 20 ? 'bg-amber-100 text-amber-700' : 'bg-hospital-success/15 text-hospital-success'"
                            >
                                النتيجة: {{ fallScreeningTotal }} / 25
                            </span>
                        </div>
                        <div class="space-y-2 rounded-lg border border-hospital-border p-3">
                            <div v-for="item in [
                                { key: 'dizziness', label: 'هل تشعر بدوخة؟' },
                                { key: 'pain_meds_today', label: 'هل أخذت اليوم أي أدوية للألم أو التشنجات؟' },
                                { key: 'diabetes_meds', label: 'هل تأخذ دواء السكر حتى آخر جرعة؟' },
                                { key: 'gait', label: 'طريقة مشي المريض غير طبيعية؟' },
                                { key: 'walking_aid', label: 'هل تستعمل أدوات مساعدة للمشي؟' },
                            ]" :key="item.key" class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs text-hospital-text">{{ item.label }}</span>
                                <div class="flex items-center gap-3">
                                    <label class="flex items-center gap-1 text-xs">
                                        <input
                                            :checked="(form.fall_screening as Record<string, number>)[item.key] === 0"
                                            type="radio"
                                            @change="(form.fall_screening as Record<string, number>)[item.key] = 0"
                                        />
                                        لا (0)
                                    </label>
                                    <label class="flex items-center gap-1 text-xs">
                                        <input
                                            :checked="(form.fall_screening as Record<string, number>)[item.key] === 5"
                                            type="radio"
                                            @change="(form.fall_screening as Record<string, number>)[item.key] = 5"
                                        />
                                        نعم (5)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <p class="mt-2 text-[11px] text-hospital-text-3">
                            نتيجة ≥ 25: وضع المريض على كرسي متحرك — نتيجة ≥ 20: وضع المريض تحت الملاحظة بالقرب من كاونتر العيادات.
                        </p>
                    </div>

                    <!-- Notes + Signatures -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-hospital-text-2">ملاحظات التمريض</label>
                            <textarea v-model="form.nursing_notes" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none resize-none" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-hospital-text-2">توقيع الممرض</label>
                                <input v-model="form.nurse_signature_name" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-hospital-text-2">القائم بالتقييم</label>
                                <input v-model="form.evaluator_name" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" />
                            </div>
                        </div>
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

            <!-- Patient Routing (Triage) -->
            <div class="mt-6 rounded-lg border border-hospital-border bg-hospital-bg p-4">
                <h4 class="mb-1 font-bold text-hospital-text">توجيه المريض</h4>
                <p class="mb-3 text-xs text-hospital-text-3">
                    وجّه المريض إلى الوجهة التالية بعد الكشف — يُرحَّل التشخيص وحدة الإبصار وضغط العين مع الحجز الجديد
                    <span v-if="form.referral_to">
                        — الوجهة الحالية: <strong>{{ availableReferralOptions[form.referral_to as string] ?? form.referral_to }}</strong>
                    </span>
                </p>
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">الوجهة</label>
                        <select v-model="routingTarget" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="onRoutingTargetChange">
                            <option value="">— اختر وجهة —</option>
                            <option v-for="(label, key) in routingOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">الخدمة</label>
                        <select
                            v-model="routingServiceId"
                            :disabled="!routingTarget || routingServices.length === 0"
                            class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none disabled:opacity-60"
                        >
                            <option value="">— بدون خدمة —</option>
                            <option v-for="s in routingServices" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="routingTarget && routingServices.length === 0" class="mt-1 text-xs text-hospital-text-3">
                            لا توجد خدمات مفعّلة في هذا القسم
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-hospital-text-2">
                            العين <span v-if="eyeRequired" class="text-hospital-danger">*</span>
                        </label>
                        <select v-model="routingEye" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                            <option value="">— غير محدد —</option>
                            <option v-for="(label, key) in eyeOptions" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div v-if="routingPrice !== null" class="pb-2 text-xs text-hospital-text-2">
                        السعر: <strong class="text-hospital-primary">{{ routingPrice }}</strong>
                    </div>
                    <label class="flex items-center gap-2 text-xs text-hospital-text-2">
                        <input v-model="createFollowUp" type="checkbox" class="rounded border-hospital-border" />
                        إنشاء حجز متابعة تلقائيًا لنفس اليوم
                    </label>
                    <button
                        type="button"
                        :disabled="!canRoute"
                        class="rounded-lg bg-hospital-accent px-4 py-2 text-sm font-semibold text-white hover:opacity-90 disabled:opacity-50 transition-opacity"
                        @click="routePatient"
                    >
                        {{ routing ? 'جارٍ التوجيه…' : 'توجيه' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
