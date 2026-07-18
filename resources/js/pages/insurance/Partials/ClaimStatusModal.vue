<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'
import Modal from '@/components/shared/Modal.vue'
import type { Claim } from './types'
import { claimStatusLabels } from './types'

const props = defineProps<{
    modelValue: boolean
    claim: Claim | null
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'success'): void
}>()

const form = useForm({
    status: 'draft' as Claim['status'],
    approved_amount: 0,
    paid_amount: 0,
    rejection_reason: '',
    submission_date: '',
    approval_date: '',
    payment_date: '',
    notes: '',
})

watch(() => props.claim, (claim) => {
    if (!claim) {
 return 
}

    form.status = claim.status
    form.approved_amount = claim.approved_amount
    form.paid_amount = claim.paid_amount
    form.rejection_reason = claim.rejection_reason ?? ''
    form.submission_date = claim.submission_date ?? ''
    form.approval_date = claim.approval_date ?? ''
    form.payment_date = claim.payment_date ?? ''
    form.notes = claim.notes ?? ''
})

function close() {
    emit('update:modelValue', false)
}

function submit() {
    if (!props.claim) {
 return 
}

    form.put(`/insurance/claims/${props.claim.id}`, {
        onSuccess: () => {
 close(); emit('success') 
},
    })
}
</script>

<template>
    <Modal :model-value="modelValue" title="تحديث حالة المطالبة" @update:model-value="emit('update:modelValue', $event)" @close="close">
        <form class="space-y-4" @submit.prevent="submit">
            <!-- Status pill selector -->
            <div>
                <label class="mb-2 block text-[11px] font-bold uppercase tracking-widest text-hospital-text-3">الحالة الجديدة</label>
                <div class="grid grid-cols-5 gap-2">
                    <button
                        v-for="(label, key) in claimStatusLabels"
                        :key="key"
                        type="button"
                        class="rounded-lg border px-2 py-2.5 text-center text-xs font-semibold transition-all"
                        :class="form.status === key
                            ? 'border-hospital-primary bg-hospital-primary text-white shadow-sm'
                            : 'border-hospital-border bg-white text-hospital-text-3 hover:border-hospital-primary/40 hover:text-hospital-primary'"
                        @click="form.status = key as Claim['status']"
                    >
                        {{ label }}
                    </button>
                </div>
            </div>

            <!-- Conditional fields -->
            <div v-if="form.status !== 'draft'" class="form-section">
                <p class="form-section-title">تفاصيل الحالة</p>
                <div class="grid grid-cols-2 gap-3">
                    <div v-if="form.status === 'submitted'" class="fg">
                        <label>تاريخ الإرسال</label>
                        <input v-model="form.submission_date" class="fi" type="date" />
                    </div>
                    <div v-if="form.status === 'approved' || form.status === 'paid'" class="fg">
                        <label>المبلغ المعتمد (ج)</label>
                        <input v-model.number="form.approved_amount" class="fi" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="form.status === 'approved'" class="fg">
                        <label>تاريخ الاعتماد</label>
                        <input v-model="form.approval_date" class="fi" type="date" />
                    </div>
                    <div v-if="form.status === 'paid'" class="fg">
                        <label>المبلغ المسدد (ج)</label>
                        <input v-model.number="form.paid_amount" class="fi" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="form.status === 'paid'" class="fg">
                        <label>تاريخ السداد</label>
                        <input v-model="form.payment_date" class="fi" type="date" />
                    </div>
                    <div v-if="form.status === 'rejected'" class="fg col-span-2">
                        <label>سبب الرفض</label>
                        <textarea v-model="form.rejection_reason" class="fi resize-none" rows="2" placeholder="اذكر سبب الرفض..." />
                    </div>
                </div>
            </div>

            <div class="fg">
                <label>ملاحظات</label>
                <textarea v-model="form.notes" class="fi resize-none" rows="2" placeholder="ملاحظات إضافية..." />
            </div>

            <div class="form-footer">
                <button type="button" class="fbtn-secondary" @click="close">إلغاء</button>
                <button type="submit" class="fbtn-primary" :disabled="form.processing">
                    {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                </button>
            </div>
        </form>
    </Modal>
</template>

<style scoped>
.fi { width:100%; padding:8px 11px; border:1.5px solid var(--color-hospital-border); border-radius:8px; font-size:13px; font-family:inherit; color:var(--color-hospital-text); background:#fff; direction:rtl; transition:border-color .15s,box-shadow .15s }
.fi:focus { outline:none; border-color:var(--color-hospital-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--color-hospital-primary) 15%,transparent) }
.fg { display:flex; flex-direction:column; gap:4px }
.fg label { font-size:11px; font-weight:700; color:var(--color-hospital-text-2); text-transform:uppercase; letter-spacing:.05em }
.form-section { border:1.5px solid var(--color-hospital-border); border-radius:10px; padding:14px; background:var(--color-hospital-surface-2); display:flex; flex-direction:column; gap:12px }
.form-section-title { display:flex; align-items:center; gap:6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:var(--color-hospital-text-3) }
.form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; border-top:1.5px solid var(--color-hospital-border) }
.fbtn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; font-family:inherit; background:var(--color-hospital-primary); color:#fff; border:none; cursor:pointer; transition:background .15s }
.fbtn-primary:hover { background:var(--color-hospital-primary-light) }
.fbtn-primary:disabled { opacity:.6; cursor:not-allowed }
.fbtn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; font-family:inherit; background:#fff; color:var(--color-hospital-text-2); border:1.5px solid var(--color-hospital-border); cursor:pointer; transition:background .15s }
.fbtn-secondary:hover { background:var(--color-hospital-bg) }
</style>
