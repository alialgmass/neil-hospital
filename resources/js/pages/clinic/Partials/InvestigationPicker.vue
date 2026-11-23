<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Option } from './examination';

export interface InvestigationRow {
    service_id: string;
    eye: string;
    notes: string;
}

/**
 * Investigations are the existing Labs / Pentacam services — the doctor
 * toggles the ones needed, then sets the eye and any note per request.
 */
const rows = defineModel<InvestigationRow[]>({ required: true });

const props = defineProps<{
    services: { id: string; name: string; dept: string }[];
    eyeOptions: Option[];
    defaultEye?: string | null;
    readOnly?: boolean;
}>();

const deptLabels: Record<string, string> = {
    labs: 'الفحوصات',
    pentacam: 'البنتكام',
};

const groupedServices = computed(() => {
    const groups: Record<string, { id: string; name: string; dept: string }[]> = {};

    for (const service of props.services) {
        (groups[service.dept] ??= []).push(service);
    }

    return groups;
});

const serviceNames = computed(() => Object.fromEntries(props.services.map((service) => [service.id, service.name])));

function isSelected(serviceId: string): boolean {
    return rows.value.some((row) => row.service_id === serviceId);
}

function toggle(serviceId: string): void {
    if (props.readOnly) {
        return;
    }

    const index = rows.value.findIndex((row) => row.service_id === serviceId);

    if (index >= 0) {
        rows.value.splice(index, 1);
    } else {
        rows.value.push({ service_id: serviceId, eye: props.defaultEye ?? '', notes: '' });
    }
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="services.length === 0 && !readOnly" class="text-xs text-hospital-text-3">
            لا توجد خدمات فحوصات مفعّلة — أضفها من شاشة الخدمات (قسم الفحوصات أو البنتكام).
        </p>

        <div v-if="!readOnly" class="space-y-3">
            <div v-for="(group, dept) in groupedServices" :key="dept">
                <p class="mb-1.5 text-[11px] font-semibold text-hospital-text-3">{{ deptLabels[dept] ?? dept }}</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="service in group"
                        :key="service.id"
                        type="button"
                        dir="ltr"
                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                        :class="isSelected(service.id)
                            ? 'border-hospital-primary bg-hospital-primary text-white'
                            : 'border-hospital-border text-hospital-text-2 hover:border-hospital-primary hover:text-hospital-primary'"
                        :aria-pressed="isSelected(service.id)"
                        @click="toggle(service.id)"
                    >
                        <Check v-if="isSelected(service.id)" class="h-3.5 w-3.5" />
                        {{ service.name }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="rows.length > 0" class="divide-y divide-hospital-border rounded-lg border border-hospital-border">
            <div
                v-for="row in rows"
                :key="row.service_id"
                class="grid grid-cols-1 items-center gap-2 p-2 sm:grid-cols-[minmax(0,1.5fr)_140px_minmax(0,2fr)]"
            >
                <span class="text-sm font-semibold text-hospital-text" dir="ltr" style="text-align: start">{{ serviceNames[row.service_id] ?? '—' }}</span>
                <select v-model="row.eye" :disabled="readOnly" class="input-field" aria-label="العين">
                    <option value="">— العين —</option>
                    <option v-for="option in eyeOptions" :key="option.value" :value="option.value">{{ option.value }}</option>
                </select>
                <input v-model="row.notes" type="text" placeholder="ملاحظات للفني" :readonly="readOnly" class="input-field" />
            </div>
        </div>
        <p v-else-if="readOnly" class="text-xs text-hospital-text-3">لم تُطلب فحوصات</p>
    </div>
</template>
