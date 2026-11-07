<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import type { Option } from './examination';

export interface DiagnosisRow {
    diagnosis_id: string | null;
    name: string;
    eye: string;
    notes: string;
}

/**
 * Multi-diagnosis entry backed by the diagnoses catalog. Typing a name that
 * matches a catalog entry links it; an unknown name is sent as free text and
 * added to the catalog by the backend.
 */
const rows = defineModel<DiagnosisRow[]>({ required: true });

const props = defineProps<{
    catalog: { id: string; name: string; code: string | null }[];
    eyeOptions: Option[];
    errors?: Record<string, string | undefined>;
    readOnly?: boolean;
}>();

function addRow(): void {
    rows.value.push({ diagnosis_id: null, name: '', eye: '', notes: '' });
}

function removeRow(index: number): void {
    rows.value.splice(index, 1);
}

function onNameInput(row: DiagnosisRow, value: string): void {
    row.name = value;
    const match = props.catalog.find((entry) => entry.name.toLowerCase() === value.trim().toLowerCase());
    row.diagnosis_id = match?.id ?? null;
}

function codeFor(row: DiagnosisRow): string | null {
    return props.catalog.find((entry) => entry.id === row.diagnosis_id)?.code ?? null;
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="rows.length === 0" class="rounded-lg border border-dashed border-hospital-border p-4 text-center text-xs text-hospital-text-3">
            لم يُضف أي تشخيص بعد
        </div>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid grid-cols-1 gap-2 rounded-lg border border-hospital-border p-2 sm:grid-cols-[minmax(0,2fr)_140px_minmax(0,1.5fr)_auto]"
        >
            <div>
                <div class="relative">
                    <input
                        :value="row.name"
                        list="diagnosis-catalog"
                        type="text"
                        dir="ltr"
                        placeholder="ابحث عن التشخيص… (e.g. Myopia)"
                        :readonly="readOnly"
                        class="input-field"
                        :class="errors?.[`diagnoses.${index}.diagnosis_id`] ? 'border-hospital-danger' : ''"
                        @input="onNameInput(row, ($event.target as HTMLInputElement).value)"
                    />
                    <span
                        v-if="codeFor(row)"
                        class="pointer-events-none absolute end-2 top-1/2 -translate-y-1/2 rounded bg-hospital-bg px-1.5 text-[10px] font-semibold text-hospital-text-3"
                    >{{ codeFor(row) }}</span>
                </div>
                <p v-if="row.name && !row.diagnosis_id && !readOnly" class="mt-0.5 text-[11px] text-hospital-text-3">
                    تشخيص جديد — سيُضاف إلى قائمة التشخيصات
                </p>
                <p v-if="errors?.[`diagnoses.${index}.diagnosis_id`]" class="form-error">{{ errors[`diagnoses.${index}.diagnosis_id`] }}</p>
            </div>
            <select v-model="row.eye" :disabled="readOnly" class="input-field" aria-label="العين">
                <option value="">— العين —</option>
                <option v-for="option in eyeOptions" :key="option.value" :value="option.value">
                    {{ option.value }} · {{ option.label }}
                </option>
            </select>
            <input v-model="row.notes" type="text" placeholder="ملاحظات التشخيص" :readonly="readOnly" class="input-field" />
            <button
                v-if="!readOnly"
                type="button"
                class="flex h-9 w-9 items-center justify-center self-start rounded-lg text-hospital-danger hover:bg-hospital-danger-pale"
                title="حذف التشخيص"
                @click="removeRow(index)"
            >
                <Trash2 class="h-4 w-4" />
            </button>
        </div>

        <button v-if="!readOnly" type="button" class="btn-secondary gap-1.5 text-xs" @click="addRow">
            <Plus class="h-3.5 w-3.5" />
            إضافة تشخيص
        </button>

        <datalist id="diagnosis-catalog">
            <option v-for="entry in catalog" :key="entry.id" :value="entry.name">{{ entry.code }}</option>
        </datalist>
    </div>
</template>
