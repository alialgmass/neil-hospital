<script setup lang="ts">
const ACTION_LABELS: Record<string, string> = {
    collect: 'تحصيل',
    advance_stage: 'نقل مرحلة',
    stage_move: 'نقل مرحلة',
    create: 'إنشاء',
    update: 'تعديل',
    delete: 'حذف',
    deposit: 'دفع مؤقت',
    deposit_convert: 'تحويل دفع مؤقت لمطالبة',
    deposit_decision: 'قرار على دفع مؤقت',
    pending_deposit: 'دفع مؤقت',
    pending_deposit_convert: 'تحويل دفع مؤقت لمطالبة',
    pending_deposit_decision: 'قرار على دفع مؤقت',
    collection_recorded: 'تسجيل تحصيل',
    collection_pending: 'تحصيل بانتظار الاعتماد',
    collection_approved: 'اعتماد تحصيل',
    collection_rejected: 'رفض تحصيل',
    day_close: 'إقفال يومي',
    cash_collection: 'تحصيل نقدي من مريض',
    reception_decision: 'قرار التأمين',
};
const actionLabel = (action: string): string => ACTION_LABELS[action] ?? action;
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    advance,
    collect,
    convertDeposit,
    decideDeposit,
    approveCollection,
    rejectCollection,
    closeDay,
    collectCash,
} from '@/actions/Modules/Insurance/Controllers/CollectionConsoleController';
import AppLayout from '@/components/layout/AppLayout.vue';
import Modal from '@/components/shared/Modal.vue';
import { usePermissions } from '@/composables/usePermissions';

defineOptions({ layout: AppLayout });

type Ageing = { within_30: number; from_30_to_60: number; from_60_to_90: number; over_90: number };
type ClaimRow = {
    id: string; reference: string | null; company: string | null; patient: string; service: string;
    stage: string; stage_label: string; share: number; approved: number | null; paid: number;
    deduction: number; outstanding: number; age: number | null;
};

const props = defineProps<{
    counts: { total: number; made: number; not_made: number; open: number };
    money: { due: number; claimed: number; collected: number; rejected: number; outstanding: number; held_deposits: number };
    stages: Record<string, { count: number; amount: number }>;
    stageLabels: Record<string, string>;
    companies: { id: string; name: string | null; claims: number; claimed: number; collected: number; rejected: number; deduction: number; outstanding: number; ageing: Ageing }[];
    claims: ClaimRow[];
    reception: { id: string; patient: string; company: string | null; service: string | null; date: string | null; price: number; status: 'ready' | 'waiting_pricing' }[];
    deposits: { id: string; patient: string | null; company: string | null; service: string | null; amount: number; paid_on: string | null; decision: string; decision_label: string; refunded: boolean; converted: boolean }[];
    flags: { kind: string; severity: 'high' | 'medium'; title: string; detail: string }[];
    log: { time: string | null; user: string | null; action: string; detail: string | null; record_id: string | null }[];
    approvals: { id: string; claim_id: string; reference: string | null; amount: number; discount: number; net: number; reason: string | null; why: string; recorded_by: string | null; recorded_at: string | null }[];
    cash: { id: string; patient: string | null; company: string | null; description: string; department: string; amount: number; entry_date: string | null; status: string }[];
    closures: { date: string; user: string | null }[];
    todayCollection: { count: number; sum: number; closed: boolean };
    auditIntact: boolean;
}>();

const { can } = usePermissions();
const canCollect = computed(() => can('insurance.collect'));
const canAudit = computed(() => can('insurance.audit'));

const tabs = [
    ['dash', 'لوحة التحكم'], ['reception', 'الاستقبال'], ['deposits', 'الدفع المؤقت'],
    ['board', 'دورة التحصيل'], ['companies', 'متابعة الشركات'], ['claims', 'كل المطالبات'], ['audit', 'الرقابة والتدقيق'],
    ['cash', 'نقدي المرضى'],
] as const;
const activeTab = ref<(typeof tabs)[number][0]>('dash');

