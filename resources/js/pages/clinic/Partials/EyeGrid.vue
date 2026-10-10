<script setup lang="ts">
import { EYE_LABELS, EYES } from './examination';
import type { Eye, EyeFindings, EyeRow } from './examination';

/**
 * Side-by-side OD / OS entry grid. The OD column always comes first (right
 * side in RTL, i.e. the patient's right eye) and each eye keeps its own
 * colour so the two are never confused while typing.
 */
const eyes = defineModel<Record<Eye, EyeFindings>>('eyes', { required: true });

const props = defineProps<{
    rows: EyeRow[];
    errors?: Record<string, string | undefined>;
    readOnly?: boolean;
    idPrefix: string;
}>();

function errorFor(eye: Eye, key: string): string | undefined {
    return props.errors?.[`eyes.${eye}.${key}`];
}

const eyeHeader: Record<Eye, string> = {
    OD: 'bg-hospital-primary-pale text-hospital-primary border-hospital-primary/30',
    OS: 'bg-hospital-accent-pale text-hospital-accent border-hospital-accent/30',
};

const eyeCell: Record<Eye, string> = {
    OD: 'border-s-4 border-s-hospital-primary/40',
    OS: 'border-s-4 border-s-hospital-accent/40',
};
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[520px] border-separate border-spacing-0 text-sm">
            <thead>
                <tr>
                    <th class="w-[34%] p-2 text-start text-[11px] font-semibold uppercase tracking-wide text-hospital-text-3">Measurement</th>
                    <th
                        v-for="eye in EYES"
                        :key="eye"
                        class="rounded-t-lg border p-2 text-center"
                        :class="eyeHeader[eye]"
                    >
                        <span class="block text-base font-extrabold tracking-wider">{{ eye }}</span>
                        <span class="block text-[11px] font-medium">{{ EYE_LABELS[eye] }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.key" class="align-top">
                    <td class="border-t border-hospital-border p-2 text-xs font-semibold text-hospital-text-2" dir="ltr" style="text-align: start">
                        {{ row.label }}
                    </td>
                    <td
                        v-for="eye in EYES"
                        :key="eye"
                        class="border-t border-hospital-border p-1.5"
                        :class="eyeCell[eye]"
                    >
                        <input
                            v-model="eyes[eye][row.key]"
                            :type="row.type ?? 'text'"
                            :step="row.step"
                            :min="row.min"
                            :max="row.max"
                            :placeholder="readOnly ? '' : row.placeholder"
                            :list="row.suggestions ? `${idPrefix}-${row.key}-list` : undefined"
                            :aria-label="`${row.label} ${eye}`"
                            :readonly="readOnly"
                            dir="ltr"
                            class="w-full rounded-md border bg-hospital-bg px-2 py-1.5 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none read-only:border-transparent read-only:bg-transparent"
                            :class="errorFor(eye, row.key) ? 'border-hospital-danger' : 'border-hospital-border'"
                        />
                        <p v-if="errorFor(eye, row.key)" class="mt-0.5 text-[11px] text-hospital-danger">{{ errorFor(eye, row.key) }}</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <template v-for="row in rows" :key="`list-${row.key}`">
            <datalist v-if="row.suggestions" :id="`${idPrefix}-${row.key}-list`">
                <option v-for="suggestion in row.suggestions" :key="suggestion" :value="suggestion" />
            </datalist>
        </template>
    </div>
</template>
