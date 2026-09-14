<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, FileSpreadsheet, Upload, CheckCircle2, AlertCircle, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import ImportUploadZone from '@/components/shared/ImportUploadZone.vue';
import Modal from '@/components/shared/Modal.vue';

defineOptions({ layout: AppLayout });

interface SystemModule {
    value: string;
    label: string;
    hasImport: boolean;
    enabled: boolean;
}

interface ImportResult {
    module: string;
    created: number;
    updated: number;
    skipped: number;
}

const props = defineProps<{
    modules: SystemModule[];
    importResult: ImportResult | null;
}>();

const page = usePage<{ flash?: { importResult?: ImportResult } }>();

const showImportModal = ref(false);
const selectedModule = ref<SystemModule | null>(null);
const importForm = useForm({ file: null as File | null });

function downloadTemplate(mod: SystemModule) {
    window.location.href = `/module-imports/${mod.value}/template`;
}

function openImport(mod: SystemModule) {
    selectedModule.value = mod;
    importForm.reset();
    importForm.clearErrors();
    showImportModal.value = true;
}

function onFileSelected(file: File) {
    importForm.file = file;
}

function submitImport() {
    if (!selectedModule.value || !importForm.file) return;

    importForm.post(`/module-imports/${selectedModule.value.value}/import`, {
        preserveScroll: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
}

const flashResult = ref<ImportResult | null>(props.importResult);

function dismissFlash() {
    flashResult.value = null;
}
</script>

<template>
    <Head title="استيراد بيانات الوحدات" />

    <div class="mb-5">
        <h2 class="text-lg font-bold text-hospital-text">استيراد بيانات الوحدات</h2>
        <p class="text-sm text-hospital-muted">تنزيل نموذج فارغ (قالب Excel) ثم رفع الملف المملوء للاستيراد.</p>
    </div>

    <!-- Flash result -->
    <Transition name="modal">
        <div
            v-if="flashResult"
            class="mb-5 flex items-center gap-3 rounded-xl border border-hospital-success/30 bg-hospital-success-pale/30 p-4"
        >
            <CheckCircle2 class="h-5 w-5 shrink-0 text-hospital-success" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-hospital-text">تم الاستيراد — {{ flashResult.module }}</p>
                <p class="text-xs text-hospital-muted">
                    إضافة: {{ flashResult.created }} · تعديل: {{ flashResult.updated }} · تخطي: {{ flashResult.skipped }}
                </p>
            </div>
            <button class="rounded p-1 hover:bg-hospital-bg transition-colors" @click="dismissFlash">
                <XCircle class="h-4 w-4 text-hospital-text-3" />
            </button>
        </div>
    </Transition>

    <!-- Module cards grid -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="mod in props.modules"
            :key="mod.value"
            class="flex flex-col rounded-xl border border-hospital-border bg-white p-4 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-hospital-primary/10 text-hospital-primary">
                    <FileSpreadsheet class="h-5 w-5" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-hospital-text">{{ mod.label }}</p>
                    <p class="text-xs text-hospital-muted">{{ mod.hasImport ? 'قالب + استيراد' : 'قالب فقط' }} · {{ mod.enabled ? 'مفعلة' : 'معطلة' }}</p>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-2">
                <button
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-hospital-border bg-hospital-surface px-3 py-2 text-xs font-medium text-hospital-text-2 transition-colors hover:bg-hospital-bg"
                    @click="downloadTemplate(mod)"
                >
                    <Download class="h-3.5 w-3.5" /> تنزيل القالب
                </button>
                <button
                    v-if="mod.hasImport"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-hospital-success px-3 py-2 text-xs font-medium text-white shadow-sm transition-all hover:opacity-90"
                    @click="openImport(mod)"
                >
                    <Upload class="h-3.5 w-3.5" /> استيراد
                </button>
            </div>
        </div>
    </div>

    <!-- Import modal -->
    <Modal v-model="showImportModal" :title="`استيراد — ${selectedModule?.label ?? ''}`" @close="showImportModal = false">
        <form @submit.prevent="submitImport" class="flex flex-col gap-4">
            <p class="text-sm text-hospital-muted">
                ارفع ملف Excel أو CSV يحتوي البيانات بالتنسيق المطابق للقالب.
            </p>

            <ImportUploadZone @file="onFileSelected" />

            <p v-if="importForm.errors.file" class="text-xs text-hospital-danger">{{ importForm.errors.file }}</p>

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
</template>
