<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Edit2, Trash2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'
import Modal from '@/components/shared/Modal.vue'

defineOptions({ layout: AppLayout })

interface Company {
    id: string
    name: string
    code?: string
    phone?: string
    coverage_pct: number
    disc_pct: number
    contact_person?: string
    email?: string
    status: 'active' | 'inactive'
    contract_no?: string
}

interface Claim {
    id: number
    insurance_company_id: string
    booking_id?: string
    service_id?: string
    patient_name: string
    file_no?: string
    service_name: string
    invoice_amount: number
    discount: number
    patient_share: number
    insurance_share: number
    approved_amount: number
    paid_amount: number
    status: 'draft' | 'submitted' | 'approved' | 'rejected' | 'paid'
    service_date: string
    claim_date: string
    claim_reference?: string
    rejection_reason?: string
    notes?: string
    company?: { id: string; name: string }
    service?: { id: string; name: string }
}

const props = defineProps<{
    companies: { data: Company[]; links: unknown[] }
    claims: { data: Claim[]; links: unknown[] }
    filters: { search?: string; company_id?: string }
    stats: { monthly_claims_count: number; monthly_claims_total: number }
}>()

const activeTab = ref<'companies' | 'pricelists' | 'contracts' | 'claims' | 'approved'>('companies')
const showModal = ref(false)
const showClaimModal = ref(false)
const showStatusModal = ref(false)
const editingCompany = ref<Company | null>(null)
const editingClaim = ref<Claim | null>(null)
const deletingCompanyId = ref<string | null>(null)
const showDeleteModal = ref(false)

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

