<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { Banknote, CheckCircle, PlusCircle, RefreshCw, Wallet } from 'lucide-vue-next'
import { ref } from 'vue'
import Modal from '@/components/shared/Modal.vue'

interface AttendanceSummary {
    present: number
    absent: number
    late: number
    half_day: number
    on_leave: number
    overtime_hours: number
    recorded_days: number
}

interface Employee { id: string; name: string; dept: string; position: string }

interface PayrollRow {
    id: string
    month: number
    year: number
    base_salary: number
    allowances: number
    overtime_pay: number
    deductions: number
    net_salary: number
    status: string
    paid_at?: string
    notes?: string
    employee: Employee
    attendance_summary: AttendanceSummary
}

const props = defineProps<{
    payrolls: { data: PayrollRow[]; current_page: number; last_page: number; total: number }
    filters: { month: number; year: number; status?: string }
    stats: { total_employees: number; total_net: number; paid_count: number; draft_count: number }
}>()

const months = [
    { value: 1, label: 'يناير' }, { value: 2, label: 'فبراير' }, { value: 3, label: 'مارس' },
    { value: 4, label: 'أبريل' }, { value: 5, label: 'مايو' }, { value: 6, label: 'يونيو' },
    { value: 7, label: 'يوليو' }, { value: 8, label: 'أغسطس' }, { value: 9, label: 'سبتمبر' },
    { value: 10, label: 'أكتوبر' }, { value: 11, label: 'نوفمبر' }, { value: 12, label: 'ديسمبر' },
]
const currentYear = new Date().getFullYear()
const years = Array.from({ length: 6 }, (_, i) => currentYear - 2 + i)

const statusConfig: Record<string, { label: string; class: string }> = {
    draft:    { label: 'مسودة',    class: 'bg-sf2 text-t3' },
    approved: { label: 'معتمدة',   class: 'bg-pp text-p' },
    paid:     { label: 'مصروفة',   class: 'bg-sp text-s' },
}

const selectedMonth = ref(props.filters.month)
const selectedYear  = ref(props.filters.year)
const statusFilter  = ref(props.filters.status ?? '')

function applyFilters() {
    router.get('/payroll', {
        month: selectedMonth.value,
        year: selectedYear.value,
        status: statusFilter.value || undefined,
    }, { preserveState: true })
}
function goToPage(page: number) {
    router.get('/payroll', {
        month: selectedMonth.value,
        year: selectedYear.value,
        status: statusFilter.value || undefined,
        page,
    }, { preserveState: true })
}

function fmt(n: number) { return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 0 }) }
function fmtDec(n: number) { return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 }) }
function monthLabel(m: number) { return months.find((x) => x.value === m)?.label ?? String(m) }

// Generate
const generateForm = useForm({ month: props.filters.month, year: props.filters.year })
function generate() {
    generateForm.month = selectedMonth.value
    generateForm.year  = selectedYear.value
    generateForm.post('/payroll/generate', { preserveState: true })
}

// Recalculate from attendance
function recalculate(id: string) {
    router.post(`/payroll/${id}/recalculate`, {}, { preserveState: true })
}

// Approve / Pay
function approve(id: string) { router.post(`/payroll/${id}/approve`, {}, { preserveState: true }) }
function markPaid(id: string) { router.post(`/payroll/${id}/pay`, {}, { preserveState: true }) }

// Edit
const showEdit   = ref(false)
const editingId  = ref('')
const editingEmp = ref('')
const editForm   = useForm({ base_salary: '', allowances: '', overtime_pay: '', deductions: '', notes: '' })

function openEdit(p: PayrollRow) {
    editingId.value  = p.id
    editingEmp.value = p.employee?.name ?? ''
    editForm.base_salary  = String(p.base_salary)
    editForm.allowances   = String(p.allowances)
    editForm.overtime_pay = String(p.overtime_pay)
    editForm.deductions   = String(p.deductions)
    editForm.notes        = p.notes ?? ''
    showEdit.value = true
}
function submitEdit() {
    editForm.put(`/payroll/${editingId.value}`, { onSuccess: () => { showEdit.value = false } })
}

function previewNet() {
    const base  = parseFloat(editForm.base_salary)  || 0
    const allow = parseFloat(editForm.allowances)    || 0
    const ot    = parseFloat(editForm.overtime_pay)  || 0
    const ded   = parseFloat(editForm.deductions)    || 0
    return base + allow + ot - ded
}

