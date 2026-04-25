<script setup lang="ts">
import Modal from '@/components/shared/Modal.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface InventoryItem {
    id: string;
    name: string;
    code: string;
    sell_price: number;
    quantity: number;
}

interface SupplyItem {
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
}

const props = defineProps<{
    modelValue: boolean;
    surgeryId: string;
    inventoryItems: InventoryItem[];
    dept: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const items = ref<SupplyItem[]>([{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }]);
const submitting = ref(false);

const total = computed(() => items.value.reduce((sum, i) => sum + i.qty * i.unit_cost, 0));

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            items.value = [{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }];
        }
    },
);

function addRow() {
    items.value.push({ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 });
}

function removeRow(idx: number) {
    items.value.splice(idx, 1);
}

function selectItem(row: SupplyItem, id: string) {
    const inv = props.inventoryItems.find((i) => i.id === id);
    if (inv) {
        row.inventory_item_id = id;
        row.name = inv.name;
        row.unit_cost = inv.sell_price;
    }
}

function submit() {
    submitting.value = true;
    router.post(
        `/${props.dept}/${props.surgeryId}/supplies`,
        { surgery_id: props.surgeryId, items: items.value },
        {
            onSuccess: () => {
                emit('update:modelValue', false);
                emit('success');
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function close() {
    emit('update:modelValue', false);
}
</script>

<template>
    <Modal :model-value="modelValue" title="تسجيل المستلزمات" size="lg" @update:model-value="close">
        <div class="space-y-3">
            <div
                v-for="(item, idx) in items"
                :key="idx"
                class="grid grid-cols-12 items-center gap-2"
            >
                <select
                    :value="item.inventory_item_id"
                    class="field-input col-span-5"
                    @change="selectItem(item, ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">— اختر صنف —</option>
                    <option v-for="inv in inventoryItems" :key="inv.id" :value="inv.id">
                        {{ inv.name }} ({{ inv.code }}) — متوفر: {{ inv.quantity }}
                    </option>
                </select>

                <input
                    v-model.number="item.qty"
                    type="number"
                    min="1"
                    placeholder="الكمية"
                    class="field-input col-span-2"
                />

                <input
                    v-model.number="item.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="السعر"
                    class="field-input col-span-3"
                />

                <span class="col-span-1 text-center text-xs font-semibold text-hospital-text-2">
                    {{ (item.qty * item.unit_cost).toLocaleString('ar-EG') }}
                </span>

                <button
                    type="button"
                    class="col-span-1 flex h-9 w-9 items-center justify-center rounded-lg text-lg text-hospital-danger hover:bg-red-50"
                    @click="removeRow(idx)"
                >
                    ×
                </button>
            </div>

            <button type="button" class="text-sm text-[#7B2FA6] hover:underline" @click="addRow">
                + إضافة صنف
            </button>

            <div class="flex items-center justify-between border-t border-hospital-border pt-3">
                <span class="text-sm font-bold text-hospital-text">
                    الإجمالي: {{ total.toLocaleString('ar-EG') }} ج.م
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                        @click="close"
                    >
                        إلغاء
                    </button>
                    <button
                        type="button"
                        :disabled="submitting"
                        class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-[#6A2890] disabled:opacity-60"
                        @click="submit"
                    >
                        تسجيل
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.field-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 7px;
    font-size: 13px;
    font-family: inherit;
    color: var(--color-hospital-text, #0d1f3c);
    background: #fff;
    direction: rtl;
}
.field-input:focus {
    outline: none;
    border-color: #7b2fa6;
    box-shadow: 0 0 0 3px rgba(123, 47, 166, 0.1);
}
</style>
