<script setup lang="ts">
import { computed, ref } from 'vue';

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
    { value: 'contract', label: 'تعاقد' },
];

const payStatusOptions = [
    { value: 'unpaid', label: 'لم يسدد' },
    { value: 'partial', label: 'جزئي' },
    { value: 'paid', label: 'مسدد' },
];


function update(field: keyof Props['modelValue'], value: string) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

/* ── Searchable insurance-company picker ── */
const insSearch = ref('');
const insOpen = ref(false);

const selectedInsCompanyName = computed(
    () =>
        props.insuranceCompanies.find(
            (c) => c.id === props.modelValue.ins_company_id,
        )?.name ?? '',
);

const filteredInsCompanies = computed(() => {
    const term = insSearch.value.trim().toLowerCase();

    if (!term) {
        return props.insuranceCompanies;
    }

    return props.insuranceCompanies.filter((c) =>
        c.name.toLowerCase().includes(term),
    );
});

function selectInsCompany(company: InsuranceCompany) {
    update('ins_company_id', company.id);
    insSearch.value = '';
    insOpen.value = false;
}

function clearInsCompany() {
    update('ins_company_id', '');
    insSearch.value = '';
}

function closeInsOnBlur() {
    setTimeout(() => {
        insOpen.value = false;
        insSearch.value = '';
    }, 150);
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
                <label class="bk-label">شركة التأمين <span class="text-hospital-danger">*</span></label>
                <div class="bk-combo">
                    <input
                        :value="insOpen ? insSearch : selectedInsCompanyName"
                        type="text"
                        placeholder="ابحث عن شركة التأمين…"
                        class="bk-input"
                        :class="{ 'border-hospital-danger': errors.ins_company_id }"
                        autocomplete="off"
                        @focus="insOpen = true"
                        @blur="closeInsOnBlur"
                        @input="
                            insSearch = ($event.target as HTMLInputElement).value;
                            insOpen = true;
                        "
                    />
                    <button
                        v-if="modelValue.ins_company_id && !insOpen"
                        type="button"
                        class="bk-combo-clear"
                        @mousedown.prevent="clearInsCompany"
                    >
                        ×
                    </button>
                    <ul v-if="insOpen" class="bk-combo-list">
                        <li
                            v-for="ins in filteredInsCompanies"
                            :key="ins.id"
                            class="bk-combo-item"
                            :class="{ 'bk-combo-item-active': ins.id === modelValue.ins_company_id }"
                            @mousedown.prevent="selectInsCompany(ins)"
                        >
                            {{ ins.name }}
                        </li>
                        <li v-if="!filteredInsCompanies.length" class="bk-combo-empty">
                            لا توجد نتائج
                        </li>
                    </ul>
                </div>
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

.bk-combo {
    position: relative;
}

.bk-combo-clear {
    position: absolute;
    inset-inline-start: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    font-size: 15px;
    line-height: 1;
    color: #8a96ae;
    cursor: pointer;
}

.bk-combo-list {
    position: absolute;
    z-index: 20;
    inset-inline: 0;
    margin-top: 3px;
    max-height: 190px;
    overflow-y: auto;
    list-style: none;
    padding: 3px;
    border: 1.5px solid #dde4ef;
    border-radius: 7px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(10, 79, 166, 0.12);
}

.bk-combo-item {
    padding: 6px 9px;
    border-radius: 5px;
    font-size: 12px;
    color: #0d1f3c;
    cursor: pointer;
}

.bk-combo-item:hover {
    background: #f3f6fa;
}

.bk-combo-item-active {
    background: #e8f1fb;
    color: #0a4fa6;
    font-weight: 600;
}

.bk-combo-empty {
    padding: 6px 9px;
    font-size: 12px;
    color: #8a96ae;
}
</style>
