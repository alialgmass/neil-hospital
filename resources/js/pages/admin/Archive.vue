<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { FileText, FolderPlus, Grid, List, Paperclip, Trash2, Upload, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Modal from '@/components/shared/Modal.vue';
import SearchBar from '@/components/shared/SearchBar.vue';
import archive from '@/routes/archive';

interface MediaFile {
    id: number;
    name: string;
    url: string;
    mime: string;
    size: string;
}

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
    media_files: MediaFile[];
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
const createFileInput = ref<HTMLInputElement | null>(null);
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
    files: [] as File[],
});

function onCreateFilesChange(event: Event) {
    const input = event.target as HTMLInputElement;
    form.files = Array.from(input.files ?? []);
}

// File management
const activeBookingId = ref<string | null>(null);
const activeBooking = computed(() =>
    props.bookings.data.find(b => b.id === activeBookingId.value) ?? null,
);
const showFilesModal = ref(false);
const uploadForm = useForm({ file: null as File | null });
const fileInput = ref<HTMLInputElement | null>(null);

function openFilesModal(booking: Booking) {
    activeBookingId.value = booking.id;
    showFilesModal.value = true;
}

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
}

function submitUpload() {
    if (!activeBooking.value || !uploadForm.file) {
        return;
    }

    uploadForm.post(archive.upload(activeBookingId.value!).url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            uploadForm.reset();
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}

function deleteMedia(mediaId: number) {
    router.delete(`/archive/media/${mediaId}`, { preserveScroll: true });
}

function isImage(mime: string): boolean {
    return mime.startsWith('image/');
}

function submitArchive() {
    form.post('/archive', {
        forceFormData: true,
        onSuccess: () => {
            showAddModal.value = false;
            form.reset();
            if (createFileInput.value) {
                createFileInput.value.value = '';
            }
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
        <div
            v-for="booking in bookings.data"
            :key="booking.id"
            class="group flex flex-col overflow-hidden rounded-xl border border-hospital-border bg-white shadow-sm transition-shadow hover:shadow-md"
        >
            <!-- Card Icon Area -->
            <a :href="`/booking/${booking.id}/patient-file`" class="flex h-28 items-center justify-center bg-gradient-to-br from-hospital-primary/10 to-hospital-primary/5">
                <FileText class="h-10 w-10 text-hospital-primary/60 transition-transform group-hover:scale-110" />
            </a>
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
                <!-- Files button -->
                <button
                    class="mt-2 flex items-center gap-1.5 rounded-lg border border-hospital-border px-2 py-1 text-xs text-hospital-text-2 transition-colors hover:border-hospital-primary hover:text-hospital-primary"
                    @click="openFilesModal(booking)"
                >
                    <Paperclip class="h-3 w-3" />
                    {{ booking.media_files.length > 0 ? `${booking.media_files.length} ملف` : 'رفع ملفات' }}
                </button>
            </div>
        </div>
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
                    <th class="px-4 py-3 text-right font-semibold text-hospital-text-2">الملفات</th>
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
                    <td class="px-4 py-3">
                        <button
                            class="flex items-center gap-1 text-xs text-hospital-text-2 hover:text-hospital-primary"
                            @click="openFilesModal(booking)"
                        >
                            <Paperclip class="h-3.5 w-3.5" />
                            {{ booking.media_files.length > 0 ? booking.media_files.length : '—' }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Files Modal -->
    <Modal v-model="showFilesModal" title="ملفات المريض" size="lg">
        <div v-if="activeBooking" class="space-y-4">
            <div>
                <p class="font-semibold text-hospital-text">{{ activeBooking.patient_name }}</p>
                <p class="text-xs text-hospital-muted">{{ activeBooking.file_no }} · {{ activeBooking.visit_date }}</p>
            </div>

            <!-- Existing files -->
            <div v-if="activeBooking.media_files.length > 0" class="space-y-2">
                <p class="text-sm font-medium text-hospital-text-2">الملفات المرفقة ({{ activeBooking.media_files.length }})</p>
                <div class="divide-y divide-hospital-border rounded-lg border border-hospital-border">
                    <div
                        v-for="file in activeBooking.media_files"
                        :key="file.id"
                        class="flex items-center gap-3 px-3 py-2"
                    >
                        <!-- Image thumbnail or file icon -->
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded">
                            <img v-if="isImage(file.mime)" :src="file.url" :alt="file.name" class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center bg-hospital-bg">
                                <FileText class="h-5 w-5 text-hospital-muted" />
                            </div>
                        </div>
                        <div class="min-w-0 flex-1">
                            <a :href="file.url" target="_blank" class="block truncate text-sm font-medium text-hospital-primary hover:underline">
                                {{ file.name }}
                            </a>
                            <p class="text-xs text-hospital-muted">{{ file.size }}</p>
                        </div>
                        <button
                            class="shrink-0 rounded p-1 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-500"
                            title="حذف"
                            @click="deleteMedia(file.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="rounded-lg border border-dashed border-hospital-border py-6 text-center">
                <Paperclip class="mx-auto mb-2 h-8 w-8 text-hospital-muted/40" />
                <p class="text-sm text-hospital-muted">لا توجد ملفات مرفقة</p>
            </div>

            <!-- Upload new file -->
            <div class="rounded-lg border border-hospital-border p-4">
                <p class="mb-3 text-sm font-medium text-hospital-text-2">رفع ملف جديد</p>
                <form class="flex items-end gap-3" @submit.prevent="submitUpload">
                    <div class="flex-1">
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx"
                            class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-hospital-primary/10 file:px-3 file:py-1 file:text-xs file:font-medium file:text-hospital-primary"
                            @change="onFileChange"
                        />
                        <p v-if="uploadForm.errors.file" class="form-error mt-1">{{ uploadForm.errors.file }}</p>
                    </div>
                    <button
                        type="submit"
                        class="flex shrink-0 items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="!uploadForm.file || uploadForm.processing"
                    >
                        <Upload class="h-4 w-4" />
                        {{ uploadForm.processing ? 'جارٍ الرفع...' : 'رفع' }}
                    </button>
                </form>
                <p class="mt-1.5 text-xs text-hospital-muted">الأنواع المدعومة: صور، PDF، Word، Excel · الحد الأقصى 20 ميجا</p>
            </div>

            <div class="flex justify-end border-t border-hospital-border pt-3">
                <button class="btn-secondary" @click="showFilesModal = false">
                    <X class="h-4 w-4" />
                    إغلاق
                </button>
            </div>
        </div>
    </Modal>

    <!-- Add Archive Modal -->
    <Modal v-model="showAddModal" title="إضافة سجل إلى الأرشيف" size="lg">
        <form class="space-y-4" @submit.prevent="submitArchive">
            <!-- Row 1: Name + Phone -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">اسم المريض <span class="text-hospital-danger">*</span></label>
                    <input v-model="form.patient_name" type="text" class="input-field" placeholder="الاسم الكامل" />
                    <p v-if="form.errors.patient_name" class="form-error">{{ form.errors.patient_name }}</p>
                </div>
                <div>
                    <label class="form-label">رقم الهاتف</label>
                    <input v-model="form.patient_phone" type="text" class="input-field" placeholder="اختياري" />
                </div>
            </div>

            <!-- Row 2: Age + Gender -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">العمر</label>
                    <input v-model.number="form.patient_age" type="number" class="input-field" min="0" max="150" placeholder="بالسنوات" />
                </div>
                <div>
                    <label class="form-label">الجنس</label>
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
                    <label class="form-label">القسم <span class="text-hospital-danger">*</span></label>
                    <select v-model="form.dept" class="input-field">
                        <option value="">— اختر القسم —</option>
                        <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <p v-if="form.errors.dept" class="form-error">{{ form.errors.dept }}</p>
                </div>
                <div>
                    <label class="form-label">الطبيب</label>
                    <select v-model="form.doctor_id" class="input-field">
                        <option value="">— اختر —</option>
                        <option v-for="doc in doctors" :key="doc.id" :value="doc.id">{{ doc.name }}</option>
                    </select>
                </div>
            </div>

            <!-- Row 4: Date + Service -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">تاريخ الزيارة <span class="text-hospital-danger">*</span></label>
                    <input v-model="form.visit_date" type="date" class="input-field" />
                    <p v-if="form.errors.visit_date" class="form-error">{{ form.errors.visit_date }}</p>
                </div>
                <div>
                    <label class="form-label">الخدمة / الإجراء</label>
                    <input v-model="form.service_name" type="text" class="input-field" placeholder="اختياري" />
                </div>
            </div>

            <!-- Row 5: Price + Paid + Method -->
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="form-label">المبلغ (ج.م)</label>
                    <input v-model.number="form.price" type="number" class="input-field" min="0" step="0.01" placeholder="0.00" />
                </div>
                <div>
                    <label class="form-label">المدفوع (ج.م)</label>
                    <input v-model.number="form.paid_amount" type="number" class="input-field" min="0" step="0.01" placeholder="0.00" />
                </div>
                <div>
                    <label class="form-label">طريقة الدفع</label>
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
                <label class="form-label">ملاحظات</label>
                <textarea v-model="form.visit_note" class="input-field" rows="2" placeholder="اختياري" />
            </div>

            <!-- Attachments -->
            <div>
                <label class="form-label">المرفقات (اختياري)</label>
                <input
                    ref="createFileInput"
                    type="file"
                    multiple
                    accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx"
                    class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-hospital-primary/10 file:px-3 file:py-1 file:text-xs file:font-medium file:text-hospital-primary"
                    @change="onCreateFilesChange"
                />
                <p class="mt-1 text-xs text-hospital-muted">صور، PDF، Word، Excel · الحد الأقصى 20 ميجا لكل ملف</p>
                <p v-if="form.errors.files" class="form-error">{{ form.errors.files }}</p>
            </div>

            <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                <button type="button" class="btn-secondary" @click="showAddModal = false">إلغاء</button>
                <button type="submit" class="btn-primary" :disabled="form.processing">
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