const egp = (n: number) => `${Math.round(n).toLocaleString('en-US')} ج.م`;
const highFlags = computed(() => props.flags.filter((f) => f.severity === 'high').length);
const stageKeys = computed(() => Object.keys(props.stageLabels));
const maxStageAmount = computed(() => Math.max(1, ...Object.values(props.stages).map((s) => s.amount)));
const collectedPct = computed(() => (props.money.claimed ? Math.min(100, (props.money.collected / props.money.claimed) * 100) : 0));

// ── Claim drawer ──
const selected = ref<ClaimRow | null>(null);
const collectForm = useForm({ amount: 0, discount: 0, reason: '' });
const discountPct = computed({
    get: () => (collectForm.amount ? +((collectForm.discount / collectForm.amount) * 100).toFixed(1) : 0),
    set: (pct: number) => {
 collectForm.discount = Math.round((collectForm.amount * (pct || 0)) / 100); 
},
});
const net = computed(() => Math.max(0, collectForm.amount - collectForm.discount));
const needsReason = computed(() => collectForm.amount > 0 && collectForm.discount / collectForm.amount > 0.1);

function openClaim(claim: ClaimRow) {
    selected.value = claim;
    collectForm.reset();
    collectForm.clearErrors();
    collectForm.amount = claim.approved ?? claim.share;
}

function advanceStage(claim: ClaimRow) {
    router.post(advance(claim.id).url, {}, { preserveScroll: true, onSuccess: () => (selected.value = null) });
}

function submitCollection(claim: ClaimRow) {
    collectForm.post(collect(claim.id).url, { preserveScroll: true, onSuccess: () => (selected.value = null) });
}

function decide(id: string, decision: 'approved' | 'rejected') {
    router.post(decideDeposit(id).url, { decision }, { preserveScroll: true });
}

function convert(id: string) {
    router.post(convertDeposit(id).url, {}, { preserveScroll: true });
}

function approve(id: string) {
    router.post(approveCollection(id).url, {}, { preserveScroll: true });
}
function reject(id: string) {
    const reason = window.prompt('سبب الرفض');

    if (reason) {
router.post(rejectCollection(id).url, { reason }, { preserveScroll: true });
}
}
function closeCollectionDay() {
    router.post(closeDay().url, {}, { preserveScroll: true });
}
function collectPatientCash(id: string) {
    router.post(collectCash(id).url, {}, { preserveScroll: true });
}

const ageClass = (age: number | null) => (age === null ? '' : age <= 30 ? 'text-hospital-success' : age <= 60 ? 'text-hospital-warning' : 'text-hospital-danger');
</script>

