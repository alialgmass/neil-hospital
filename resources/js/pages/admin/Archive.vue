<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileText, FolderPlus, Grid, List } from 'lucide-vue-next';
import { ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';

interface Booking {
    id: string;
    file_no: string;
    patient_name: string;
    patient_phone?: string;
    dept: string;
    visit_date: string;
    status: string;
    pay_status: string;
    price: number;
    doctor_name?: string;
}

interface Doctor {
    id: string;
    name: string;
}

const props = defineProps<{
    bookings: { data: Booking[]; current_page: number; last_page: number; total: number };
    filters: { search?: string; dept?: string; from?: string; to?: string };
    doctors: Doctor[];
}>();

const deptLabels: Record<string, string> = {
    clinic:  'العيادة',
    labs:    'الفحوصات',
    surgery: 'العمليات',
    lasik:   'الليزك',
    laser:   'الليزر',
};

const deptColors: Record<string, string> = {
    clinic:  'bg-blue-100 text-blue-700',
    labs:    'bg-purple-100 text-purple-700',
    surgery: 'bg-red-100 text-red-700',
    lasik:   'bg-teal-100 text-teal-700',
    laser:   'bg-orange-100 text-orange-700',
};

const payStatusColors: Record<string, string> = {
    paid:    'bg-green-100 text-green-700',
    partial: 'bg-yellow-100 text-yellow-700',
    unpaid:  'bg-red-100 text-red-700',
};

const payStatusLabels: Record<string, string> = {
    paid:    'مسدد',
    partial: 'جزئي',
    unpaid:  'غير مسدد',
};

const viewMode = ref<'grid' | 'table'>('grid');
const search    = ref(props.filters.search ?? '');
const deptFilter = ref(props.filters.dept  ?? '');
const fromFilter = ref(props.filters.from  ?? '');
const toFilter   = ref(props.filters.to    ?? '');

const showAddModal = ref(false);
const form = useForm({
    patient_name: '',
    patient_phone: '',
    patient_age: '' as number | '',
    gender: '',
    dept: '',
    doctor_id: '',
    visit_date: '',
    service_name: '',
    price: '' as number | '',
    paid_amount: '' as number | '',
    pay_method: 'cash',
    visit_note: '',
});

function submitArchive() {
    form.post('/archive', {
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
        },
    });
}

function applyFilters() {
    router.get('/archive', {
        search: search.value   || undefined,
        dept:   deptFilter.value || undefined,
        from:   fromFilter.value || undefined,
        to:     toFilter.value   || undefined,
    }, { preserveState: true });
}

function goToPage(page: number) {
    router.get('/archive', {
        search: search.value   || undefined,
        dept:   deptFilter.value || undefined,
        from:   fromFilter.value || undefined,
        to:     toFilter.value   || undefined,
        page,
    }, { preserveState: true });
}
</script>

