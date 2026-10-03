<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface Service {
    id: string;
    name: string;
}

interface Doctor {
    id: string;
    name: string;
}

interface Props {
    modelValue: {
        service_id: string;
        doctor_id: string;
    };
    services: Service[];
    doctors: Doctor[];
    isEditMode?: boolean;
    hideService?: boolean;
    errors?: Record<string, string>;
}

const props = withDefaults(defineProps<Props>(), {
    isEditMode: false,
    hideService: false,
    errors: () => ({}),
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: Props['modelValue']): void;
}>();

function update(field: keyof Props['modelValue'], value: string) {
    emit('update:modelValue', { ...props.modelValue, [field]: value });
}

/**
 * Normalizes Arabic letter variants so "احمد" matches "أحمد", "ة" matches "ه", etc.
 */
function normalizeSearchText(text: string): string {
    return text
        .toLowerCase()
        .replace(/[ً-ْـ]/g, '')
        .replace(/[أإآ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .trim();
}

const serviceQuery = ref('');
const isServiceListOpen = ref(false);
const highlightedServiceIndex = ref(0);

const selectedService = computed(() =>
    props.services.find((svc) => svc.id === props.modelValue.service_id),
);

const filteredServices = computed(() => {
    const query = normalizeSearchText(serviceQuery.value);

    if (!query) {
        return props.services;
    }

    return props.services.filter((svc) =>
        normalizeSearchText(svc.name).includes(query),
    );
});

watch(filteredServices, () => {
    highlightedServiceIndex.value = 0;
});

function openServiceList() {
    serviceQuery.value = '';
    isServiceListOpen.value = true;
}

function closeServiceList() {
    setTimeout(() => {
        isServiceListOpen.value = false;
        serviceQuery.value = '';
    }, 150);
}

function selectService(svc: Service) {
    update('service_id', svc.id);
    isServiceListOpen.value = false;
    serviceQuery.value = '';
}

function onServiceKeydown(event: KeyboardEvent) {
    if (!isServiceListOpen.value) {
        isServiceListOpen.value = true;

        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedServiceIndex.value = Math.min(
            highlightedServiceIndex.value + 1,
            filteredServices.value.length - 1,
        );
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedServiceIndex.value = Math.max(
            highlightedServiceIndex.value - 1,
            0,
        );
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const svc = filteredServices.value[highlightedServiceIndex.value];

        if (svc) {
            selectService(svc);
        }
    } else if (event.key === 'Escape') {
        isServiceListOpen.value = false;
        serviceQuery.value = '';
    }
}
</script>

<template>
    <div class="bk-section">
        <span class="bk-title bk-title-teal">{{
            isEditMode ? 'الخدمة والدفع' : 'الخدمة'
        }}</span>
        <div class="bk-grid-2">
            <div v-if="!hideService">
                <label class="bk-label">الخدمة</label>
                <div class="relative">
                    <input
                        type="text"
                        class="bk-input"
                        :class="{ 'border-hospital-danger': errors.service_id }"
                        :value="
                            isServiceListOpen
                                ? serviceQuery
                                : (selectedService?.name ?? '')
                        "
                        :placeholder="
                            isServiceListOpen
                                ? (selectedService?.name ?? 'ابحث عن الخدمة...')
                                : '— اختر الخدمة —'
                        "
                        autocomplete="off"
                        @focus="openServiceList"
                        @click="isServiceListOpen = true"
                        @blur="closeServiceList"
                        @input="
                            serviceQuery = ($event.target as HTMLInputElement)
                                .value;
                            isServiceListOpen = true;
                        "
                        @keydown="onServiceKeydown"
                    />
                    <ul
                        v-if="isServiceListOpen"
                        class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-br bg-sf shadow-lg"
                    >
                        <li
                            v-for="(svc, index) in filteredServices"
                            :key="svc.id"
                            class="cursor-pointer px-3 py-2 text-xs text-t hover:bg-sf2"
                            :class="{
                                'bg-sf2': index === highlightedServiceIndex,
                                'font-bold': svc.id === modelValue.service_id,
                            }"
                            @mousedown.prevent="selectService(svc)"
                            @mouseenter="highlightedServiceIndex = index"
                        >
                            {{ svc.name }}
                        </li>
                        <li
                            v-if="filteredServices.length === 0"
                            class="px-3 py-2 text-xs text-t3"
                        >
                            لا توجد نتائج
                        </li>
                    </ul>
                </div>
                <p
                    v-if="errors.service_id"
                    class="mt-1 text-xs text-hospital-danger"
                >
                    {{ errors.service_id }}
                </p>
            </div>
            <div>
                <label class="bk-label">الطبيب</label>
                <select
                    :value="modelValue.doctor_id"
                    class="bk-input"
                    :class="{ 'border-hospital-danger': errors.doctor_id }"
                    @change="
                        update(
                            'doctor_id',
                            ($event.target as HTMLSelectElement).value,
                        )
                    "
                >
                    <option value="">— اختر الطبيب —</option>
                    <option v-for="dr in doctors" :key="dr.id" :value="dr.id">
                        {{ dr.name }}
                    </option>
                </select>
                <p
                    v-if="errors.doctor_id"
                    class="mt-1 text-xs text-hospital-danger"
                >
                    {{ errors.doctor_id }}
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bk-section {
    background: var(--color-hospital-bg, #f3f6fa);
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 14px;
}

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

.bk-title-teal {
    background: #00b5a4;
}

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
</style>
