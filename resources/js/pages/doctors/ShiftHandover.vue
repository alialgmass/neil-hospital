<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Users, TrendingUp, Clock, CheckCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions';

interface ShiftSummary {
    id: string;
    doctor?: { name: string };
    shift_date: string;
    started_at?: string;
    status: string;
    bookings_count: number;
    revenue: number;
    pending_count: number;
}

const props = defineProps<{
    shift: ShiftSummary;
    pending_bookings?: Array<{ id: string; patient_name: string; dept: string; status: string }>;
}>();

// ── Permissions ──
const { can } = usePermissions();
const canWrite = computed(() => can('doctors.write'));

function fmt(n: number) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
}

const showHandover = ref(false);
const handoverForm = useForm({
    notes:   '',
    summary: '',
});

function submitHandover() {
    if (!canWrite.value) {
        return;
    }

    handoverForm.post(`/doctor-shifts/${props.shift.id}/handover`, {
        onSuccess: () => {
 showHandover.value = false; 
},
    });
}
</script>

<template>
    <Head title="تسليم الوردية" />

    <!-- Header -->
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">تسليم الوردية</h2>
            <p class="text-sm text-hospital-text-3">
                {{ shift.doctor?.name }} — {{ shift.shift_date }}
            </p>
        </div>
        <button
            v-if="shift.status === 'open'"
            class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white hover:bg-hospital-success/90 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
            @click="canWrite && (showHandover = true)"
            :disabled="!canWrite"
            :title="canWrite ? undefined : NO_PERMISSION_TITLE"
        >
            <CheckCircle class="h-4 w-4" /> تسليم الوردية
        </button>
        <span
            v-else
            class="rounded-full bg-hospital-muted/20 px-3 py-1 text-sm text-hospital-text-3"
        >
            تم التسليم
        </span>
    </div>

    <!-- Stats -->
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
        <StatCard label="عدد الحالات" :value="shift.bookings_count.toString()" color="primary">
            <template #icon><Users class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="الإيراد الكلي" :value="`${fmt(shift.revenue)} ج.م`" color="success">
            <template #icon><TrendingUp class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="حالات معلقة" :value="shift.pending_count.toString()" :color="shift.pending_count > 0 ? 'danger' : 'success'">
            <template #icon><Clock class="h-5 w-5" /></template>
        </StatCard>
        <StatCard label="بدأت الوردية" :value="shift.started_at ? shift.started_at.slice(11, 16) : '—'" color="primary">
            <template #icon><Clock class="h-5 w-5" /></template>
        </StatCard>
    </div>

    <!-- Pending Bookings -->
    <div v-if="pending_bookings && pending_bookings.length > 0" class="mb-5 rounded-xl border border-hospital-danger/30 bg-hospital-danger/5 p-4">
        <p class="mb-2 font-medium text-hospital-danger">حالات معلقة يجب معالجتها قبل التسليم</p>
        <ul class="space-y-1 text-sm">
            <li v-for="b in pending_bookings" :key="b.id" class="text-hospital-text">
                {{ b.patient_name }} — {{ b.dept }} ({{ b.status }})
            </li>
        </ul>
    </div>

    <!-- All good -->
    <div v-else class="rounded-xl border border-hospital-success/30 bg-hospital-success/5 p-4 text-center text-hospital-success">
        <CheckCircle class="mx-auto mb-1 h-6 w-6" />
        <p class="text-sm font-medium">لا توجد حالات معلقة — الوردية جاهزة للتسليم</p>
    </div>

    <!-- Handover Modal -->
    <Modal v-model="showHandover" title="تأكيد تسليم الوردية" size="sm">
        <form class="space-y-4" @submit.prevent="submitHandover">
            <div>
                <label class="mb-1 block text-sm font-medium">ملخص الوردية</label>
                <textarea
                    v-model="handoverForm.summary"
                    rows="3"
                    placeholder="ملخص ما تم إنجازه خلال الوردية..."
                    class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">ملاحظات للوردية القادمة</label>
                <textarea
                    v-model="handoverForm.notes"
                    rows="3"
                    placeholder="أي تعليمات أو ملاحظات للوردية التالية..."
                    class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                />
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg" @click="showHandover = false">إلغاء</button>
                <button type="submit" :disabled="handoverForm.processing || !canWrite" class="rounded-lg bg-hospital-success px-4 py-2 text-sm font-medium text-white disabled:opacity-60 disabled:cursor-not-allowed" :title="canWrite ? undefined : NO_PERMISSION_TITLE">تأكيد التسليم</button>
            </div>
        </form>
    </Modal>
</template>
