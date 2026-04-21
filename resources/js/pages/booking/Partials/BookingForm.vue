<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
    priceLists: PriceList[];
    orRooms?: OrRoom[];
    booking?: Record<string, unknown>;
    submitUrl: string;
    submitMethod?: 'post' | 'put';
}

const props = withDefaults(defineProps<Props>(), {
    booking: undefined,
    submitMethod: 'post',
    orRooms: () => [],
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

const statusOptions = [
    { value: 'confirmed', label: 'مؤكد', icon: '✅' },
    { value: 'waiting', label: 'انتظار', icon: '⏳' },
    { value: 'in_progress', label: 'جارٍ', icon: '🔄' },
    { value: 'completed', label: 'مكتمل', icon: '🏁' },
    { value: 'cancelled', label: 'ملغي', icon: '❌' },
];

const payMethodOptions = [
    { value: 'cash', label: 'كاش' },
    { value: 'card', label: 'شبكة' },
    { value: 'transfer', label: 'تحويل' },
    { value: 'insurance', label: 'تأمين' },
];

const payStatusOptions = [
    { value: 'unpaid', label: 'لم يسدد' },
    { value: 'partial', label: 'جزئي' },
    { value: 'paid', label: 'مسدد' },
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
        new Date().toISOString().slice(0, 10),
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

const analysisOptions = [
    'تحاليل ما قبل العملية (روتين)',
    'تحاليل دم كاملة CBC',
    'تحاليل كيمياء الدم',
    'OCT شبكية',
    'تصوير قرنية Topography',
    'A-Scan قياسات',
    'فحص مجال بصري',
    'أنجيوغرافيا',
    'أشعة صدر',
    'رسم قلب ECG',
];

const filteredServices = computed(() =>
    props.services.filter((s) => s.dept === form.dept),
);

const bedPickerColor = computed(() =>
    form.dept === 'lasik' ? '#7B2FA6' : '#27AE60',
);

const selectedBedId = ref<number | null>(
    props.booking?.bed_id
        ? (props.orRooms
              .flatMap((r) => r.beds)
              .find((b) => String(b.id) === String(props.booking!.id))?.id ??
              null)
        : null,
);

function isBedOccupied(bed: OrBed): boolean {
    return (
        bed.status != 'available' || (!!bed.surgery && bed.surgery !== null)
    );
}

function selectBed(bed: OrBed) {
    if (isBedOccupied(bed)) return;
    if (selectedBedId.value === bed.id) {
        selectedBedId.value = null;
        form.bed_id = '';
    } else {
        selectedBedId.value = bed.id;
        form.bed_id = String(bed.id);
    }
}

const isInsurance = computed(() => form.pay_method === 'insurance');

const priceListId = ref('');

const filteredPriceLists = computed(() =>
    props.priceLists.filter(
        (pl) => !form.ins_company_id || pl.ins_company_id === form.ins_company_id,
    ),
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
    if (!service) return;

    form.service_name = service.name;

    if (isInsurance.value && priceListId.value) {
        const pl = props.priceLists.find((p) => p.id === priceListId.value);
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
    if (!form.service_id) return;
    recalcPrice();
    if (!isInsurance.value) {
        form.ins_company_id = '';
        priceListId.value = '';
        form.ins_amount = '0';
    }
});
watch(priceListId, recalcPrice);

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
            <!-- ═══ RIGHT COLUMN: patient + service ═══ -->
            <div>
                <!-- Patient data section -->
                <div class="bk-section">
                    <span class="bk-title bk-title-blue">بيانات المريض</span>
                    <div class="bk-grid-2">
                        <!-- Name (full width) -->
                        <div class="col-span-2">
                            <label class="bk-label">اسم المريض *</label>
                            <input
                                v-model="form.patient_name"
                                type="text"
                                placeholder="الاسم الكامل للمريض"
                                class="bk-input"
                                :class="{
                                    'border-hospital-danger':
                                        form.errors.patient_name,
                                }"
                            />
                            <p
                                v-if="form.errors.patient_name"
                                class="mt-1 text-xs text-hospital-danger"
                            >
                                {{ form.errors.patient_name }}
                            </p>
                        </div>
                        <!-- National ID -->
                        <div>
                            <label class="bk-label">الرقم القومي</label>
                            <input
                                v-model="form.national_id"
                                type="text"
                                placeholder="14 رقم"
                                class="bk-input"
                            />
                        </div>
                        <!-- Phone -->
                        <div>
                            <label class="bk-label">رقم الهاتف</label>
                            <input
                                v-model="form.patient_phone"
                                type="tel"
                                placeholder="01xxxxxxxxx"
                                class="bk-input"
                            />
                        </div>
                        <!-- Age -->
                        <div>
                            <label class="bk-label">السن</label>
                            <input
                                v-model="form.patient_age"
                                type="number"
                                min="0"
                                max="150"
                                placeholder="سنة"
                                class="bk-input"
                            />
                        </div>
                        <!-- Gender -->
                        <div>
                            <label class="bk-label">الجنس</label>
                            <select v-model="form.gender" class="bk-input">
                                <option value="">— اختر —</option>
                                <option value="male">ذكر</option>
                                <option value="female">أنثى</option>
                            </select>
                        </div>
                        <!-- Date -->
                        <div>
                            <label class="bk-label">التاريخ *</label>
                            <input
                                v-model="form.visit_date"
                                type="date"
                                class="bk-input"
                                :class="{
                                    'border-hospital-danger':
                                        form.errors.visit_date,
                                }"
                            />
                        </div>
                        <!-- Time -->
                        <div>
                            <label class="bk-label">الوقت</label>
                            <input
                                v-model="form.visit_time"
                                type="time"
                                class="bk-input"
                            />
                        </div>
                    </div>
                </div>

                <!-- Service section -->
                <div class="bk-section">
                    <span class="bk-title bk-title-teal">{{
                        isCreating ? 'الخدمة' : 'الخدمة والدفع'
                    }}</span>
                    <div class="bk-grid-2">
                        <!-- Service -->
                        <div>
                            <label class="bk-label">الخدمة</label>
                            <select v-model="form.service_id" class="bk-input">
                                <option value="">— اختر الخدمة —</option>
                                <option
                                    v-for="svc in filteredServices"
                                    :key="svc.id"
                                    :value="svc.id"
                                >
                                    {{ svc.name }}
                                </option>
                            </select>
                        </div>
                        <!-- Doctor -->
                        <div>
                            <label class="bk-label">الطبيب</label>
                            <select v-model="form.doctor_id" class="bk-input">
                                <option value="">— اختر الطبيب —</option>
                                <option
                                    v-for="dr in doctors"
                                    :key="dr.id"
                                    :value="dr.id"
                                >
                                    {{ dr.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Pricing & payment — edit only -->
                    <template v-if="!isCreating">
                        <div class="bk-grid-2 mt-3">
                            <!-- Pay method -->
                            <div>
                                <label class="bk-label">طريقة الدفع</label>
                                <select v-model="form.pay_method" class="bk-input">
                                    <option v-for="opt in payMethodOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>

                            <!-- Paid amount -->
                            <div>
                                <label class="bk-label">المبلغ المدفوع (ج)</label>
                                <input v-model="form.paid_amount" type="number" step="0.01" min="0" class="bk-input" />
                            </div>

                            <!-- Insurance company + price list (when insurance) -->
                            <template v-if="isInsurance">
                                <div>
                                    <label class="bk-label">شركة التأمين</label>
                                    <select v-model="form.ins_company_id" class="bk-input" @change="priceListId = ''">
                                        <option value="">— اختر الشركة —</option>
                                        <option v-for="ins in insuranceCompanies" :key="ins.id" :value="ins.id">
                                            {{ ins.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="bk-label">قائمة الأسعار</label>
                                    <select v-model="priceListId" class="bk-input">
                                        <option value="">— اختر القائمة —</option>
                                        <option v-for="pl in filteredPriceLists" :key="pl.id" :value="pl.id">
                                            {{ pl.name }} ({{ pl.ins_coverage }}%)
                                        </option>
                                    </select>
                                </div>
                            </template>

                            <!-- Price -->
                            <div>
                                <label class="bk-label">السعر الأصلي (ج)</label>
                                <input v-model="form.price" type="number" step="0.01" min="0" class="bk-input" />
                            </div>

                            <!-- Discount -->
                            <div>
                                <label class="bk-label">الخصم (ج)</label>
                                <input v-model="form.discount" type="number" step="0.01" min="0" class="bk-input" />
                            </div>

                            <!-- Insurance amount -->
                            <div v-if="isInsurance">
                                <label class="bk-label">مبلغ التأمين (ج)</label>
                                <input v-model="form.ins_amount" type="number" step="0.01" min="0" class="bk-input bk-input-readonly" readonly />
                            </div>

                            <!-- Net due -->
                            <div>
                                <label class="bk-label">الإجمالي المستحق (ج)</label>
                                <input :value="netAmount" type="number" class="bk-input bk-input-readonly" style="font-weight:700;color:#0a4fa6;font-size:14px;" readonly />
                            </div>

                            <!-- Pay status -->
                            <div class="col-span-2">
                                <label class="bk-label">حالة السداد</label>
                                <select v-model="form.pay_status" class="bk-input">
                                    <option v-for="opt in payStatusOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Invoice preview -->
                        <div v-if="showInvoicePreview" class="inv-preview mt-3">
                            <p class="inv-preview-title">
                                🧾 فاتورة تلقائية — ستُنشأ عند الحفظ
                            </p>
                            <div class="inv-line">
                                <span>الخدمة</span
                                ><span>{{ selectedServiceName || '—' }}</span>
                            </div>
                            <div class="inv-line">
                                <span>السعر الأصلي</span
                                ><span
                                    >{{
                                        Number(form.price).toLocaleString(
                                            'ar-EG',
                                        )
                                    }}
                                    ج</span
                                >
                            </div>
                            <div class="inv-line">
                                <span>الخصم</span
                                ><span
                                    >{{
                                        Number(form.discount).toLocaleString(
                                            'ar-EG',
                                        )
                                    }}
                                    ج</span
                                >
                            </div>
                            <div v-if="isInsurance" class="inv-line">
                                <span>مبلغ التأمين</span
                                ><span
                                    >{{
                                        Number(form.ins_amount).toLocaleString(
                                            'ar-EG',
                                        )
                                    }}
                                    ج</span
                                >
                            </div>
                            <div class="inv-line font-bold">
                                <span>💰 الإجمالي المستحق</span
                                ><span
                                    >{{
                                        netAmount.toLocaleString('ar-EG')
                                    }}
                                    ج</span
                                >
                            </div>
                        </div>
                    </template>

                    <!-- Create mode: pay method + insurance -->
                    <div v-else class="bk-grid-2 mt-3">
                        <div class="col-span-2">
                            <label class="bk-label">طريقة الدفع</label>
                            <select v-model="form.pay_method" class="bk-input">
                                <option v-for="opt in payMethodOptions" :key="opt.value" :value="opt.value">
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <template v-if="isInsurance">
                            <div>
                                <label class="bk-label">شركة التأمين</label>
                                <select v-model="form.ins_company_id" class="bk-input" @change="priceListId = ''">
                                    <option value="">— اختر الشركة —</option>
                                    <option v-for="ins in insuranceCompanies" :key="ins.id" :value="ins.id">
                                        {{ ins.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="bk-label">قائمة الأسعار</label>
                                <select v-model="priceListId" class="bk-input">
                                    <option value="">— اختر القائمة —</option>
                                    <option v-for="pl in filteredPriceLists" :key="pl.id" :value="pl.id">
                                        {{ pl.name }} ({{ pl.ins_coverage }}%)
                                    </option>
                                </select>
                            </div>
                        </template>

                        <p class="col-span-2 rounded-lg border border-hospital-warning-pale bg-hospital-warning-pale/40 px-3 py-2 text-xs text-hospital-warning">
                            💡 يمكن إكمال بيانات الدفع لاحقاً من خلال زر "دفع" في قائمة الحجوزات
                        </p>
                    </div>
                </div>
            </div>

            <!-- ═══ LEFT COLUMN: dept routing + status + summary + notes ═══ -->
            <div>
                <!-- Dept routing section -->
                <div class="bk-section">
                    <span class="bk-title bk-title-purple"
                        >التوجيه إلى قسم</span
                    >
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="dept in deptOptions"
                            :key="dept.value"
                            type="button"
                            :class="[
                                'dept-btn',
                                form.dept === dept.value
                                    ? 'dept-btn-selected'
                                    : '',
                            ]"
                            @click="form.dept = dept.value"
                        >
                            <div
                                :class="[
                                    'dept-btn-icon',
                                    form.dept === dept.value
                                        ? 'dept-btn-icon-selected'
                                        : '',
                                ]"
                            >
                                {{ dept.icon }}
                            </div>
                            <p
                                :class="[
                                    'dept-btn-name',
                                    form.dept === dept.value
                                        ? 'text-hospital-primary'
                                        : '',
                                ]"
                            >
                                {{ dept.label }}
                            </p>
                            <p class="dept-btn-cap">{{ dept.cap }}</p>
                        </button>
                    </div>
                    <p
                        v-if="form.errors.dept"
                        class="mt-2 text-xs text-hospital-danger"
                    >
                        {{ form.errors.dept }}
                    </p>
                </div>

                <!-- Dept-specific extra fields — shown for surgery / lasik / laser -->
                <div v-if="showEyeSide" class="bk-section">
                    <span class="bk-title bk-title-green">{{
                        deptExtraTitle
                    }}</span>
                    <div class="bk-grid-2">
                        <!-- Eye side -->
                        <div
                            :class="
                                showBeds && props.orRooms.length
                                    ? 'col-span-2'
                                    : showBeds
                                      ? ''
                                      : 'col-span-2'
                            "
                        >
                            <label class="bk-label">جهة العين</label>
                            <div class="eye-side-row">
                                <button
                                    v-for="side in [
                                        { v: 'OD', l: 'OD — يمين' },
                                        { v: 'OS', l: 'OS — يسار' },
                                        { v: 'OU', l: 'OU — كلاهما' },
                                    ]"
                                    :key="side.v"
                                    type="button"
                                    :class="[
                                        'eye-btn',
                                        form.eye_side === side.v
                                            ? 'eye-btn-selected'
                                            : '',
                                    ]"
                                    @click="
                                        form.eye_side =
                                            form.eye_side === side.v
                                                ? ''
                                                : side.v
                                    "
                                >
                                    {{ side.l }}
                                </button>
                            </div>
                        </div>
                        <!-- Bed number — surgery / lasik only -->
                        <div
                            v-if="showBeds"
                            :class="props.orRooms.length ? 'col-span-2' : ''"
                        >
                            <label class="bk-label">رقم السرير</label>
                            <div
                                v-if="props.orRooms.length"
                                class="bed-picker-panel"
                            >
                                <!-- Legend -->
                                <div class="bed-picker-legend">
                                    <span
                                        class="legend-dot"
                                        :style="{ background: bedPickerColor }"
                                    />
                                    فارغ
                                    <span class="legend-dot legend-dot-busy" />
                                    مشغول
                                    <span
                                        class="legend-dot legend-dot-selected"
                                        :style="{
                                            background: bedPickerColor,
                                            opacity: '1',
                                        }"
                                    />
                                    محدد
                                </div>
                                <!-- Rooms -->
                                <div
                                    v-for="room in props.orRooms"
                                    :key="room.id"
                                    class="bed-room"
                                >
                                    <p class="bed-room-label">
                                        {{ room.name }}
                                    </p>
                                    <div class="bed-room-row">
                                        <button
                                            v-for="bed in room.beds"
                                            :key="bed.id"
                                            type="button"
                                            :class="[
                                                'bk-bed',
                                                isBedOccupied(bed)
                                                    ? 'bk-bed-busy'
                                                    : 'bk-bed-free',
                                                selectedBedId === bed.id
                                                    ? 'bk-bed-selected'
                                                    : '',
                                            ]"
                                            :style="
                                                selectedBedId === bed.id
                                                    ? {
                                                          background:
                                                              bedPickerColor,
                                                          borderColor:
                                                              bedPickerColor,
                                                      }
                                                    : {}
                                            "
                                            :title="
                                                isBedOccupied(bed)
                                                    ? `${room.name} - سرير ${bed.bed_number} مشغول`
                                                    : `${room.name} - سرير ${bed.bed_number}`
                                            "
                                            @click="selectBed(bed)"
                                        >
                                            <span class="bk-bed-num">{{
                                                bed.bed_number
                                            }}</span>
                                            <span
                                                v-if="isBedOccupied(bed)"
                                                class="bk-bed-busy-dot"
                                            />
                                        </button>
                                    </div>
                                </div>
                                <!-- Selection label -->
                                <p
                                    v-if="form.bed_no"
                                    class="bed-picker-selected"
                                    :style="{ color: bedPickerColor }"
                                >
                                    ✓ سرير {{ form.bed_id }} محدد
                                </p>
                            </div>
                            <input
                                v-else
                                v-model="form.bed_id"
                                type="number"
                                min="1"
                                max="9999"
                                placeholder="مثال: 5"
                                class="bk-input"
                            />
                        </div>
                        <!-- Analysis type + notes — surgery / lasik only -->
                        <template v-if="showAnalysis">
                            <div class="col-span-2">
                                <label class="bk-label"
                                    >نوع التحاليل / الفحص المطلوب</label
                                >
                                <select
                                    v-model="form.analysis_type"
                                    class="bk-input"
                                >
                                    <option value="">— بدون —</option>
                                    <option
                                        v-for="opt in analysisOptions"
                                        :key="opt"
                                        :value="opt"
                                    >
                                        {{ opt }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="bk-label">ملاحظات التحاليل</label>
                                <input
                                    v-model="form.analysis_notes"
                                    type="text"
                                    placeholder="تفاصيل إضافية على التحاليل..."
                                    class="bk-input"
                                />
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Status selector section -->
                <div class="bk-section">
                    <span class="bk-title bk-title-orange">حالة الحجز</span>
                    <div class="grid grid-cols-3 gap-2">
                        <button
                            v-for="s in statusOptions"
                            :key="s.value"
                            type="button"
                            :class="[
                                'status-opt',
                                form.status === s.value
                                    ? 'status-opt-selected'
                                    : '',
                            ]"
                            @click="form.status = s.value"
                        >
                            <span>{{ s.icon }}</span> {{ s.label }}
                        </button>
                    </div>
                </div>

                <!-- Summary section -->
                <div class="bk-section">
                    <span class="bk-title bk-title-blue">ملخص الحجز</span>
                    <div class="pay-summary">
                        <div class="pay-row">
                            <span class="pay-lbl">اسم المريض</span>
                            <span class="pay-val">{{
                                form.patient_name || '—'
                            }}</span>
                        </div>
                        <div class="pay-row">
                            <span class="pay-lbl">القسم</span>
                            <span class="pay-val">{{ selectedDeptLabel }}</span>
                        </div>
                        <div class="pay-row">
                            <span class="pay-lbl">الخدمة</span>
                            <span class="pay-val">{{
                                selectedServiceName || '—'
                            }}</span>
                        </div>
                        <div class="pay-row">
                            <span class="pay-lbl">الطبيب</span>
                            <span class="pay-val">{{
                                selectedDoctorName
                            }}</span>
                        </div>
                        <div class="pay-row">
                            <span class="pay-lbl">التاريخ</span>
                            <span class="pay-val"
                                >{{ form.visit_date }}
                                {{ form.visit_time }}</span
                            >
                        </div>
                        <div class="pay-row">
                            <span class="pay-lbl">إجمالي المستحق</span>
                            <span class="pay-val pay-val-total"
                                >{{ netAmount.toLocaleString('ar-EG') }} ج</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Notes section -->
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

        <!-- Footer actions -->
        <div
            class="mt-2 flex items-center justify-between border-t border-hospital-border pt-4"
        >
            <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full bg-hospital-success" />
                <span class="text-xs text-hospital-text-3"
                    >عند السداد سيتم إنشاء الفاتورة أوتوماتيكياً</span
                >
            </div>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm font-medium text-hospital-text-2 transition-colors hover:bg-hospital-bg"
                    @click="emit('cancel')"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-hospital-primary px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light disabled:opacity-60"
                >
                    {{ form.processing ? 'جارٍ الحفظ…' : '💾 حفظ الحجز' }}
                </button>
            </div>
        </div>
    </form>
