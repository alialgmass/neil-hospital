<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Edit2, Upload } from 'lucide-vue-next';
import { ref } from 'vue';
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
    duration_mins: number;
    status: 'active' | 'inactive';
}

const props = defineProps<{
    services: { data: Service[]; links: unknown[] };
    filters: { search?: string; dept?: string; status?: string };
}>();

const showModal = ref(false);
const editingService = ref<Service | null>(null);

const form = useForm({
    name: '',
    dept: 'clinic' as Service['dept'],
    price: 0,
    ins_price: 0,
    center_type: 'pct' as 'pct' | 'fixed',
    center_val: 0,
    duration_mins: 30,
    status: 'active' as 'active' | 'inactive',
});

const importForm = useForm({ file: null as File | null });
const showImportModal = ref(false);

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
};

const filters = ref({ ...props.filters });

function search() {
    router.get('/services', filters.value, {
        preserveState: true,
        replace: true,
    });
}

function openCreate() {
    editingService.value = null;
    form.reset();
    showModal.value = true;
}

function openEdit(svc: Service) {
    editingService.value = svc;
    form.name = svc.name;
    form.dept = svc.dept as Service['dept'];
    form.price = svc.price;
    form.ins_price = svc.ins_price;
    form.center_type = svc.center_type;
    form.center_val = svc.center_val;
    form.duration_mins = svc.duration_mins;
    form.status = svc.status;
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
            onSuccess: closeModal,
        });
    } else {
        form.post('/services', {
            onSuccess: closeModal,
        });
    }
}

