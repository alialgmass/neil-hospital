<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { BookOpen, Layers } from 'lucide-vue-next';

interface CostCenterStat {
    code: string;
    label: string;
    entry_count: number;
    total_amount: number;
}

const props = defineProps<{
    centers: CostCenterStat[];
    filters: { from?: string; to?: string };
    grand_total: number;
    grand_entries: number;
}>();

const fromFilter = ref(props.filters.from ?? '');
const toFilter   = ref(props.filters.to   ?? '');

function applyFilters() {
    router.get('/cost-centers', {
        from: fromFilter.value || undefined,
        to:   toFilter.value   || undefined,
    }, { preserveState: true });
}

function goToJournal(code: string) {
    router.get('/journal', { cost_center: code });
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}

const centerMeta: Record<string, { bg: string; border: string; text: string; icon: string }> = {
    'CC-CLINIC': { bg: 'bg-pp',  border: 'border-p',  text: 'text-pd', icon: '🏥' },
    'CC-LAB':    { bg: 'bg-wp',  border: 'border-w',  text: 'text-w',  icon: '🔬' },
    'CC-SURG':   { bg: 'bg-sp',  border: 'border-s',  text: 'text-s',  icon: '🔪' },
    'CC-LASIK':  { bg: 'bg-[#F3E8FD]', border: 'border-[#7B2FA6]', text: 'text-[#4A0F7A]', icon: '💡' },
    'CC-LASER':  { bg: 'bg-ap',  border: 'border-a',  text: 'text-a',  icon: '⚡' },
    'CC-INS':    { bg: 'bg-[#FBF4DC]', border: 'border-[#C9A227]', text: 'text-[#5A440A]', icon: '🏦' },
    'CC-DR':     { bg: 'bg-dp',  border: 'border-d',  text: 'text-d',  icon: '👨‍⚕️' },
    'CC-INV':    { bg: 'bg-sf2', border: 'border-br', text: 'text-t',  icon: '📦' },
    'CC-EQUIP':  { bg: 'bg-pp',  border: 'border-pl', text: 'text-pd', icon: '⚙️' },
};

const maxAmount = computed(() => Math.max(...props.centers.map(c => c.total_amount), 1));
</script>

<template>
    <Head title="مراكز التكلفة" />

    <!-- Summary Row -->
    <div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-p text-white">
                <Layers class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-t2">مراكز التكلفة</p>
                <p class="text-xl font-bold text-t">{{ centers.length }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-s text-white">
                <BookOpen class="h-5 w-5" />
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-t2">إجمالي القيود</p>
                <p class="text-xl font-bold text-s">{{ grand_entries }}</p>
            </div>
        </div>
        <div class="col-span-2 flex items-center gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)] sm:col-span-1">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pd text-white">
                <span class="text-xs font-bold">ج</span>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-t2">إجمالي المبالغ</p>
                <p class="text-xl font-bold text-pd">{{ fmt(grand_total) }}</p>
            </div>
        </div>
    </div>

    <!-- Date Filters -->
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="flex flex-col gap-1">
            <label class="form-label">من تاريخ</label>
            <input v-model="fromFilter" type="date" class="input-field w-auto" @change="applyFilters" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى تاريخ</label>
            <input v-model="toFilter" type="date" class="input-field w-auto" @change="applyFilters" />
        </div>
        <button class="btn-primary pb-0.5" @click="applyFilters">عرض</button>
    </div>

    <!-- Cost Center Cards Grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="center in centers"
            :key="center.code"
            class="group cursor-pointer overflow-hidden rounded-[var(--rl)] border bg-sf shadow-[var(--sh)] transition-all hover:shadow-[var(--shl)] hover:-translate-y-0.5"
            :class="[centerMeta[center.code]?.border ?? 'border-br', 'border-r-4']"
            @click="goToJournal(center.code)"
        >
            <!-- Card Header -->
            <div class="flex items-center gap-3 p-4" :class="centerMeta[center.code]?.bg ?? 'bg-sf2'">
                <span class="text-2xl leading-none">{{ centerMeta[center.code]?.icon ?? '📊' }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold" :class="centerMeta[center.code]?.text ?? 'text-t'">
                        {{ center.label }}
                    </p>
                    <p class="font-mono text-[10px] text-t3">{{ center.code }}</p>
                </div>
                <span class="rounded-full bg-white/60 px-2 py-0.5 text-xs font-medium text-t2">
                    {{ center.entry_count }} قيد
                </span>
            </div>

            <!-- Card Body -->
            <div class="px-4 py-3">
                <p class="mb-2 text-xl font-bold text-t">{{ fmt(center.total_amount) }} <span class="text-xs font-normal text-t3">ج.م</span></p>

                <!-- Progress bar relative to highest center -->
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-br">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="centerMeta[center.code]?.border?.replace('border-', 'bg-') ?? 'bg-p'"
                        :style="{ width: center.total_amount > 0 ? `${Math.round((center.total_amount / maxAmount) * 100)}%` : '0%' }"
                    />
                </div>
            </div>

            <!-- Card Footer -->
            <div class="border-t border-br px-4 py-2">
                <span class="text-xs text-t3 group-hover:text-p transition-colors">عرض القيود ←</span>
            </div>
        </div>
    </div>

    <!-- Empty state -->
    <div v-if="centers.every(c => c.entry_count === 0)" class="mt-8 text-center text-sm text-t3">
        لا توجد قيود مرتبطة بمراكز التكلفة في هذه الفترة
    </div>
</template>
