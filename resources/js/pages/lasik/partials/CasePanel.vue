<script setup lang="ts">
import { ClipboardList, X } from 'lucide-vue-next';
import { onMounted, onUnmounted } from 'vue';

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
}

defineProps<{ surgery: Surgery }>();

const emit = defineEmits<{
    close: [];
    openReport: [id: string];
    openSupplies: [id: string];
    updateStatus: [status: string];
}>();

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

const anaesthesiaAr: Record<string, string> = {
    local: 'موضعي',
    topical: 'سطحي',
    sedation: 'مهدئ',
    general: 'عام',
};

const nextStatuses: Record<string, { value: string; label: string; color: string }[]> = {
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

function onKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition name="case-modal">
            <div class="overlay" @click.self="emit('close')">
                <!-- Backdrop -->
                <div class="overlay-bg" @click="emit('close')" />

                <!-- Panel -->
                <div class="case-panel" role="dialog" aria-modal="true">
                    <div class="panel-hd">
                        <div>
                            <p class="panel-patient">{{ surgery.booking?.patient_name ?? '—' }}</p>
                            <p class="panel-sub">
                                {{ surgery.procedure }}
                                {{ surgery.bed_no ? `— سرير ${surgery.bed_no}` : '— بدون سرير' }}
                            </p>
                        </div>
                        <button class="panel-close" @click="emit('close')">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="panel-body">
                        <div class="info-grid">
                            <div class="info-cell">
                                <p class="info-lbl">الحالة</p>
                                <p class="info-val">{{ statusAr[surgery.status] ?? surgery.status }}</p>
                            </div>
                            <div class="info-cell">
                                <p class="info-lbl">العين</p>
                                <p class="info-val">{{ surgery.eye ? (eyeLabel[surgery.eye] ?? surgery.eye) : '—' }}</p>
                            </div>
                            <div class="info-cell">
                                <p class="info-lbl">الطبيب</p>
                                <p class="info-val">{{ surgery.surgeon?.name ?? '—' }}</p>
                            </div>
                            <div class="info-cell">
                                <p class="info-lbl">الموعد</p>
                                <p class="info-val">{{ surgery.scheduled_at ? surgery.scheduled_at.slice(0, 10) : '—' }}</p>
                            </div>
                            <div class="info-cell">
                                <p class="info-lbl">رقم الملف</p>
                                <p class="info-val">{{ surgery.booking?.file_no ?? '—' }}</p>
                            </div>
                            <div class="info-cell">
                                <p class="info-lbl">التخدير</p>
                                <p class="info-val">
                                    {{ surgery.anaesthesia ? (anaesthesiaAr[surgery.anaesthesia] ?? surgery.anaesthesia) : '—' }}
                                </p>
                            </div>
                        </div>

                        <p class="supplies-heading">المستلزمات المستخدمة في الليزك</p>

                        <div class="supplies-list">
                            <template v-if="surgery.supplies_used && surgery.supplies_used.length">
                                <div
                                    v-for="(item, idx) in surgery.supplies_used"
                                    :key="idx"
                                    class="supply-row"
                                >
                                    <span class="supply-name">{{ item.name }}</span>
                                    <span class="supply-qty">× {{ item.qty }}</span>
                                    <span class="supply-cost">
                                        {{ Number(item.unit_cost).toLocaleString('ar-EG') }} ج
                                    </span>
                                </div>
                            </template>
                            <p v-else class="supplies-empty">لا توجد مستلزمات مسجلة بعد</p>
                        </div>

                        <button class="add-supply-btn" @click="emit('openSupplies', surgery.id)">
                            + إضافة مستلزم
                        </button>

                        <div class="totals-bar">
                            <div class="totals-row">
                                <span class="totals-lbl">إجمالي المستلزمات</span>
                                <span class="totals-val">
                                    {{ Number(surgery.supply_total).toLocaleString('ar-EG') }} ج
                                </span>
                            </div>
                        </div>

                        <div class="panel-actions">
                            <button class="action-btn-outline" @click="emit('openReport', surgery.id)">
                                <ClipboardList class="h-3.5 w-3.5" />
                                تقرير العملية
                            </button>
                        </div>

                        <div v-if="nextStatuses[surgery.status]?.length" class="status-section">
                            <p class="status-lbl">تحديث الحالة:</p>
                            <div class="status-btns">
                                <button
                                    v-for="s in nextStatuses[surgery.status]"
                                    :key="s.value"
                                    class="status-btn"
                                    :style="{ background: s.color }"
                                    @click="emit('updateStatus', s.value)"
                                >
                                    {{ s.label }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* ── Overlay ── */
.overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.overlay-bg {
    position: absolute;
    inset: 0;
    background: rgba(7, 46, 99, 0.45);
    backdrop-filter: blur(3px);
}

/* ── Centered panel ── */
.case-panel {
    position: relative;
    z-index: 10;
    width: 100%;
    max-width: 480px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(7, 46, 99, 0.22);
    overflow: hidden;
}
.panel-hd {
    background: linear-gradient(135deg, #7B2FA6, #9B4FC6);
    padding: 13px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.panel-patient { font-size: 14px; font-weight: 700; color: #fff; margin: 0 0 3px; }
.panel-sub { font-size: 11px; color: rgba(255,255,255,0.65); margin: 0; }
.panel-close {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: none; color: #fff;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0;
    transition: background 0.15s;
}
.panel-close:hover { background: rgba(255,255,255,0.28); }

.panel-body { padding: 14px; overflow-y: auto; flex: 1; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 14px; }
.info-cell { background: var(--color-hospital-bg, #f3f6fa); border-radius: 7px; padding: 8px 10px; }
.info-lbl { font-size: 10px; color: var(--color-hospital-text-3, #8a96ae); margin: 0 0 2px; }
.info-val { font-size: 12px; font-weight: 600; color: var(--color-hospital-text, #0d1f3c); margin: 0; }

.supplies-heading {
    font-size: 11px; font-weight: 700; color: #7B2FA6;
    border-bottom: 2px solid #F3E8FD;
    padding-bottom: 5px; margin-bottom: 8px;
}
.supplies-list {
    min-height: 40px;
    background: var(--color-hospital-bg, #f3f6fa);
    border-radius: 8px; padding: 8px 10px; margin-bottom: 10px;
}
.supply-row {
    display: flex; align-items: center; gap: 6px;
    font-size: 11px; padding: 3px 0;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}
.supply-row:last-child { border-bottom: none; }
.supply-name { flex: 1; color: var(--color-hospital-text, #0d1f3c); font-weight: 500; }
.supply-qty { font-size: 10px; color: var(--color-hospital-text-3, #8a96ae); }
.supply-cost { font-weight: 700; color: #7B2FA6; font-size: 11px; }
.supplies-empty { font-size: 11px; color: var(--color-hospital-text-3, #8a96ae); margin: 0; text-align: center; padding: 6px 0; }

.add-supply-btn {
    width: 100%; padding: 8px;
    background: #7B2FA6; color: #fff;
    border: none; border-radius: 8px;
    font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: inherit;
    margin-bottom: 12px; transition: background 0.15s;
}
.add-supply-btn:hover { background: #6A2890; }

.totals-bar {
    background: linear-gradient(135deg, #5B2080, #7B2FA6);
    border-radius: 8px; padding: 10px 13px; margin-bottom: 12px;
}
.totals-row { display: flex; justify-content: space-between; align-items: center; }
.totals-lbl { font-size: 11px; color: rgba(255,255,255,0.75); }
.totals-val { font-size: 13px; font-weight: 800; color: #fff; }

.panel-actions { margin-bottom: 12px; }
.action-btn-outline {
    width: 100%; display: flex; align-items: center; justify-content: center; gap: 5px;
    padding: 8px; border-radius: 8px;
    font-size: 12px; font-weight: 600;
    background: transparent; border: 1.5px solid #7B2FA6; color: #7B2FA6;
    cursor: pointer; font-family: inherit; transition: all 0.15s;
}
.action-btn-outline:hover { background: #f5eeff; }

.status-section { margin-top: 4px; }
.status-lbl { font-size: 10px; font-weight: 600; color: var(--color-hospital-text-3, #8a96ae); margin-bottom: 6px; }
.status-btns { display: flex; flex-wrap: wrap; gap: 6px; }
.status-btn {
    padding: 6px 12px; border-radius: 6px;
    font-size: 11px; font-weight: 700;
    color: #fff; border: none; cursor: pointer;
    font-family: inherit; transition: opacity 0.15s;
}
.status-btn:hover { opacity: 0.85; }

/* ── Transition ── */
.case-modal-enter-active,
.case-modal-leave-active {
    transition: opacity 0.2s ease;
}
.case-modal-enter-from,
.case-modal-leave-to {
    opacity: 0;
}
.case-modal-enter-active .case-panel,
.case-modal-leave-active .case-panel {
    transition: transform 0.22s ease;
}
.case-modal-enter-from .case-panel,
.case-modal-leave-to .case-panel {
    transform: scale(0.94) translateY(10px);
}
</style>