</template>

<style scoped>
/* ── Section wrapper ── */
.bk-section {
    background: var(--color-hospital-bg, #f3f6fa);
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

/* ── Section title badge ── */
.bk-title {
    display: inline-block;
    border-radius: 6px;
    padding: 4px 14px;
    font-size: 11px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
    letter-spacing: 0.3px;
}
.bk-title-blue {
    background: #0a4fa6;
}
.bk-title-teal {
    background: #00b5a4;
}
.bk-title-purple {
    background: #7b2fa6;
}
.bk-title-orange {
    background: #e07c10;
}
.bk-title-green {
    background: #1a8c5b;
}

/* ── Form grid ── */
.bk-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* ── Field label ── */
.bk-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

/* ── Input / select / textarea ── */
.bk-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid #dde4ef;
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: #0d1f3c;
    background: #fff;
    direction: rtl;
    transition: border-color 0.15s;
}
.bk-input:focus {
    outline: none;
    border-color: #0a4fa6;
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.1);
}
.bk-input-readonly {
    background: #f3f6fa;
    color: #4a5878;
}

/* ── Dept routing buttons ── */
.dept-btn {
    border: 2px solid #dde4ef;
    border-radius: 10px;
    padding: 10px 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.18s;
    background: #fff;
}
.dept-btn:hover {
    border-color: #0a4fa6;
    background: #e8f1fb;
}
.dept-btn-selected {
    border-color: #0a4fa6;
    background: #e8f1fb;
}