const claimForm = useForm({
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

const statusForm = useForm({
    status: 'draft' as Claim['status'],
    approved_amount: 0,
    paid_amount: 0,
    rejection_reason: '',
    submission_date: '',
    approval_date: '',
    payment_date: '',
    notes: '',
})

const searchQuery = ref(props.filters.search ?? '')
const claimsCompanyFilter = ref(props.filters.company_id ?? '')

const activeCount = computed(() => props.companies.data.filter((c) => c.status === 'active').length)

const claimStatusLabels: Record<string, string> = {
    draft: 'مسودة',
    submitted: 'مُرسلة',
    approved: 'معتمدة',
    rejected: 'مرفوضة',
    paid: 'مسددة',
}

const claimStatusVariants: Record<string, string> = {
    draft: 'inactive',
    submitted: 'active',
    approved: 'active',
    rejected: 'cancelled',
    paid: 'active',
}

function search() {
    router.get('/insurance', { search: searchQuery.value }, { preserveState: true, replace: true })
}

function filterClaims() {
    router.get('/insurance', { company_id: claimsCompanyFilter.value }, { preserveState: true, replace: true })
}

function openCreate() {
    editingCompany.value = null
    form.reset()
    form.clearErrors()
    showModal.value = true
}

function openEdit(company: Company) {
    editingCompany.value = company
    form.name = company.name
    form.code = company.code ?? ''
    form.phone = company.phone ?? ''
    form.coverage_pct = company.coverage_pct
    form.disc_pct = company.disc_pct
    form.contact_person = company.contact_person ?? ''
    form.email = company.email ?? ''
    form.status = company.status
    form.contract_no = company.contract_no ?? ''
    form.clearErrors()
    showModal.value = true
}

function closeModal() {
    showModal.value = false
    form.reset()
    form.clearErrors()
}

function submit() {
    if (editingCompany.value) {
        form.put(`/insurance/${editingCompany.value.id}`, { onSuccess: closeModal })
    } else {
        form.post('/insurance', { onSuccess: closeModal })
    }
}

function confirmDelete(id: string) {
    deletingCompanyId.value = id
    showDeleteModal.value = true
}

function deleteCompany() {
    if (!deletingCompanyId.value) {
return
}

    router.delete(`/insurance/${deletingCompanyId.value}`, {
        onSuccess: () => {
 showDeleteModal.value = false; deletingCompanyId.value = null 
},
    })
}

function openCreateClaim() {
    editingClaim.value = null
    claimForm.reset()
    claimForm.clearErrors()
    showClaimModal.value = true
}

function closeClaimModal() {
    showClaimModal.value = false
    claimForm.reset()
    claimForm.clearErrors()
}

function submitClaim() {
    claimForm.post('/insurance/claims', { onSuccess: closeClaimModal })
}

function openStatusModal(claim: Claim) {
    editingClaim.value = claim
    statusForm.status = claim.status
    statusForm.approved_amount = claim.approved_amount
    statusForm.paid_amount = claim.paid_amount
    statusForm.rejection_reason = claim.rejection_reason ?? ''
    statusForm.submission_date = claim.submission_date ?? ''
    statusForm.approval_date = claim.approval_date ?? ''
    statusForm.payment_date = claim.payment_date ?? ''
    statusForm.notes = claim.notes ?? ''
    showStatusModal.value = true
}

function submitStatus() {
    if (!editingClaim.value) {
return
}

    statusForm.put(`/insurance/claims/${editingClaim.value.id}`, {
        onSuccess: () => {
 showStatusModal.value = false 
},
    })
}

function deleteClaim(id: number) {
    if (!confirm('هل تريد حذف هذه المطالبة؟')) {
return
}

    router.delete(`/insurance/claims/${id}`)
}

const tabs = [
    { key: 'companies', label: 'شركات التأمين' },
    { key: 'pricelists', label: 'قوائم الأسعار' },
    { key: 'contracts', label: 'العقود' },
    { key: 'claims', label: 'المطالبات' },
    { key: 'approved', label: 'الخدمات المعتمدة' },
] as const
</script>

<template>
    <div class="p-6">
        <!-- Stats Row -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4">
                <div class="text-xs font-semibold text-blue-600">شركات التأمين</div>
                <div class="mt-1 text-2xl font-bold text-blue-700">{{ companies.data.length }}</div>
                <div class="mt-0.5 text-xs text-blue-500">شركة مسجلة</div>
            </div>
            <div class="rounded-xl border border-green-100 bg-green-50 p-4">
                <div class="text-xs font-semibold text-green-600">عقود نشطة</div>
                <div class="mt-1 text-2xl font-bold text-green-700">{{ activeCount }}</div>
                <div class="mt-0.5 text-xs text-green-500">سارية</div>
            </div>
            <div class="rounded-xl border border-teal-100 bg-teal-50 p-4">
                <div class="text-xs font-semibold text-teal-600">مطالبات هذا الشهر</div>
                <div class="mt-1 text-2xl font-bold text-teal-700">{{ stats.monthly_claims_count }}</div>
                <div class="mt-0.5 text-xs text-teal-500">مطالبة</div>
            </div>
            <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                <div class="text-xs font-semibold text-orange-600">إجمالي المطالبات (ج)</div>
                <div class="mt-1 text-2xl font-bold text-orange-700">{{ stats.monthly_claims_total.toLocaleString('ar-EG') }}</div>
                <div class="mt-0.5 text-xs text-orange-500">↑ هذا الشهر</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-5 flex gap-1 border-b border-gray-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="px-4 py-2 text-sm font-medium transition-colors"
                :class="activeTab === tab.key ? 'border-b-2 border-hospital-primary text-hospital-primary' : 'text-gray-500 hover:text-gray-700'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Companies Tab -->
        <div v-if="activeTab === 'companies'">
            <div class="mb-4 flex items-center justify-between gap-3">
                <input
                    v-model="searchQuery"
                    class="w-72 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none"
                    type="text"
                    placeholder="بحث باسم الشركة..."
                    @input="search"
                />
                <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="openCreate">
                    + شركة جديدة
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الشركة</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الكود</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الهاتف</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم العقد</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">التغطية %</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الخصم %</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالة</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="company in companies.data" :key="company.id" class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ company.name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ company.code || '—' }}</td>
                            <td class="px-4 py-3">{{ company.phone || '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ company.contract_no || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-blue-700">{{ company.coverage_pct }}%</span>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ company.disc_pct }}%</td>
                            <td class="px-4 py-3">
                                <Badge :variant="company.status === 'active' ? 'active' : 'cancelled'">
                                    {{ company.status === 'active' ? 'نشط' : 'غير نشط' }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="text-gray-400 hover:text-blue-600" @click="openEdit(company)">
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button class="text-gray-400 hover:text-red-500" @click="confirmDelete(company.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="companies.data.length === 0">
                            <td class="px-4 py-8 text-center text-gray-400" colspan="8">لا توجد شركات تأمين</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Price Lists Tab -->
        <div v-else-if="activeTab === 'pricelists'">
            <div class="mb-4 flex justify-end">
                <a href="/insurance/price-lists" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90">
                    إدارة قوائم الأسعار
                </a>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-400 shadow-sm">
                <p class="mb-3 text-4xl">📋</p>
                <p class="font-medium">قوائم الأسعار المرتبطة بشركات التأمين</p>
                <p class="mt-1 text-sm">يمكنك إدارة قوائم الأسعار من الصفحة المخصصة لها</p>
            </div>
        </div>

        <!-- Contracts Tab -->
        <div v-else-if="activeTab === 'contracts'">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الشركة</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">رقم العقد</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">تاريخ البداية</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">تاريخ الانتهاء</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-4 py-8 text-center text-gray-400" colspan="5">لا توجد عقود مسجلة</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Claims Tab -->
        <div v-else-if="activeTab === 'claims'">
            <div class="mb-4 flex items-center justify-between gap-3">
                <select v-model="claimsCompanyFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none" @change="filterClaims">
                    <option value="">كل الشركات</option>
                    <option v-for="c in companies.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90" @click="openCreateClaim">
                    + مطالبة جديدة
                </button>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-4 py-3 font-semibold text-gray-700">سجل المطالبات</div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الشركة</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">المريض</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الخدمة</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">السعر الكلي</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">يتحمله التأمين</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">يتحمله المريض</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">التاريخ</th>
                            <th class="px-4 py-3 text-right font-semibold text-gray-600">الحالة</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="claim in claims.data" :key="claim.id" class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium">{{ claim.company?.name || '—' }}</td>
                            <td class="px-4 py-3">
                                <div>{{ claim.patient_name }}</div>
                                <div v-if="claim.file_no" class="text-xs text-gray-400">{{ claim.file_no }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ claim.service_name }}</td>
                            <td class="px-4 py-3 font-medium">{{ claim.invoice_amount.toFixed(2) }} ج</td>
                            <td class="px-4 py-3 text-blue-700">{{ claim.insurance_share.toFixed(2) }} ج</td>
                            <td class="px-4 py-3 text-orange-700">{{ claim.patient_share.toFixed(2) }} ج</td>
                            <td class="px-4 py-3 text-gray-500">{{ claim.service_date }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="claimStatusVariants[claim.status] as any">
                                    {{ claimStatusLabels[claim.status] }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button class="text-gray-400 hover:text-blue-600" @click="openStatusModal(claim)">
                                        <Edit2 class="h-4 w-4" />
                                    </button>
                                    <button v-if="claim.status === 'draft'" class="text-gray-400 hover:text-red-500" @click="deleteClaim(claim.id)">
                                        <Trash2 class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="claims.data.length === 0">
                            <td class="px-4 py-8 text-center text-gray-400" colspan="9">لا توجد مطالبات</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Approved Services Tab -->
        <div v-else-if="activeTab === 'approved'">
            <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                ℹ️ الخدمات المعتمدة هي الخدمات التي توافق عليها كل شركة تأمين ويتم صرفها مباشرة على حساب التأمين.
            </div>
            <div v-for="company in companies.data.filter((c) => c.status === 'active')" :key="company.id" class="mb-4">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                        <span class="font-semibold text-gray-700">{{ company.name }}</span>
                        <span class="text-xs text-gray-400">تغطية {{ company.coverage_pct }}%</span>
                    </div>
                    <div class="px-4 py-6 text-center text-sm text-gray-400">لم يتم تحديد خدمات معتمدة بعد</div>
                </div>
            </div>
            <div v-if="companies.data.filter((c) => c.status === 'active').length === 0" class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-400">
                لا توجد شركات تأمين نشطة
            </div>
        </div>

        <!-- Create / Edit Company Modal -->
        <Modal v-model="showModal" :title="editingCompany ? 'تعديل شركة التأمين' : 'إضافة شركة تأمين'" @close="closeModal">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">اسم الشركة *</label>
                        <input v-model="form.name" :class="['input-field', form.errors.name && 'border-red-400']" type="text" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الكود</label>
                        <input v-model="form.code" :class="['input-field', form.errors.code && 'border-red-400']" type="text" />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-red-500">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">رقم العقد</label>
                        <input v-model="form.contract_no" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الهاتف</label>
                        <input v-model="form.phone" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">المسؤول</label>
                        <input v-model="form.contact_person" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نسبة التغطية %</label>
                        <input v-model.number="form.coverage_pct" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نسبة الخصم %</label>
                        <input v-model.number="form.disc_pct" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الحالة</label>
                        <select v-model="form.status" class="input-field">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">البريد الإلكتروني</label>
                        <input v-model="form.email" :class="['input-field', form.errors.email && 'border-red-400']" type="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                        <textarea v-model="form.notes" class="input-field" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="closeModal">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal v-model="showDeleteModal" title="تأكيد الحذف" size="sm" @close="showDeleteModal = false">
            <p class="text-sm text-gray-600">هل أنت متأكد من حذف هذه الشركة؟ سيتم حذف جميع البيانات المرتبطة بها.</p>
            <div class="mt-4 flex justify-end gap-3">
                <button class="btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700" @click="deleteCompany">
                    حذف
                </button>
            </div>
        </Modal>

        <!-- New Claim Modal -->
        <Modal v-model="showClaimModal" title="إنشاء مطالبة جديدة" size="lg" @close="closeClaimModal">
            <form class="space-y-4" @submit.prevent="submitClaim">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">شركة التأمين *</label>
                        <select v-model="claimForm.insurance_company_id" :class="['input-field', claimForm.errors.insurance_company_id && 'border-red-400']" required>
                            <option value="">— اختر الشركة —</option>
                            <option v-for="c in companies.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="claimForm.errors.insurance_company_id" class="mt-1 text-xs text-red-500">{{ claimForm.errors.insurance_company_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">اسم المريض *</label>
                        <input v-model="claimForm.patient_name" :class="['input-field', claimForm.errors.patient_name && 'border-red-400']" type="text" required />
                        <p v-if="claimForm.errors.patient_name" class="mt-1 text-xs text-red-500">{{ claimForm.errors.patient_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">رقم الملف</label>
                        <input v-model="claimForm.file_no" class="input-field" type="text" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">الخدمة *</label>
                        <input v-model="claimForm.service_name" :class="['input-field', claimForm.errors.service_name && 'border-red-400']" type="text" required />
                        <p v-if="claimForm.errors.service_name" class="mt-1 text-xs text-red-500">{{ claimForm.errors.service_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">إجمالي الفاتورة (ج) *</label>
                        <input v-model.number="claimForm.invoice_amount" :class="['input-field', claimForm.errors.invoice_amount && 'border-red-400']" type="number" min="0" step="0.01" required />
                        <p v-if="claimForm.errors.invoice_amount" class="mt-1 text-xs text-red-500">{{ claimForm.errors.invoice_amount }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الخصم (ج)</label>
                        <input v-model.number="claimForm.discount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">حصة التأمين (ج)</label>
                        <input v-model.number="claimForm.insurance_share" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">حصة المريض (ج)</label>
                        <input v-model.number="claimForm.patient_share" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">تاريخ الخدمة *</label>
                        <input v-model="claimForm.service_date" class="input-field" type="date" required />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                        <textarea v-model="claimForm.notes" class="input-field" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="closeClaimModal">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="claimForm.processing">
                        {{ claimForm.processing ? 'جارٍ الحفظ...' : 'إنشاء المطالبة' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Claim Status Modal -->
        <Modal v-model="showStatusModal" title="تحديث حالة المطالبة" @close="showStatusModal = false">
            <form class="space-y-4" @submit.prevent="submitStatus">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">الحالة *</label>
                        <select v-model="statusForm.status" class="input-field">
                            <option value="draft">مسودة</option>
                            <option value="submitted">مُرسلة</option>
                            <option value="approved">معتمدة</option>
                            <option value="rejected">مرفوضة</option>
                            <option value="paid">مسددة</option>
                        </select>
                    </div>
                    <div v-if="statusForm.status === 'submitted'">
                        <label class="mb-1 block text-sm font-medium">تاريخ الإرسال</label>
                        <input v-model="statusForm.submission_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'approved' || statusForm.status === 'paid'">
                        <label class="mb-1 block text-sm font-medium">المبلغ المعتمد (ج)</label>
                        <input v-model.number="statusForm.approved_amount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="statusForm.status === 'approved'">
                        <label class="mb-1 block text-sm font-medium">تاريخ الاعتماد</label>
                        <input v-model="statusForm.approval_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'paid'">
                        <label class="mb-1 block text-sm font-medium">المبلغ المسدد (ج)</label>
                        <input v-model.number="statusForm.paid_amount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="statusForm.status === 'paid'">
                        <label class="mb-1 block text-sm font-medium">تاريخ السداد</label>
                        <input v-model="statusForm.payment_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'rejected'" class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">سبب الرفض</label>
                        <textarea v-model="statusForm.rejection_reason" class="input-field" rows="2" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                        <textarea v-model="statusForm.notes" class="input-field" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" @click="showStatusModal = false">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="statusForm.processing">
                        {{ statusForm.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
