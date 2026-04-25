<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AnalysisFields from './AnalysisFields.vue';
import BedPicker from './BedPicker.vue';
import BookingSummary from './BookingSummary.vue';
import DepartmentSelector from './DepartmentSelector.vue';
import EyeSideSelector from './EyeSideSelector.vue';
import FormFooter from './FormFooter.vue';
import InvoicePreview from './InvoicePreview.vue';
import PatientFields from './PatientFields.vue';
import PaymentFields from './PaymentFields.vue';
import ServiceSelect from './ServiceSelect.vue';
import StatusSelector from './StatusSelector.vue';

interface Service {
    id: string;
    name: string;
    dept: string;
    price: number;
    ins_price: number;
}

interface Doctor {
    id: string;
    name: string;
    is_active: boolean;
}

interface InsuranceCompany {
    id: string;
    name: string;
}

interface PriceListItem {
    service_id: string;
    price: number;
}

interface PriceList {
    id: string;
    name: string;
    ins_company_id: string;
    ins_coverage: number;
    items: PriceListItem[];
}

interface OrBed {
    id: number;
    bed_number: number;
    status: string;
    surgery?: { id: string; status: string } | null;
}

interface OrRoom {
    id: number;
    name: string;
    beds: OrBed[];
}

interface Props {
    services: Service[];
    doctors: Doctor[];
    insuranceCompanies: InsuranceCompany[];
    priceLists?: PriceList[];
    orRooms?: OrRoom[];
    booking?: Record<string, unknown>;
    today?: string;
    submitUrl: string;
    submitMethod?: 'post' | 'put';
}

const props = withDefaults(defineProps<Props>(), {
    booking: undefined,
    today: undefined,
    submitMethod: 'post',
    orRooms: () => [],
    priceLists: () => [],
});

const emit = defineEmits<{
    (e: 'success'): void;
    (e: 'cancel'): void;
}>();

const deptOptions = [
    { value: 'clinic', label: 'العيادة', icon: '🏥', cap: 'فحص عام' },
    { value: 'labs', label: 'الفحوصات', icon: '🔬', cap: 'تحاليل وأشعة' },
    { value: 'laser', label: 'الليزر', icon: '💡', cap: 'ليزر علاجي' },
    { value: 'lasik', label: 'الليزك', icon: '👁️', cap: 'تصحيح النظر' },
    { value: 'surgery', label: 'العمليات', icon: '⚕️', cap: 'جراحة عيون' },
];

const form = useForm({
    patient_name: (props.booking?.patient_name as string) ?? '',
    patient_phone: (props.booking?.patient_phone as string) ?? '',
    patient_age: (props.booking?.patient_age as string) ?? '',
    national_id: (props.booking?.national_id as string) ?? '',
    gender: (props.booking?.gender as string) ?? '',
    dept: (props.booking?.dept as string) ?? 'clinic',
    service_id: (props.booking?.service_id as string) ?? '',
    service_name: (props.booking?.service_name as string) ?? '',
    doctor_id: (props.booking?.doctor_id as string) ?? '',
    ins_company_id: (props.booking?.ins_company_id as string) ?? '',
    visit_date:
        (props.booking?.visit_date as string) ??
        props.today ??
        '',
    visit_time: (props.booking?.visit_time as string) ?? '',
    price: (props.booking?.price as string) ?? '0',
    discount: (props.booking?.discount as string) ?? '0',
    ins_amount: (props.booking?.ins_amount as string) ?? '0',
    paid_amount: (props.booking?.paid_amount as string) ?? '0',
    pay_method: (props.booking?.pay_method as string) ?? 'cash',
    pay_status: (props.booking?.pay_status as string) ?? 'unpaid',
    status: (props.booking?.status as string) ?? 'confirmed',
    visit_note: (props.booking?.visit_note as string) ?? '',
    bed_id: (props.booking?.bed_id as string) ?? '',
    eye_side: (props.booking?.eye_side as string) ?? '',
    analysis_type: (props.booking?.analysis_type as string) ?? '',
    analysis_notes: (props.booking?.analysis_notes as string) ?? '',
});

const isCreating = computed(() => props.submitMethod === 'post');

