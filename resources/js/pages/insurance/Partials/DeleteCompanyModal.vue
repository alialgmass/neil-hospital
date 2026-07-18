<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Trash2 } from 'lucide-vue-next'
import Modal from '@/components/shared/Modal.vue'

const props = defineProps<{
    modelValue: boolean
    companyId: string | null
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'success'): void
}>()

function close() {
    emit('update:modelValue', false)
}

function confirm() {
    if (!props.companyId) {
 return 
}

    router.delete(`/insurance/${props.companyId}`, {
        onSuccess: () => {
 close(); emit('success') 
},
    })
}
</script>

<template>
    <Modal :model-value="modelValue" title="تأكيد الحذف" size="sm" @update:model-value="emit('update:modelValue', $event)" @close="close">
        <div class="flex items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-hospital-danger-pale">
                <Trash2 class="h-5 w-5 text-hospital-danger" />
            </div>
            <div>
                <p class="font-medium text-hospital-text">حذف شركة التأمين</p>
                <p class="mt-1 text-sm text-hospital-text-3">هل أنت متأكد؟ سيتم حذف جميع البيانات المرتبطة بها ولا يمكن التراجع.</p>
            </div>
        </div>
        <div class="mt-5 flex justify-end gap-2 border-t border-hospital-border pt-4">
            <button class="fbtn-secondary" @click="close">إلغاء</button>
            <button class="fbtn-danger" @click="confirm">
                <Trash2 class="h-4 w-4" />
                تأكيد الحذف
            </button>
        </div>
    </Modal>
</template>

<style scoped>
.fbtn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; font-family:inherit; background:#fff; color:var(--color-hospital-text-2); border:1.5px solid var(--color-hospital-border); cursor:pointer; transition:background .15s }
.fbtn-secondary:hover { background:var(--color-hospital-bg) }
.fbtn-danger { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; font-family:inherit; background:var(--color-hospital-danger); color:#fff; border:none; cursor:pointer; transition:background .15s }
.fbtn-danger:hover { background:#b73030 }
</style>