function attendanceColor(s: AttendanceSummary) {
    if (!s || s.recorded_days === 0) return 'text-t3'
    if (s.absent > 3) return 'text-d'
    if (s.absent > 0) return 'text-w'
    return 'text-s'
}
</script>

<template>
    <Head title="الرواتب" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الرواتب</h1>
        <p class="mt-0.5 text-sm text-t3">إدارة رواتب الموظفين — تُحسب الخصومات والإضافي تلقائياً من سجلات الحضور</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-4 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-pp">
                <Wallet class="h-5 w-5 text-p" />
            </div>
            <div>
                <p class="text-xs text-t3">موظفون في الكشف</p>
                <p class="text-xl font-bold text-t">{{ stats.total_employees }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-sp">
                <Banknote class="h-5 w-5 text-s" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الصافي</p>
                <p class="text-lg font-bold text-s">{{ fmt(stats.total_net) }} ج.م</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ap">
                <CheckCircle class="h-5 w-5 text-a" />
            </div>
            <div>
                <p class="text-xs text-t3">تم الصرف</p>
                <p class="text-xl font-bold text-a">{{ stats.paid_count }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <PlusCircle class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">مسودة</p>
                <p class="text-xl font-bold text-w">{{ stats.draft_count }}</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <select v-model="selectedMonth" class="input-field w-32" @change="applyFilters">
                <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select v-model="selectedYear" class="input-field w-24" @change="applyFilters">
                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <select v-model="statusFilter" class="input-field w-28" @change="applyFilters">
                <option value="">كل الحالات</option>
                <option v-for="(cfg, key) in statusConfig" :key="key" :value="key">{{ cfg.label }}</option>
            </select>
        </div>
        <button class="btn-primary flex items-center gap-1.5" :disabled="generateForm.processing" @click="generate">
            <PlusCircle class="h-4 w-4" />
            {{ generateForm.processing ? 'جارٍ التوليد...' : `توليد رواتب ${monthLabel(selectedMonth)} ${selectedYear}` }}
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الموظف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">سجل الحضور</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الراتب الأساسي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">البدلات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إضافي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">خصومات</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الصافي</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الحالة</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="p in payrolls.data" :key="p.id"
                    :class="[p.status === 'paid' ? 'opacity-70' : '', 'hover:bg-sf2 transition-colors']">
                    <td class="px-4 py-3">
                        <p class="font-medium text-t">{{ p.employee?.name }}</p>
                        <p class="text-xs text-t3">{{ p.employee?.dept }} · {{ p.employee?.position }}</p>
                    </td>

                    <!-- Attendance Summary -->
                    <td class="px-4 py-3">
                        <template v-if="p.attendance_summary && p.attendance_summary.recorded_days > 0">
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="text-s font-medium">{{ p.attendance_summary.present }}ح</span>
                                <span class="text-t3">/</span>
                                <span :class="p.attendance_summary.absent > 0 ? 'text-d font-medium' : 'text-t3'">
                                    {{ p.attendance_summary.absent }}غ
                                </span>
                                <span v-if="p.attendance_summary.late > 0" class="text-w">
                                    · {{ p.attendance_summary.late }}ت
                                </span>
                            </div>
                            <div class="mt-0.5 text-xs" :class="p.attendance_summary.overtime_hours > 0 ? 'text-p' : 'text-t3'">
                                <span v-if="p.attendance_summary.overtime_hours > 0">
                                    +{{ p.attendance_summary.overtime_hours }}س إضافي
                                </span>
                                <span v-else>لا يوجد إضافي</span>
                            </div>
                        </template>
                        <span v-else class="text-xs text-t3">لا يوجد سجل</span>
                    </td>

                    <td class="px-4 py-3 font-mono text-t2">{{ fmt(p.base_salary) }}</td>
                    <td class="px-4 py-3 font-mono text-t2">{{ fmt(p.allowances) }}</td>
                    <td class="px-4 py-3 font-mono" :class="Number(p.overtime_pay) > 0 ? 'text-s font-medium' : 'text-t3'">
                        {{ Number(p.overtime_pay) > 0 ? '+' + fmt(p.overtime_pay) : '—' }}
                    </td>
                    <td class="px-4 py-3 font-mono" :class="Number(p.deductions) > 0 ? 'text-d font-medium' : 'text-t3'">
                        {{ Number(p.deductions) > 0 ? '-' + fmt(p.deductions) : '—' }}
                    </td>
                    <td class="px-4 py-3 font-mono font-bold text-t">{{ fmtDec(p.net_salary) }} ج.م</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusConfig[p.status]?.class ?? 'bg-sf2 text-t3'">
                            {{ statusConfig[p.status]?.label ?? p.status }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1">
                            <!-- Recalculate from attendance -->
                            <button v-if="p.status !== 'paid'"
                                class="rounded-lg border border-br p-1 text-t3 hover:border-p/40 hover:bg-pp hover:text-p transition-colors"
                                title="إعادة حساب من الحضور"
                                @click="recalculate(p.id)">
                                <RefreshCw class="h-3.5 w-3.5" />
                            </button>
                            <button v-if="p.status !== 'paid'"
                                class="rounded-lg border border-br px-2 py-0.5 text-xs text-t2 hover:border-p/40 hover:bg-pp hover:text-p transition-colors"
                                @click="openEdit(p)">تعديل</button>
                            <button v-if="p.status === 'draft'"
                                class="rounded-lg border border-br px-2 py-0.5 text-xs text-p hover:bg-pp hover:border-p/40 transition-colors"
                                @click="approve(p.id)">اعتماد</button>
                            <button v-if="p.status === 'approved'"
                                class="rounded-lg border border-br px-2 py-0.5 text-xs text-s hover:bg-sp hover:border-s/40 transition-colors"
                                @click="markPaid(p.id)">صرف</button>
                        </div>
                    </td>
                </tr>
                <tr v-if="payrolls.data.length === 0">
                    <td class="px-4 py-12 text-center text-t3" colspan="9">
                        لا توجد رواتب لهذا الشهر —
                        <button class="text-p underline" @click="generate">توليد الآن</button>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Summary footer -->
        <div v-if="payrolls.data.length > 0" class="flex items-center justify-between border-t border-br bg-sf2 px-4 py-3">
            <span class="text-xs text-t3">{{ payrolls.total }} موظف · {{ monthLabel(filters.month) }} {{ filters.year }}</span>
            <span class="text-sm font-bold text-t">الإجمالي الصافي: {{ fmtDec(stats.total_net) }} ج.م</span>
        </div>

        <div v-if="payrolls.last_page > 1" class="flex items-center justify-end border-t border-br px-4 py-3">
            <div class="flex gap-1">
                <button v-for="p in payrolls.last_page" :key="p" class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === payrolls.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'" @click="goToPage(p)">
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Legend -->
    <div class="mt-3 flex items-center gap-4 text-xs text-t3">
        <span><span class="font-medium text-t">ح</span> = حاضر</span>
        <span><span class="font-medium text-d">غ</span> = غائب (خصم يوم كامل)</span>
        <span><span class="font-medium text-w">ت</span> = متأخر (خصم ربع يوم)</span>
        <span><span class="font-medium text-p">س إضافي</span> = وقت إضافي (× 1.5)</span>
        <span class="mr-auto flex items-center gap-1"><RefreshCw class="h-3 w-3" /> = إعادة حساب من الحضور</span>
    </div>

    <!-- Edit Modal -->
    <Modal v-model="showEdit" :title="`تعديل راتب: ${editingEmp}`">
        <form class="space-y-4" @submit.prevent="submitEdit">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">الراتب الأساسي</label>
                    <input v-model="editForm.base_salary" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div>
                    <label class="form-label">البدلات</label>
                    <input v-model="editForm.allowances" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div>
                    <label class="form-label">وقت إضافي (محسوب أو يدوي)</label>
                    <input v-model="editForm.overtime_pay" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div>
                    <label class="form-label">خصومات (محسوبة أو يدوية)</label>
                    <input v-model="editForm.deductions" type="number" min="0" step="0.01" class="input-field" />
                </div>
                <div class="col-span-2 rounded-xl border border-br bg-sf2 p-3 text-center">
                    <p class="text-xs text-t3">الراتب الصافي المحسوب</p>
                    <p class="text-2xl font-bold" :class="previewNet() >= 0 ? 'text-s' : 'text-d'">
                        {{ previewNet().toLocaleString('ar-EG', { minimumFractionDigits: 2 }) }} ج.م
                    </p>
                </div>
                <div class="col-span-2">
                    <label class="form-label">ملاحظات</label>
                    <textarea v-model="editForm.notes" rows="2" class="input-field" />
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showEdit = false">إلغاء</button>
                <button type="submit" :disabled="editForm.processing" class="btn-primary">
                    {{ editForm.processing ? 'جارٍ الحفظ...' : 'حفظ التعديلات' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
