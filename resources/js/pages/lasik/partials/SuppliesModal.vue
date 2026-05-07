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

interface Bundle {
    id: string;
    name: string;
    price: number;
}

const props = defineProps<{
    modelValue: boolean;
    surgeryId: string;
    inventoryItems: InventoryItem[];
    bundles: Bundle[];
    dept: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const items = ref<SupplyItem[]>([{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }]);
const submitting = ref(false);

const newBundlePick = ref('');
const selectedBundles = ref<{ bundle_id: string; name: string; price: number; qty: number }[]>([]);

const itemsTotal = computed(() => items.value.reduce((sum, i) => sum + i.qty * i.unit_cost, 0));
const bundlesTotal = computed(() => selectedBundles.value.reduce((sum, b) => sum + b.price * b.qty, 0));
const total = computed(() => itemsTotal.value + bundlesTotal.value);

function addBundleToSelected() {
    if (!newBundlePick.value) return;
    const bundle = props.bundles.find((b) => b.id === newBundlePick.value);
    if (!bundle) return;
    selectedBundles.value.push({ bundle_id: bundle.id, name: bundle.name, price: bundle.price, qty: 1 });
    newBundlePick.value = '';
}

function removeBundleFromSelected(idx: number) {
    selectedBundles.value.splice(idx, 1);
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            items.value = [{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }];
            selectedBundles.value = [];
            newBundlePick.value = '';
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
        {
            surgery_id: props.surgeryId,
            items: items.value,
            bundles: selectedBundles.value.map((b) => ({ bundle_id: b.bundle_id, qty: b.qty })),
        },
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

            <!-- Bundle picker -->
            <div v-if="bundles.length" class="rounded-lg border border-purple-200 bg-purple-50 p-3">
                <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-purple-700">📦 البنود الجاهزة</div>
                <div class="flex gap-2">
                    <select v-model="newBundlePick" class="field-input flex-1">
                        <option value="">— اختر بنداً —</option>
                        <option v-for="b in bundles" :key="b.id" :value="b.id">
                            {{ b.name }} — {{ Number(b.price).toLocaleString('ar-EG') }} ج
                        </option>
                    </select>
                    <button
                        type="button"
                        class="rounded-lg bg-purple-700 px-3 py-1.5 text-sm text-white hover:bg-purple-800"
                        @click="addBundleToSelected"
                    >+ إضافة</button>
                </div>
                <div v-if="selectedBundles.length" class="mt-2 space-y-1.5">
                    <div
                        v-for="(b, idx) in selectedBundles"
                        :key="idx"
                        class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm shadow-sm"
                    >
                        <span class="flex-1 font-medium text-purple-800">📦 {{ b.name }}</span>
                        <input v-model.number="b.qty" type="number" min="1" class="field-input w-16 text-center" />
                        <span class="w-24 text-left font-semibold text-purple-700">{{ (b.price * b.qty).toLocaleString('ar-EG') }} ج</span>
                        <button type="button" class="text-red-400 hover:text-red-600" @click="removeBundleFromSelected(idx)">×</button>
                    </div>
                </div>
            </div>

            <!-- Individual items -->
            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">أصناف فردية</div>
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
