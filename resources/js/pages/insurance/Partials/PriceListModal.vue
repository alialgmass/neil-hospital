<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { FileText, Plus, Trash2 } from 'lucide-vue-next'
import Modal from '@/components/shared/Modal.vue'

interface Company { id: string; name: string; coverage_pct: number }
interface ServiceItem { id: string; name: string; dept: string; price: number; ins_price: number }

const props = defineProps<{
    modelValue: boolean
    companies: Company[]
    services: ServiceItem[]
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'success'): void
}>()

const form = useForm({
    name: '',
    type: 'insurance' as 'cash' | 'insurance' | 'vip' | 'special',
    ins_company_id: '',
    ins_coverage: 80,
    discount_pct: 0,
    notes: '',
    items: [] as { service_id: string; price: number }[],
})

function addRow() {
    form.items.push({ service_id: '', price: 0 })
}

function removeRow(idx: number) {
    form.items.splice(idx, 1)
}

function onServiceSelect(idx: number) {
    const svc = props.services.find((s) => s.id === form.items[idx].service_id)

    if (svc) {
 form.items[idx].price = svc.ins_price || svc.price 
}
}

function close() {
    emit('update:modelValue', false)
    form.reset()
    form.clearErrors()
}

function submit() {
    form.post('/insurance/price-lists', {
        onSuccess: () => {
 close(); emit('success') 
},
    })
}
</script>

<template>
    <Modal :model-value="modelValue" title="إنشاء قائمة أسعار" size="lg" @update:model-value="emit('update:modelValue', $event)" @close="close">
        <form class="space-y-4" @submit.prevent="submit">
            <!-- Basic Info -->
            <div class="form-section">
                <p class="form-section-title">
                    <FileText class="h-3.5 w-3.5" />
                    بيانات القائمة
                </p>
                <div class="fg">
                    <label>اسم القائمة <span class="text-hospital-danger">*</span></label>
                    <input v-model="form.name" :class="['fi', form.errors.name && 'fi-err']" type="text" placeholder="مثال: قائمة شركة الراجحي 2024" required />
                    <p v-if="form.errors.name" class="form-err-msg">{{ form.errors.name }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg">
                        <label>نوع القائمة</label>
                        <select v-model="form.type" class="fi">
                            <option value="cash">نقدي</option>
                            <option value="insurance">تأمين</option>
                            <option value="vip">VIP</option>
                            <option value="special">خاص</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>نسبة الخصم %</label>
                        <div class="relative">
                            <input v-model.number="form.discount_pct" class="fi pl-7" type="number" min="0" max="100" step="0.01" />
                            <span class="pct-badge text-hospital-text-3">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Insurance fields -->
            <div v-if="form.type === 'insurance'" class="form-section">
                <p class="form-section-title">إعدادات التأمين</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="fg col-span-2">
                        <label>شركة التأمين</label>
                        <select v-model="form.ins_company_id" class="fi">
                            <option value="">— اختر شركة —</option>
                            <option v-for="co in companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>نسبة التغطية %</label>
                        <div class="relative">
                            <input v-model.number="form.ins_coverage" class="fi pl-7" type="number" min="0" max="100" step="0.01" />
                            <span class="pct-badge text-hospital-primary">%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services table -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-hospital-text-3">الخدمات وأسعارها</p>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 rounded-lg border border-hospital-primary/30 bg-hospital-primary-pale px-3 py-1.5 text-xs font-semibold text-hospital-primary hover:bg-hospital-primary/10 transition-colors"
                        @click="addRow"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        إضافة خدمة
                    </button>
                </div>

                <div v-if="form.items.length === 0" class="rounded-xl border border-dashed border-hospital-border bg-gray-50/60 py-8 text-center text-sm text-hospital-text-3">
                    لا توجد خدمات — اضغط "إضافة خدمة" لبدء التسعير
                </div>

                <div v-else class="overflow-hidden rounded-xl border border-hospital-border">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الخدمة</th>
                                <th class="w-36 px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">السعر (ج)</th>
                                <th class="w-10 px-3 py-2.5" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hospital-border">
                            <tr v-for="(item, idx) in form.items" :key="idx" class="bg-white">
                                <td class="px-3 py-2">
                                    <select v-model="item.service_id" class="fi" @change="onServiceSelect(idx)">
                                        <option value="">— اختر خدمة —</option>
                                        <option v-for="svc in services" :key="svc.id" :value="svc.id">{{ svc.name }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model.number="item.price" class="fi text-center tabular-nums" type="number" min="0" step="0.01" placeholder="0.00" />
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button
                                        type="button"
                                        class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-danger-pale hover:text-hospital-danger"
                                        @click="removeRow(idx)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-footer">
                <button type="button" class="fbtn-secondary" @click="close">إلغاء</button>
                <button type="submit" class="fbtn-primary" :disabled="form.processing">
                    {{ form.processing ? 'جارٍ الحفظ...' : 'إنشاء القائمة' }}
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
.form-section-title { display:flex; align-items:center; gap:6px; font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:var(--color-hospital-primary) }
.form-err-msg { font-size:11px; color:var(--color-hospital-danger); margin-top:2px }
.pct-badge { position:absolute; left:10px; top:50%; transform:translateY(-50%); font-size:11px; font-weight:800; pointer-events:none }
.form-footer { display:flex; justify-content:flex-end; gap:8px; padding-top:16px; border-top:1.5px solid var(--color-hospital-border) }
.fbtn-primary { display:inline-flex; align-items:center; gap:6px; padding:8px 20px; border-radius:8px; font-size:13px; font-weight:600; font-family:inherit; background:var(--color-hospital-primary); color:#fff; border:none; cursor:pointer; transition:background .15s }
.fbtn-primary:hover { background:var(--color-hospital-primary-light) }
.fbtn-primary:disabled { opacity:.6; cursor:not-allowed }
.fbtn-secondary { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:8px; font-size:13px; font-weight:500; font-family:inherit; background:#fff; color:var(--color-hospital-text-2); border:1.5px solid var(--color-hospital-border); cursor:pointer; transition:background .15s }
.fbtn-secondary:hover { background:var(--color-hospital-bg) }
</style>
