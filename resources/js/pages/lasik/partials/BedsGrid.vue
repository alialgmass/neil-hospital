<script setup lang="ts">
import { CalendarPlus } from 'lucide-vue-next';
import { computed } from 'vue';

interface Surgery {
    id: string;
    dept: string;
    booking: { file_no: string; patient_name: string };
    procedure: string;
    eye: 'OD' | 'OS' | 'OU' | null;
    surgeon: { id: string; name: string } | null;
    or_bed_id: number | null;
    scheduled_at: string | null;
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
    dept: string;
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
    const list: { bed: OrBed; surgery: Surgery | null; displayNumber: number }[] = [];
    let seq = 1;
    props.orRooms.forEach((room) => {
        room.beds.forEach((bed) => {
            list.push({ bed, surgery: bed.surgery ?? null, displayNumber: seq++ });
        });
    });
    return list;
});

const totalBeds = computed(() => bedMap.value.length);

function isOwnDept(surgery: Surgery): boolean {
    return surgery.dept === props.dept;
}
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
                    <span class="legend-item"><span class="legend-dot" style="background:#E67E22" />قسم آخر</span>
                </div>
            </div>
            <button class="schedule-btn" @click="emit('scheduleNew')">
                <CalendarPlus class="h-4 w-4" />
                جدولة ليزك
            </button>
        </div>

        <div class="beds-grid">
            <div
                v-for="item in bedMap"
                :key="item.bed.id"
                class="bed-card"
                :style="
                    item.surgery
                        ? { background: isOwnDept(item.surgery) ? (statusColor[item.surgery.status] ?? '#7B2FA6') : '#E67E22' }
                        : { background: '#BDC3C7', opacity: '0.75' }
                "
                @click="item.surgery && isOwnDept(item.surgery) ? emit('openCase', item.surgery) : emit('scheduleNew')"
            >
                <div class="bed-card-hd">
                    <span class="text-[13px] font-black">سرير {{ item.displayNumber }}</span>
                    <span v-if="item.surgery" class="bed-status-badge">
                        {{ isOwnDept(item.surgery) ? statusAr[item.surgery.status] : 'قسم آخر' }}
                    </span>
                </div>

                <div v-if="item.surgery && isOwnDept(item.surgery)" class="bed-card-body">
                    <p class="mb-1 text-[14px] font-extrabold leading-tight">
                        {{ item.surgery.booking?.patient_name ?? '—' }}
                    </p>
                    <p class="bed-info-row"><span>الإجراء:</span><strong>{{ item.surgery.procedure || '—' }}</strong></p>
                    <p class="bed-info-row">
                        <span>الطبيب:</span><strong>{{ item.surgery.surgeon?.name ?? '—' }}</strong>
                    </p>
                    <p v-if="item.surgery.eye" class="bed-info-row">
                        <span>العين:</span><strong>{{ eyeLabel[item.surgery.eye] ?? item.surgery.eye }}</strong>
                    </p>
                    <p v-if="item.surgery.scheduled_at" class="bed-info-row">
                        <span>الموعد:</span><strong>{{ item.surgery.scheduled_at.slice(0, 16).replace('T', ' ') }}</strong>
                    </p>
                    <div class="mt-2 flex gap-1.5" @click.stop>
                        <button class="bed-action-btn" @click="emit('openReport', item.surgery!.id)">
                            📋 تقرير
                        </button>
                        <button class="bed-action-btn" @click="emit('openSupplies', item.surgery!.id)">
                            💊 مستلزمات
                        </button>
                    </div>
                </div>

                <div v-else-if="item.surgery && !isOwnDept(item.surgery)" class="bed-card-empty">
                    <div class="text-2xl">🔒</div>
                    <p class="mt-1 text-[11px] opacity-90 font-semibold">مشغول بقسم آخر</p>
                </div>

                <div v-else class="bed-card-empty">
                    <div class="text-2xl">🛏️</div>
                    <p class="mt-1 text-[11px] opacity-80">سرير فارغ</p>
                    <div class="mt-2 rounded bg-white/30 px-2 py-1 text-[10px]">+ جدولة ليزك</div>
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
    grid-template-columns: repeat(1, 1fr);
    gap: 12px;
}
@media (min-width: 640px)  { .beds-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .beds-grid { grid-template-columns: repeat(3, 1fr); } }

.bed-card {
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    color: #fff;
    box-shadow: 0 3px 12px rgba(0,0,0,0.18);
    transition: transform 0.18s, box-shadow 0.18s;
}
.bed-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.28);
}
.bed-card-hd {
    background: rgba(0,0,0,0.18);
    padding: 8px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bed-status-badge {
    font-size: 9px;
    background: rgba(255,255,255,0.25);
    padding: 2px 8px;
    border-radius: 12px;
}
.bed-card-body {
    padding: 10px 12px;
    font-size: 11px;
    line-height: 1.85;
}
.bed-info-row { display: flex; gap: 4px; }
.bed-info-row span { opacity: 0.75; }
.bed-action-btn {
    flex: 1;
    padding: 5px 4px;
    background: rgba(255,255,255,0.22);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.45);
    border-radius: 5px;
    cursor: pointer;
    font-size: 10px;
    font-family: inherit;
    transition: background 0.15s;
    white-space: nowrap;
}
.bed-action-btn:hover { background: rgba(255,255,255,0.35); }
.bed-card-empty {
    padding: 20px 12px;
    text-align: center;
}
</style>
