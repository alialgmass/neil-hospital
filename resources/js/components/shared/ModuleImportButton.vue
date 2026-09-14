<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Download, Upload, CheckCircle2, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';
import ImportUploadZone from '@/components/shared/ImportUploadZone.vue';
import Modal from '@/components/shared/Modal.vue';

const props = defineProps<{
    /** Download-template URL (GET). */
    templateUrl: string;
    /** Import endpoint URL (POST file). */
    importUrl: string;
    /** Module label shown in the modal title. */
    label?: string;
}>();

const showModal = ref(false);
const importForm = useForm({ file: null as File | null });
const result = ref<{ created: number; updated: number; skipped: number } | null>(null);
const page = usePage<{ flash?: { importResult?: { created: number; updated: number; skipped: number } } }>();

function downloadTemplate() {
    window.location.href = props.templateUrl;
}

function openModal() {
    result.value = null;
    importForm.reset();
    importForm.clearErrors();
    showModal.value = true;
}

function onFileSelected(file: File) {
    importForm.file = file;
}

function submitImport() {
    if (!importForm.file) return;

    importForm.post(props.importUrl, {
        preserveScroll: true,
        onSuccess: () => {
            const flash = page.props.flash?.importResult;
            if (flash) {
                result.value = flash;
            }
            showModal.value = false;
            importForm.reset();
        },
    });
}
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg border border-hospital-border bg-white px-3 py-2 text-xs font-medium text-hospital-text-2 transition-colors hover:bg-hospital-bg"
            @click="downloadTemplate"
        >
            <Download class="h-3.5 w-3.5" />
            قالب
        </button>
        <button
            type="button"
            class="flex items-center gap-1.5 rounded-lg bg-hospital-success px-3 py-2 text-xs font-medium text-white shadow-sm transition-colors hover:opacity-90"
            @click="openModal"
        >
            <Upload class="h-3.5 w-3.5" />
            استيراد
        </button>
    </div>

    <Modal v-model="showModal" :title="label ? `استيراد — ${label}` : 'استيراد بيانات'" @close="showModal = false">
        <form @submit.prevent="submitImport" class="flex flex-col gap-4">
            <p class="text-sm text-hospital-muted">
                ارفع ملف Excel أو CSV يحتوي البيانات بالتنسيق المطابق للقالب.
            </p>

            <ImportUploadZone @file="onFileSelected" />

            <p v-if="importForm.errors.file" class="text-xs text-hospital-danger">{{ importForm.errors.file }}</p>

            <Transition name="modal">
                <div v-if="result" class="flex items-center gap-2 rounded-lg border border-hospital-success/30 bg-hospital-success-pale/30 p-3">
                    <CheckCircle2 class="h-4 w-4 shrink-0 text-hospital-success" />
                    <p class="text-xs text-hospital-text">
                        إضافة: {{ result.created }} · تعديل: {{ result.updated }} · تخطي: {{ result.skipped }}
                    </p>
                    <button type="button" class="mr-auto rounded p-1 hover:bg-hospital-bg" @click="result = null">
                        <XCircle class="h-4 w-4 text-hospital-text-3" />
                    </button>
                </div>
            </Transition>

            <div class="flex justify-end gap-3 border-t border-hospital-border pt-4">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm text-hospital-text-2 hover:bg-hospital-bg"
                    @click="showModal = false"
                >
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
