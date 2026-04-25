<script setup lang="ts">
import { computed } from 'vue';

interface OrBed {
    id: number;
    bed_number: number;
    status: string;
    surgery?: { id: string; status: string } | null;
}

interface OrRoom {
    id: number;
    name: string;
    beds: OrBed[];
}

interface Props {
    modelValue: string;
    orRooms: OrRoom[];
    dept: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const bedPickerColor = computed(() =>
    props.dept === 'lasik' ? '#7B2FA6' : '#27AE60',
);

const hasRooms = computed(() => props.orRooms.length > 0);

function isBedOccupied(bed: OrBed): boolean {
    return !!bed.surgery && bed.surgery !== null;
}

function selectBed(bed: OrBed) {
    if (isBedOccupied(bed)) {
return;
}

    const newValue = props.modelValue === String(bed.id) ? '' : String(bed.id);
    emit('update:modelValue', newValue);
}
</script>

<template>
    <div>
        <label class="bk-label">رقم السرير</label>
        <div v-if="hasRooms" class="bed-picker-panel">
            <div class="bed-picker-legend">
                <span class="legend-dot" :style="{ background: bedPickerColor }" />
                فارغ
                <span class="legend-dot legend-dot-busy" />
                مشغول
                <span class="legend-dot legend-dot-selected" :style="{ background: bedPickerColor, opacity: '1' }" />
                محدد
            </div>
            <div v-for="room in orRooms" :key="room.id" class="bed-room">
                <p class="bed-room-label">{{ room.name }}</p>
                <div class="bed-room-row">
                    <button
                        v-for="bed in room.beds"
                        :key="bed.id"
                        type="button"
                        :class="[
                            'bk-bed',
                            isBedOccupied(bed) ? 'bk-bed-busy' : 'bk-bed-free',
                            modelValue === String(bed.id) ? 'bk-bed-selected' : '',
                        ]"
                        :style="modelValue === String(bed.id) ? { background: bedPickerColor, borderColor: bedPickerColor } : {}"
                        :title="isBedOccupied(bed) ? `${room.name} - سرير ${bed.bed_number} مشغول` : `${room.name} - سرير ${bed.bed_number}`"
                        @click="selectBed(bed)"
                    >
                        <span class="bk-bed-num">{{ bed.bed_number }}</span>
                        <span v-if="isBedOccupied(bed)" class="bk-bed-busy-dot" />
                    </button>
                </div>
            </div>
            <p v-if="modelValue" class="bed-picker-selected" :style="{ color: bedPickerColor }">
                ✓ سرير {{ modelValue }} محدد
            </p>
        </div>
        <input
            v-else
            :value="modelValue"
            type="number"
            min="1"
            max="9999"
            placeholder="مثال: 5"
            class="bk-input"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />
    </div>
</template>

<style scoped>
.bk-label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 3px;
}

.bk-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid #dde4ef;
    border-radius: 7px;
    font-size: 12px;
    font-family: inherit;
    color: #0d1f3c;
    background: #fff;
    direction: rtl;
    transition: border-color 0.15s;
}

.bk-input:focus {
    outline: none;
    border-color: #0a4fa6;
    box-shadow: 0 0 0 3px rgba(10, 79, 166, 0.1);
}

.bed-picker-panel {
    background: #f3f6fa;
    border: 1.5px solid #dde4ef;
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bed-picker-legend {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    color: #4a5878;
    font-weight: 600;
    border-bottom: 1px solid #dde4ef;
    padding-bottom: 7px;
}

.legend-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
    opacity: 0.35;
}

.legend-dot-busy {
    background: #e74c3c;
    opacity: 1;
}

.legend-dot-selected {
    opacity: 1;
}

.bed-room {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.bed-room-label {
    font-size: 10px;
    font-weight: 700;
    color: #4a5878;
    text-transform: uppercase;
    letter-spacing: 0.4px;
}

.bed-room-row {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.bk-bed {
    width: 44px;
    height: 38px;
    border-radius: 7px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    font-family: inherit;
    padding: 0;
    position: relative;
    gap: 2px;
}

.bk-bed-num {
    font-size: 13px;
    font-weight: 800;
    color: #0d1f3c;
    line-height: 1;
}

.bk-bed-free:hover {
    border-color: currentColor;
    background: #f0f7ff;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
}

.bk-bed-free:hover .bk-bed-num {
    color: inherit;
}

.bk-bed-busy {
    background: #fff0ee;
    border-color: #e74c3c;
    cursor: not-allowed;
    opacity: 0.75;
}

.bk-bed-busy .bk-bed-num {
    color: #e74c3c;
}

.bk-bed-busy-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #e74c3c;
}

.bk-bed-selected {
    color: #fff !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.bk-bed-selected .bk-bed-num {
    color: #fff !important;
}

.bed-picker-selected {
    font-size: 11px;
    font-weight: 700;
    margin: 0;
    padding-top: 6px;
    border-top: 1px solid #dde4ef;
}
</style>