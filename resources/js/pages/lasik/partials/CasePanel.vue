<script setup lang="ts">
import { X } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, computed } from 'vue';

interface SupplyUsedItem {
    inventory_item_id: string;
    name: string;
    qty: number;
    unit_cost: number;
    total: number;
}

interface Surgery {
    id: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    surgeon: { id: string; name: string } | null;
    or_bed_id: number | null;
    bed_no: number | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
    scheduled_at: string | null;
    supply_total: number;
    supplies_used: SupplyUsedItem[] | null;
    anaesthesia: string | null;
    op_report?: string | null;
    post_op_notes?: string | null;
    complications?: string | null;
    pre_op_notes?: string | null;
}

interface InventoryItem {
    id: string;
    name: string;
    code: string;
    quantity: number;
    sell_price: number;
}

const props = defineProps<{
    surgery: Surgery;
    inventoryItems: InventoryItem[];
}>();

const emit = defineEmits<{
    close: [];
    openReport: [id: string];
    openSupplies: [id: string];
    updateStatus: [status: string];
    submitSupplies: [items: SupplyUsedItem[]];
    submitReport: [data: { op_report: string; post_op_notes: string; complications: string }];
}>();

const activeOverlayTab = ref<'supplies' | 'report' | 'status'>('supplies');
const newSupplyItems = ref<SupplyUsedItem[]>([{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }]);
const reportForm = ref({ op_report: '', post_op_notes: '', complications: '' });

const eyeLabel: Record<string, string> = {
    OD: 'عين يمنى',
    OS: 'عين يسرى',
    OU: 'كلاهما',
};

const statusAr: Record<string, string> = {
    scheduled: 'مجدولة',
    prep: 'تحضير',
    in_progress: 'جارية',
    completed: 'مكتملة',
    cancelled: 'ملغاة',
};

const bedBg: Record<string, string> = {
    scheduled: '#2980B9',
    prep: '#E67E22',
    in_progress: '#E74C3C',
    completed: '#1A8C5B',
    cancelled: '#95A5A6',
};

const nextStatuses = computed(() => {
    const c = props.surgery.status;
    const map: Record<string, { value: string; label: string; color: string }[]> = {
        scheduled: [
            { value: 'prep', label: 'بدء التحضير', color: '#2980B9' },
            { value: 'cancelled', label: 'إلغاء', color: '#95A5A6' },
        ],
        prep: [
            { value: 'in_progress', label: 'بدء الجلسة', color: '#E74C3C' },
            { value: 'cancelled', label: 'إلغاء', color: '#95A5A6' },
        ],
        in_progress: [
            { value: 'completed', label: 'اكتمال الجلسة ✓', color: '#1A8C5B' },
            { value: 'cancelled', label: 'إلغاء', color: '#95A5A6' },
        ],
    };

    return c ? (map[c] ?? []) : [];
});

const newSuppliesTotal = computed(() => {
    return newSupplyItems.value.reduce((sum, item) => sum + (item.qty * item.unit_cost), 0);
});

function selectNewSupplyItem(item: SupplyUsedItem, inventoryItemId: string) {
    const inv = props.inventoryItems.find(i => i.id === inventoryItemId);
    if (inv) {
        item.inventory_item_id = inv.id;
        item.name = inv.name;
        item.unit_cost = inv.sell_price;
    }
}

function addNewSupplyRow() {
    newSupplyItems.value.push({ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 });
}

function removeNewSupplyRow(idx: number) {
    newSupplyItems.value.splice(idx, 1);
    if (newSupplyItems.value.length === 0) {
        addNewSupplyRow();
    }
}

function clearSupplyRows() {
    newSupplyItems.value = [{ inventory_item_id: '', name: '', qty: 1, unit_cost: 0 }];
}

function submitOverlaySupplies() {
    const validItems = newSupplyItems.value.filter(item => item.name && item.qty > 0);
    if (validItems.length > 0) {
        emit('submitSupplies', validItems);
        clearSupplyRows();
    }
}

function submitOverlayReport() {
    emit('submitReport', {
        op_report: reportForm.value.op_report,
        post_op_notes: reportForm.value.post_op_notes,
        complications: reportForm.value.complications,
    });
}

