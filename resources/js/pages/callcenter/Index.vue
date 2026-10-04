<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { BellRing, CalendarClock, CalendarPlus, ClipboardList, PhoneCall, PhoneIncoming, PhoneOutgoing, Search } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions';

interface Option {
    value: string;
    label: string;
}

interface CallRow {
    id: string;
    direction: string;
    caller_name: string | null;
    phone: string;
    file_no: string | null;
    reason: string;
    outcome: string;
    notes: string | null;
    follow_up_at: string | null;
    follow_up_done_at: string | null;
    created_at: string;
    creator?: { id: number; name: string } | null;
}

interface PreBookingRow {
    id: string;
    patient_name: string;
    patient_phone: string;
    file_no: string | null;
    dept: string;
    preferred_date: string;
    preferred_time: string | null;
    notes: string | null;
    status: string;
    service?: { id: string; name: string } | null;
    doctor?: { id: string; name: string } | null;
    creator?: { id: number; name: string } | null;
    booking?: { id: string; file_no: string } | null;
}

interface ReminderRow {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone: string | null;
    dept: string;
    service_name: string | null;
    visit_time: string | null;
    doctor_name: string | null;
    last_reminder_outcome: string | null;
    last_reminder_at: string | null;
}

interface PatientMatch {
    file_no: string;
    patient_name: string;
    patient_phone: string | null;
    national_id: string | null;
    last_visit: string | null;
    last_dept: string | null;
}

const props = defineProps<{
    filters: { date: string | null; search: string | null; reminder_date: string; pre_status: string };
    calls: { data: CallRow[]; links: Array<{ url: string | null; label: string; active: boolean }>; last_page: number; current_page: number };
    preBookings: PreBookingRow[];
    reminders: ReminderRow[];
    followUps: CallRow[];
    services: { id: string; name: string; dept: string }[];
    doctors: { id: string; name: string }[];
    options: { directions: Option[]; reasons: Option[]; outcomes: Option[]; preStatuses: Option[] };
}>();

const { can } = usePermissions();
const canWrite = computed(() => can('callcenter.write'));

const page = usePage<{ departments?: Option[] }>();
const departments = computed(() => page.props.departments ?? []);

type Tab = 'calls' | 'pre' | 'reminders' | 'followups';
const tab = ref<Tab>('calls');

function label(options: Option[], value: string | null | undefined): string {
    return options.find((option) => option.value === value)?.label ?? value ?? '—';
}

function deptLabel(value: string): string {
    return label(departments.value, value);
}

function formatDateTime(value: string | null): string {
    return value ? value.replace('T', ' ').slice(0, 16) : '—';
}

// ── Filters ──
const filterForm = reactive({
    date: props.filters.date ?? '',
    search: props.filters.search ?? '',
    reminder_date: props.filters.reminder_date,
    pre_status: props.filters.pre_status,
});

function applyFilters() {
    const query = Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== ''));

    router.get('/call-center', query, { preserveState: true, preserveScroll: true });
}

// ── Patient lookup (shared by both forms) ──
const lookupTerm = ref('');
const lookupResults = ref<PatientMatch[]>([]);
let lookupTimer: ReturnType<typeof setTimeout> | undefined;