<template>
    <div dir="rtl" class="space-y-5 p-4">
        <header>
            <h1 class="text-xl font-bold">تحصيل مطالبات شركات التعاقد</h1>
            <p class="text-sm text-gray-500">متابعة دورة المطالبة من تسجيل الحالة حتى تحصيل قيمتها من الشركة</p>
        </header>

        <section class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">المطالَب به</div><div class="text-lg font-bold tabular-nums">{{ egp(money.claimed) }}</div></div>
            <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">المحصّل فعلياً</div><div class="text-lg font-bold tabular-nums text-hospital-success">{{ egp(money.collected) }}</div></div>
            <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">المرفوض / المخصوم</div><div class="text-lg font-bold tabular-nums text-hospital-danger">{{ egp(money.rejected) }}</div></div>
            <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">الرصيد المتبقي ({{ counts.open }} مطالبة)</div><div class="text-lg font-bold tabular-nums text-hospital-warning">{{ egp(money.outstanding) }}</div></div>
        </section>

        <nav class="flex flex-wrap gap-1 rounded-xl border bg-gray-50 p-1" role="tablist">
            <button
                v-for="[key, label] in tabs" :key="key" role="tab" :aria-selected="activeTab === key"
                class="rounded-lg px-3 py-1.5 text-sm font-semibold"
                :class="activeTab === key ? 'bg-white shadow' : 'text-gray-500'"
                @click="activeTab = key"
            >
                {{ label }}<span v-if="key === 'audit' && flags.length" class="mr-1 text-hospital-danger">({{ flags.length }})</span>
            </button>
        </nav>

        <!-- Dashboard -->
        <section v-if="activeTab === 'dash'" class="space-y-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">إجمالي الفواتير</div><div class="text-2xl font-bold">{{ counts.total }}</div></div>
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">فواتير اتعملت</div><div class="text-2xl font-bold">{{ counts.made }}</div></div>
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">لسه ما اتعملتش</div><div class="text-2xl font-bold">{{ counts.not_made }}</div></div>
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">مطالبات مفتوحة</div><div class="text-2xl font-bold">{{ counts.open }}</div></div>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <div class="flex flex-wrap justify-between gap-4">
                    <div><div class="text-xs text-gray-500">المستحق لك إجمالاً</div><div class="text-2xl font-bold tabular-nums">{{ egp(money.due) }}</div></div>
                    <div><div class="text-xs text-gray-500">أمانات مرضى محجوزة</div><div class="text-lg font-bold tabular-nums">{{ egp(money.held_deposits) }}</div></div>
                </div>
                <div class="mt-3 h-3 overflow-hidden rounded bg-gray-100"><div class="h-full bg-hospital-success" :style="{ width: collectedPct + '%' }" /></div>
                <div class="mt-1 text-xs text-gray-500">محصّل {{ Math.round(collectedPct) }}% من اللي اتقدّم</div>
            </div>
            <div class="rounded-xl border bg-white p-4">
                <h3 class="mb-3 font-bold">التعاقدات حسب المرحلة</h3>
                <div v-for="key in stageKeys" :key="key" class="mb-2 grid grid-cols-[9rem_1fr_auto] items-center gap-3 text-sm">
                    <span>{{ stageLabels[key] }}</span>
                    <div class="h-2 rounded bg-gray-100"><div class="h-full rounded bg-hospital-primary" :style="{ width: (stages[key].amount / maxStageAmount) * 100 + '%' }" /></div>
                    <span class="tabular-nums">{{ egp(stages[key].amount) }} <small class="text-gray-400">{{ stages[key].count }}</small></span>
                </div>
            </div>
        </section>

        <!-- Reception -->
        <section v-else-if="activeTab === 'reception'" class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500"><tr><th class="p-2 text-start">المريض</th><th class="p-2 text-start">الشركة</th><th class="p-2 text-start">الخدمة</th><th class="p-2 text-start">التاريخ</th><th class="p-2 text-end">القيمة</th><th class="p-2 text-start">الحالة</th></tr></thead>
                <tbody>
                    <tr v-for="r in reception" :key="r.id" class="border-t">
                        <td class="p-2">{{ r.patient }}</td><td class="p-2">{{ r.company }}</td><td class="p-2">{{ r.service }}</td><td class="p-2 tabular-nums">{{ r.date }}</td>
                        <td class="p-2 text-end tabular-nums">{{ r.price ? egp(r.price) : '٠' }}</td>
                        <td class="p-2" :class="r.status === 'ready' ? 'text-hospital-success' : 'text-hospital-warning'">{{ r.status === 'ready' ? 'مسعّرة' : 'في انتظار التسعير' }}</td>
                    </tr>
                    <tr v-if="!reception.length"><td colspan="6" class="p-4 text-center text-gray-400">لا يوجد</td></tr>
                </tbody>
            </table>
        </section>

        <!-- Deposits -->
        <section v-else-if="activeTab === 'deposits'" class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500"><tr><th class="p-2 text-start">المريض</th><th class="p-2 text-start">الشركة</th><th class="p-2 text-start">الخدمة</th><th class="p-2 text-end">الأمانة</th><th class="p-2 text-start">قرار الشركة</th><th class="p-2 text-start">الاسترداد</th><th class="p-2" /></tr></thead>
                <tbody>
                    <tr v-for="d in deposits" :key="d.id" class="border-t">
                        <td class="p-2">{{ d.patient }}</td><td class="p-2">{{ d.company }}</td><td class="p-2">{{ d.service }}</td>
                        <td class="p-2 text-end tabular-nums">{{ egp(d.amount) }}</td><td class="p-2">{{ d.decision_label }}</td>
                        <td class="p-2">{{ d.refunded ? 'اتردّت ✓' : d.decision === 'approved' ? 'مطلوب ردّها' : d.decision === 'rejected' ? 'نقدي خاص' : '—' }}</td>
                        <td v-if="canCollect" class="space-x-2 space-x-reverse p-2 text-end">
                            <template v-if="d.decision === 'waiting'">
                                <button class="rounded bg-hospital-primary px-2 py-1 text-white" @click="decide(d.id, 'approved')">موافقة</button>
                                <button class="rounded border px-2 py-1" @click="decide(d.id, 'rejected')">رفض</button>
                            </template>
                            <button v-else-if="d.decision === 'approved' && !d.converted" class="rounded bg-hospital-primary px-2 py-1 text-white" @click="convert(d.id)">حوّلها لمطالبة واسترد الأمانة</button>
                        </td>
                    </tr>
                    <tr v-if="!deposits.length"><td colspan="7" class="p-4 text-center text-gray-400">لا يوجد</td></tr>
                </tbody>
            </table>
        </section>

        <!-- Board -->
        <section v-else-if="activeTab === 'board'" class="overflow-x-auto">
            <div class="flex min-w-min gap-3">
                <div v-for="key in stageKeys" :key="key" class="w-56 shrink-0 rounded-xl border bg-gray-50">
                    <div class="border-b p-2 text-sm font-bold">{{ stageLabels[key] }} <small class="text-gray-400">{{ stages[key].count }} · {{ egp(stages[key].amount) }}</small></div>
                    <div class="max-h-[32rem] space-y-2 overflow-y-auto p-2">
                        <button v-for="c in claims.filter((x) => x.stage === key)" :key="c.id" class="w-full rounded-lg border bg-white p-2 text-start" @click="openClaim(c)">
                            <div class="flex justify-between text-sm font-bold"><span>{{ c.reference ?? c.id.slice(-6) }}</span><span class="tabular-nums">{{ c.share.toLocaleString('en-US') }}</span></div>
                            <div class="truncate text-xs text-gray-500">{{ c.company }}</div>
                            <div v-if="c.age !== null && c.outstanding > 0" class="text-xs font-bold" :class="ageClass(c.age)">{{ c.age }} يوم</div>
                        </button>
                        <div v-if="!stages[key].count" class="py-3 text-center text-xs text-gray-400">لا يوجد</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Companies -->
        <section v-else-if="activeTab === 'companies'" class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500"><tr>
                    <th class="p-2 text-start">الشركة</th><th class="p-2 text-end">مطالبات</th><th class="p-2 text-end">المطالَب به</th><th class="p-2 text-end">المحصّل</th><th class="p-2 text-end">المرفوض</th><th class="p-2 text-end">الخصم</th><th class="p-2 text-end">المتبقي</th>
                    <th class="p-2 text-end">خلال شهر</th><th class="p-2 text-end">شهر–شهرين</th><th class="p-2 text-end">شهرين–٣</th><th class="p-2 text-end">أكتر من ٣ شهور</th>
                </tr></thead>
                <tbody>
                    <tr v-for="co in companies" :key="co.id" class="border-t tabular-nums">
                        <td class="p-2 font-bold">{{ co.name }}</td><td class="p-2 text-end">{{ co.claims }}</td><td class="p-2 text-end">{{ co.claimed.toLocaleString('en-US') }}</td>
                        <td class="p-2 text-end">{{ co.collected.toLocaleString('en-US') }}</td><td class="p-2 text-end">{{ co.rejected.toLocaleString('en-US') }}</td><td class="p-2 text-end">{{ co.deduction.toLocaleString('en-US') }}</td>
                        <td class="p-2 text-end font-bold">{{ co.outstanding.toLocaleString('en-US') }}</td>
                        <td class="p-2 text-end">{{ co.ageing.within_30 || '—' }}</td><td class="p-2 text-end">{{ co.ageing.from_30_to_60 || '—' }}</td><td class="p-2 text-end">{{ co.ageing.from_60_to_90 || '—' }}</td>
                        <td class="p-2 text-end font-bold text-hospital-danger">{{ co.ageing.over_90 || '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Claims -->
        <section v-else-if="activeTab === 'claims'" class="overflow-x-auto rounded-xl border bg-white">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500"><tr><th class="p-2 text-start">المطالبة</th><th class="p-2 text-start">الشركة</th><th class="p-2 text-start">المريض</th><th class="p-2 text-start">المرحلة</th><th class="p-2 text-end">القيمة</th><th class="p-2 text-end">المحصّل</th><th class="p-2 text-end">المتبقي</th><th class="p-2 text-end">العمر</th></tr></thead>
                <tbody>
                    <tr v-for="c in claims" :key="c.id" class="cursor-pointer border-t tabular-nums hover:bg-gray-50" @click="openClaim(c)">
                        <td class="p-2">{{ c.reference ?? c.id.slice(-6) }}</td><td class="p-2">{{ c.company }}</td><td class="p-2">{{ c.patient }}</td><td class="p-2">{{ c.stage_label }}</td>
                        <td class="p-2 text-end">{{ c.share.toLocaleString('en-US') }}</td><td class="p-2 text-end">{{ c.paid.toLocaleString('en-US') }}</td><td class="p-2 text-end">{{ c.outstanding.toLocaleString('en-US') }}</td>
                        <td class="p-2 text-end" :class="ageClass(c.age)">{{ c.age ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Patient cash -->
        <section v-else-if="activeTab === 'cash'" class="space-y-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">مبالغ نقدية مستحقة</div><div class="text-2xl font-bold">{{ cash.filter((entry) => entry.status === 'due').length }}</div></div>
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">إجمالي المستحق</div><div class="text-2xl font-bold">{{ egp(cash.filter((entry) => entry.status === 'due').reduce((sum, entry) => sum + entry.amount, 0)) }}</div></div>
                <div class="rounded-xl border bg-white p-3"><div class="text-xs text-gray-500">مبالغ محصلة</div><div class="text-2xl font-bold">{{ cash.filter((entry) => entry.status === 'paid').length }}</div></div>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-white"><table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500"><tr><th class="p-2 text-start">المريض</th><th class="p-2 text-start">البيان</th><th class="p-2 text-end">المبلغ</th><th class="p-2 text-start">الحالة</th><th class="p-2" /></tr></thead>
                <tbody><tr v-for="entry in cash" :key="entry.id" class="border-t"><td class="p-2">{{ entry.patient }}</td><td class="p-2">{{ entry.description }}</td><td class="p-2 text-end">{{ egp(entry.amount) }}</td><td class="p-2">{{ entry.status === 'due' ? 'مستحق' : 'مدفوع' }}</td><td class="p-2 text-end"><button v-if="canCollect && entry.status === 'due'" class="rounded bg-hospital-primary px-2 py-1 text-white" @click="collectPatientCash(entry.id)">تحصيل</button></td></tr></tbody>
            </table></div>
        </section>

        <!-- Audit -->
        <section v-else class="space-y-4">
            <div v-if="canAudit" class="flex flex-wrap items-center gap-3 text-sm">
                <div class="rounded-xl border bg-white p-3">تحصيل اليوم: <b>{{ todayCollection.count }}</b> / {{ egp(todayCollection.sum) }}</div>
                <div class="rounded-xl border bg-white p-3" :class="auditIntact ? 'text-hospital-success' : 'text-hospital-danger'">{{ auditIntact ? 'سجل التدقيق سليم' : 'سجل التدقيق تم العبث به' }}</div>
                <button v-if="!todayCollection.closed" class="rounded bg-hospital-danger px-3 py-2 font-bold text-white" @click="closeCollectionDay">إغلاق اليوم</button>
            </div>
            <div v-if="canAudit && approvals.length" class="rounded-xl border bg-white p-3"><h3 class="mb-2 font-bold">تحصيلات بانتظار الاعتماد</h3><div v-for="approval in approvals" :key="approval.id" class="flex flex-wrap items-center justify-between gap-2 border-t py-2 text-sm"><span>{{ approval.reference || approval.claim_id }} · {{ egp(approval.net) }} · {{ approval.recorded_by }}</span><span class="flex gap-2"><button class="rounded bg-hospital-primary px-2 py-1 text-white" @click="approve(approval.id)">اعتماد</button><button class="rounded border px-2 py-1" @click="reject(approval.id)">رفض</button></span></div></div>
            <div v-if="canAudit" class="rounded-xl border bg-white p-3 text-sm"><h3 class="mb-2 font-bold">أيام الإغلاق</h3><div v-for="closure in closures" :key="closure.date" class="border-t py-1">{{ closure.date }} · {{ closure.user }}</div><div v-if="!closures.length" class="text-gray-500">لا توجد أيام مغلقة.</div></div>
            <div class="flex gap-3 text-sm">
                <div class="rounded-xl border bg-white p-3">إجمالي التنبيهات <b class="mr-2 text-lg">{{ flags.length }}</b></div>
                <div class="rounded-xl border bg-white p-3">خطر عالي <b class="mr-2 text-lg text-hospital-danger">{{ highFlags }}</b></div>
            </div>
            <div class="space-y-2">
                <div v-for="(f, i) in flags" :key="i" class="rounded-lg border bg-white p-3" :class="f.severity === 'high' ? 'border-r-4 border-r-hospital-danger' : 'border-r-4 border-r-hospital-warning'">
                    <div class="font-bold">{{ f.title }}</div><div class="text-sm text-gray-500">{{ f.detail }}</div>
                </div>
                <div v-if="!flags.length" class="rounded-lg border border-dashed p-3 text-sm text-gray-500">مفيش تنبيهات حالياً — كل الحركات متوازنة.</div>
            </div>
            <div class="overflow-x-auto rounded-xl border bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500"><tr><th class="p-2 text-start">الوقت</th><th class="p-2 text-start">المستخدم</th><th class="p-2 text-start">الحركة</th><th class="p-2 text-start">التفاصيل</th></tr></thead>
                    <tbody><tr v-for="(l, i) in log" :key="i" class="border-t"><td class="p-2 tabular-nums">{{ l.time }}</td><td class="p-2">{{ l.user }}</td><td class="p-2">{{ actionLabel(l.action) }}</td><td class="p-2">{{ l.detail }}</td></tr></tbody>
                </table>
            </div>
        </section>

        <Modal :model-value="selected !== null" :title="selected?.reference ?? 'مطالبة'" @update:model-value="selected = null">
            <div v-if="selected" class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-2">
                    <div class="rounded border p-2"><div class="text-xs text-gray-500">الشركة</div>{{ selected.company }}</div>
                    <div class="rounded border p-2"><div class="text-xs text-gray-500">المرحلة</div>{{ selected.stage_label }}</div>
                    <div class="rounded border p-2"><div class="text-xs text-gray-500">المريض</div>{{ selected.patient }}</div>
                    <div class="rounded border p-2"><div class="text-xs text-gray-500">القيمة</div>{{ egp(selected.share) }}</div>
                </div>

                <template v-if="canCollect">
                    <form v-if="selected.stage === 'approved'" class="space-y-2 rounded-xl border bg-gray-50 p-3" @submit.prevent="submitCollection(selected)">
                        <div class="font-bold">تسجيل التحصيل</div>
                        <label class="flex items-center justify-between gap-2">المبلغ المستلم<input v-model.number="collectForm.amount" type="number" min="0" class="w-36 rounded border p-1 text-end"></label>
                        <label class="flex items-center justify-between gap-2">الخصم %<input v-model.number="discountPct" type="number" min="0" step="0.1" class="w-36 rounded border p-1 text-end"></label>
                        <label class="flex items-center justify-between gap-2">قيمة الخصم<input v-model.number="collectForm.discount" type="number" min="0" class="w-36 rounded border p-1 text-end"></label>
                        <label v-if="needsReason || collectForm.reason" class="block">سبب الخصم<textarea v-model="collectForm.reason" class="mt-1 w-full rounded border p-1" rows="2" /></label>
                        <div class="flex justify-between border-t pt-2 font-bold"><span>الصافي</span><span class="tabular-nums text-hospital-success">{{ egp(net) }}</span></div>
                        <p v-for="(msg, field) in collectForm.errors" :key="field" class="text-hospital-danger">{{ msg }}</p>
                        <button type="submit" class="rounded bg-hospital-primary px-4 py-2 font-bold text-white" :disabled="collectForm.processing">تأكيد التحصيل</button>
                    </form>
                    <button v-else-if="selected.stage !== 'closed'" class="rounded bg-hospital-primary px-4 py-2 font-bold text-white" @click="advanceStage(selected)">نقل للمرحلة التالية</button>
                    <div v-else class="rounded border border-dashed p-2 text-gray-500">المطالبة مقفولة — اكتملت دورة التحصيل.</div>
                </template>
            </div>
        </Modal>
    </div>
</template>