function clearReportForm() {
    reportForm.value = { op_report: '', post_op_notes: '', complications: '' };
}

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div class="case-overlay-backdrop" @click.self="emit('close')">
            <div class="case-overlay-panel">

                <!-- Sticky header -->
                <div class="case-overlay-hdr">
                    <div>
                        <div class="text-[16px] font-extrabold">
                            {{ surgery.booking?.patient_name ?? '—' }}
                        </div>
                        <div class="text-[12px] opacity-75">
                            {{ surgery.procedure || 'عملية' }}
                            <span v-if="surgery.bed_no"> — سرير {{ surgery.bed_no }}</span>
                        </div>
                    </div>
                    <button class="case-close-btn" @click="emit('close')">×</button>
                </div>

                <!-- Patient summary bar -->
                <div class="case-patient-bar">
                    <span><strong>ملف:</strong> {{ surgery.booking?.file_no ?? '—' }}</span>
                    <span><strong>الطبيب:</strong> {{ surgery.surgeon?.name ?? '—' }}</span>
                    <span><strong>الحالة:</strong> {{ statusAr[surgery.status] }}</span>
                    <span v-if="surgery.eye"><strong>العين:</strong> {{ eyeLabel[surgery.eye!] ?? surgery.eye }}</span>
                    <span v-if="surgery.anaesthesia"><strong>التخدير:</strong> {{ surgery.anaesthesia }}</span>
                    <span v-if="surgery.scheduled_at">
                        <strong>الموعد:</strong> {{ surgery.scheduled_at.slice(0, 16).replace('T', ' ') }}
                    </span>
                    <span>
                        <strong>المستلزمات:</strong>
                        {{ Number(surgery.supply_total).toLocaleString('ar-EG') }} ج
                    </span>
                </div>

                <!-- Tab bar -->
                <div class="case-tab-bar">
                    <button
                        :class="['case-tab', activeOverlayTab === 'supplies' ? 'case-tab-active' : '']"
                        @click="activeOverlayTab = 'supplies'"
                    >مستلزمات العملية</button>
                    <button
                        :class="['case-tab', activeOverlayTab === 'report' ? 'case-tab-active' : '']"
                        @click="activeOverlayTab = 'report'"
                    >تقرير العملية</button>
                    <button
                        :class="['case-tab', activeOverlayTab === 'status' ? 'case-tab-active' : '']"
                        @click="activeOverlayTab = 'status'"
                    >تحديث الحالة</button>
                </div>

                <!-- Tab content -->
                <div class="case-overlay-body">

                    <!-- ===== SUPPLIES TAB ===== -->
                    <div v-if="activeOverlayTab === 'supplies'">
                        <!-- Add supply form -->
                        <div class="overlay-card mb-4">
                            <div class="overlay-card-hd">إضافة مستلزم عملية</div>
                            <div class="p-4">
                                <div
                                    v-for="(item, idx) in newSupplyItems"
                                    :key="idx"
                                    class="mb-2 grid grid-cols-12 items-center gap-2"
                                >
                                    <select
                                        :value="item.inventory_item_id"
                                        class="overlay-input col-span-5"
                                        @change="selectNewSupplyItem(item, ($event.target as HTMLSelectElement).value)"
                                    >
                                        <option value="">— اختر من المخزن —</option>
                                        <option v-for="inv in inventoryItems" :key="inv.id" :value="inv.id">
                                            {{ inv.name }} ({{ inv.code }}) — {{ inv.quantity }} متوفر
                                        </option>
                                    </select>
                                    <input
                                        v-model="item.name"
                                        type="text"
                                        placeholder="الاسم"
                                        class="overlay-input col-span-2"
                                    />
                                    <input
                                        v-model.number="item.qty"
                                        type="number"
                                        min="1"
                                        placeholder="الكمية"
                                        class="overlay-input col-span-2"
                                    />
                                    <input
                                        v-model.number="item.unit_cost"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="السعر"
                                        class="overlay-input col-span-2"
                                    />
                                    <button
                                        class="col-span-1 flex h-8 w-8 items-center justify-center rounded text-hospital-danger hover:bg-hospital-danger/10"
                                        @click="removeNewSupplyRow(idx)"
                                    >×</button>
                                </div>
                                <button class="mt-2 text-sm text-[#1A8C5B] hover:underline" @click="addNewSupplyRow">
                                    + إضافة صنف آخر
                                </button>
                                <!-- Total preview -->
                                <div v-if="newSuppliesTotal > 0" class="overlay-total-preview">
                                    الإجمالي المضاف:
                                    <strong style="color:#1A8C5B;font-size:14px">
                                        {{ newSuppliesTotal.toLocaleString('ar-EG') }} ج
                                    </strong>
                                </div>
                                <div class="mt-3 flex justify-end gap-2">
                                    <button class="overlay-btn-grey" @click="clearSupplyRows">
                                        مسح
                                    </button>
                                    <button class="overlay-btn-green" @click="submitOverlaySupplies">
                                        حفظ المستلزمات ✓
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Existing supplies table -->
                        <div class="overlay-card overflow-hidden">
                            <div class="overlay-card-hd-green flex items-center justify-between">
                                <span>المستلزمات المضافة</span>
                                <span class="text-xs font-normal opacity-80">
                                    الإجمالي: {{ Number(surgery.supply_total).toLocaleString('ar-EG') }} ج
                                </span>
                            </div>
                            <div v-if="surgery.supplies_used && surgery.supplies_used.length" class="overflow-x-auto">
                                <table class="supply-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>الصنف</th>
                                            <th>الكمية</th>
                                            <th>السعر/الوحدة</th>
                                            <th>الإجمالي</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(s, i) in surgery.supplies_used" :key="i">
                                            <td>{{ i + 1 }}</td>
                                            <td>{{ s.name || '—' }}</td>
                                            <td>{{ s.qty }}</td>
                                            <td>{{ Number(s.unit_cost).toLocaleString('ar-EG') }} ج</td>
                                            <td class="font-semibold">{{ Number(s.total).toLocaleString('ar-EG') }} ج</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-right font-bold">الإجمالي الكلي</td>
                                            <td class="font-bold text-[#1A8C5B]">
                                                {{ Number(surgery.supply_total).toLocaleString('ar-EG') }} ج
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <div v-else class="p-6 text-center text-sm text-hospital-text-2">
                                لا توجد مستلزمات مسجلة بعد
                            </div>
                        </div>
                    </div>

                    <!-- ===== REPORT TAB ===== -->
                    <div v-if="activeOverlayTab === 'report'">
                        <div class="overlay-card">
                            <div class="overlay-card-hd">تقرير العملية</div>
                            <form class="space-y-4 p-4" @submit.prevent="submitOverlayReport">
                                <div>
                                    <label class="overlay-label">تقرير العملية التفصيلي</label>
                                    <textarea
                                        v-model="reportForm.op_report"
                                        rows="5"
                                        class="overlay-input"
                                        placeholder="اكتب تقرير العملية هنا..."
                                    />
                                </div>
                                <div>
                                    <label class="overlay-label">ملاحظات ما بعد العملية</label>
                                    <textarea
                                        v-model="reportForm.post_op_notes"
                                        rows="3"
                                        class="overlay-input"
                                        placeholder="ملاحظات ما بعد العملية..."
                                    />
                                </div>
                                <div>
                                    <label class="overlay-label">المضاعفات</label>
                                    <textarea
                                        v-model="reportForm.complications"
                                        rows="2"
                                        class="overlay-input"
                                        placeholder="إن وجدت..."
                                    />
                                </div>
                                <div class="flex justify-end gap-2 border-t border-hospital-border pt-3">
                                    <button type="button" class="overlay-btn-grey" @click="clearReportForm">
                                        مسح
                                    </button>
                                    <button type="submit" class="overlay-btn-green">
                                        حفظ التقرير ✓
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Show existing report if exists -->
                        <div v-if="surgery.op_report" class="overlay-card mt-4">
                            <div class="overlay-card-hd">التقرير المحفوظ</div>
                            <div class="space-y-3 p-4 text-sm text-hospital-text">
                                <div>
                                    <p class="mb-1 font-semibold text-hospital-text-2">تقرير العملية:</p>
                                    <p class="whitespace-pre-wrap">{{ surgery.op_report }}</p>
                                </div>
                                <div v-if="surgery.post_op_notes">
                                    <p class="mb-1 font-semibold text-hospital-text-2">ملاحظات ما بعد العملية:</p>
                                    <p class="whitespace-pre-wrap">{{ surgery.post_op_notes }}</p>
                                </div>
                                <div v-if="surgery.complications">
                                    <p class="mb-1 font-semibold text-hospital-text-2">المضاعفات:</p>
                                    <p class="whitespace-pre-wrap text-hospital-danger">{{ surgery.complications }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== STATUS TAB ===== -->
                    <div v-if="activeOverlayTab === 'status'">
                        <div class="overlay-card">
                            <div class="overlay-card-hd">تحديث حالة العملية</div>
                            <div class="p-5">
                                <!-- Current status -->
                                <div class="mb-5 flex items-center gap-3">
                                    <p class="text-sm font-medium text-hospital-text-2">الحالة الحالية:</p>
                                    <span
                                        class="rounded-full px-4 py-1 text-sm font-bold text-white"
                                        :style="{ background: bedBg[surgery.status] ?? '#999' }"
                                    >
                                        {{ statusAr[surgery.status] }}
                                    </span>
                                </div>
                                <!-- Transition buttons -->
                                <div class="flex flex-wrap gap-3">
                                    <button
                                        v-for="s in nextStatuses"
                                        :key="s.value"
                                        class="rounded-lg px-6 py-2.5 text-sm font-bold text-white transition-opacity hover:opacity-90"
                                        :style="{ background: s.color }"
                                        @click="emit('updateStatus', s.value)"
                                    >
                                        {{ s.label }}
                                    </button>
                                </div>

                                <!-- Pre-op notes display -->
                                <div v-if="surgery.pre_op_notes" class="mt-5 rounded-lg bg-hospital-bg p-3 text-sm">
                                    <p class="mb-1 font-semibold text-hospital-text-2">ملاحظات ما قبل العملية:</p>
                                    <p class="whitespace-pre-wrap text-hospital-text">{{ surgery.pre_op_notes }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* ── Case overlay (matches surgery/Index.vue) ── */
.case-overlay-backdrop {
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(7, 46, 99, 0.45);
    backdrop-filter: blur(3px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.case-overlay-panel {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 580px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(7, 46, 99, 0.22);
    overflow: hidden;
}
.case-overlay-hdr {
    background: linear-gradient(135deg, #27ae60, #2ecc71);
    padding: 13px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.case-close-btn {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: #fff;
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}
.case-close-btn:hover { background: rgba(255, 255, 255, 0.35); }

/* ── Patient summary bar ── */
.case-patient-bar {
    background: #f3f6fa;
    padding: 10px 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    font-size: 12px;
    color: var(--color-hospital-text, #0d1f3c);
    border-bottom: 1px solid var(--color-hospital-border, #dde4ef);
}
.case-patient-bar strong { font-weight: 700; color: var(--color-hospital-text-2, #4a5878); }

/* ── Tab bar ── */
.case-tab-bar {
    display: flex;
    background: #fff;
    border-bottom: 2px solid var(--color-hospital-border, #dde4ef);
}
.case-tab {
    flex: 1;
    padding: 10px 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-hospital-text-2, #4a5878);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
}
.case-tab:hover { color: var(--color-hospital-primary, #0a4fa6); background: #f8fafc; }
.case-tab-active { color: #27ae60; border-bottom-color: #27ae60; background: #f0faf5; }

/* ── Tab body ── */
.case-overlay-body { padding: 14px; overflow-y: auto; flex: 1; }

/* ── Cards ── */
.overlay-card {
    background: #fff;
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 10px;
    overflow: hidden;
}
.overlay-card-hd {
    background: #f3f6fa;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-hospital-text, #0d1f3c);
    border-bottom: 1px solid var(--color-hospital-border, #dde4ef);
}
.overlay-card-hd-green {
    background: #f0faf5;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: 700;
    color: #1a8c5b;
    border-bottom: 1px solid #1a8c5b30;
}

/* ── Inputs ── */
.overlay-input {
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
.overlay-input:focus { outline: none; border-color: #1a8c5b; box-shadow: 0 0 0 3px rgba(26, 140, 91, 0.12); }
.overlay-label { display: block; font-size: 12px; font-weight: 600; color: var(--color-hospital-text-2, #4a5878); margin-bottom: 5px; }
.overlay-total-preview {
    margin-top: 10px;
    background: #f0faf5;
    border: 1px solid #1a8c5b;
    border-radius: 6px;
    padding: 8px 12px;
    font-size: 12px;
    color: var(--color-hospital-text, #0d1f3c);
}
.overlay-btn-green {
    padding: 8px 20px;
    background: #1a8c5b;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    font-weight: 600;
    transition: background 0.15s;
}
.overlay-btn-green:hover { background: #0f6040; }
.overlay-btn-grey {
    padding: 8px 20px;
    background: #95a5a6;
    color: #fff;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    font-family: inherit;
    transition: background 0.15s;
}
.overlay-btn-grey:hover { background: #7f8c8d; }

/* ── Supplies table ── */
.supply-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.supply-table th {
    background: #f0faf5;
    padding: 8px 12px;
    text-align: right;
    font-weight: 700;
    color: #1a8c5b;
    border-bottom: 1px solid #1a8c5b30;
}
.supply-table td { padding: 8px 12px; border-bottom: 1px solid var(--color-hospital-border, #dde4ef); }
.supply-table tbody tr:hover { background: #f9fafb; }
.supply-table tfoot td { background: #f0faf5; padding: 8px 12px; border-top: 2px solid #1a8c5b30; }
</style>