.dept-btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 6px;
    background: #f3f6fa;
    font-size: 16px;
    transition: all 0.18s;
}
.dept-btn-icon-selected {
    background: #0a4fa6;
}

.dept-btn-name {
    font-size: 11px;
    font-weight: 700;
    color: #0d1f3c;
}
.dept-btn-cap {
    font-size: 9px;
    color: #8a96ae;
    margin-top: 2px;
}

/* ── Status buttons ── */
.status-opt {
    padding: 7px 6px;
    border-radius: 8px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
    color: #4a5878;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
}
.status-opt:hover {
    border-color: #0a4fa6;
    background: #e8f1fb;
    color: #0a4fa6;
}
.status-opt-selected {
    border-color: #0a4fa6;
    background: #0a4fa6;
    color: #fff;
}

/* ── Summary panel ── */
.pay-summary {
    background: #0d1f3c;
    border-radius: 8px;
    overflow: hidden;
    font-size: 12px;
}
.pay-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 7px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.pay-row:last-child {
    border-bottom: none;
}
.pay-lbl {
    color: rgba(255, 255, 255, 0.5);
    font-size: 11px;
}
.pay-val {
    font-weight: 600;
    color: #fff;
    font-size: 12px;
}
.pay-val-total {
    font-size: 16px;
    font-weight: 900;
    color: #7fffd4;
}

