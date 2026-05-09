<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

interface AttendanceRow {
    employee_id: string
    employee_name: string
    dept: string
    position: string
    status: string
    check_in: string
    check_out: string
    overtime_hours: number
    notes: string
    shift_id: string
}

interface Shift {
    id: string
    name: string
    start_time: string
    end_time: string
}

const props = defineProps<{
    rows: AttendanceRow[]
    shifts: Shift[]
    filters: { date: string }
}>()

const statusOptions = [
    { value: '', label: '— الحالة —' },
    { value: 'present', label: 'حاضر' },
    { value: 'absent', label: 'غائب' },
    { value: 'late', label: 'متأخر' },
    { value: 'half_day', label: 'نصف يوم' },
    { value: 'on_leave', label: 'في إجازة' },
]

const statusClass: Record<string, string> = {
    present: 'text-s',
    absent: 'text-d',
    late: 'text-w',
    half_day: 'text-w',
    on_leave: 'text-p',
}

const form = useForm({
    date: props.filters.date,
    shift_id: '',
    rows: props.rows.map((r) => ({ ...r })),
})

function changeDate() {
    router.get('/attendance', { date: form.date }, { preserveState: false })
}

const presentCount = computed(() => form.rows.filter((r) => r.status === 'present').length)
const absentCount = computed(() => form.rows.filter((r) => r.status === 'absent').length)
const lateCount = computed(() => form.rows.filter((r) => r.status === 'late').length)
const leaveCount = computed(() => form.rows.filter((r) => r.status === 'on_leave').length)

function markAll(status: string) {
    form.rows.forEach((r) => { r.status = status })
}

function submit() {
    form.post('/attendance', { preserveState: true })
}
</script>

<template>
    <Head title="الحضور والانصراف" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">الحضور والانصراف</h1>
        <p class="mt-0.5 text-sm text-t3">تسجيل الحضور اليومي لجميع الموظفين</p>
    </div>

    <!-- Stats -->
    <div class="mb-5 grid grid-cols-4 gap-4">
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="h-3 w-3 rounded-full bg-s shrink-0"></div>
            <div>
                <p class="text-xs text-t3">حاضر</p>
                <p class="text-2xl font-bold text-s">{{ presentCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="h-3 w-3 rounded-full bg-d shrink-0"></div>
            <div>
                <p class="text-xs text-t3">غائب</p>
                <p class="text-2xl font-bold text-d">{{ absentCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="h-3 w-3 rounded-full bg-w shrink-0"></div>
            <div>
                <p class="text-xs text-t3">متأخر</p>
                <p class="text-2xl font-bold text-w">{{ lateCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <div class="h-3 w-3 rounded-full bg-p shrink-0"></div>
            <div>
                <p class="text-xs text-t3">في إجازة</p>
                <p class="text-2xl font-bold text-p">{{ leaveCount }}</p>
            </div>
        </div>
    </div>

    <form @submit.prevent="submit">
        <!-- Toolbar -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <input v-model="form.date" type="date" class="input-field w-44" @change="changeDate" />
                <select v-model="form.shift_id" class="input-field w-36">
                    <option value="">الوردية (اختياري)</option>
                    <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-secondary text-xs" @click="markAll('present')">تحضير الكل</button>
                <button type="button" class="btn-secondary text-xs" @click="markAll('absent')">تغيب الكل</button>
                <button type="submit" :disabled="form.processing" class="btn-primary">
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ الحضور' }}
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]">
            <table class="w-full text-sm">
                <thead class="bg-sf2">
                    <tr>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الموظف</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">الحالة</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">وقت الحضور</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">وقت الانصراف</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">إضافي (س)</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-t2">ملاحظات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-br/50">
                    <tr v-for="row in form.rows" :key="row.employee_id"
                        :class="[
                            row.status === 'absent' ? 'bg-dp/8' : row.status === 'late' ? 'bg-wp/8' : '',
                            'transition-colors',
                        ]">
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-t">{{ row.employee_name }}</p>
                            <p class="text-xs text-t3">{{ row.dept }}</p>
                        </td>
                        <td class="px-4 py-2.5">
                            <select v-model="row.status" class="input-field w-32 py-1 text-xs" :class="statusClass[row.status]">
                                <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </td>
                        <td class="px-4 py-2.5">
                            <input v-model="row.check_in" type="time" class="input-field w-28 py-1 text-xs font-mono"
                                :disabled="row.status === 'absent'" />
                        </td>
                        <td class="px-4 py-2.5">
                            <input v-model="row.check_out" type="time" class="input-field w-28 py-1 text-xs font-mono"
                                :disabled="row.status === 'absent'" />
                        </td>
                        <td class="px-4 py-2.5">
                            <input v-model.number="row.overtime_hours" type="number" min="0" max="12" step="0.5"
                                class="input-field w-20 py-1 text-xs text-center font-mono" />
                        </td>
                        <td class="px-4 py-2.5">
                            <input v-model="row.notes" type="text" placeholder="ملاحظة..." class="input-field py-1 text-xs w-full max-w-[160px]" />
                        </td>
                    </tr>
                    <tr v-if="form.rows.length === 0">
                        <td class="px-4 py-12 text-center text-t3" colspan="6">لا يوجد موظفون نشطون</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom save -->
        <div class="mt-4 flex justify-end">
            <button type="submit" :disabled="form.processing" class="btn-primary px-8">
                {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ الحضور ليوم ' + form.date }}
            </button>
        </div>
    </form>
</template>
