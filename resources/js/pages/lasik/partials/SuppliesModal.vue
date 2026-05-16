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

interface BundleItem {
    inventory_item_id: string | null;
    item_name: string;
    qty: number;
    unit_cost: number;
}

interface Bundle {
    id: string;
    name: string;
    price: number;
    items: BundleItem[];
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
    bundles: Bundle[];
    dept: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const items = ref<SupplyItem[]>([{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }]);
const submitting = ref(false);

// Bundle expansion state
const newBundlePick = ref('');

interface ExpandedBundleItem extends BundleItem {
    selected: boolean;
}

interface SelectedBundle {
    bundle_id: string;
    name: string;
    price: number;
    selected_items: { inventory_item_id: string; qty: number }[];
    items_label: string;
    bundle_total: number;
}

const expandedBundleItems = ref<ExpandedBundleItem[]>([]);
const expandedBundleId = ref('');
const expandedBundleName = ref('');

const selectedBundles = ref<SelectedBundle[]>([]);

const itemsTotal = computed(() => items.value.reduce((sum, i) => sum + i.qty * i.unit_cost, 0));
const bundlesTotal = computed(() => selectedBundles.value.reduce((sum, b) => sum + b.bundle_total, 0));
const total = computed(() => itemsTotal.value + bundlesTotal.value);

watch(newBundlePick, (bundleId) => {
    if (!bundleId) {
        expandedBundleItems.value = [];
        return;
    }
    const bundle = props.bundles.find((b) => b.id === bundleId);
    if (!bundle) return;
    expandedBundleId.value = bundle.id;
    expandedBundleName.value = bundle.name;
    expandedBundleItems.value = bundle.items.map((item) => ({
        ...item,
        qty: Number(item.qty),
        unit_cost: Number(item.unit_cost),
        selected: !!item.inventory_item_id,
    }));
});

function confirmAddBundle() {
    const selected = expandedBundleItems.value.filter((i) => i.selected && i.inventory_item_id);
    if (selected.length === 0) return;
    const bundleTotal = selected.reduce((s, i) => s + i.qty * i.unit_cost, 0);
    selectedBundles.value.push({
        bundle_id: expandedBundleId.value,
        name: expandedBundleName.value,
        price: 0,
        selected_items: selected.map((i) => ({ inventory_item_id: i.inventory_item_id!, qty: i.qty })),
        items_label: `${selected.length} ${selected.length === 1 ? 'صنف' : 'أصناف'}`,
        bundle_total: bundleTotal,
    });
    newBundlePick.value = '';
    expandedBundleItems.value = [];
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
            expandedBundleItems.value = [];
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
            items: items.value.filter((i) => i.inventory_item_id),
            bundles: selectedBundles.value.map((b) => ({
                bundle_id: b.bundle_id,
                qty: 1,
                selected_items: b.selected_items,
            })),
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
                <select v-model="newBundlePick" class="field-input text-sm">
                    <option value="">— اختر بنداً لعرض محتوياته —</option>
                    <option v-for="b in bundles" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>

                <!-- Bundle items expansion -->
                <div v-if="expandedBundleItems.length" class="mt-3 rounded-lg border border-purple-300 bg-white p-3">
                    <div class="mb-2 text-xs font-semibold text-purple-800">اختر المستلزمات المستخدمة من بند: {{ expandedBundleName }}</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="(item, idx) in expandedBundleItems"
                            :key="idx"
                            class="flex items-center gap-2 rounded border px-3 py-2 text-sm"
                            :class="item.selected ? 'border-purple-200 bg-purple-50' : 'border-gray-100 bg-gray-50'"
                        >
                            <input
                                v-model="item.selected"
                                type="checkbox"
                                class="h-4 w-4 rounded"
                                :disabled="!item.inventory_item_id"
                            />
                            <span class="flex-1" :class="item.selected ? 'font-medium text-gray-900' : 'text-gray-400'">
                                {{ item.item_name }}
                                <span v-if="!item.inventory_item_id" class="text-xs text-gray-400">(غير مرتبط)</span>
                            </span>
                            <input
                                v-if="item.selected && item.inventory_item_id"
                                v-model.number="item.qty"
                                type="number"
                                min="0.01"
                                step="0.01"
                                class="field-input w-20 text-center"
                            />
                            <span v-else class="w-20 text-center text-gray-400">{{ item.qty }}</span>
                            <span class="w-28 text-left text-xs text-gray-500">{{ Number(item.unit_cost).toLocaleString('ar-EG') }} ج/وحدة</span>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-purple-100 pt-2">
                        <span class="text-xs text-gray-500">
                            {{ expandedBundleItems.filter(i => i.selected && i.inventory_item_id).length }} أصناف محددة
                        </span>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-100"
                                @click="newBundlePick = ''"
                            >إلغاء</button>
                            <button
                                type="button"
                                class="rounded-lg bg-purple-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-purple-800"
                                @click="confirmAddBundle"
                            >+ إضافة البند</button>
                        </div>
                    </div>
                </div>

                <!-- Confirmed bundles -->
                <div v-if="selectedBundles.length" class="mt-2 space-y-1.5">
                    <div
                        v-for="(b, idx) in selectedBundles"
                        :key="idx"
                        class="flex items-center gap-2 rounded-md bg-white px-3 py-2 text-sm shadow-sm"
                    >
                        <span class="flex-1 font-medium text-purple-800">📦 {{ b.name }}</span>
                        <span class="rounded-full bg-purple-100 px-2 py-0.5 text-xs text-purple-700">{{ b.items_label }}</span>
                        <span class="w-24 text-left font-semibold text-purple-700">{{ Number(b.bundle_total).toLocaleString('ar-EG') }} ج</span>
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
