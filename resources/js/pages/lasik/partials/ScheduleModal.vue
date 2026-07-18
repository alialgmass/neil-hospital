<script setup lang="ts">
import Modal from '@/components/shared/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface OrBed {
    id: number;
    bed_number: number;
    status: string;
    surgery?: { status: string } | null;
}

interface OrRoom {
    id: number;
    name: string;
    beds: OrBed[];
}

const props = defineProps<{
    modelValue: boolean;
    orRooms: OrRoom[];
    doctors: { id: string; name: string }[];
    bookings: { id: string; file_no: string; patient_name: string }[];
    dept: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const form = useForm({
    booking_id: '',
    dept: props.dept,
    or_bed_id: null as number | null,
    surgeon_id: '',
    eye: '',
    procedure: '',
    anaesthesia: 'topical',
    pre_op_notes: '',
    scheduled_at: '',
});

const procedures = ['LASIK', 'SMILE', 'PRK', 'LASEK', 'Femto-LASIK', 'Trans PRK'];

interface FlatBed {
    id: number;
    displayNumber: number;
    surgery?: { status: string } | null;
}

const flatBeds = computed<FlatBed[]>(() => {
    const result: FlatBed[] = [];
    let seq = 1;
    props.orRooms.forEach((room) => {
        room.beds.forEach((bed) => {
            result.push({ id: bed.id, displayNumber: seq++, surgery: bed.surgery });
        });
    });
    return result;
});

const occupiedBedIds = computed(() =>
    flatBeds.value
        .filter((b) => b.surgery && ['scheduled', 'prep', 'in_progress'].includes(b.surgery.status))
        .map((b) => b.id),
);

const selectedDisplayNumber = computed(() =>
    flatBeds.value.find((b) => b.id === form.or_bed_id)?.displayNumber ?? null,
);

function selectBed(bedId: number) {
    if (occupiedBedIds.value.includes(bedId)) return;
    form.or_bed_id = form.or_bed_id === bedId ? null : bedId;
}

function submit() {
    form.post(`/${props.dept}`, {
        onSuccess: () => {
            emit('update:modelValue', false);
            form.reset();
            emit('success');
        },
    });
}

function close() {
    emit('update:modelValue', false);
}
</script>

<template>
    <Modal :model-value="modelValue" title="جدولة إجراء ليزك" size="lg" @update:model-value="close">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <!-- Patient -->
                <div class="col-span-2">
                    <label class="field-label">المريض / الحجز</label>
                    <select v-model="form.booking_id" class="field-input">
                        <option value="">— اختر المريض —</option>
                        <option v-for="b in bookings" :key="b.id" :value="b.id">
                            {{ b.file_no }} — {{ b.patient_name }}
                        </option>
                    </select>
                    <p v-if="form.errors.booking_id" class="field-error">{{ form.errors.booking_id }}</p>
                </div>

                <!-- Doctor -->
                <div>
                    <label class="field-label">الطبيب الجراح</label>
                    <select v-model="form.surgeon_id" class="field-input">
                        <option value="">— اختر الطبيب —</option>
                        <option v-for="doc in doctors" :key="doc.id" :value="doc.id">{{ doc.name }}</option>
                    </select>
                </div>

                <!-- Procedure -->
                <div>
                    <label class="field-label">الإجراء</label>
                    <select v-model="form.procedure" class="field-input">
                        <option value="">— اختر —</option>
                        <option v-for="p in procedures" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>

                <!-- Eye -->
                <div>
                    <label class="field-label">العين</label>
                    <select v-model="form.eye" class="field-input">
                        <option value="">—</option>
                        <option value="OD">عين يمنى (OD)</option>
                        <option value="OS">عين يسرى (OS)</option>
                        <option value="OU">كلاهما (OU)</option>
                    </select>
                </div>

                <!-- Anaesthesia -->
                <div>
                    <label class="field-label">التخدير</label>
                    <select v-model="form.anaesthesia" class="field-input">
                        <option value="">—</option>
                        <option value="local">موضعي (Local)</option>
                        <option value="topical">سطحي (Topical)</option>
                        <option value="sedation">مهدئ (Sedation)</option>
                        <option value="general">عام (General)</option>
                    </select>
                </div>

                <!-- Scheduled at -->
                <div>
                    <label class="field-label">موعد الإجراء</label>
                    <input v-model="form.scheduled_at" type="datetime-local" class="field-input" />
                </div>
            </div>

            <!-- Bed picker -->
            <div class="beds-panel">
                <div class="beds-legend-row">
                    <span class="beds-legend-item">
                        <span class="beds-dot beds-dot-free" /> فارغ
                    </span>
                    <span class="beds-legend-item">
                        <span class="beds-dot beds-dot-busy" /> مشغول
                    </span>
                    <span class="beds-legend-item">
                        <span class="beds-dot beds-dot-selected" /> محدد
                    </span>
                    <span v-if="selectedDisplayNumber" class="beds-selected-label ms-auto">
                        ✓ سرير {{ selectedDisplayNumber }}
                    </span>
                </div>
                <div class="beds-row">
                    <button
                        v-for="bed in flatBeds"
                        :key="bed.id"
                        type="button"
                        :class="[
                            'bed-btn',
                            occupiedBedIds.includes(bed.id) ? 'bed-btn-busy' : 'bed-btn-free',
                            form.or_bed_id === bed.id ? 'bed-btn-selected' : '',
                        ]"
                        :title="occupiedBedIds.includes(bed.id) ? `سرير ${bed.displayNumber} مشغول` : `سرير ${bed.displayNumber}`"
                        @click="selectBed(bed.id)"
                    >
                        <span class="bed-btn-num">{{ bed.displayNumber }}</span>
                        <span v-if="occupiedBedIds.includes(bed.id)" class="bed-busy-dot" />
                    </button>
                </div>
            </div>

            <!-- Pre-op notes -->
            <div>
                <label class="field-label">ملاحظات ما قبل الإجراء</label>
                <textarea v-model="form.pre_op_notes" rows="3" class="field-input" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="close"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white disabled:opacity-60 hover:bg-[#6A2890]"
                >
                    جدولة
                </button>
            </div>
        </form>
    </Modal>
</template>

<style scoped>
.field-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-hospital-text, #0d1f3c);
    margin-bottom: 5px;
}
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
.field-error { font-size: 11px; color: var(--color-hospital-danger, #e74c3c); margin-top: 3px; }

/* Bed picker */
.beds-panel {
    background: #f3f6fa;
    border: 1.5px solid #dde4ef;
    border-radius: 10px;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.beds-legend-row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 10px;
    font-weight: 600;
    color: #4a5878;
    border-bottom: 1px solid #dde4ef;
    padding-bottom: 8px;
    flex-wrap: wrap;
}
.beds-legend-item { display: flex; align-items: center; gap: 4px; }
.beds-dot {
    display: inline-block;
    width: 11px; height: 11px;
    border-radius: 3px;
    border: 1.5px solid transparent;
}
.beds-dot-free     { background: #fff; border-color: #dde4ef; }
.beds-dot-busy     { background: #fff0ee; border-color: #e74c3c; }
.beds-dot-selected { background: #7b2fa6; border-color: #7b2fa6; }
.beds-selected-label {
    font-size: 10px; font-weight: 700;
    background: #7b2fa6; color: #fff;
    border-radius: 12px; padding: 2px 10px;
}
.beds-row { display: flex; gap: 6px; flex-wrap: wrap; }
.bed-btn {
    width: 48px; height: 42px;
    border-radius: 8px;
    border: 1.5px solid #dde4ef;
    background: #fff;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.15s;
    font-family: inherit; padding: 0;
    position: relative; gap: 3px;
}
.bed-btn-num { font-size: 14px; font-weight: 800; color: #0d1f3c; line-height: 1; }
.bed-btn-free:hover {
    border-color: #7b2fa6; background: #f5eeff;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(123,47,166,0.18);
}
.bed-btn-free:hover .bed-btn-num { color: #7b2fa6; }
.bed-btn-busy { background: #fff0ee; border-color: #e74c3c; cursor: not-allowed; opacity: 0.8; }
.bed-btn-busy .bed-btn-num { color: #e74c3c; }
.bed-busy-dot { width: 5px; height: 5px; border-radius: 50%; background: #e74c3c; }
.bed-btn-selected {
    background: #7b2fa6 !important; border-color: #7b2fa6 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(123,47,166,0.35);
}
.bed-btn-selected .bed-btn-num { color: #fff !important; }
</style>