/* ── Eye side selector ── */
.eye-side-row {
    display: flex;
    gap: 6px;
}
.eye-btn {
    flex: 1;
    padding: 7px 4px;
    border-radius: 7px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    color: #4a5878;
    transition: all 0.15s;
}
.eye-btn:hover {
    border-color: #1a8c5b;
    background: #e4f5ee;
    color: #1a8c5b;
}
.eye-btn-selected {
    border-color: #1a8c5b;
    background: #1a8c5b;
    color: #fff;
}

/* ── Invoice preview ── */
.inv-preview {
    background: #fffef5;
    border: 1.5px dashed #e07c10;
    border-radius: 8px;
    padding: 12px;
    font-size: 11px;
}
.inv-preview-title {
    font-weight: 700;
    color: #e07c10;
    margin-bottom: 8px;
    font-size: 12px;
}
.inv-line {
    display: flex;
    justify-content: space-between;
    padding: 4px 0;
    border-bottom: 1px solid #f3f6fa;
    color: #0d1f3c;
}
.inv-line:last-child {
    border-bottom: none;
}

/* ── Bed picker ── */
.bed-picker-panel {
    background: #f3f6fa;
    border: 1.5px solid #dde4ef;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bed-picker-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    color: #4a5878;
    font-weight: 600;
    border-bottom: 1px solid #dde4ef;
    padding-bottom: 7px;
}
.legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
    opacity: 0.35;
}
.legend-dot-busy {
    background: #e74c3c;
    opacity: 1;
}
.legend-dot-selected {
    opacity: 1;
}

.bed-room {
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.bed-room-label {
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.bed-room-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.bk-bed {
    width: 44px;
    height: 38px;
    border-radius: 7px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    padding: 0;
    position: relative;
    gap: 2px;
}
.bk-bed-num {
    font-size: 13px;
    font-weight: 800;
    color: #0d1f3c;
    line-height: 1;
}
.bk-bed-free:hover {
    border-color: currentColor;
    background: #f0f7ff;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
}
.bk-bed-free:hover .bk-bed-num {
    color: inherit;
}
.bk-bed-busy {
    background: #fff0ee;
    border-color: #e74c3c;
    cursor: not-allowed;
    opacity: 0.75;
}
.bk-bed-busy .bk-bed-num {
    color: #e74c3c;
}
.bk-bed-busy-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #e74c3c;
}
.bk-bed-selected {
    color: #fff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}
.bk-bed-selected .bk-bed-num {
    color: #fff !important;
}

.bed-picker-selected {
    font-size: 11px;
    font-weight: 700;
    margin: 0;
    padding-top: 6px;
    border-top: 1px solid #dde4ef;
}
</style>
