<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { CalendarCheck, CalendarX, Clock4, PlusCircle } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import Modal from '@/components/shared/Modal.vue'

interface Employee { id: string; name: string; dept: string }
interface Leave {
    id: string
    from_date: string
    to_date: string
    days: number
    type: string
    reason?: string
    status: string
    employee: Employee
}

const props = defineProps<{
    leaves: { data: Leave[]; current_page: number; last_page: number; total: number }
    employees: Employee[]
    filters: { employee_id?: string; type?: string; status?: string }
    stats: { pending: number; approved: number; rejected: number; total_days: number }
}>()

const leaveTypeConfig: Record<string, { label: string; class: string }> = {
    annual: { label: 'سنوية', class: 'bg-pp text-p' },
    sick: { label: 'مرضية', class: 'bg-dp text-d' },
    unpaid: { label: 'بدون راتب', class: 'bg-sf2 text-t2' },
    emergency: { label: 'طارئة', class: 'bg-wp text-w' },
    maternity: { label: 'أمومة', class: 'bg-ap text-a' },
}
const statusConfig: Record<string, { label: string; class: string }> = {
    pending: { label: 'معلقة', class: 'bg-wp text-w' },
    approved: { label: 'موافق عليها', class: 'bg-sp text-s' },
    rejected: { label: 'مرفوضة', class: 'bg-dp text-d' },
}

const employeeFilter = ref(props.filters.employee_id ?? '')
const typeFilter = ref(props.filters.type ?? '')
const statusFilter = ref(props.filters.status ?? '')

function applyFilters() {
    router.get('/leaves', {
        employee_id: employeeFilter.value || undefined,
        type: typeFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true })
}
function goToPage(page: number) {
    router.get('/leaves', {
        employee_id: employeeFilter.value || undefined,
        type: typeFilter.value || undefined,
        status: statusFilter.value || undefined,
        page,
    }, { preserveState: true })
}

function approve(id: string) { router.post(`/leaves/${id}/approve`, {}, { preserveState: true }) }
function reject(id: string) { router.post(`/leaves/${id}/reject`, {}, { preserveState: true }) }

// Add
const showAdd = ref(false)
const addForm = useForm({
    employee_id: '',
    type: 'annual',
    from_date: '',
    to_date: '',
    reason: '',
    notes: '',
})

const autodays = computed(() => {
    if (!addForm.from_date || !addForm.to_date) return 0
    const from = new Date(addForm.from_date)
    const to = new Date(addForm.to_date)
    const diff = Math.round((to.getTime() - from.getTime()) / 86400000) + 1
    return diff > 0 ? diff : 0
})

function submitAdd() {
    addForm.post('/leaves', { onSuccess: () => { showAdd.value = false; addForm.reset() } })
}
</script>

