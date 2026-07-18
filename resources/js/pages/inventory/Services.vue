<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Edit2, FileSpreadsheet, Package, Trash2, ToggleLeft, ToggleRight, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/components/layout/AppLayout.vue';
import Badge from '@/components/shared/Badge.vue';
import Modal from '@/components/shared/Modal.vue';

defineOptions({ layout: AppLayout });

interface Service {
    id: string;
    name: string;
    dept: string;
    price: number;
    ins_price: number;
    center_type: 'pct' | 'fixed';
    center_val: number;
    center_share: number;
    dr_share: number;
    duration_mins: number;
    status: 'active' | 'inactive';
    revenue_account_id: string | null;
}

interface RevenueAccount {
    id: string;
    code: string;
    name: string;
}

const props = defineProps<{
    services: { data: Service[]; links: unknown[]; total: number; current_page: number; last_page: number };
    filters: { search?: string; dept?: string; status?: string };
    revenueAccounts: RevenueAccount[];
}>();

// ── Filters ──────────────────────────────────────────────────────────────────
const filters = ref({ ...props.filters });
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(filters, () => {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        router.get('/services', filters.value, { preserveState: true, replace: true });
    }, 300);
}, { deep: true });

// ── Dept helpers ──────────────────────────────────────────────────────────────
const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const deptBadgeVariant: Record<string, 'info' | 'success' | 'warning' | 'danger' | 'active'> = {
    clinic: 'info',
    labs: 'active',
    surgery: 'danger',
    lasik: 'warning',
    laser: 'success',
};

// ── Create / Edit form ────────────────────────────────────────────────────────
const showModal = ref(false);
const editingService = ref<Service | null>(null);

const form = useForm({
    name: '',
    dept: 'clinic',
    price: 0 as number,
    ins_price: 0 as number,
    center_type: 'pct' as 'pct' | 'fixed',
    center_val: 40 as number,
    duration_mins: 30 as number,
    status: 'active' as 'active' | 'inactive',
    revenue_account_id: null as string | null,
});

function openCreate() {
    editingService.value = null;
    form.reset();
    form.dept = 'clinic';
    form.center_type = 'pct';
    form.center_val = 40;
    form.duration_mins = 30;
    form.status = 'active';
    form.revenue_account_id = null;
    showModal.value = true;
}

function openEdit(svc: Service) {
    editingService.value = svc;
    form.name = svc.name;
    form.dept = svc.dept;
    form.price = Number(svc.price);
    form.ins_price = Number(svc.ins_price);
    form.center_type = svc.center_type;
    form.center_val = Number(svc.center_val);
    form.duration_mins = svc.duration_mins ?? 30;
    form.status = svc.status;
    form.revenue_account_id = svc.revenue_account_id;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingService.value) {
        form.put(`/services/${editingService.value.id}`, {
            onSuccess: () => { closeModal(); toast.success('تم تحديث الخدمة بنجاح'); },
        });
    } else {
        form.post('/services', {
            onSuccess: () => { closeModal(); toast.success('تم إضافة الخدمة بنجاح'); },
        });
    }
}

const centerPreview = computed(() => {
    if (!form.price) {
        return null;
    }

    const center = form.center_type === 'pct'
        ? (form.price * form.center_val) / 100
        : Number(form.center_val);
    const dr = Math.max(0, form.price - center);

    return { center: center.toFixed(2), dr: dr.toFixed(2) };
});

// ── Toggle status ─────────────────────────────────────────────────────────────
function toggleStatus(svc: Service) {
    const newStatus = svc.status === 'active' ? 'inactive' : 'active';
    router.patch(`/services/${svc.id}/status`, { status: newStatus }, {
        preserveScroll: true,
        onSuccess: () => toast.success(newStatus === 'active' ? 'تم تفعيل الخدمة' : 'تم إيقاف الخدمة'),
    });
}

// ── Delete ────────────────────────────────────────────────────────────────────
const showDeleteModal = ref(false);
const deletingService = ref<Service | null>(null);
const deleteForm = useForm({});

function confirmDelete(svc: Service) {
    deletingService.value = svc;
    showDeleteModal.value = true;
}

function deleteService() {
    if (!deletingService.value) {
        return;
    }

    deleteForm.delete(`/services/${deletingService.value.id}`, {
        onSuccess: () => {
            showDeleteModal.value = false;
            deletingService.value = null;
            toast.success('تم حذف الخدمة');
        },
    });
}

// ── Import ────────────────────────────────────────────────────────────────────
const showImportModal = ref(false);
const importForm = useForm({ file: null as File | null });
const importFileName = ref('');

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    importForm.file = file;
    importFileName.value = file?.name ?? '';
}

const page = usePage<{ flash?: { importResult?: { created: number; updated: number; skipped: number } } }>();