<template>
    <Head title="الأرشيف الطبي" />

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-hospital-text">الأرشيف الطبي</h2>
            <p class="text-xs text-hospital-muted">رفع وحفظ الصور والملفات الطبية لكل مريض</p>
        </div>
        <div class="flex items-center gap-3">
            <span class="rounded-full bg-hospital-primary/10 px-3 py-1 text-sm font-medium text-hospital-primary">
                {{ bookings.total }} سجل
            </span>
            <button
                class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90"
                @click="showAddModal = true"
            >
                <FolderPlus class="h-4 w-4" />
                إضافة سجل
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-hospital-border bg-hospital-bg p-4">
        <div class="flex-1 min-w-48">
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">البحث</label>
            <SearchBar v-model="search" placeholder="اسم المريض أو رقم الملف..." @update:model-value="applyFilters" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">القسم</label>
            <select
                v-model="deptFilter"
                class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                @change="applyFilters"
            >
                <option value="">كل الأقسام</option>
                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">من تاريخ</label>
            <input v-model="fromFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-hospital-text-2">إلى تاريخ</label>
            <input v-model="toFilter" type="date" class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" @change="applyFilters" />
        </div>
        <!-- View Toggle -->
        <div class="mr-auto flex items-center gap-1 rounded-lg border border-hospital-border bg-white p-1">
            <button
                class="rounded p-1.5 transition-colors"
                :class="viewMode === 'grid' ? 'bg-hospital-primary text-white' : 'text-gray-400 hover:text-gray-600'"
                @click="viewMode = 'grid'"
            >
                <Grid class="h-4 w-4" />
            </button>
            <button
                class="rounded p-1.5 transition-colors"
                :class="viewMode === 'table' ? 'bg-hospital-primary text-white' : 'text-gray-400 hover:text-gray-600'"
                @click="viewMode = 'table'"
            >
                <List class="h-4 w-4" />
            </button>
        </div>
    </div>

    <!-- Empty state -->
    <div v-if="bookings.data.length === 0" class="py-16 text-center">
        <FileText class="mx-auto mb-3 h-12 w-12 text-hospital-muted/50" />
        <p class="text-hospital-muted">لا توجد سجلات في الأرشيف</p>
    </div>

    <!-- Grid View -->
    <div v-else-if="viewMode === 'grid'" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <a
            v-for="booking in bookings.data"
            :key="booking.id"
            :href="`/booking/${booking.id}/patient-file`"
            class="group flex flex-col overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm transition-shadow hover:shadow-md"
        >
            <!-- Card Icon Area -->
            <div class="flex h-28 items-center justify-center bg-gradient-to-br from-hospital-primary/10 to-hospital-primary/5">
                <FileText class="h-10 w-10 text-hospital-primary/60 transition-transform group-hover:scale-110" />
            </div>
            <!-- Card Info -->
            <div class="flex flex-1 flex-col p-3">
                <p class="truncate font-semibold text-hospital-text text-sm">{{ booking.patient_name }}</p>
                <p class="mt-0.5 text-xs text-hospital-muted">{{ booking.file_no }}</p>
                <div class="mt-2 flex flex-wrap gap-1">
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="deptColors[booking.dept] ?? 'bg-gray-100 text-gray-600'"
                    >
                        {{ deptLabels[booking.dept] ?? booking.dept }}
                    </span>
                    <span
                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="payStatusColors[booking.pay_status] ?? 'bg-gray-100 text-gray-600'"
                    >
                        {{ payStatusLabels[booking.pay_status] ?? booking.pay_status }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-hospital-muted">{{ booking.visit_date }}</p>
            </div>
        </a>
    </div>

    <!-- Table View -->
    <div v-else class="overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-hospital-bg">
                <tr>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">رقم الملف</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">المريض</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">القسم</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">الطبيب</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">تاريخ الزيارة</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">المبلغ</th>
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">السداد</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hospital-border">
                <tr v-for="booking in bookings.data" :key="booking.id" class="hover:bg-hospital-bg/50">
                    <td class="px-4 py-3">
                        <a :href="`/booking/${booking.id}/patient-file`" class="font-medium text-hospital-primary hover:underline">
                            {{ booking.file_no }}
                        </a>
                    </td>
                    <td class="px-4 py-3 font-medium text-hospital-text">{{ booking.patient_name }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="deptColors[booking.dept] ?? 'bg-gray-100 text-gray-600'">
                            {{ deptLabels[booking.dept] ?? booking.dept }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-hospital-text-2">{{ booking.doctor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-hospital-text-2">{{ booking.visit_date }}</td>
                    <td class="px-4 py-3 font-mono text-hospital-text">{{ Number(booking.price).toLocaleString('ar-EG') }} ج</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="payStatusColors[booking.pay_status] ?? 'bg-gray-100 text-gray-600'">
                            {{ payStatusLabels[booking.pay_status] ?? booking.pay_status }}
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Add Archive Modal -->
    <Modal v-model="showAddModal" title="إضافة سجل إلى الأرشيف" size="lg">
        <form class="space-y-4" @submit.prevent="submitArchive">
            <!-- Row 1: Name + Phone -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">اسم المريض *</label>
                    <input v-model="form.patient_name" type="text" class="input-field" placeholder="الاسم الكامل" />
                    <p v-if="form.errors.patient_name" class="mt-1 text-xs text-red-600">{{ form.errors.patient_name }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">رقم الهاتف</label>
                    <input v-model="form.patient_phone" type="text" class="input-field" placeholder="اختياري" />
                </div>
            </div>

            <!-- Row 2: Age + Gender -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">العمر</label>
                    <input v-model.number="form.patient_age" type="number" class="input-field" min="0" max="150" placeholder="سنة" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">الجنس</label>
                    <select v-model="form.gender" class="input-field">
                        <option value="">— اختر —</option>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
            </div>

            <!-- Row 3: Dept + Doctor -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">القسم *</label>
                    <select v-model="form.dept" class="input-field">
                        <option value="">— اختر القسم —</option>
                        <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.dept" class="mt-1 text-xs text-red-600">{{ form.errors.dept }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">الطبيب</label>
                    <select v-model="form.doctor_id" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="doc in doctors" :key="doc.id" :value="doc.id">{{ doc.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Row 4: Date + Service -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">تاريخ الزيارة *</label>
                    <input v-model="form.visit_date" type="date" class="input-field" />
                    <p v-if="form.errors.visit_date" class="mt-1 text-xs text-red-600">{{ form.errors.visit_date }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">الخدمة / الإجراء</label>
                    <input v-model="form.service_name" type="text" class="input-field" placeholder="اختياري" />
                </div>
            </div>

            <!-- Row 5: Price + Paid + Method -->
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المبلغ (ج.م)</label>
                    <input v-model.number="form.price" type="number" class="input-field" min="0" step="0.01" placeholder="0" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">المدفوع</label>
                    <input v-model.number="form.paid_amount" type="number" class="input-field" min="0" step="0.01" placeholder="0" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">طريقة الدفع</label>
                    <select v-model="form.pay_method" class="input-field">
                        <option value="cash">نقدي</option>
                        <option value="card">بطاقة</option>
                        <option value="transfer">تحويل</option>
                        <option value="insurance">تأمين</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">ملاحظات</label>
                <textarea v-model="form.visit_note" class="input-field" rows="2" placeholder="اختياري" />
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-3">
                <button type="button" class="btn-secondary" @click="showAddModal = false">إلغاء</button>
                <button
                    type="submit"
                    class="rounded-lg bg-hospital-primary px-5 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ في الأرشيف' }}
                </button>
            </div>
        </form>
    </Modal>

    <!-- Pagination -->
    <div v-if="bookings.last_page > 1" class="mt-5 flex items-center justify-center gap-2">
        <button
            v-for="page in bookings.last_page"
            :key="page"
            class="min-w-9 rounded-lg border px-3 py-1.5 text-sm transition-colors"
            :class="page === bookings.current_page
                ? 'border-hospital-primary bg-hospital-primary text-white'
                : 'border-hospital-border bg-white text-hospital-text hover:bg-hospital-bg'"
            @click="goToPage(page)"
        >
            {{ page }}
        </button>
    </div>
</template>