const showBeds = computed(
    () => form.dept === 'surgery' || form.dept === 'lasik',
);
const showAnalysis = computed(
    () => form.dept === 'surgery' || form.dept === 'lasik',
);
const showEyeSide = computed(
    () =>
        form.dept === 'surgery' ||
        form.dept === 'lasik' ||
        form.dept === 'laser',
);

const deptExtraTitle = computed(() => {
    if (form.dept === 'surgery') {
return 'بيانات العملية الجراحية';
}

    if (form.dept === 'lasik') {
return 'بيانات جلسة الليزك';
}

    if (form.dept === 'laser') {
return 'بيانات جلسة الليزر';
}

    return '';
});

const filteredServices = computed(() =>
    props.services.filter((s) => s.dept === form.dept),
);

const isInsurance = computed(() => form.pay_method === 'insurance');

const activePriceList = computed(() =>
    props.priceLists?.find((pl) => pl.ins_company_id === form.ins_company_id),
);

const netAmount = computed(() => {
    const price = Number(form.price) || 0;
    const discount = Number(form.discount) || 0;
    const ins = Number(form.ins_amount) || 0;

    return Math.max(0, price - discount - ins);
});

const selectedServiceName = computed(
    () =>
        props.services.find((s) => s.id === form.service_id)?.name ??
        form.service_name,
);

const selectedDoctorName = computed(
    () => props.doctors.find((d) => d.id === form.doctor_id)?.name ?? '—',
);

const selectedDeptLabel = computed(
    () => deptOptions.find((d) => d.value === form.dept)?.label ?? '—',
);

const showInvoicePreview = computed(
    () => form.pay_status === 'paid' || form.pay_status === 'partial',
);

function recalcPrice() {
    const service = props.services.find((s) => s.id === form.service_id);

    if (!service) {
return;
}

    form.service_name = service.name;

    if (isInsurance.value && activePriceList.value) {
        const pl = activePriceList.value;
        const item = pl?.items.find((i) => i.service_id === form.service_id);
        const itemPrice = item?.price ?? service.ins_price ?? service.price;
        form.price = String(itemPrice);
        form.ins_amount = pl
            ? String(Math.round((itemPrice * pl.ins_coverage) / 100 * 100) / 100)
            : '0';
    } else if (isInsurance.value) {
        form.price = String(service.ins_price ?? service.price);
        form.ins_amount = '0';
    } else {
        form.price = String(service.price);
        form.ins_amount = '0';
    }
}

watch(() => form.service_id, recalcPrice);
watch(() => form.pay_method, () => {
    if (!form.service_id) {
        return;
    }

    recalcPrice();

    if (!isInsurance.value) {
        form.ins_company_id = '';
        form.ins_amount = '0';
    }
});
watch(() => form.ins_company_id, recalcPrice);

function submit() {
    const method = props.submitMethod === 'put' ? form.put : form.post;
    method.call(form, props.submitUrl, {
        onSuccess: () => emit('success'),
    });
}
</script>

