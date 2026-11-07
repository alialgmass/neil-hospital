<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { MinusCircle, PlusCircle, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import Modal from '@/components/shared/Modal.vue'
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions'

interface Employee { id: string; name: string; dept: string; base_salary: string }
interface Deduction {
    id: string
    deduction_date: string
    type: 'days' | 'amount'
    days: string | null
    daily_rate: string | null
    amount: string
    reason: string
    employee: { id: string; name: string; dept: string }
    creator?: { id: number; name: string } | null
}

const props = defineProps<{
    deductions: { data: Deduction[]; current_page: number; last_page: number; total: number }
    employees: Employee[]
    workingDays: number
    filters: { employee_id?: string; from?: string; to?: string }
    totals: { count: number; amount: number }
}>()

const { can } = usePermissions()
const canWrite = computed(() => can('hr.manage'))

function money(value: number | string): string {
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

// ── Filters ──
const employeeFilter = ref(props.filters.employee_id ?? '')
const fromFilter = ref(props.filters.from ?? '')
const toFilter = ref(props.filters.to ?? '')

function query(page?: number) {
    return {
        employee_id: employeeFilter.value || undefined,
        from: fromFilter.value || undefined,
        to: toFilter.value || undefined,
        page,
    }
}

function applyFilters() {
    router.get('/employee-deductions', query(), { preserveState: true })
}

function goToPage(page: number) {
    router.get('/employee-deductions', query(page), { preserveState: true })
}

// ── Add ──
const showAdd = ref(false)
const form = useForm({
    employee_id: '',
    deduction_date: new Date().toISOString().slice(0, 10),
    type: 'days' as 'days' | 'amount',
    days: '' as string | number,
    amount: '' as string | number,
    reason: '',
})

const selectedEmployee = computed(() => props.employees.find((e) => e.id === form.employee_id))

const dailyRate = computed(() => {
    const base = Number(selectedEmployee.value?.base_salary ?? 0)

    return props.workingDays > 0 ? Math.round((base / props.workingDays) * 100) / 100 : 0
})

const calculatedAmount = computed(() => {
    if (form.type === 'days') {
        return Math.round(Number(form.days || 0) * dailyRate.value * 100) / 100
    }

    return Number(form.amount || 0)
})

function submit() {
    if (!canWrite.value) {
        return
    }

    form.post('/employee-deductions', {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false
            form.reset('employee_id', 'days', 'amount', 'reason')
        },
    })
}

function remove(deduction: Deduction) {
    if (!canWrite.value || !window.confirm(`حذف خصم ${deduction.employee.name} (${money(deduction.amount)} ج.م)؟`)) {
        return
    }

    router.delete(`/employee-deductions/${deduction.id}`, { preserveScroll: true })
}
</script>

<template>
    <Head title="خصومات الموظفين" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">خصومات الموظفين</h1>
        <p class="mt-0.5 text-sm text-t3">تسجيل خصم بعدد أيام (يُحسب من راتب الموظف) أو بمبلغ مباشر مع السبب</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-2 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-dp">
                <MinusCircle class="h-5 w-5 text-d" />
            </div>
            <div>
                <p class="text-xs text-t3">عدد الخصومات</p>
                <p class="text-xl font-bold text-t">{{ totals.count }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-wp">
                <MinusCircle class="h-5 w-5 text-w" />
            </div>
            <div>
                <p class="text-xs text-t3">إجمالي الخصومات (ج.م)</p>
                <p class="text-xl font-bold text-d">{{ money(totals.amount) }}</p>
            </div>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="employeeFilter" class="input-field w-44" @change="applyFilters">
                <option value="">كل الموظفين</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
            <input v-model="fromFilter" type="date" class="input-field w-40" @change="applyFilters" />
            <input v-model="toFilter" type="date" class="input-field w-40" @change="applyFilters" />
        </div>
        <button
            class="btn-primary flex items-center gap-1.5 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!canWrite"
            :title="canWrite ? undefined : NO_PERMISSION_TITLE"
            @click="canWrite && (showAdd = true)"
        >
            <PlusCircle class="h-4 w-4" />
            خصم جديد
        </button>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">التاريخ</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">الموظف</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">نوع الخصم</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">المبلغ (ج.م)</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">السبب</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-t2">بواسطة</th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr v-for="d in deductions.data" :key="d.id" class="transition-colors hover:bg-sf2">
                    <td class="px-4 py-3 text-xs text-t2">{{ d.deduction_date.slice(0, 10) }}</td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-t">{{ d.employee?.name }}</p>
                        <p class="text-xs text-t3">{{ d.employee?.dept }}</p>
                    </td>
                    <td class="px-4 py-3 text-xs text-t2">
                        <template v-if="d.type === 'days'">
                            {{ Number(d.days) }} يوم
                            <span class="block text-t3">× {{ money(d.daily_rate ?? 0) }} يومية</span>
                        </template>
                        <template v-else>مبلغ مباشر</template>
                    </td>
                    <td class="px-4 py-3 font-mono font-bold text-d">{{ money(d.amount) }}</td>
                    <td class="max-w-[220px] truncate px-4 py-3 text-xs text-t3" :title="d.reason">{{ d.reason }}</td>
                    <td class="px-4 py-3 text-xs text-t3">{{ d.creator?.name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <button
                            type="button"
                            class="rounded p-1.5 text-t3 transition-colors hover:bg-dp hover:text-d disabled:cursor-not-allowed disabled:opacity-30"
                            :disabled="!canWrite"
                            :title="canWrite ? 'حذف' : NO_PERMISSION_TITLE"
                            @click="remove(d)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </td>
                </tr>
                <tr v-if="deductions.data.length === 0">
                    <td class="px-4 py-12 text-center text-t3" colspan="7">لا توجد خصومات</td>
                </tr>
            </tbody>
        </table>
        <div v-if="deductions.last_page > 1" class="flex items-center justify-between border-t border-br px-4 py-3">
            <span class="text-xs text-t3">إجمالي {{ deductions.total }} خصم</span>
            <div class="flex gap-1">
                <button
                    v-for="p in deductions.last_page"
                    :key="p"
                    class="h-7 w-7 rounded-lg text-xs transition-colors"
                    :class="p === deductions.current_page ? 'bg-p text-white' : 'text-t2 hover:bg-sf2'"
                    @click="goToPage(p)"
                >
                    {{ p }}
                </button>
            </div>
        </div>
    </div>

    <!-- Add Modal -->
    <Modal v-model="showAdd" title="تسجيل خصم جديد" size="lg">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="form-label">الموظف <span class="text-d">*</span></label>
                    <select v-model="form.employee_id" class="input-field">
                        <option value="">— اختر الموظف —</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }} ({{ e.dept }})</option>
                    </select>
                    <p v-if="form.errors.employee_id" class="form-error">{{ form.errors.employee_id }}</p>
                </div>
                <div>
                    <label class="form-label">تاريخ الخصم <span class="text-d">*</span></label>
                    <input v-model="form.deduction_date" type="date" class="input-field" />
                    <p v-if="form.errors.deduction_date" class="form-error">{{ form.errors.deduction_date }}</p>
                </div>
                <div>
                    <label class="form-label">طريقة الخصم <span class="text-d">*</span></label>
                    <select v-model="form.type" class="input-field">
                        <option value="days">بعدد أيام</option>
                        <option value="amount">بمبلغ</option>
                    </select>
                </div>
                <div v-if="form.type === 'days'">
                    <label class="form-label">عدد الأيام <span class="text-d">*</span></label>
                    <input v-model="form.days" type="number" min="0.25" max="31" step="0.25" class="input-field" />
                    <p v-if="form.errors.days" class="form-error">{{ form.errors.days }}</p>
                </div>
                <div v-else>
                    <label class="form-label">المبلغ (ج.م) <span class="text-d">*</span></label>
                    <input v-model="form.amount" type="number" min="0" step="0.01" class="input-field" />
                    <p v-if="form.errors.amount" class="form-error">{{ form.errors.amount }}</p>
                </div>
                <div class="flex items-end">
                    <div class="w-full rounded-lg bg-pp px-3 py-2 text-sm">
                        <template v-if="form.type === 'days' && selectedEmployee">
                            <span class="text-t3">اليومية: {{ money(dailyRate) }} (الراتب ÷ {{ workingDays }} يوم)</span>
                            <br />
                        </template>
                        <span class="font-bold text-p">قيمة الخصم: {{ money(calculatedAmount) }} ج.م</span>
                    </div>
                </div>
                <div class="col-span-2">
                    <label class="form-label">السبب <span class="text-d">*</span></label>
                    <textarea v-model="form.reason" rows="2" class="input-field" placeholder="سبب الخصم..." />
                    <p v-if="form.errors.reason" class="form-error">{{ form.errors.reason }}</p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-br pt-4">
                <button type="button" class="btn-secondary" @click="showAdd = false">إلغاء</button>
                <button
                    type="submit"
                    :disabled="form.processing || !canWrite"
                    class="btn-primary disabled:cursor-not-allowed disabled:opacity-50"
                    :title="canWrite ? undefined : NO_PERMISSION_TITLE"
                >
                    {{ form.processing ? 'جارٍ الحفظ...' : 'تسجيل الخصم' }}
                </button>
            </div>
        </form>
    </Modal>
</template>