function submitImport() {
    if (!importForm.file) {
        toast.error('يرجى اختيار ملف أولاً');

        return;
    }

    importForm.post('/services/import', {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
            importFileName.value = '';
            const result = page.props.flash?.importResult;

            if (result) {
                toast.success(`تم الاستيراد: ${result.created} جديدة، ${result.updated} محدّثة، ${result.skipped} متجاهلة`);
            } else {
                toast.success('تم الاستيراد بنجاح');
            }
        },
        onError: () => toast.error('فشل الاستيراد، تحقق من تنسيق الملف'),
    });
}

function fmt(n: number) {
    return Number(n).toLocaleString('ar-EG', { minimumFractionDigits: 2 });
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-hospital-text">إدارة الخدمات والأسعار</h1>
                <p class="mt-0.5 text-sm text-hospital-text-3">{{ services.total ?? 0 }} خدمة مسجّلة</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    class="flex items-center gap-2 rounded-lg border border-hospital-border bg-hospital-surface px-4 py-2 text-sm font-medium text-hospital-text-2 transition-colors hover:bg-hospital-bg hover:text-hospital-text"
                    @click="showImportModal = true"
                >
                    <FileSpreadsheet class="h-4 w-4 text-hospital-success" />
                    استيراد Excel
                </button>
                <button
                    class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light"
                    @click="openCreate"
                >
                    <span class="text-base leading-none">+</span> إضافة خدمة
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="relative">
                <svg class="absolute end-3 top-1/2 h-4 w-4 -translate-y-1/2 text-hospital-text-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="filters.search"
                    class="w-60 rounded-lg border border-hospital-border bg-hospital-surface pe-9 py-2 ps-3 text-sm text-hospital-text placeholder-hospital-text-3 focus:border-hospital-primary focus:outline-none"
                    type="text"
                    placeholder="بحث باسم الخدمة..."
                />
            </div>
            <select
                v-model="filters.dept"
                class="rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
            >
                <option value="">كل الأقسام</option>
                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <select
                v-model="filters.status"
                class="rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none"
            >
                <option value="">كل الحالات</option>
                <option value="active">نشط</option>
                <option value="inactive">غير نشط</option>
            </select>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-hospital-border bg-hospital-surface shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm" dir="rtl">
                    <thead class="border-b border-hospital-border bg-hospital-bg">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">الخدمة</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">القسم</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">السعر</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">سعر التأمين</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">حصة المركز</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">حصة الطبيب</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-2">الحالة</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-2">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Empty state -->
                        <tr v-if="services.data.length === 0">
                            <td colspan="8" class="py-16 text-center">
                                <div class="flex flex-col items-center gap-3 text-hospital-text-3">
                                    <Package class="h-14 w-14 opacity-30" />
                                    <p class="text-base font-medium">لا توجد خدمات</p>
                                    <p class="text-sm">قم بإضافة خدمة جديدة أو عدّل عوامل البحث</p>
                                </div>
                            </td>
                        </tr>
                        <!-- Rows -->
                        <tr
                            v-for="svc in services.data"
                            :key="svc.id"
                            class="border-t border-hospital-border/50 transition-colors hover:bg-hospital-primary-pale/30"
                        >
                            <td class="px-4 py-3 font-medium text-hospital-text">{{ svc.name }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="deptBadgeVariant[svc.dept] ?? 'info'" :label="deptLabels[svc.dept] ?? svc.dept" />
                            </td>
                            <td class="px-4 py-3 font-mono text-hospital-text">{{ fmt(Number(svc.price)) }}</td>
                            <td class="px-4 py-3 font-mono text-hospital-primary">{{ fmt(Number(svc.ins_price)) }}</td>
                            <td class="px-4 py-3 font-mono text-hospital-warning">
                                {{ svc.center_type === 'pct' ? `${svc.center_val}%` : `${fmt(Number(svc.center_val))} ج` }}
                            </td>
                            <td class="px-4 py-3 font-mono font-semibold text-hospital-success">{{ fmt(Number(svc.dr_share)) }} ج</td>
                            <td class="px-4 py-3">
                                <Badge :variant="svc.status === 'active' ? 'active' : 'inactive'" />
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        class="rounded p-1.5 transition-colors"
                                        :class="svc.status === 'active' ? 'text-hospital-success hover:bg-hospital-success-pale' : 'text-hospital-text-3 hover:bg-hospital-bg'"
                                        :title="svc.status === 'active' ? 'إيقاف الخدمة' : 'تفعيل الخدمة'"
                                        @click="toggleStatus(svc)"
                                    >
                                        <ToggleRight v-if="svc.status === 'active'" class="h-5 w-5" />
                                        <ToggleLeft v-else class="h-5 w-5" />
                                    </button>
                                    <button
                                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                                        title="تعديل"
                                        @click="openEdit(svc)"
                                    >
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button
                                        class="rounded p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-danger-pale hover:text-hospital-danger"
                                        title="حذف"
                                        @click="confirmDelete(svc)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Create / Edit Modal ─────────────────────────────────────────── -->
        <Modal v-model="showModal" :title="editingService ? 'تعديل خدمة' : 'إضافة خدمة جديدة'" size="lg" @close="closeModal">
            <form class="space-y-5" @submit.prevent="submit">
                <!-- Basic info -->
                <div class="rounded-lg border border-hospital-border p-4">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-hospital-text-2">المعلومات الأساسية</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-hospital-text">اسم الخدمة <span class="text-hospital-danger">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="مثال: فحص نظر شامل"
                                class="w-full rounded-lg border px-3 py-2 text-sm text-hospital-text placeholder-hospital-text-3 focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                                :class="form.errors.name ? 'border-hospital-danger focus:border-hospital-danger' : 'border-hospital-border focus:border-hospital-primary'"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">القسم <span class="text-hospital-danger">*</span></label>
                            <select
                                v-model="form.dept"
                                class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                                :disabled="!!editingService"
                            >
                                <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">مدة الخدمة (دقيقة)</label>
                            <input
                                v-model.number="form.duration_mins"
                                type="number"
                                min="1"
                                placeholder="30"
                                class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium text-hospital-text">الحالة</label>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border py-2 text-sm font-medium transition-all"
                                    :class="form.status === 'active'
                                        ? 'border-hospital-success bg-hospital-success-pale text-hospital-success'
                                        : 'border-hospital-border text-hospital-text-2 hover:border-hospital-border'"
                                    @click="form.status = 'active'"
                                >
                                    نشط
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border py-2 text-sm font-medium transition-all"
                                    :class="form.status === 'inactive'
                                        ? 'border-hospital-danger bg-hospital-danger-pale text-hospital-danger'
                                        : 'border-hospital-border text-hospital-text-2 hover:border-hospital-border'"
                                    @click="form.status = 'inactive'"
                                >
                                    متوقف
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pricing -->
                <div class="rounded-lg border border-hospital-border p-4">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-hospital-text-2">الأسعار</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">السعر الأساسي (ج.م)</label>
                            <input
                                v-model.number="form.price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                            />
                            <p v-if="form.errors.price" class="mt-1 text-xs text-hospital-danger">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">سعر التأمين (ج.م)</label>
                            <input
                                v-model.number="form.ins_price"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                            />
                        </div>
                    </div>
                </div>

                <!-- Share distribution -->
                <div class="rounded-lg border border-hospital-border p-4">
                    <h3 class="mb-3 text-xs font-semibold uppercase tracking-wide text-hospital-text-2">توزيع الإيرادات</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">نوع حصة المركز</label>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border py-2 text-sm font-medium transition-all"
                                    :class="form.center_type === 'pct'
                                        ? 'border-hospital-primary bg-hospital-primary-pale text-hospital-primary'
                                        : 'border-hospital-border text-hospital-text-2 hover:border-hospital-border'"
                                    @click="form.center_type = 'pct'"
                                >
                                    نسبة %
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-lg border py-2 text-sm font-medium transition-all"
                                    :class="form.center_type === 'fixed'
                                        ? 'border-hospital-primary bg-hospital-primary-pale text-hospital-primary'
                                        : 'border-hospital-border text-hospital-text-2 hover:border-hospital-border'"
                                    @click="form.center_type = 'fixed'"
                                >
                                    مبلغ ثابت
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-hospital-text">
                                {{ form.center_type === 'pct' ? 'نسبة المركز (%)' : 'مبلغ المركز (ج.م)' }}
                            </label>
                            <input
                                v-model.number="form.center_val"
                                type="number"
                                min="0"
                                step="0.01"
                                class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                            />
                        </div>
                    </div>

                    <!-- Live preview -->
                    <div v-if="centerPreview" class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-hospital-warning-pale p-3 text-center">
                            <p class="text-xs text-hospital-text-2">حصة المركز</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-hospital-warning">{{ centerPreview.center }} ج</p>
                        </div>
                        <div class="rounded-lg bg-hospital-success-pale p-3 text-center">
                            <p class="text-xs text-hospital-text-2">مستحق الطبيب</p>
                            <p class="mt-0.5 font-mono text-base font-bold text-hospital-success">{{ centerPreview.dr }} ج</p>
                        </div>
                    </div>

                    <!-- Revenue account -->
                    <div class="mt-4">
                        <label class="mb-1 block text-sm font-medium text-hospital-text">
                            حساب الإيراد
                            <span class="ms-1 text-xs font-normal text-hospital-text-3">(اختياري — يُستخدم في القيود المحاسبية)</span>
                        </label>
                        <select
                            v-model="form.revenue_account_id"
                            class="w-full rounded-lg border border-hospital-border px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                        >
                            <option :value="null">— افتراضي حسب القسم —</option>
                            <option v-for="acc in revenueAccounts" :key="acc.id" :value="acc.id">
                                {{ acc.code }} — {{ acc.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.revenue_account_id" class="mt-1 text-xs text-hospital-danger">{{ form.errors.revenue_account_id }}</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 border-t border-hospital-border pt-4">
                    <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg" @click="closeModal">
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-hospital-primary px-6 py-2 text-sm font-semibold text-white transition-colors hover:bg-hospital-primary-light disabled:opacity-60"
                    >
                        {{ form.processing ? 'جارٍ الحفظ...' : editingService ? 'حفظ التغييرات' : 'إضافة الخدمة' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ── Import Modal ────────────────────────────────────────────────── -->
        <Modal v-model="showImportModal" title="استيراد الخدمات من Excel" @close="showImportModal = false">
            <form class="space-y-4" @submit.prevent="submitImport">
                <!-- Info box -->
                <div class="rounded-lg border border-hospital-primary/20 bg-hospital-primary-pale p-3 text-sm text-hospital-primary">
                    <p class="mb-1 font-semibold">أعمدة الملف المطلوبة:</p>
                    <div class="mt-1 flex flex-wrap gap-1">
                        <code v-for="col in ['name', 'dept', 'price', 'ins_price', 'center_val']" :key="col" class="rounded bg-white/70 px-1.5 py-0.5 font-mono text-xs">{{ col }}</code>
                    </div>
                    <p class="mt-2 text-xs text-hospital-primary/70">القسم: clinic / labs / surgery / lasik / laser</p>
                </div>

                <!-- File input -->
                <div>
                    <label class="mb-1 block text-sm font-medium text-hospital-text">ملف Excel أو CSV</label>
                    <label
                        class="flex w-full cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-hospital-border px-4 py-5 transition-colors hover:border-hospital-primary hover:bg-hospital-primary-pale/30"
                        :class="importForm.file ? 'border-hospital-success bg-hospital-success-pale/30' : ''"
                    >
                        <Upload class="h-6 w-6 shrink-0 text-hospital-text-3" :class="importForm.file ? 'text-hospital-success' : ''" />
                        <div class="min-w-0">
                            <p v-if="importFileName" class="truncate text-sm font-medium text-hospital-text">{{ importFileName }}</p>
                            <p v-else class="text-sm text-hospital-text-3">اضغط لاختيار ملف...</p>
                            <p class="mt-0.5 text-xs text-hospital-text-3">.xlsx, .xls, .csv</p>
                        </div>
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            class="sr-only"
                            @change="onFileChange"
                        />
                    </label>
                    <p v-if="importForm.errors.file" class="mt-1 text-xs text-hospital-danger">{{ importForm.errors.file }}</p>
                </div>

                <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                    <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg" @click="showImportModal = false">
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        :disabled="importForm.processing || !importForm.file"
                        class="flex items-center gap-2 rounded-lg bg-hospital-success px-5 py-2 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:opacity-50"
                    >
                        <Upload class="h-4 w-4" />
                        {{ importForm.processing ? 'جارٍ الاستيراد...' : 'استيراد' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- ── Delete Confirmation Modal ──────────────────────────────────── -->
        <Modal v-model="showDeleteModal" title="تأكيد الحذف" @close="showDeleteModal = false">
            <div class="space-y-3">
                <div class="flex items-start gap-3 rounded-lg bg-hospital-danger-pale p-3">
                    <Trash2 class="mt-0.5 h-5 w-5 shrink-0 text-hospital-danger" />
                    <div>
                        <p class="text-sm font-medium text-hospital-text">
                            هل أنت متأكد من حذف خدمة
                            <strong class="text-hospital-danger">{{ deletingService?.name }}</strong>؟
                        </p>
                        <p class="mt-1 text-xs text-hospital-text-2">لا يمكن التراجع عن هذا الإجراء.</p>
                    </div>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button type="button" class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg" @click="showDeleteModal = false">
                    إلغاء
                </button>
                <button
                    type="button"
                    :disabled="deleteForm.processing"
                    class="rounded-lg bg-hospital-danger px-5 py-2 text-sm font-semibold text-white transition-colors hover:opacity-90 disabled:opacity-60"
                    @click="deleteService"
                >
                    {{ deleteForm.processing ? 'جارٍ الحذف...' : 'حذف الخدمة' }}
                </button>
            </div>
        </Modal>
    </div>
</template>
