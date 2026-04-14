<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

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

interface Props {
    services: Service[];
    doctors: Doctor[];
    insuranceCompanies: InsuranceCompany[];
    /** Pass existing booking to pre-fill form for editing */
    booking?: Record<string, unknown>;
    submitUrl: string;
    submitMethod?: 'post' | 'put';
}

const props = withDefaults(defineProps<Props>(), {
    booking: undefined,
    submitMethod: 'post',
});

const emit = defineEmits<{
    (e: 'success'): void;
    (e: 'cancel'): void;
}>();

const deptOptions = [
    { value: 'clinic',  label: 'العيادة' },
    { value: 'labs',    label: 'الفحوصات' },
    { value: 'surgery', label: 'العمليات' },
    { value: 'lasik',   label: 'الليزك' },
    { value: 'laser',   label: 'الليزر' },
];

const payMethodOptions = [
    { value: 'cash',      label: 'كاش' },
    { value: 'card',      label: 'شبكة' },
    { value: 'transfer',  label: 'تحويل' },
    { value: 'insurance', label: 'تأمين' },
];

const payStatusOptions = [
    { value: 'unpaid',  label: 'لم يسدد' },
    { value: 'partial', label: 'جزئي' },
    { value: 'paid',    label: 'مسدد' },
];

const form = useForm({
    patient_name:   (props.booking?.patient_name as string) ?? '',
    patient_phone:  (props.booking?.patient_phone as string) ?? '',
    patient_age:    (props.booking?.patient_age as string) ?? '',
    national_id:    (props.booking?.national_id as string) ?? '',
    gender:         (props.booking?.gender as string) ?? '',
    dept:           (props.booking?.dept as string) ?? 'clinic',
    service_id:     (props.booking?.service_id as string) ?? '',
    service_name:   (props.booking?.service_name as string) ?? '',
    doctor_id:      (props.booking?.doctor_id as string) ?? '',
    ins_company_id: (props.booking?.ins_company_id as string) ?? '',
    visit_date:     (props.booking?.visit_date as string) ?? new Date().toISOString().slice(0, 10),
    visit_time:     (props.booking?.visit_time as string) ?? '',
    price:          (props.booking?.price as string) ?? '0',
    discount:       (props.booking?.discount as string) ?? '0',
    ins_amount:     (props.booking?.ins_amount as string) ?? '0',
    paid_amount:    (props.booking?.paid_amount as string) ?? '0',
    pay_method:     (props.booking?.pay_method as string) ?? 'cash',
    pay_status:     (props.booking?.pay_status as string) ?? 'unpaid',
    visit_note:     (props.booking?.visit_note as string) ?? '',
});

const filteredServices = computed(() =>
    props.services.filter((s) => s.dept === form.dept),
);

const isInsurance = computed(() => form.pay_method === 'insurance');

// Auto-fill price when service is selected
watch(() => form.service_id, (id) => {
    const service = props.services.find((s) => s.id === id);
    if (service) {
        form.service_name = service.name;
        form.price = isInsurance.value ? String(service.ins_price) : String(service.price);
    }
});

function submit() {
    const method = props.submitMethod === 'put' ? form.put : form.post;
    method.call(form, props.submitUrl, {
        onSuccess: () => emit('success'),
    });
}
</script>

<template>
    <form class="flex flex-col gap-5" @submit.prevent="submit">
        <!-- Patient Info -->
        <fieldset class="rounded-xl border border-hospital-border p-4">
            <legend class="px-2 text-xs font-semibold text-hospital-primary">بيانات المريض</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Name -->
                <div class="col-span-full sm:col-span-1 lg:col-span-2">
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">اسم المريض *</label>
                    <input
                        v-model="form.patient_name"
                        type="text"
                        class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                        :class="{ 'border-hospital-danger': form.errors.patient_name }"
                        placeholder="الاسم الكامل"
                    />
                    <p v-if="form.errors.patient_name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.patient_name }}</p>
                </div>
                <!-- Phone -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">رقم الهاتف</label>
                    <input v-model="form.patient_phone" type="tel" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" placeholder="01xxxxxxxxx" />
                </div>
                <!-- Age -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">العمر</label>
                    <input v-model="form.patient_age" type="number" min="0" max="150" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <!-- National ID -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">رقم الهوية</label>
                    <input v-model="form.national_id" type="text" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <!-- Gender -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الجنس</label>
                    <select v-model="form.gender" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option value="">— اختر —</option>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- Visit Info -->
        <fieldset class="rounded-xl border border-hospital-border p-4">
            <legend class="px-2 text-xs font-semibold text-hospital-primary">تفاصيل الزيارة</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Dept -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">القسم *</label>
                    <select
                        v-model="form.dept"
                        class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                        :class="{ 'border-hospital-danger': form.errors.dept }"
                    >
                        <option v-for="opt in deptOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <!-- Service -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الخدمة</label>
                    <select v-model="form.service_id" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option value="">— اختر الخدمة —</option>
                        <option v-for="svc in filteredServices" :key="svc.id" :value="svc.id">{{ svc.name }}</option>
                    </select>
                </div>
                <!-- Doctor -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الطبيب</label>
                    <select v-model="form.doctor_id" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option value="">— اختر الطبيب —</option>
                        <option v-for="dr in doctors" :key="dr.id" :value="dr.id">{{ dr.name }}</option>
                    </select>
                </div>
                <!-- Date -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">التاريخ *</label>
                    <input
                        v-model="form.visit_date"
                        type="date"
                        class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
                        :class="{ 'border-hospital-danger': form.errors.visit_date }"
                    />
                </div>
                <!-- Time -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">الوقت</label>
                    <input v-model="form.visit_time" type="time" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
            </div>
            <!-- Notes -->
            <div class="mt-4">
                <label class="mb-1 block text-xs font-medium text-hospital-text-2">ملاحظات</label>
                <textarea v-model="form.visit_note" rows="2" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none resize-none" />
            </div>
        </fieldset>

        <!-- Payment Info -->
        <fieldset class="rounded-xl border border-hospital-border p-4">
            <legend class="px-2 text-xs font-semibold text-hospital-primary">بيانات الدفع</legend>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">طريقة الدفع</label>
                    <select v-model="form.pay_method" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option v-for="opt in payMethodOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <!-- Insurance company — shown only when pay_method=insurance -->
                <div v-if="isInsurance">
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">شركة التأمين</label>
                    <select v-model="form.ins_company_id" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option value="">— اختر —</option>
                        <option v-for="ins in insuranceCompanies" :key="ins.id" :value="ins.id">{{ ins.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">السعر (ج.م)</label>
                    <input v-model="form.price" type="number" step="0.01" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">خصم</label>
                    <input v-model="form.discount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <div v-if="isInsurance">
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">مبلغ التأمين</label>
                    <input v-model="form.ins_amount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">المبلغ المدفوع</label>
                    <input v-model="form.paid_amount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-hospital-text-2">حالة السداد</label>
                    <select v-model="form.pay_status" class="w-full rounded-lg border border-hospital-border bg-hospital-bg px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none">
                        <option v-for="opt in payStatusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
            </div>
        </fieldset>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <button
                type="button"
                class="rounded-lg border border-hospital-border px-5 py-2 text-sm font-medium text-hospital-text-2 hover:bg-hospital-bg transition-colors"
                @click="emit('cancel')"
            >
                إلغاء
            </button>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white hover:bg-hospital-primary-light disabled:opacity-60 transition-colors"
            >
                {{ form.processing ? 'جارٍ الحفظ…' : 'حفظ الحجز' }}
            </button>
        </div>
    </form>
</template>
