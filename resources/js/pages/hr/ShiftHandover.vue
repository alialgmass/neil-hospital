<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { ArrowLeftRight, CheckCircle, Clock, PlusCircle } from 'lucide-vue-next'
import { ref } from 'vue'
import Modal from '@/components/shared/Modal.vue'

interface Employee { id: string; name: string; dept: string }
interface Shift { id: string; name: string; start_time: string; end_time: string }
interface Handover {
    id: string
    handover_date: string
    handover_time?: string
    cash_amount?: number
    notes?: string
    status: string
    shift: Shift
    from_employee: Employee
    to_employee: Employee
}

const props = defineProps<{
    handovers: { data: Handover[]; current_page: number; last_page: number; total: number }
    shifts: Shift[]
    employees: Employee[]
    filters: { date?: string; shift_id?: string; status?: string }
    stats: { today: number; pending: number; accepted: number }
}>()

const statusConfig: Record<string, { label: string; class: string }> = {
    pending: { label: 'معلق', class: 'bg-wp text-w' },
    accepted: { label: 'مقبول', class: 'bg-sp text-s' },
}

const dateFilter = ref(props.filters.date ?? '')
const shiftFilter = ref(props.filters.shift_id ?? '')
const statusFilter = ref(props.filters.status ?? '')

function applyFilters() {
    router.get('/shift-handovers', {
        date: dateFilter.value || undefined,
        shift_id: shiftFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true })
}
function goToPage(page: number) {
    router.get('/shift-handovers', {
        date: dateFilter.value || undefined,
        shift_id: shiftFilter.value || undefined,
        status: statusFilter.value || undefined,
        page,
    }, { preserveState: true })
}

function accept(id: string) {
    router.post(`/shift-handovers/${id}/accept`, {}, { preserveState: true })
}

function fmt(n: number) { return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 }) }

// Add Modal
const showAdd = ref(false)
const addForm = useForm({
    shift_id: '',
    from_employee_id: '',
    to_employee_id: '',
    handover_date: new Date().toISOString().split('T')[0],
    handover_time: '',
    cash_amount: '',
    notes: '',
})
function submitAdd() {
    addForm.post('/shift-handovers', {
        onSuccess: () => { showAdd.value = false; addForm.reset() },
    })
}
</script>

<template>
    <Head title="تسليم الوردية" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تسليم الوردية</h1>
        <p class="mt-0.5 text-sm text-t3">توثيق عمليات تسليم واستلام الورديات بين الموظفين</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-3 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Clock class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">تسليمات اليوم</p>
                <p class="text-xl font-bold text-t">{{ stats.today }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <ArrowLeftRight class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">معلقة</p>
                <p class="text-xl font-bold text-w">{{ stats.pending }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <CheckCircle class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">مقبولة</p>
                <p class="text-xl font-bold text-s">{{ stats.accepted }}</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <input v-model="dateFilter" type="date" class="input-field w-44" @change="applyFilters" />
            <select v-model="shiftFilter" class="input-field w-32" @change="applyFilters">
                <option value="">كل الورديات</option>
                <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
            <select v-model="statusFilter" class="input-field w-28" @change="applyFilters">
                <option value="">الكل</option>
                <option value="pending">معلق</option>
                <option value="accepted">مقبول</option>
            </select>
        </div>
        <button class="btn-primary flex items-center gap-1.5" @click="showAdd = true">
            <PlusCircle class="h-4 w-4" />
            تسليم وردية
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ والوقت</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الوردية</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المسلِّم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المستلِم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المبلغ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">ملاحظات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="h in handovers.data" :key="h.id" class="hover:bg-sf2 transition-colors">
                    <td class="px-4 py-3">
                        <p class="text-t text-xs font-medium">{{ h.handover_date }}</p>
                        <p v-if="h.handover_time" class="font-mono text-xs text-t3">{{ h.handover_time }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full bg-pp px-2 py-0.5 text-xs text-p">{{ h.shift?.name }}</span>
                    </td>
                    <td class="px-4 py-3 text-t2">{{ h.from_employee?.name }}</td>
                    <td class="px-4 py-3 font-medium text-t">{{ h.to_employee?.name }}</td>
                    <td class="px-4 py-3 font-mono text-xs" :class="h.cash_amount ? 'text-t' : 'text-t3'">
                        {{ h.cash_amount ? fmt(h.cash_amount) + ' ج.م' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-xs text-t3 max-w-[160px] truncate">{{ h.notes || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusConfig[h.status]?.class ?? 'bg-sf2 text-t3'">
                            {{ statusConfig[h.status]?.label ?? h.status }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <button v-if="h.status === 'pending'"
                            class="rounded-lg border border-br px-3 py-1 text-xs text-s hover:bg-sp hover:border-s/40 transition-colors"
                            @click="accept(h.id)">
                            قبول
                        </button>
                        <span v-else class="text-xs text-t3">—</span>
                    </td>
                </tr>
                <tr v-if="handovers.data.length === 0">
                    <td class="px-4 py-12 text-center text-t3" colspan="8">لا توجد عمليات تسليم</td>
                </tr>
            </tbody>
        </table>
        <div v-if="handovers.last_page > 1" class="flex items-center justify-between border-t border-br px-4 py-3">
            <span class="text-xs text-t3">إجمالي {{ handovers.total }} عملية</span>
            <div class="flex gap-1">
                <button v-for="p in handovers.last_page" :key="p" class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === handovers.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'" @click="goToPage(p)">
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="تسليم وردية جديدة" size="lg">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">الوردية <span class="text-d">*</span></label>
                    <select v-model="addForm.shift_id" class="input-field">
                        <option value="">— اختر الوردية —</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.start_time }}–{{ s.end_time }})</option>
                    </select>
                    <p v-if="addForm.errors.shift_id" class="form-error">{{ addForm.errors.shift_id }}</p>
                </div>
                <div>
                    <label class="form-label">التاريخ <span class="text-d">*</span></label>
                    <input v-model="addForm.handover_date" type="date" class="input-field" />
                </div>
                <div>
                    <label class="form-label">المسلِّم <span class="text-d">*</span></label>
                    <select v-model="addForm.from_employee_id" class="input-field">
                        <option value="">— اختر الموظف —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <p v-if="addForm.errors.from_employee_id" class="form-error">{{ addForm.errors.from_employee_id }}</p>
                </div>
                <div>
                    <label class="form-label">المستلِم <span class="text-d">*</span></label>
                    <select v-model="addForm.to_employee_id" class="input-field">
                        <option value="">— اختر الموظف —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                    </select>
                    <p v-if="addForm.errors.to_employee_id" class="form-error">{{ addForm.errors.to_employee_id }}</p>
                </div>
                <div>
                    <label class="form-label">وقت التسليم</label>
                    <input v-model="addForm.handover_time" type="time" class="input-field font-mono" />
                </div>
                <div>
                    <label class="form-label">مبلغ الخزنة (ج.م)</label>
                    <input v-model="addForm.cash_amount" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات التسليم</label>
                    <textarea v-model="addForm.notes" rows="3" class="input-field" placeholder="أي ملاحظات يجب توثيقها عند التسليم..." />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="btn-primary">
                    {{ addForm.processing ? 'جارٍ الحفظ...' : 'تسجيل التسليم' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
