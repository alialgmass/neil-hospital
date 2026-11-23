<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref } from 'vue';

interface CaseRow {
    visit_date: string;
    file_no: string;
    patient_name: string;
    service_name: string | null;
    net_due: number;
    paid: number;
    remaining: number;
}

interface DoctorGroup {
    doctor_id: string;
    doctor_name: string;
    cases: CaseRow[];
    cases_count: number;
    net_due: number;
    paid: number;
    remaining: number;
}

const props = defineProps<{
    data: {
        doctors: DoctorGroup[];
        totals: {
            cases_count: number;
            net_due: number;
            paid: number;
            remaining: number;
        };
        from: string;
        to: string;
    };
    filters: { from: string; to: string; doctorId: string | null };
    doctors: { id: string; name: string }[];
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const doctorId = ref(props.filters.doctorId ?? '');

function query() {
    return {
        from: from.value,
        to: to.value,
        doctor_id: doctorId.value || undefined,
    };
}

function search() {
    router.get('/reports/doctor-cases', query(), { preserveState: true });
}

function exportExcel() {
    window.location.href = `/reports/doctor-cases/export?${new URLSearchParams(query() as Record<string, string>)}`;
}

function fmt(n: number) {
    return Number(n).toLocaleString('en-US', { minimumFractionDigits: 2 });
}
</script>

<template>
    <Head title="حالات الأطباء" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">حالات الأطباء</h1>
        <p class="mt-0.5 text-sm text-t3">
            حالات كل طبيب بالمستحق والمدفوع والمتبقي
        </p>
    </div>

    <div class="mb-5 flex flex-wrap items-end gap-3 print:hidden">
        <div class="flex flex-col gap-1">
            <label class="form-label">الطبيب</label>
            <select v-model="doctorId" class="input-field min-w-48">
                <option value="">كل الأطباء</option>
                <option v-for="d in doctors" :key="d.id" :value="d.id">
                    {{ d.name }}
                </option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">من</label>
            <input v-model="from" class="input-field" type="date" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى</label>
            <input v-model="to" class="input-field" type="date" />
        </div>
        <button class="btn-primary self-end" @click="search">بحث</button>
        <button
            class="btn-secondary flex items-center gap-2 self-end"
            @click="exportExcel"
        >
            <Download class="h-4 w-4" />
            Excel
        </button>
        <button class="btn-secondary self-end" @click="() => window.print()">
            طباعة
        </button>
    </div>

    <div class="mb-5 grid grid-cols-2 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="text-xs text-t3">عدد الحالات</p>
            <p class="text-xl font-bold text-t">
                {{ data.totals.cases_count }}
            </p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="text-xs text-t3">إجمالي المستحق</p>
            <p class="text-xl font-bold text-t">
                {{ fmt(data.totals.net_due) }}
            </p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="text-xs text-t3">المدفوع</p>
            <p class="text-xl font-bold text-s">{{ fmt(data.totals.paid) }}</p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="text-xs text-t3">المتبقي</p>
            <p class="text-xl font-bold text-hospital-danger">
                {{ fmt(data.totals.remaining) }}
            </p>
        </div>
    </div>

    <p
        v-if="data.doctors.length === 0"
        class="rounded-xl border border-br bg-sf px-4 py-10 text-center text-t3"
    >
        لا توجد حالات في هذه الفترة
    </p>

    <section
        v-for="doctor in data.doctors"
        :key="doctor.doctor_id"
        class="mb-5 overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]"
    >
        <header class="flex items-center justify-between bg-sf2 px-4 py-3">
            <h2 class="font-bold text-t">{{ doctor.doctor_name }}</h2>
            <span class="text-xs text-t3">{{ doctor.cases_count }} حالة</span>
        </header>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-br">
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        التاريخ
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        رقم الملف
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        اسم الحالة
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        الخدمة
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        المستحق
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        المدفوع
                    </th>
                    <th
                        class="px-4 py-2 text-right text-xs font-semibold text-t2"
                    >
                        المتبقي
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr
                    v-for="c in doctor.cases"
                    :key="`${c.file_no}-${c.visit_date}`"
                    class="hover:bg-sf2"
                >
                    <td class="px-4 py-2 text-t2">{{ c.visit_date }}</td>
                    <td class="px-4 py-2 font-mono text-t2">{{ c.file_no }}</td>
                    <td class="px-4 py-2 font-medium text-t">
                        {{ c.patient_name }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ c.service_name || '—' }}
                    </td>
                    <td class="px-4 py-2 font-mono">{{ fmt(c.net_due) }}</td>
                    <td class="px-4 py-2 font-mono text-s">
                        {{ fmt(c.paid) }}
                    </td>
                    <td
                        class="px-4 py-2 font-mono"
                        :class="
                            c.remaining > 0 ? 'text-hospital-danger' : 'text-t3'
                        "
                    >
                        {{ fmt(c.remaining) }}
                    </td>
                </tr>
            </tbody>
            <tfoot class="border-t-2 border-br bg-sf2">
                <tr>
                    <td class="px-4 py-2 font-bold text-t" colspan="4">
                        إجمالي الطبيب
                    </td>
                    <td class="px-4 py-2 font-mono font-bold">
                        {{ fmt(doctor.net_due) }}
                    </td>
                    <td class="px-4 py-2 font-mono font-bold text-s">
                        {{ fmt(doctor.paid) }}
                    </td>
                    <td
                        class="px-4 py-2 font-mono font-bold text-hospital-danger"
                    >
                        {{ fmt(doctor.remaining) }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </section>
</template>
