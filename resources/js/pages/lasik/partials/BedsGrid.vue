<script setup lang="ts">
import { CalendarPlus } from 'lucide-vue-next';
import { computed } from 'vue';

interface Surgery {
    id: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    surgeon: { id: string; name: string } | null;
    or_bed_id: number | null;
    status: 'scheduled' | 'prep' | 'in_progress' | 'completed' | 'cancelled';
}

interface OrBed {
    id: number;
    bed_number: number;
    status: string;
    surgery?: Surgery | null;
}

interface OrRoom {
    id: number;
    name: string;
    beds: OrBed[];
}

const props = defineProps<{
    orRooms: OrRoom[];
    surgeries: Surgery[];
}>();

const emit = defineEmits<{
    openCase: [surgery: Surgery];
    scheduleNew: [];
    openReport: [id: string];
    openSupplies: [id: string];
}>();

const statusColor: Record<string, string> = {
    scheduled: '#7B2FA6',
    prep: '#2980B9',
    in_progress: '#E74C3C',
    completed: '#1A8C5B',
    cancelled: '#95A5A6',
};

const statusAr: Record<string, string> = {
    scheduled: 'مجدولة',
    prep: 'تحضير',
    in_progress: 'جارية',
    completed: 'مكتملة',
    cancelled: 'ملغاة',
};

const eyeLabel: Record<string, string> = {
    OD: 'يمنى',
    OS: 'يسرى',
    OU: 'كلاهما',
};

const bedMap = computed(() => {
    const map: { room: OrRoom; bed: OrBed; surgery: Surgery | null }[] = [];
    props.orRooms.forEach((room) => {
        room.beds.forEach((bed) => {
            const surgery = props.surgeries.find((s) => s.or_bed_id === bed.id) ?? null;
            map.push({ room, bed, surgery });
        });
    });
    return map;
});

const totalBeds = computed(() => bedMap.value.length);
</script>

