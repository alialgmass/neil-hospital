<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref } from 'vue';

interface Row {
    visit_date: string;
    visit_time: string | null;
    file_no: string;
    patient_name: string;
    patient_phone: string | null;
    dept: string;
    service_name: string | null;
    doctor_name: string | null;
    status: string;
    pay_status: string;
}

const props = defineProps<{
    data: {
        rows: Row[];
        stats: {
            total: number;
            by_status: Record<string, number>;
            by_dept: Record<string, number>;
            by_pay_status: Record<string, number>;
            by_day: Record<string, number>;
        };
        from: string;
        to: string;
    };
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const statusLabels: Record<string, string> = {
    waiting: 'في الانتظار',
    confirmed: 'مؤكد',
    in_progress: 'جارٍ',
    completed: 'مكتمل',
    completed_electronic: 'مكتمل إلكترونياً',
    cancelled: 'ملغي',
};

const payLabels: Record<string, string> = {
    paid: 'مدفوع',
    partial: 'جزئي',
    unpaid: 'غير مدفوع',
};

const deptLabels: Record<string, string> = {
    clinic: 'العيادات',
    labs: 'المعامل',
    surgery: 'الجراحة',
    lasik: 'الليزك',
    laser: 'الليزر',
    pentacam: 'البنتاكام',
};

function search() {
    router.get(
        '/reports/reception',
        { from: from.value, to: to.value },
        { preserveState: true },
    );
}

function exportExcel() {
    window.location.href = `/reports/reception/export?from=${from.value}&to=${to.value}`;
}

function today() {
    from.value = to.value = new Date().toLocaleDateString('en-CA');
    search();
}
</script>

<template>
    <Head title="تقرير الاستقبال" />

    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">تقرير الاستقبال</h1>
        <p class="mt-0.5 text-sm text-t3">
            مواعيد الحجز وإحصائيات الحجوزات لليوم أو لفترة
        </p>
    </div>

    <div class="mb-5 flex flex-wrap items-end gap-3 print:hidden">
        <div class="flex flex-col gap-1">
            <label class="form-label">من</label>
            <input v-model="from" class="input-field" type="date" />
        </div>
        <div class="flex flex-col gap-1">
            <label class="form-label">إلى</label>
            <input v-model="to" class="input-field" type="date" />
        </div>
        <button class="btn-primary self-end" @click="search">بحث</button>
        <button class="btn-secondary self-end" @click="today">اليوم</button>
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

    <div class="mb-5 grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="text-xs text-t3">إجمالي الحجوزات</p>
            <p class="text-2xl font-bold text-t">{{ data.stats.total }}</p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="mb-1 text-xs text-t3">حسب الحالة</p>
            <p
                v-for="(n, k) in data.stats.by_status"
                :key="k"
                class="flex justify-between text-sm"
            >
                <span class="text-t2">{{ statusLabels[k] ?? k }}</span
                ><strong>{{ n }}</strong>
            </p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="mb-1 text-xs text-t3">حسب القسم</p>
            <p
                v-for="(n, k) in data.stats.by_dept"
                :key="k"
                class="flex justify-between text-sm"
            >
                <span class="text-t2">{{ deptLabels[k] ?? k }}</span
                ><strong>{{ n }}</strong>
            </p>
        </div>
        <div class="rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]">
            <p class="mb-1 text-xs text-t3">حسب السداد</p>
            <p
                v-for="(n, k) in data.stats.by_pay_status"
                :key="k"
                class="flex justify-between text-sm"
            >
                <span class="text-t2">{{ payLabels[k] ?? k }}</span
                ><strong>{{ n }}</strong>
            </p>
        </div>
    </div>

    <div
        v-if="Object.keys(data.stats.by_day).length > 1"
        class="mb-5 rounded-xl border border-br bg-sf p-4 shadow-[var(--sh)]"
    >
        <p class="mb-2 text-xs text-t3">عدد الحجوزات يومياً</p>
        <div class="flex flex-wrap gap-2">
            <span
                v-for="(n, day) in data.stats.by_day"
                :key="day"
                class="rounded-lg bg-sf2 px-3 py-1 text-xs text-t2"
            >
                {{ day }}: <strong>{{ n }}</strong>
            </span>
        </div>
    </div>

    <div
        class="overflow-hidden rounded-[var(--rl)] border border-br bg-sf shadow-[var(--sh)]"
    >
        <table class="w-full text-sm">
            <thead class="bg-sf2">
                <tr>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        التاريخ
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الوقت
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        المريض
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الهاتف
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        القسم
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الخدمة
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الطبيب
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        الحالة
                    </th>
                    <th
                        class="px-4 py-3 text-right text-xs font-semibold text-t2"
                    >
                        السداد
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-br/50">
                <tr
                    v-for="r in data.rows"
                    :key="`${r.file_no}-${r.visit_date}-${r.visit_time}`"
                    class="hover:bg-sf2"
                >
                    <td class="px-4 py-2 text-t2">{{ r.visit_date }}</td>
                    <td class="px-4 py-2 font-mono text-t2">
                        {{ r.visit_time?.slice(0, 5) ?? '—' }}
                    </td>
                    <td class="px-4 py-2 font-medium text-t">
                        {{ r.patient_name }}
                    </td>
                    <td class="px-4 py-2 font-mono text-t2">
                        {{ r.patient_phone || '—' }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ deptLabels[r.dept] ?? r.dept }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ r.service_name || '—' }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ r.doctor_name || '—' }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ statusLabels[r.status] ?? r.status }}
                    </td>
                    <td class="px-4 py-2 text-t2">
                        {{ payLabels[r.pay_status] ?? r.pay_status }}
                    </td>
                </tr>
                <tr v-if="data.rows.length === 0">
                    <td class="px-4 py-10 text-center text-t3" colspan="9">
                        لا توجد حجوزات في هذه الفترة
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
