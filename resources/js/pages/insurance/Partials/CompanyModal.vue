<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { Building2 } from 'lucide-vue-next'
import { watch } from 'vue'
import Modal from '@/components/shared/Modal.vue'
import type { Company } from './types'

const props = defineProps<{
    modelValue: boolean
    editingCompany: Company | null
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'success'): void
}>()

const form = useForm({
    name: '',
    code: '',
    phone: '',
    address: '',
    contract_no: '',
    coverage_pct: 80,
    disc_pct: 0,
    contact_person: '',
    email: '',
    status: 'active' as 'active' | 'inactive',
    notes: '',
})

watch(() => props.editingCompany, (company) => {
    if (company) {
        form.name = company.name
        form.code = company.code ?? ''
        form.phone = company.phone ?? ''
        form.coverage_pct = company.coverage_pct
        form.disc_pct = company.disc_pct
        form.contact_person = company.contact_person ?? ''
        form.email = company.email ?? ''
        form.status = company.status
        form.contract_no = company.contract_no ?? ''
    } else {
        form.reset()
    }

    form.clearErrors()
}, { immediate: true })

function close() {
    emit('update:modelValue', false)
    form.reset()
    form.clearErrors()
}

function submit() {
    const opts = { onSuccess: () => {
 close(); emit('success') 
} }

    if (props.editingCompany) {
        form.put(`/insurance/${props.editingCompany.id}`, opts)
    } else {
        form.post('/insurance', opts)
    }
}
</script>

<template>
    <Modal
        :model-value="modelValue"
        :title="editingCompany ? 'تعديل شركة التأمين' : 'إضافة شركة تأمين'"
        @update:model-value="emit('update:modelValue', $event)"
        @close="close"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div class="form-section">
                <p class="form-section-title text-hospital-primary">
                    <Building2 class="h-3.5 w-3.5" />
                    بيانات الشركة
                </p>
                <div class="fg">
                    <label>اسم الشركة <span class="text-hospital-danger">*</span></label>
                    <input v-model="form.name" :class="['fi', form.errors.name && 'fi-err']" type="text" placeholder="اسم شركة التأمين" required />
                    <p v-if="form.errors.name" class="form-err-msg">{{ form.errors.name }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg">
                        <label>الكود</label>
                        <input v-model="form.code" :class="['fi font-mono', form.errors.code && 'fi-err']" type="text" placeholder="INS-001" />
                        <p v-if="form.errors.code" class="form-err-msg">{{ form.errors.code }}</p>
                    </div>
                    <div class="fg">
                        <label>رقم العقد</label>
                        <input v-model="form.contract_no" class="fi" type="text" placeholder="CON-2024-001" />
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="form-section-title">بيانات التواصل</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg">
                        <label>الهاتف</label>
                        <input v-model="form.phone" class="fi" type="text" placeholder="05xxxxxxxx" />
                    </div>
                    <div class="fg">
                        <label>المسؤول</label>
                        <input v-model="form.contact_person" class="fi" type="text" placeholder="اسم المسؤول" />
                    </div>
                    <div class="fg col-span-2">
                        <label>البريد الإلكتروني</label>
                        <input v-model="form.email" :class="['fi', form.errors.email && 'fi-err']" type="email" placeholder="info@insurance.sa" />
                        <p v-if="form.errors.email" class="form-err-msg">{{ form.errors.email }}</p>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <p class="form-section-title">الشروط المالية</p>
                <div class="grid grid-cols-3 gap-3">
                    <div class="fg">
                        <label>التغطية %</label>
                        <div class="relative">
                            <input v-model.number="form.coverage_pct" class="fi pl-7" type="number" min="0" max="100" step="0.01" />
                            <span class="pct-badge text-hospital-primary">%</span>
                        </div>
                    </div>
                    <div class="fg">
                        <label>الخصم %</label>
                        <div class="relative">
                            <input v-model.number="form.disc_pct" class="fi pl-7" type="number" min="0" max="100" step="0.01" />
                            <span class="pct-badge text-hospital-text-3">%</span>
                        </div>
                    </div>
                    <div class="fg">
                        <label>الحالة</label>
                        <select v-model="form.status" class="fi">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="fg">
                <label>ملاحظات</label>
                <textarea v-model="form.notes" class="fi resize-none" rows="2" placeholder="أي ملاحظات إضافية..." />
            </div>

            <div class="form-footer">
                <button type="button" class="fbtn-secondary" @click="close">إلغاء</button>
                <button type="submit" class="fbtn-primary" :disabled="form.processing">
                    {{ form.processing ? 'جارٍ الحفظ...' : editingCompany ? 'حفظ التعديلات' : 'إضافة الشركة' }}
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
.form-section-title { display:flex; align-items:center; gap:6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:var(--color-hospital-text-3); margin-bottom:2px }
.form-err-msg { font-size:11px; color:var(--color-hospital-danger); margin-top:2px }
.pct-badge { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; font-weight:800; pointer-events:none }
.form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; border-top:1.5px solid var(--color-hospital-border) }
.fbtn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; font-family:inherit; background:var(--color-hospital-primary); color:#fff; border:none; cursor:pointer; transition:background .15s }
.fbtn-primary:hover { background:var(--color-hospital-primary-light) }
.fbtn-primary:disabled { opacity:.6; cursor:not-allowed }
.fbtn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; font-family:inherit; background:#fff; color:var(--color-hospital-text-2); border:1.5px solid var(--color-hospital-border); cursor:pointer; transition:background .15s }
.fbtn-secondary:hover { background:var(--color-hospital-bg) }
</style>
