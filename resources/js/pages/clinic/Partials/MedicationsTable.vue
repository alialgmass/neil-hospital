<script setup lang="ts">
interface MedicationRow {
    name: string;
    dose: string;
    route: string;
    frequency: string;
    remarks: string;
}

const props = defineProps<{
    modelValue: MedicationRow[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: MedicationRow[]): void;
}>();

function emptyRow(): MedicationRow {
    return { name: '', dose: '', route: '', frequency: '', remarks: '' };
}

function addRow() {
    emit('update:modelValue', [...props.modelValue, emptyRow()]);
}

function removeRow(index: number) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index));
}

function updateRow(index: number, field: keyof MedicationRow, value: string) {
    const rows = props.modelValue.map((row, i) => (i === index ? { ...row, [field]: value } : row));
    emit('update:modelValue', rows);
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] border-collapse text-xs">
            <thead>
                <tr class="text-hospital-text-2">
                    <th class="w-6"></th>
                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">اسم الدواء</th>
                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">الجرعة</th>
                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">طريقة الاستخدام</th>
                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">التكرار</th>
                    <th class="border border-hospital-border bg-hospital-bg p-1.5 text-right">ملاحظات</th>
                    <th class="w-8"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(row, index) in modelValue" :key="index">
                    <td class="border border-hospital-border p-1 text-center text-hospital-text-3">{{ index + 1 }}</td>
                    <td class="border border-hospital-border p-1">
                        <input :value="row.name" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" @input="updateRow(index, 'name', ($event.target as HTMLInputElement).value)" />
                    </td>
                    <td class="border border-hospital-border p-1">
                        <input :value="row.dose" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" @input="updateRow(index, 'dose', ($event.target as HTMLInputElement).value)" />
                    </td>
                    <td class="border border-hospital-border p-1">
                        <input :value="row.route" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" @input="updateRow(index, 'route', ($event.target as HTMLInputElement).value)" />
                    </td>
                    <td class="border border-hospital-border p-1">
                        <input :value="row.frequency" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" @input="updateRow(index, 'frequency', ($event.target as HTMLInputElement).value)" />
                    </td>
                    <td class="border border-hospital-border p-1">
                        <input :value="row.remarks" type="text" class="w-full rounded border-0 bg-transparent p-1 text-xs focus:outline-none" @input="updateRow(index, 'remarks', ($event.target as HTMLInputElement).value)" />
                    </td>
                    <td class="border border-hospital-border p-1 text-center">
                        <button type="button" class="text-hospital-danger hover:underline" :disabled="modelValue.length <= 1" @click="removeRow(index)">×</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <button
            type="button"
            class="mt-2 rounded-lg border border-dashed border-hospital-primary px-3 py-1 text-xs font-medium text-hospital-primary hover:bg-hospital-primary/5"
            @click="addRow"
        >
            + إضافة دواء
        </button>
    </div>
</template>
