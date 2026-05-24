<script setup lang="ts">
import { computed } from 'vue';

interface InsuranceCompany {
    id: string;
    name: string;
}

interface Props {
    modelValue: {
        pay_method: string;
        paid_amount: string;
        ins_company_id: string;
        price: string;
        discount: string;
        ins_amount: string;
        pay_status: string;
    };
    insuranceCompanies: InsuranceCompany[];
    isInsurance: boolean;
    netAmount: number;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Props['modelValue']): void;
}>();

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


function update(field: keyof Props['modelValue'], value: string) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

function handleInsCompanyChange(value: string) {
    update('ins_company_id', value);
}
</script>

<template>
    <div class="bk-grid-2 mt-3">
        <div>
            <label class="bk-label">طريقة دفع</label>
            <select
                :value="modelValue.pay_method"
                class="bk-input"
                :class="{ 'border-hospital-danger': errors.pay_method }"
                @change="
                    update(
                        'pay_method',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option
                    v-for="opt in payMethodOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
            <p v-if="errors.pay_method" class="mt-1 text-xs text-hospital-danger">
                {{ errors.pay_method }}
            </p>
        </div>

        <div>
            <label class="bk-label">المبلغ المدفوع (ج)</label>
            <input
                :value="modelValue.paid_amount"
                type="number"
                step="0.01"
                min="0"
                class="bk-input"
                :class="{ 'border-hospital-danger': errors.paid_amount }"
                @input="
                    update(
                        'paid_amount',
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
            <p v-if="errors.paid_amount" class="mt-1 text-xs text-hospital-danger">
                {{ errors.paid_amount }}
            </p>
        </div>

        <template v-if="isInsurance">
            <div>
                <label class="bk-label">شركة التأمين</label>
                <select
                    :value="modelValue.ins_company_id"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.ins_company_id }"
                    @change="
                        handleInsCompanyChange(
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">— اختر الشركة —</option>
                    <option
                        v-for="ins in insuranceCompanies"
                        :key="ins.id"
                        :value="ins.id"
                    >
                        {{ ins.name }}
                    </option>
                </select>
                <p v-if="errors.ins_company_id" class="mt-1 text-xs text-hospital-danger">
                    {{ errors.ins_company_id }}
                </p>
            </div>
        </template>

        <div>
            <label class="bk-label">السعر الأصلي (ج)</label>
            <input
                :value="modelValue.price"
                type="number"
                step="0.01"
                min="0"
                class="bk-input"
                :class="{ 'border-hospital-danger': errors.price }"
                @input="
                    update('price', ($event.target as HTMLInputElement).value)
                "
            />
            <p v-if="errors.price" class="mt-1 text-xs text-hospital-danger">
                {{ errors.price }}
            </p>
        </div>

        <div>
            <label class="bk-label">الخصم (ج)</label>
            <input
                :value="modelValue.discount"
                type="number"
                step="0.01"
                min="0"
                class="bk-input"
                :class="{ 'border-hospital-danger': errors.discount }"
                @input="
                    update(
                        'discount',
                        ($event.target as HTMLInputElement).value,
                    )
                "
            />
            <p v-if="errors.discount" class="mt-1 text-xs text-hospital-danger">
                {{ errors.discount }}
            </p>
        </div>

        <div v-if="isInsurance">
            <label class="bk-label">مبلغ التأمين (ج)</label>
            <input
                :value="modelValue.ins_amount"
                type="number"
                step="0.01"
                min="0"
                class="bk-input bk-input-readonly"
                readonly
            />
        </div>

        <div>
            <label class="bk-label">الإجمالي المستحق (ج)</label>
            <input
                :value="netAmount"
                type="number"
                class="bk-input bk-input-readonly"
                style="font-weight: 700; color: #0a4fa6; font-size: 14px"
                readonly
            />
        </div>

        <div class="col-span-2">
            <label class="bk-label">حالة السداد</label>
            <select
                :value="modelValue.pay_status"
                class="bk-input"
                :class="{ 'border-hospital-danger': errors.pay_status }"
                @change="
                    update(
                        'pay_status',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option
                    v-for="opt in payStatusOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>
            <p v-if="errors.pay_status" class="mt-1 text-xs text-hospital-danger">
                {{ errors.pay_status }}
            </p>
        </div>
    </div>
</template>

<style scoped>
.bk-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.bk-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

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
</style>
