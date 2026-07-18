<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import Modal from '@/components/shared/Modal.vue'
import type { Company } from './types'

const props = defineProps<{
    modelValue: boolean
    companies: Company[]
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'success'): void
}>()

const form = useForm({
    insurance_company_id: '',
    patient_name: '',
    file_no: '',
    service_name: '',
    invoice_amount: 0,
    discount: 0,
    patient_share: 0,
    insurance_share: 0,
    service_date: new Date().toISOString().slice(0, 10),
    notes: '',
})

function close() {
    emit('update:modelValue', false)
    form.reset()
    form.clearErrors()
}

function submit() {
    form.post('/insurance/claims', {
        onSuccess: () => {
 close(); emit('success') 
},
    })
}
</script>

<template>
    <Modal :model-value="modelValue" title="إنشاء مطالبة جديدة" size="lg" @update:model-value="emit('update:modelValue', $event)" @close="close">
        <form class="space-y-4" @submit.prevent="submit">
            <div class="fg">
                <label>شركة التأمين <span class="text-hospital-danger">*</span></label>
                <select v-model="form.insurance_company_id" :class="['fi', form.errors.insurance_company_id && 'fi-err']" required>
                    <option value="">— اختر الشركة —</option>
                    <option v-for="c in companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <p v-if="form.errors.insurance_company_id" class="form-err-msg">{{ form.errors.insurance_company_id }}</p>
            </div>

            <div class="form-section">
                <p class="form-section-title">بيانات المريض</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg">
                        <label>اسم المريض <span class="text-hospital-danger">*</span></label>
                        <input v-model="form.patient_name" :class="['fi', form.errors.patient_name && 'fi-err']" type="text" placeholder="الاسم الكامل" required />
                        <p v-if="form.errors.patient_name" class="form-err-msg">{{ form.errors.patient_name }}</p>
                    </div>
                    <div class="fg">
                        <label>رقم الملف</label>
                        <input v-model="form.file_no" class="fi font-mono" type="text" placeholder="PT-0001" />
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="form-section-title">بيانات الخدمة</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg col-span-2">
                        <label>الخدمة <span class="text-hospital-danger">*</span></label>
                        <input v-model="form.service_name" :class="['fi', form.errors.service_name && 'fi-err']" type="text" placeholder="اسم الخدمة المقدمة" required />
                        <p v-if="form.errors.service_name" class="form-err-msg">{{ form.errors.service_name }}</p>
                    </div>
                    <div class="fg">
                        <label>تاريخ الخدمة <span class="text-hospital-danger">*</span></label>
                        <input v-model="form.service_date" class="fi" type="date" required />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-hospital-border bg-hospital-surface-2 p-4">
                <p class="form-section-title mb-3">المبالغ المالية</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg">
                        <label>إجمالي الفاتورة (ج) <span class="text-hospital-danger">*</span></label>
                        <input v-model.number="form.invoice_amount" :class="['fi', form.errors.invoice_amount && 'fi-err']" type="number" min="0" step="0.01" required />
                        <p v-if="form.errors.invoice_amount" class="form-err-msg">{{ form.errors.invoice_amount }}</p>
                    </div>
                    <div class="fg">
                        <label>الخصم (ج)</label>
                        <input v-model.number="form.discount" class="fi" type="number" min="0" step="0.01" />
                    </div>
                    <div class="fg">
                        <label class="text-hospital-primary">حصة التأمين (ج)</label>
                        <input v-model.number="form.insurance_share" class="fi border-hospital-primary/30 bg-hospital-primary-pale/30 focus:border-hospital-primary" type="number" min="0" step="0.01" />
                    </div>
                    <div class="fg">
                        <label class="text-hospital-warning">حصة المريض (ج)</label>
                        <input v-model.number="form.patient_share" class="fi border-hospital-warning/30 bg-hospital-warning-pale/30 focus:border-hospital-warning" type="number" min="0" step="0.01" />
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
                    {{ form.processing ? 'جارٍ الحفظ...' : 'إنشاء المطالبة' }}
                </button>
            </div>
        </form>
    </Modal>
</template>

<style scoped>
.fi { width:100%; padding:8px 11px; border:1.5px solid var(--color-hospital-border); border-radius:8px; font-size:13px; font-family:inherit; color:var(--color-hospital-text); background:#fff; direction:rtl; transition:border-color .15s,box-shadow .15s }
.fi:focus { outline:none; border-color:var(--color-hospital-primary); box-shadow:0 0 0 3px color-mix(in srgb,var(--color-hospital-primary) 15%,transparent) }
.fi-err { border-color:var(--color-hospital-danger); box-shadow:0 0 0 2px color-mix(in srgb,var(--color-hospital-danger) 15%,transparent) }
.fg { display:flex; flex-direction:column; gap:4px }
.fg label { font-size:11px; font-weight:700; color:var(--color-hospital-text-2); text-transform:uppercase; letter-spacing:.05em }
.form-section { border:1.5px solid var(--color-hospital-border); border-radius:10px; padding:14px; background:var(--color-hospital-surface-2); display:flex; flex-direction:column; gap:12px }
.form-section-title { display:flex; align-items:center; gap:6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:var(--color-hospital-text-3) }
.form-err-msg { font-size:11px; color:var(--color-hospital-danger); margin-top:2px }
.form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; border-top:1.5px solid var(--color-hospital-border) }
.fbtn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; font-family:inherit; background:var(--color-hospital-primary); color:#fff; border:none; cursor:pointer; transition:background .15s }
.fbtn-primary:hover { background:var(--color-hospital-primary-light) }
.fbtn-primary:disabled { opacity:.6; cursor:not-allowed }
.fbtn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; font-family:inherit; background:#fff; color:var(--color-hospital-text-2); border:1.5px solid var(--color-hospital-border); cursor:pointer; transition:background .15s }
.fbtn-secondary:hover { background:var(--color-hospital-bg) }
</style>