function onLookupInput() {
    clearTimeout(lookupTimer);

    if (lookupTerm.value.trim().length < 2) {
        lookupResults.value = [];

        return;
    }

    lookupTimer = setTimeout(async () => {
        const response = await fetch(`/call-center/lookup?q=${encodeURIComponent(lookupTerm.value.trim())}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        lookupResults.value = response.ok ? await response.json() : [];
    }, 300);
}

// ── Log a call ──
const showCall = ref(false);
const callForm = useForm({
    direction: 'incoming',
    caller_name: '',
    phone: '',
    file_no: '',
    booking_id: '',
    reason: 'inquiry',
    outcome: 'resolved',
    notes: '',
    follow_up_at: '',
    resolves_call_id: '',
});

function openCall(prefill: Record<string, string> = {}) {
    if (!canWrite.value) {
        return;
    }

    callForm.reset();
    callForm.clearErrors();
    Object.assign(callForm, prefill);
    lookupTerm.value = '';
    lookupResults.value = [];
    showCall.value = true;
}

function pickPatientForCall(match: PatientMatch) {
    callForm.caller_name = match.patient_name;
    callForm.phone = match.patient_phone ?? callForm.phone;
    callForm.file_no = match.file_no;
    lookupResults.value = [];
    lookupTerm.value = '';
}

function remindBooking(row: ReminderRow) {
    openCall({
        direction: 'outgoing',
        caller_name: row.patient_name,
        phone: row.patient_phone ?? '',
        file_no: row.file_no,
        booking_id: row.id,
        reason: 'reminder',
        outcome: 'confirmed',
    });
}

function followUp(row: CallRow) {
    openCall({
        direction: 'outgoing',
        caller_name: row.caller_name ?? '',
        phone: row.phone,
        file_no: row.file_no ?? '',
        reason: 'follow_up',
        outcome: 'resolved',
        resolves_call_id: row.id,
    });
}

function submitCall() {
    callForm.post('/call-center/calls', {
        preserveScroll: true,
        onSuccess: () => {
            showCall.value = false;
            callForm.reset();
        },
    });
}

// ── Preliminary booking ──
const showPre = ref(false);
const preForm = useForm({
    patient_name: '',
    patient_phone: '',
    national_id: '',
    file_no: '',
    dept: 'clinic',
    service_id: '',
    doctor_id: '',
    preferred_date: '',
    preferred_time: '',
    notes: '',
});

const deptServices = computed(() => props.services.filter((service) => service.dept === preForm.dept));

function openPre() {
    if (!canWrite.value) {
        return;
    }

    preForm.reset();
    preForm.clearErrors();
    lookupTerm.value = '';
    lookupResults.value = [];
    showPre.value = true;
}

function pickPatientForPre(match: PatientMatch) {
    preForm.patient_name = match.patient_name;
    preForm.patient_phone = match.patient_phone ?? '';
    preForm.national_id = match.national_id ?? '';
    preForm.file_no = match.file_no;
    lookupResults.value = [];
    lookupTerm.value = '';
}

function submitPre() {
    preForm.post('/call-center/pre-bookings', {
        preserveScroll: true,
        onSuccess: () => {
            showPre.value = false;
            preForm.reset();
        },
    });
}

function cancelPre(row: PreBookingRow) {
    if (!canWrite.value || !window.confirm(`إلغاء الحجز المبدئي لـ ${row.patient_name}؟`)) {
        return;
    }

    router.patch(`/call-center/pre-bookings/${row.id}/cancel`, {}, { preserveScroll: true });
}

const preStatusClasses: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    converted: 'bg-green-100 text-green-700',
    cancelled: 'bg-gray-100 text-gray-500',
};

const reminderPending = computed(() => props.reminders.filter((row) => !row.last_reminder_outcome).length);
</script>

<template>
    <Head title="الكول سنتر" />

    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold text-t">الكول سنتر</h1>
            <p class="mt-0.5 text-sm text-t3">تسجيل المكالمات، الحجوزات المبدئية للاستقبال، وتذكير ومتابعة المرضى</p>
        </div>
        <div class="flex gap-2">
            <button
                type="button"
                class="btn-secondary flex items-center gap-1.5 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canWrite"
                :title="canWrite ? undefined : NO_PERMISSION_TITLE"
                @click="openCall()"
            >
                <PhoneCall class="h-4 w-4" />
                تسجيل مكالمة
            </button>
            <button
                type="button"
                class="btn-primary flex items-center gap-1.5 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!canWrite"
                :title="canWrite ? undefined : NO_PERMISSION_TITLE"
                @click="openPre"
            >
                <CalendarPlus class="h-4 w-4" />
                حجز مبدئي
            </button>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-4 flex flex-wrap gap-2 border-b border-br">
        <button
            v-for="item in [
                { key: 'calls', label: 'سجل المكالمات', icon: ClipboardList, count: null },
                { key: 'pre', label: 'الحجوزات المبدئية', icon: CalendarPlus, count: preBookings.length },
                { key: 'reminders', label: 'تذكير المواعيد', icon: BellRing, count: reminderPending },
                { key: 'followups', label: 'متابعات مستحقة', icon: CalendarClock, count: followUps.length },
            ] as const"
            :key="item.key"
            type="button"
            class="-mb-px flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
            :class="tab === item.key ? 'border-p text-p' : 'border-transparent text-t3 hover:text-t'"
            @click="tab = item.key"
        >
            <component :is="item.icon" class="h-4 w-4" />
            {{ item.label }}
            <span v-if="item.count" class="rounded-full bg-p/10 px-1.5 text-xs text-p">{{ item.count }}</span>
        </button>
    </div>

    <!-- Calls -->
    <div v-if="tab === 'calls'">
        <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="applyFilters">
            <div class="flex flex-col gap-1">
                <label class="form-label">اليوم</label>
                <input v-model="filterForm.date" type="date" class="input-field" />
            </div>
            <div class="relative flex flex-col gap-1">
                <label class="form-label">بحث</label>
                <Search class="absolute bottom-2.5 right-3 h-3.5 w-3.5 text-t3" />
                <input v-model="filterForm.search" type="text" class="input-field w-64 pr-9" placeholder="الهاتف / الاسم / رقم الملف" />
            </div>
            <button type="submit" class="btn-primary">عرض</button>
        </form>

        <div class="overflow-x-auto rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
            <table class="w-full text-sm">
                <thead class="bg-sf2">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الوقت</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">النوع</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المتصل</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">السبب</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">النتيجة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">ملاحظات</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">متابعة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الموظف</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="call in calls.data" :key="call.id" class="hover:bg-sf2">
                        <td class="whitespace-nowrap px-4 py-3 text-t3">{{ formatDateTime(call.created_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="flex items-center gap-1 text-xs" :class="call.direction === 'incoming' ? 'text-s' : 'text-p'">
                                <PhoneIncoming v-if="call.direction === 'incoming'" class="h-3.5 w-3.5" />
                                <PhoneOutgoing v-else class="h-3.5 w-3.5" />
                                {{ label(options.directions, call.direction) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-t">{{ call.caller_name || '—' }}</span>
                            <span class="block font-mono text-xs text-t3">{{ call.phone }}<template v-if="call.file_no"> — {{ call.file_no }}</template></span>
                        </td>
                        <td class="px-4 py-3 text-t2">{{ label(options.reasons, call.reason) }}</td>
                        <td class="px-4 py-3 text-t2">{{ label(options.outcomes, call.outcome) }}</td>
                        <td class="max-w-xs px-4 py-3 text-xs text-t3">{{ call.notes || '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-xs">
                            <span v-if="!call.follow_up_at" class="text-t3">—</span>
                            <span v-else-if="call.follow_up_done_at" class="text-s">تمت</span>
                            <span v-else class="text-w">{{ formatDateTime(call.follow_up_at) }}</span>
                        </td>
                        <td class="px-4 py-3 text-t3">{{ call.creator?.name || '—' }}</td>
                    </tr>
                    <tr v-if="calls.data.length === 0">
                        <td class="px-4 py-10 text-center text-t3" colspan="8">لا توجد مكالمات</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="calls.last_page > 1" class="flex justify-end gap-1 border-t border-br px-4 py-3">
                <button
                    v-for="link in calls.links"
                    :key="link.label"
                    :disabled="!link.url"
                    class="rounded px-2.5 py-1 text-xs"
                    :class="link.active ? 'bg-p text-white' : 'border border-br text-t2 disabled:opacity-40'"
                    @click="link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>

    <!-- Preliminary bookings -->
    <div v-else-if="tab === 'pre'">
        <div class="mb-4 flex items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="form-label">الحالة</label>
                <select v-model="filterForm.pre_status" class="input-field" @change="applyFilters">
                    <option v-for="opt in options.preStatuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
            <table class="w-full text-sm">
                <thead class="bg-sf2">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الموعد</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المريض</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القسم / الخدمة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الطبيب</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">سجّله</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in preBookings" :key="row.id" class="hover:bg-sf2">
                        <td class="whitespace-nowrap px-4 py-3 text-t2">{{ row.preferred_date }} {{ row.preferred_time?.slice(0, 5) ?? '' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-t">{{ row.patient_name }}</span>
                            <span class="block font-mono text-xs text-t3">{{ row.patient_phone }}<template v-if="row.file_no"> — {{ row.file_no }}</template></span>
                        </td>
                        <td class="px-4 py-3 text-t2">
                            {{ deptLabel(row.dept) }}
                            <span v-if="row.service" class="block text-xs text-t3">{{ row.service.name }}</span>
                        </td>
                        <td class="px-4 py-3 text-t2">{{ row.doctor?.name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="preStatusClasses[row.status]">
                                {{ label(options.preStatuses, row.status) }}
                            </span>
                            <span v-if="row.booking" class="block font-mono text-xs text-t3">{{ row.booking.file_no }}</span>
                        </td>
                        <td class="px-4 py-3 text-t3">{{ row.creator?.name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <button
                                v-if="row.status === 'pending' && canWrite"
                                type="button"
                                class="text-xs font-medium text-d hover:underline"
                                @click="cancelPre(row)"
                            >
                                إلغاء
                            </button>
                        </td>
                    </tr>
                    <tr v-if="preBookings.length === 0">
                        <td class="px-4 py-10 text-center text-t3" colspan="7">لا توجد حجوزات مبدئية</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Reminders -->
    <div v-else-if="tab === 'reminders'">
        <div class="mb-4 flex items-end gap-3">
            <div class="flex flex-col gap-1">
                <label class="form-label">مواعيد يوم</label>
                <input v-model="filterForm.reminder_date" type="date" class="input-field" @change="applyFilters" />
            </div>
        </div>
        <div class="overflow-x-auto rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
            <table class="w-full text-sm">
                <thead class="bg-sf2">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الوقت</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المريض</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">القسم / الخدمة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الطبيب</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">آخر تذكير</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in reminders" :key="row.id" class="hover:bg-sf2">
                        <td class="px-4 py-3 font-mono text-t2">{{ row.visit_time?.slice(0, 5) ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-t">{{ row.patient_name }}</span>
                            <span class="block font-mono text-xs text-t3">{{ row.patient_phone ?? 'بدون هاتف' }} — {{ row.file_no }}</span>
                        </td>
                        <td class="px-4 py-3 text-t2">
                            {{ deptLabel(row.dept) }}
                            <span v-if="row.service_name" class="block text-xs text-t3">{{ row.service_name }}</span>
                        </td>
                        <td class="px-4 py-3 text-t2">{{ row.doctor_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs">
                            <span v-if="row.last_reminder_outcome" class="text-s">
                                {{ label(options.outcomes, row.last_reminder_outcome) }}
                                <span class="block text-t3">{{ formatDateTime(row.last_reminder_at) }}</span>
                            </span>
                            <span v-else class="text-w">لم يتم التذكير</span>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                v-if="canWrite"
                                type="button"
                                class="flex items-center gap-1 text-xs font-medium text-p hover:underline"
                                @click="remindBooking(row)"
                            >
                                <PhoneOutgoing class="h-3.5 w-3.5" />
                                تسجيل اتصال
                            </button>
                        </td>
                    </tr>
                    <tr v-if="reminders.length === 0">
                        <td class="px-4 py-10 text-center text-t3" colspan="6">لا توجد مواعيد في هذا اليوم</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Due follow-ups -->
    <div v-else>
        <div class="overflow-x-auto rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
            <table class="w-full text-sm">
                <thead class="bg-sf2">
                    <tr>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">موعد المتابعة</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المتصل</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المكالمة الأصلية</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-t2">ملاحظات</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in followUps" :key="row.id" class="hover:bg-sf2">
                        <td class="whitespace-nowrap px-4 py-3 text-w">{{ formatDateTime(row.follow_up_at) }}</td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-t">{{ row.caller_name || '—' }}</span>
                            <span class="block font-mono text-xs text-t3">{{ row.phone }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-t2">
                            {{ label(options.reasons, row.reason) }} — {{ label(options.outcomes, row.outcome) }}
                            <span class="block text-t3">{{ formatDateTime(row.created_at) }} · {{ row.creator?.name ?? '' }}</span>
                        </td>
                        <td class="max-w-xs px-4 py-3 text-xs text-t3">{{ row.notes || '—' }}</td>
                        <td class="px-4 py-3">
                            <button
                                v-if="canWrite"
                                type="button"
                                class="flex items-center gap-1 text-xs font-medium text-p hover:underline"
                                @click="followUp(row)"
                            >
                                <PhoneOutgoing class="h-3.5 w-3.5" />
                                تسجيل المتابعة
                            </button>
                        </td>
                    </tr>
                    <tr v-if="followUps.length === 0">
                        <td class="px-4 py-10 text-center text-t3" colspan="5">لا توجد متابعات مستحقة</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Log call modal -->
    <Modal v-model="showCall" title="تسجيل مكالمة" size="lg">
        <form class="space-y-4" @submit.prevent="submitCall">
            <div v-if="!callForm.booking_id && !callForm.resolves_call_id" class="relative">
                <label class="form-label">بحث عن مريض مسجل</label>
                <input v-model="lookupTerm" type="text" class="input-field" placeholder="الهاتف / الاسم / رقم الملف" @input="onLookupInput" />
                <div v-if="lookupResults.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-br bg-sf shadow-lg">
                    <button
                        v-for="match in lookupResults"
                        :key="match.file_no"
                        type="button"
                        class="block w-full px-3 py-2 text-right text-sm hover:bg-sf2"
                        @click="pickPatientForCall(match)"
                    >
                        <span class="font-medium text-t">{{ match.patient_name }}</span>
                        <span class="mr-2 font-mono text-xs text-t3">{{ match.patient_phone }} — {{ match.file_no }}</span>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">نوع المكالمة *</label>
                    <select v-model="callForm.direction" class="input-field">
                        <option v-for="opt in options.directions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">رقم الهاتف *</label>
                    <input v-model="callForm.phone" type="text" class="input-field" dir="ltr" />
                    <p v-if="callForm.errors.phone" class="form-error">{{ callForm.errors.phone }}</p>
                </div>
                <div>
                    <label class="form-label">اسم المتصل</label>
                    <input v-model="callForm.caller_name" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">رقم الملف</label>
                    <input v-model="callForm.file_no" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">سبب المكالمة *</label>
                    <select v-model="callForm.reason" class="input-field">
                        <option v-for="opt in options.reasons" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">النتيجة *</label>
                    <select v-model="callForm.outcome" class="input-field">
                        <option v-for="opt in options.outcomes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="callForm.notes" rows="2" class="input-field" />
                </div>
                <div>
                    <label class="form-label">موعد متابعة (اختياري)</label>
                    <input v-model="callForm.follow_up_at" type="datetime-local" class="input-field" />
                    <p v-if="callForm.errors.follow_up_at" class="form-error">{{ callForm.errors.follow_up_at }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showCall = false">إلغاء</button>
                <button type="submit" class="btn-primary" :disabled="callForm.processing">
                    {{ callForm.processing ? 'جارٍ الحفظ...' : 'حفظ المكالمة' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Preliminary booking modal -->
    <Modal v-model="showPre" title="حجز مبدئي — يُرسل للاستقبال للتأكيد" size="lg">
        <form class="space-y-4" @submit.prevent="submitPre">
            <div class="relative">
                <label class="form-label">بحث عن مريض مسجل</label>
                <input v-model="lookupTerm" type="text" class="input-field" placeholder="الهاتف / الاسم / رقم الملف" @input="onLookupInput" />
                <div v-if="lookupResults.length" class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-br bg-sf shadow-lg">
                    <button
                        v-for="match in lookupResults"
                        :key="match.file_no"
                        type="button"
                        class="block w-full px-3 py-2 text-right text-sm hover:bg-sf2"
                        @click="pickPatientForPre(match)"
                    >
                        <span class="font-medium text-t">{{ match.patient_name }}</span>
                        <span class="mr-2 font-mono text-xs text-t3">{{ match.patient_phone }} — {{ match.file_no }}</span>
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">اسم المريض *</label>
                    <input v-model="preForm.patient_name" type="text" class="input-field" />
                    <p v-if="preForm.errors.patient_name" class="form-error">{{ preForm.errors.patient_name }}</p>
                </div>
                <div>
                    <label class="form-label">الهاتف *</label>
                    <input v-model="preForm.patient_phone" type="text" class="input-field" dir="ltr" />
                    <p v-if="preForm.errors.patient_phone" class="form-error">{{ preForm.errors.patient_phone }}</p>
                </div>
                <div>
                    <label class="form-label">الرقم القومي</label>
                    <input v-model="preForm.national_id" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">رقم الملف</label>
                    <input v-model="preForm.file_no" type="text" class="input-field" />
                </div>
                <div>
                    <label class="form-label">القسم *</label>
                    <select v-model="preForm.dept" class="input-field" @change="preForm.service_id = ''">
                        <option v-for="dept in departments" :key="dept.value" :value="dept.value">{{ dept.label }}</option>
                    </select>
                    <p v-if="preForm.errors.dept" class="form-error">{{ preForm.errors.dept }}</p>
                </div>
                <div>
                    <label class="form-label">الخدمة</label>
                    <select v-model="preForm.service_id" class="input-field">
                        <option value="">— غير محددة —</option>
                        <option v-for="service in deptServices" :key="service.id" :value="service.id">{{ service.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">الطبيب</label>
                    <select v-model="preForm.doctor_id" class="input-field">
                        <option value="">— غير محدد —</option>
                        <option v-for="doctor in doctors" :key="doctor.id" :value="doctor.id">{{ doctor.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="form-label">التاريخ *</label>
                        <input v-model="preForm.preferred_date" type="date" class="input-field" />
                        <p v-if="preForm.errors.preferred_date" class="form-error">{{ preForm.errors.preferred_date }}</p>
                    </div>
                    <div>
                        <label class="form-label">الوقت</label>
                        <input v-model="preForm.preferred_time" type="time" class="input-field" />
                    </div>
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات للاستقبال</label>
                    <textarea v-model="preForm.notes" rows="2" class="input-field" />
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showPre = false">إلغاء</button>
                <button type="submit" class="btn-primary" :disabled="preForm.processing">
                    {{ preForm.processing ? 'جارٍ الحفظ...' : 'إرسال للاستقبال' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