<template>
    <div class="beds-section">
        <div class="beds-section-hd">
            <div>
                <h2 class="beds-title">
                    لوحة أسرّة قسم الليزك
                    <span class="beds-count">({{ totalBeds }} سرير)</span>
                </h2>
                <p class="beds-sub">اضغط على السرير لعرض بيانات الحالة</p>
                <div class="beds-legend">
                    <span class="legend-item"><span class="legend-dot" style="background:#7B2FA6" />مجدولة</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#2980B9" />تحضير</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#E74C3C" />جارية</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#1A8C5B" />مكتملة</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#BDC3C7" />فارغة</span>
                </div>
            </div>
            <button class="schedule-btn" @click="emit('scheduleNew')">
                <CalendarPlus class="h-4 w-4" />
                جدولة ليزك
            </button>
        </div>

        <div class="beds-grid">
            <div
                v-for="(item, idx) in bedMap"
                :key="idx"
                class="bed-card"
                :style="
                    item.surgery
                        ? { background: statusColor[item.surgery.status] ?? '#7B2FA6' }
                        : { background: '#BDC3C7', opacity: '0.75' }
                "
                @click="item.surgery ? emit('openCase', item.surgery) : emit('scheduleNew')"
            >
                <div class="bed-header">
                    <span class="bed-room-label">{{ item.room.name }}</span>
                    <span class="bed-num">{{ item.bed.bed_number }}</span>
                    <span v-if="item.surgery" class="bed-status-badge">
                        {{ statusAr[item.surgery.status] }}
                    </span>
                </div>

                <div v-if="item.surgery" class="bed-body">
                    <p class="bed-patient">{{ item.surgery.booking?.patient_name ?? '—' }}</p>
                    <p class="bed-detail"><span>الإجراء:</span><strong>{{ item.surgery.procedure }}</strong></p>
                    <p class="bed-detail">
                        <span>الطبيب:</span><strong>{{ item.surgery.surgeon?.name ?? '—' }}</strong>
                    </p>
                    <p v-if="item.surgery.eye" class="bed-detail">
                        <span>العين:</span><strong>{{ eyeLabel[item.surgery.eye] ?? item.surgery.eye }}</strong>
                    </p>
                    <div class="bed-actions" @click.stop>
                        <button class="bed-action-btn" @click="emit('openReport', item.surgery!.id)">
                            ▶ تقرير
                        </button>
                        <button class="bed-action-btn" @click="emit('openSupplies', item.surgery!.id)">
                            💊 مستلزمات
                        </button>
                    </div>
                </div>

                <div v-else class="bed-empty">
                    <div class="bed-empty-icon">🛏️</div>
                    <p class="bed-empty-label">سرير فارغ</p>
                    <div class="bed-empty-action">+ جدولة ليزك</div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.beds-section {
    background: #fff;
    border: 1px solid var(--color-hospital-border, #dde4ef);
    border-radius: 12px;
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 16px;
}
.beds-section-hd {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 14px 16px 12px;
    border-bottom: 1px solid var(--color-hospital-border, #dde4ef);
    background: var(--color-hospital-bg, #f3f6fa);
    gap: 12px;
}
.beds-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--color-hospital-text, #0d1f3c);
    margin: 0 0 2px;
}
.beds-count {
    font-size: 12px;
    font-weight: 500;
    color: var(--color-hospital-text-3, #8a96ae);
}
.beds-sub {
    font-size: 11px;
    color: var(--color-hospital-text-3, #8a96ae);
    margin: 0 0 8px;
}
.beds-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    font-size: 10px;
    color: var(--color-hospital-text-2, #4a5878);
    font-weight: 600;
}
.legend-item {
    display: flex;
    align-items: center;
    gap: 4px;
}
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    display: inline-block;
    flex-shrink: 0;
}
.schedule-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: #7B2FA6;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.15s;
}
.schedule-btn:hover { background: #6A2890; }

.beds-grid {
    padding: 14px;
    display: grid;
    grid-template-columns: repeat(10, 1fr);
    gap: 8px;
}
@media (max-width: 1024px) { .beds-grid { grid-template-columns: repeat(7, 1fr); } }
@media (max-width: 640px)  { .beds-grid { grid-template-columns: repeat(5, 1fr); } }

.bed-card {
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    color: #fff;
    box-shadow: 0 3px 12px rgba(0,0,0,0.18);
    transition: transform 0.18s, box-shadow 0.18s;
    min-height: 110px;
}
.bed-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.28);
}
.bed-header {
    background: rgba(0,0,0,0.18);
    padding: 6px 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
}
.bed-room-label {
    font-size: 9px;
    opacity: 0.8;
    font-weight: 600;
    flex-basis: 100%;
    line-height: 1;
}
.bed-num {
    font-size: 15px;
    font-weight: 900;
    line-height: 1;
}
.bed-status-badge {
    font-size: 8px;
    background: rgba(255,255,255,0.25);
    padding: 1px 6px;
    border-radius: 10px;
    margin-right: auto;
}
.bed-body {
    padding: 7px 8px;
    font-size: 10px;
    line-height: 1.8;
}
.bed-patient {
    font-size: 11px;
    font-weight: 800;
    margin-bottom: 2px;
    line-height: 1.3;
}
.bed-detail {
    display: flex;
    gap: 3px;
    font-size: 10px;
}
.bed-detail span { opacity: 0.75; }
.bed-actions {
    display: flex;
    gap: 4px;
    margin-top: 6px;
}
.bed-action-btn {
    flex: 1;
    padding: 3px 4px;
    background: rgba(255,255,255,0.22);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.45);
    border-radius: 5px;
    cursor: pointer;
    font-size: 9px;
    font-family: inherit;
    transition: background 0.15s;
}
.bed-action-btn:hover { background: rgba(255,255,255,0.35); }
.bed-empty {
    padding: 14px 8px;
    text-align: center;
}
.bed-empty-icon { font-size: 20px; }
.bed-empty-label { font-size: 10px; opacity: 0.8; margin: 3px 0; }
.bed-empty-action {
    font-size: 9px;
    background: rgba(255,255,255,0.3);
    border-radius: 4px;
    padding: 2px 6px;
    display: inline-block;
}
</style>