function submitImport() {
    importForm.post('/services/import', {
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
}

function centerSharePreview(): string {
    if (form.center_type === 'pct') {
        return `${((form.price * form.center_val) / 100).toFixed(2)} ج (${form.center_val}%)`;
    }

    return `${form.center_val} ج (ثابت)`;
}

function drSharePreview(): number {
    if (form.center_type === 'pct') {
        return form.price - (form.price * form.center_val) / 100;
    }

    return form.price - form.center_val;
}
</script>

<template>
    <div class="p-6">
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">
                إدارة الخدمات والأسعار
            </h1>
            <div class="flex gap-3">
                <button
                    class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50"
                    @click="showImportModal = true"
                >
                    <Upload class="h-4 w-4" />
                    استيراد Excel
                </button>
                <button class="btn-primary" @click="openCreate">
                    + إضافة خدمة
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-4 flex flex-wrap gap-3">
            <input
                v-model="filters.search"
                class="input-field w-64"
                type="text"
                placeholder="بحث باسم الخدمة..."
                @input="search"
            />
            <select v-model="filters.dept" class="input-field" @change="search">
                <option value="">كل الأقسام</option>
                <option
                    v-for="(label, key) in deptLabels"
                    :key="key"
                    :value="key"
                >
                    {{ label }}
                </option>
            </select>
            <select
                v-model="filters.status"
                class="input-field"
                @change="search"
            >
                <option value="">كل الحالات</option>
                <option value="active">نشط</option>
                <option value="inactive">غير نشط</option>
            </select>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
        >
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            الخدمة
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            القسم
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            السعر
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            سعر التأمين
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            حصة المركز
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            حصة الطبيب
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        >
                            الحالة
                        </th>
                        <th
                            class="px-4 py-3 text-right font-semibold text-gray-600"
                        ></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="svc in services.data"
                        :key="svc.id"
                        class="border-t border-gray-100 hover:bg-gray-50"
                    >
                        <td class="px-4 py-3 font-medium">{{ svc.name }}</td>
                        <td class="px-4 py-3">
                            <Badge variant="active">{{
                                deptLabels[svc.dept] || svc.dept
                            }}</Badge>
                        </td>
                        <td class="px-4 py-3">{{ svc.price.toFixed(2) }} ج</td>
                        <td class="px-4 py-3 text-blue-600">
                            {{ svc.ins_price.toFixed(2) }} ج
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{
                                svc.center_type === 'pct'
                                    ? `${svc.center_val}%`
                                    : `${svc.center_val} ج`
                            }}
                        </td>
                        <td class="px-4 py-3 font-medium text-green-700">
                            {{
                                (svc.center_type === 'pct'
                                    ? svc.price -
                                      (svc.price * svc.center_val) / 100
                                    : svc.price - svc.center_val
                                ).toFixed(2)
                            }}
                            ج
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    svc.status === 'active'
                                        ? 'active'
                                        : 'cancelled'
                                "
                            >
                                {{
                                    svc.status === 'active' ? 'نشط' : 'غير نشط'
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                class="text-gray-400 hover:text-blue-600"
                                @click="openEdit(svc)"
                            >
                                <Edit2 class="h-4 w-4" />
                            </button>
                        </td>
                    </tr>
                    <tr v-if="services.data.length === 0">
                        <td
                            class="px-4 py-8 text-center text-gray-400"
                            colspan="8"
                        >
                            لا توجد خدمات
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create/Edit Modal -->
        <Modal
            v-model="showModal"
            :title="editingService ? 'تعديل خدمة' : 'إضافة خدمة'"
            @close="closeModal"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">اسم الخدمة *</label>
                        <input
                            v-model="form.name"
                            :class="['input-field', form.errors.name && 'border-red-400']"
                            type="text"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">القسم *</label>
                        <select
                            v-model="form.dept"
                            :class="['input-field', form.errors.dept && 'border-red-400']"
                            required
                        >
                            <option v-for="(label, key) in deptLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p v-if="form.errors.dept" class="mt-1 text-xs text-red-500">{{ form.errors.dept }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">الحالة</label>
                        <select v-model="form.status" class="input-field">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">السعر الأساسي (ج)</label>
                        <input
                            v-model.number="form.price"
                            :class="['input-field', form.errors.price && 'border-red-400']"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">سعر التأمين (ج)</label>
                        <input
                            v-model.number="form.ins_price"
                            :class="['input-field', form.errors.ins_price && 'border-red-400']"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <p v-if="form.errors.ins_price" class="mt-1 text-xs text-red-500">{{ form.errors.ins_price }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">نوع حصة المركز</label>
                        <select v-model="form.center_type" class="input-field">
                            <option value="pct">نسبة %</option>
                            <option value="fixed">قيمة ثابتة</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">
                            {{ form.center_type === 'pct' ? 'النسبة %' : 'القيمة الثابتة (ج)' }}
                        </label>
                        <input
                            v-model.number="form.center_val"
                            :class="['input-field', form.errors.center_val && 'border-red-400']"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <p v-if="form.errors.center_val" class="mt-1 text-xs text-red-500">{{ form.errors.center_val }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">مدة الخدمة (دقيقة)</label>
                        <input
                            v-model.number="form.duration_mins"
                            :class="['input-field', form.errors.duration_mins && 'border-red-400']"
                            type="number"
                            min="1"
                        />
                        <p v-if="form.errors.duration_mins" class="mt-1 text-xs text-red-500">{{ form.errors.duration_mins }}</p>
                    </div>
                </div>

                <!-- Preview -->
                <div v-if="form.price > 0" class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <p class="text-sm font-medium text-blue-800">معاينة التوزيع:</p>
                    <p class="text-sm text-blue-700">حصة المركز: {{ centerSharePreview() }}</p>
                    <p class="text-sm text-green-700">مستحق الطبيب: {{ drSharePreview().toFixed(2) }} ج</p>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="closeModal">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Import Modal -->
        <Modal
            v-model="showImportModal"
            title="استيراد الخدمات من Excel"
            @close="showImportModal = false"
        >
            <form class="space-y-4" @submit.prevent="submitImport">
                <p class="text-sm text-gray-600">
                    الأعمدة المطلوبة: <code>name, dept, price, center_val</code>
                    <br />
                    أو بالعربي: <code>الاسم, القسم, السعر, نسبة_المركز</code>
                </p>
                <div>
                    <label class="mb-1 block text-sm font-medium"
                        >ملف Excel / CSV</label
                    >
                    <input
                        class="input-field"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        @change="
                            (e: Event) => {
                                importForm.file =
                                    (e.target as HTMLInputElement).files?.[0] ??
                                    null;
                            }
                        "
                    />
                    <p
                        v-if="importForm.errors.file"
                        class="text-sm text-red-500"
                    >
                        {{ importForm.errors.file }}
                    </p>
                </div>
                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="btn-secondary"
                        @click="showImportModal = false"
                    >
                        إلغاء
                    </button>
                    <button
                        type="submit"
                        class="btn-primary"
                        :disabled="importForm.processing"
                    >
                        {{
                            importForm.processing
                                ? 'جارٍ الاستيراد...'
                                : 'استيراد'
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