<template>
    <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div>
                <PatientFields
                    :model-value="{
                        patient_name: form.patient_name,
                        national_id: form.national_id,
                        patient_phone: form.patient_phone,
                        patient_age: form.patient_age,
                        gender: form.gender,
                        visit_date: form.visit_date,
                        visit_time: form.visit_time,
                    }"
                    :errors="form.errors"
                    @update:model-value="(v) => Object.assign(form, v)"
                />

                <ServiceSelect
                    :model-value="{ service_id: form.service_id, doctor_id: form.doctor_id }"
                    :services="filteredServices"
                    :doctors="doctors"
                    :is-edit-mode="!isCreating"
                    @update:model-value="(v) => { form.service_id = v.service_id; form.doctor_id = v.doctor_id; }"
                />

                <template v-if="!isCreating">
                    <PaymentFields
                        :model-value="{
                            pay_method: form.pay_method,
                            paid_amount: form.paid_amount,
                            ins_company_id: form.ins_company_id,
                            price: form.price,
                            discount: form.discount,
                            ins_amount: form.ins_amount,
                            pay_status: form.pay_status,
                        }"
                        :insurance-companies="insuranceCompanies"
                        :price-lists="priceLists"
                        :is-insurance="isInsurance"
                        :net-amount="netAmount"
                        @update:model-value="(v) => Object.assign(form, v)"
                    />
                    <InvoicePreview
                        v-if="showInvoicePreview"
                        :selected-service-name="selectedServiceName"
                        :price="form.price"
                        :discount="form.discount"
                        :ins-amount="form.ins_amount"
                        :is-insurance="isInsurance"
                        :net-amount="netAmount"
                    />
                </template>

                <div v-else class="bk-section">
                    <span class="bk-title bk-title-teal">الدفع</span>
                    <div class="bk-grid-2 mt-3">
                        <div class="col-span-2">
                            <label class="bk-label">طريقة الدفع</label>
                            <select v-model="form.pay_method" class="bk-input">
                                <option value="cash">كاش</option>
                                <option value="card">شبكة</option>
                                <option value="transfer">تحويل</option>
                                <option value="insurance">تأمين</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="bk-label">شركة التأمين</label>
                            <select v-model="form.ins_company_id" class="bk-input">
                                <option value="">— غير تابع لتأمين —</option>
                                <option v-for="ins in insuranceCompanies" :key="ins.id" :value="ins.id">
                                    {{ ins.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-3 rounded-lg border border-hospital-warning-pale bg-hospital-warning-pale/40 px-3 py-2 text-xs text-hospital-warning">
                        💡 يمكن إكمال بيانات الدفع لاحقاً من خلال زر "دفع" في قائمة الحجوزات
                    </p>
                </div>
            </div>

            <div>
                <DepartmentSelector
                    v-model="form.dept"
                    :error="form.errors.dept"
                />

                <div v-if="showEyeSide" class="bk-section">
                    <span class="bk-title bk-title-green">{{ deptExtraTitle }}</span>
                    <div class="bk-grid-2">
                        <div :class="showBeds && orRooms.length ? 'col-span-2' : ''">
                            <EyeSideSelector v-model="form.eye_side" />
                        </div>
                        <div v-if="showBeds" :class="orRooms.length ? 'col-span-2' : ''">
                            <BedPicker
                                v-model="form.bed_id"
                                :or-rooms="orRooms"
                                :dept="form.dept"
                            />
                        </div>
                        <template v-if="showAnalysis">
                            <AnalysisFields
                                :model-value="{ analysis_type: form.analysis_type, analysis_notes: form.analysis_notes }"
                                @update:model-value="(v) => { form.analysis_type = v.analysis_type; form.analysis_notes = v.analysis_notes; }"
                            />
                        </template>
                    </div>
                </div>

                <StatusSelector v-model="form.status" />

                <BookingSummary
                    :patient-name="form.patient_name"
                    :dept-label="selectedDeptLabel"
                    :service-name="selectedServiceName"
                    :doctor-name="selectedDoctorName"
                    :visit-date="form.visit_date"
                    :visit-time="form.visit_time"
                    :net-amount="netAmount"
                />

                <div class="bk-section">
                    <span class="bk-title bk-title-blue">ملاحظات</span>
                    <textarea
                        v-model="form.visit_note"
                        rows="3"
                        placeholder="أي ملاحظات إضافية على الحجز..."
                        class="bk-input resize-none"
                    />
                </div>
            </div>
        </div>

        <FormFooter
            :processing="form.processing"
            :is-edit-mode="!isCreating"
            @cancel="emit('cancel')"
            @submit="submit"
        />
    </form>
</template>

<style scoped>
.bk-section {
    background: var(--color-hospital-surface, #ffffff);
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: var(--hospital-rl, 14px);
    padding: 18px 20px;
    margin-bottom: 16px;
    box-shadow: var(--hospital-sh, 0 2px 12px rgba(10,79,166,.08));
}

.bk-title {
    display: inline-block;
    border-radius: 6px;
    padding: 3px 12px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 14px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

.bk-title-blue { background: #0a4fa6; }
.bk-title-teal { background: #00b5a4; }
.bk-title-purple { background: #7b2fa6; }
.bk-title-orange { background: #e07c10; }
.bk-title-green { background: #1a8c5b; }

.bk-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px 16px;
}

.bk-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #4a5878;
    margin-bottom: 4px;
}

.bk-input {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid #dde4ef;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    color: #0d1f3c;
    background: #fff;
    direction: rtl;
    transition: all 0.15s ease;
}

.bk-input:focus {
    outline: none;
    border-color: #0a4fa6;
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.08);
}
</style>