<template>
    <Head title="الإجازات" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الإجازات</h1>
        <p class="mt-0.5 text-sm text-t3">إدارة طلبات إجازات الموظفين والموافقة عليها</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-4 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <Clock4 class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">معلقة</p>
                <p class="text-xl font-bold text-w">{{ stats.pending }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <CalendarCheck class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">موافق عليها</p>
                <p class="text-xl font-bold text-s">{{ stats.approved }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <CalendarX class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">مرفوضة</p>
                <p class="text-xl font-bold text-d">{{ stats.rejected }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <CalendarCheck class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي أيام مأخوذة</p>
                <p class="text-xl font-bold text-t">{{ stats.total_days }}</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <select v-model="employeeFilter" class="input-field w-44" @change="applyFilters">
                <option value="">كل الموظفين</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
            <select v-model="typeFilter" class="input-field w-32" @change="applyFilters">
                <option value="">كل الأنواع</option>
                <option v-for="(cfg, key) in leaveTypeConfig" :key="key" :value="key">{{ cfg.label }}</option>
            </select>
            <select v-model="statusFilter" class="input-field w-32" @change="applyFilters">
                <option value="">كل الحالات</option>
                <option v-for="(cfg, key) in statusConfig" :key="key" :value="key">{{ cfg.label }}</option>
            </select>
        </div>
        <button class="btn-primary flex items-center gap-1.5" @click="showAdd = true">
            <PlusCircle class="h-4 w-4" />
            إجازة جديدة
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الموظف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">نوع الإجازة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">من</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إلى</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الأيام</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">السبب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجراء</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="l in leaves.data" :key="l.id"
                    :class="[l.status === 'pending' ? 'bg-wp/5' : '', 'hover:bg-sf2 transition-colors']">
                    <td class="px-4 py-3">
                        <p class="font-medium text-t">{{ l.employee?.name }}</p>
                        <p class="text-xs text-t3">{{ l.employee?.dept }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="leaveTypeConfig[l.type]?.class ?? 'bg-sf2 text-t3'">
                            {{ leaveTypeConfig[l.type]?.label ?? l.type }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-t2">{{ l.from_date }}</td>
                    <td class="px-4 py-3 text-xs text-t2">{{ l.to_date }}</td>
                    <td class="px-4 py-3 font-mono font-bold text-t">{{ l.days }}</td>
                    <td class="px-4 py-3 text-xs text-t3 max-w-[180px] truncate">{{ l.reason || '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusConfig[l.status]?.class ?? 'bg-sf2 text-t3'">
                            {{ statusConfig[l.status]?.label ?? l.status }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div v-if="l.status === 'pending'" class="flex gap-1">
                            <button class="rounded-lg border border-br px-2.5 py-1 text-xs text-s hover:bg-sp hover:border-s/40 transition-colors"
                                @click="approve(l.id)">موافقة</button>
                            <button class="rounded-lg border border-br px-2.5 py-1 text-xs text-d hover:bg-dp hover:border-d/40 transition-colors"
                                @click="reject(l.id)">رفض</button>
                        </div>
                        <span v-else class="text-xs text-t3">—</span>
                    </td>
                </tr>
                <tr v-if="leaves.data.length === 0">
                    <td class="px-4 py-12 text-center text-t3" colspan="8">لا توجد إجازات</td>
                </tr>
            </tbody>
        </table>
        <div v-if="leaves.last_page > 1" class="flex items-center justify-between border-t border-br px-4 py-3">
            <span class="text-xs text-t3">إجمالي {{ leaves.total }} إجازة</span>
            <div class="flex gap-1">
                <button v-for="p in leaves.last_page" :key="p" class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === leaves.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'" @click="goToPage(p)">
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="تسجيل إجازة جديدة" size="lg">
        <form class="space-y-4" @submit.prevent="submitAdd">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">الموظف <span class="text-d">*</span></label>
                    <select v-model="addForm.employee_id" class="input-field">
                        <option value="">— اختر الموظف —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} ({{ e.dept }})</option>
                    </select>
                    <p v-if="addForm.errors.employee_id" class="form-error">{{ addForm.errors.employee_id }}</p>
                </div>
                <div>
                    <label class="form-label">نوع الإجازة <span class="text-d">*</span></label>
                    <select v-model="addForm.type" class="input-field">
                        <option v-for="(cfg, key) in leaveTypeConfig" :key="key" :value="key">{{ cfg.label }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-1">
                    <div class="flex-1">
                        <label class="form-label">من <span class="text-d">*</span></label>
                        <input v-model="addForm.from_date" type="date" class="input-field" />
                    </div>
                    <div class="flex-1">
                        <label class="form-label">إلى <span class="text-d">*</span></label>
                        <input v-model="addForm.to_date" type="date" class="input-field" />
                    </div>
                    <div class="shrink-0 pb-1">
                        <span class="rounded-lg bg-pp px-3 py-2 text-sm font-bold text-p">{{ autodays }} يوم</span>
                    </div>
                </div>
                <div class="col-span-2">
                    <label class="form-label">السبب</label>
                    <textarea v-model="addForm.reason" rows="2" class="input-field" placeholder="سبب الإجازة..." />
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="addForm.notes" rows="2" class="input-field" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button type="submit" :disabled="addForm.processing" class="btn-primary">
                    {{ addForm.processing ? 'جارٍ الحفظ...' : 'تسجيل الإجازة' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
