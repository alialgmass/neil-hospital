<script setup lang="ts">
import Modal from '@/components/shared/Modal.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    modelValue: boolean;
    surgeryId: string;
    dept: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    success: [];
}>();

const form = useForm({
    op_report: '',
    post_op_notes: '',
    complications: '',
});

function submit() {
    form.post(`/${props.dept}/${props.surgeryId}/report`, {
        onSuccess: () => {
            emit('update:modelValue', false);
            form.reset();
            emit('success');
        },
    });
}

function close() {
    emit('update:modelValue', false);
}
</script>

<template>
    <Modal :model-value="modelValue" title="تقرير الليزك" size="md" @update:model-value="close">
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <label class="field-label">تقرير الإجراء</label>
                <textarea v-model="form.op_report" rows="4" class="field-input" />
            </div>
            <div>
                <label class="field-label">ملاحظات ما بعد الإجراء</label>
                <textarea v-model="form.post_op_notes" rows="3" class="field-input" />
            </div>
            <div>
                <label class="field-label">المضاعفات</label>
                <textarea v-model="form.complications" rows="2" class="field-input" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-hospital-border px-4 py-2 text-sm hover:bg-hospital-bg"
                    @click="close"
                >
                    إلغاء
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#7B2FA6] px-4 py-2 text-sm font-medium text-white disabled:opacity-60 hover:bg-[#6A2890]"
                >
                    حفظ التقرير
                </button>
            </div>
        </form>
    </Modal>
</template>

<style scoped>
.field-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-hospital-text, #0d1f3c);
    margin-bottom: 5px;
}
.field-input {
    width: 100%;
    padding: 7px 10px;
    border: 1.5px solid var(--color-hospital-border, #dde4ef);
    border-radius: 7px;
    font-size: 13px;
    font-family: inherit;
    color: var(--color-hospital-text, #0d1f3c);
    background: #fff;
    direction: rtl;
    resize: vertical;
}
.field-input:focus {
    outline: none;
    border-color: #7b2fa6;
    box-shadow: 0 0 0 3px rgba(123, 47, 166, 0.1);
}
</style>