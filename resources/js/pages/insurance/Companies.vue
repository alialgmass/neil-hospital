<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { Building2, Edit2, FileText, Plus, Shield, Trash2, TrendingUp } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'
import Modal from '@/components/shared/Modal.vue'
import StatCard from '@/components/shared/StatCard.vue'

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
    submitted: 'info',
    approved: 'success',
    rejected: 'danger',
    paid: 'paid',
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
            showDeleteModal.value = false
            deletingCompanyId.value = null
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
    statusForm.submission_date = (claim as any).submission_date ?? ''
    statusForm.approval_date = (claim as any).approval_date ?? ''
    statusForm.payment_date = (claim as any).payment_date ?? ''
    statusForm.notes = claim.notes ?? ''
    showStatusModal.value = true
}

function submitStatus() {
    if (!editingClaim.value) {
        return
    }

    statusForm.put(`/insurance/claims/${editingClaim.value.id}`, {
        onSuccess: () => { showStatusModal.value = false },
    })
}

function deleteClaim(id: number) {
    if (!confirm('هل تريد حذف هذه المطالبة؟')) {
        return
    }

    router.delete(`/insurance/claims/${id}`)
}

function companyInitials(name: string): string {
    return name.trim().charAt(0)
}

const tabs = [
    { key: 'companies', label: 'شركات التأمين', icon: Building2 },
    { key: 'pricelists', label: 'قوائم الأسعار', icon: FileText },
    { key: 'contracts', label: 'العقود', icon: Shield },
    { key: 'claims', label: 'المطالبات', icon: TrendingUp },
] as const
</script>

<template>
    <div class="space-y-6 p-6">
        <!-- Stats Row -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <StatCard
                label="شركات التأمين"
                :value="companies.data.length"
                :icon="Building2"
                color="primary"
                change="شركة مسجلة"
            />
            <StatCard
                label="عقود نشطة"
                :value="activeCount"
                :icon="Shield"
                color="success"
                change="سارية المفعول"
                :change-positive="true"
            />
            <StatCard
                label="مطالبات هذا الشهر"
                :value="stats.monthly_claims_count"
                :icon="FileText"
                color="accent"
                change="هذا الشهر"
            />
            <StatCard
                label="إجمالي المطالبات"
                :value="stats.monthly_claims_total.toLocaleString('ar-EG') + ' ج'"
                :icon="TrendingUp"
                color="warning"
                change="↑ هذا الشهر"
                :change-positive="true"
            />
        </div>

        <!-- Main Card -->
        <div class="overflow-hidden rounded-2xl border border-hospital-border bg-hospital-surface shadow-sm">
            <!-- Tab Bar -->
            <div class="flex border-b border-hospital-border bg-gray-50/60 px-4">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors"
                    :class="activeTab === tab.key
                        ? 'border-hospital-primary text-hospital-primary'
                        : 'border-transparent text-hospital-text-3 hover:text-hospital-text'"
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                    <span
                        v-if="tab.key === 'companies'"
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                        :class="activeTab === 'companies' ? 'bg-hospital-primary text-white' : 'bg-gray-200 text-gray-500'"
                    >
                        {{ companies.data.length }}
                    </span>
                    <span
                        v-if="tab.key === 'claims'"
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                        :class="activeTab === 'claims' ? 'bg-hospital-primary text-white' : 'bg-gray-200 text-gray-500'"
                    >
                        {{ claims.data.length }}
                    </span>
                </button>
            </div>

            <!-- Companies Tab -->
            <div v-if="activeTab === 'companies'" class="p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="relative w-72">
                        <input
                            v-model="searchQuery"
                            class="w-full rounded-lg border border-hospital-border bg-white py-2 pl-3 pr-9 text-sm text-hospital-text placeholder:text-hospital-text-3 focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                            type="text"
                            placeholder="بحث باسم الشركة..."
                            @input="search"
                        />
                        <svg class="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-hospital-text-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <button
                        class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90 active:scale-95"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        شركة جديدة
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-hospital-border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-hospital-border bg-gray-50/80">
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الشركة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الكود / العقد</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الهاتف</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">التغطية</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الخصم</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الحالة</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hospital-border">
                            <tr
                                v-for="company in companies.data"
                                :key="company.id"
                                class="group bg-white transition-colors hover:bg-hospital-primary/5"
                            >
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-hospital-primary-pale text-sm font-bold text-hospital-primary">
                                            {{ companyInitials(company.name) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-hospital-text">{{ company.name }}</div>
                                            <div v-if="company.contact_person" class="text-xs text-hospital-text-3">{{ company.contact_person }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs font-mono text-hospital-text-3">{{ company.code || '—' }}</div>
                                    <div class="text-xs text-hospital-text-3">{{ company.contract_no || '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-hospital-text-3">{{ company.phone || '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-2 py-0.5 text-sm font-bold text-blue-700">
                                        {{ company.coverage_pct }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-sm text-hospital-text-3">{{ company.disc_pct }}%</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="company.status === 'active' ? 'active' : 'inactive'" />
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                                            title="تعديل"
                                            @click="openEdit(company)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </button>
                                        <button
                                            class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-danger-pale hover:text-hospital-danger"
                                            title="حذف"
                                            @click="confirmDelete(company.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="companies.data.length === 0">
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <Building2 class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                    <p class="font-medium text-gray-400">لا توجد شركات تأمين</p>
                                    <p class="mt-1 text-sm text-gray-300">ابدأ بإضافة شركة جديدة</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Price Lists Tab -->
            <div v-else-if="activeTab === 'pricelists'" class="p-5">
                <div class="mb-4 flex justify-end">
                    <a
                        href="/insurance/price-lists"
                        class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90"
                    >
                        <FileText class="h-4 w-4" />
                        إدارة قوائم الأسعار
                    </a>
                </div>
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-hospital-border bg-gray-50/60 py-16">
                    <FileText class="mb-4 h-12 w-12 text-gray-300" />
                    <p class="font-semibold text-gray-400">قوائم الأسعار</p>
                    <p class="mt-1 text-sm text-gray-300">يمكنك إدارة قوائم الأسعار من الصفحة المخصصة لها</p>
                </div>
            </div>

            <!-- Contracts Tab -->
            <div v-else-if="activeTab === 'contracts'" class="p-5">
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-hospital-border bg-gray-50/60 py-16">
                    <Shield class="mb-4 h-12 w-12 text-gray-300" />
                    <p class="font-semibold text-gray-400">لا توجد عقود مسجلة</p>
                    <p class="mt-1 text-sm text-gray-300">سيتم إضافة إدارة العقود قريباً</p>
                </div>
            </div>

            <!-- Claims Tab -->
            <div v-else-if="activeTab === 'claims'" class="p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <select
                        v-model="claimsCompanyFilter"
                        class="rounded-lg border border-hospital-border bg-white px-3 py-2 text-sm text-hospital-text focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
                        @change="filterClaims"
                    >
                        <option value="">كل الشركات</option>
                        <option v-for="c in companies.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <button
                        class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90 active:scale-95"
                        @click="openCreateClaim"
                    >
                        <Plus class="h-4 w-4" />
                        مطالبة جديدة
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl border border-hospital-border">
                    <div class="border-b border-hospital-border bg-gray-50/80 px-4 py-2.5">
                        <span class="text-xs font-semibold uppercase tracking-wide text-hospital-text-3">سجل المطالبات</span>
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-hospital-border bg-gray-50/40">
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الشركة</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المريض</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الخدمة</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الفاتورة</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">التأمين</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المريض</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">التاريخ</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الحالة</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hospital-border">
                            <tr
                                v-for="claim in claims.data"
                                :key="claim.id"
                                class="group bg-white transition-colors hover:bg-hospital-primary/5"
                            >
                                <td class="px-4 py-3">
                                    <span class="font-medium text-hospital-text">{{ claim.company?.name || '—' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-hospital-text">{{ claim.patient_name }}</div>
                                    <div v-if="claim.file_no" class="font-mono text-xs text-hospital-text-3">{{ claim.file_no }}</div>
                                </td>
                                <td class="px-4 py-3 text-hospital-text-3">{{ claim.service_name }}</td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-hospital-text">
                                    {{ claim.invoice_amount.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3">ج</span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-hospital-primary">
                                    {{ claim.insurance_share.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3">ج</span>
                                </td>
                                <td class="px-4 py-3 text-center font-semibold tabular-nums text-hospital-warning">
                                    {{ claim.patient_share.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3">ج</span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs tabular-nums text-hospital-text-3">{{ claim.service_date }}</td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="claimStatusVariants[claim.status] as any">
                                        {{ claimStatusLabels[claim.status] }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button
                                            class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                                            title="تحديث الحالة"
                                            @click="openStatusModal(claim)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-if="claim.status === 'draft'"
                                            class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-danger-pale hover:text-hospital-danger"
                                            title="حذف"
                                            @click="deleteClaim(claim.id)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="claims.data.length === 0">
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <TrendingUp class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                                    <p class="font-medium text-gray-400">لا توجد مطالبات</p>
                                    <p class="mt-1 text-sm text-gray-300">ابدأ بإنشاء مطالبة جديدة</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create / Edit Company Modal -->
        <Modal v-model="showModal" :title="editingCompany ? 'تعديل شركة التأمين' : 'إضافة شركة تأمين'" @close="closeModal">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">اسم الشركة <span class="text-hospital-danger">*</span></label>
                        <input v-model="form.name" :class="['input-field', form.errors.name && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="text" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-hospital-danger">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الكود</label>
                        <input v-model="form.code" :class="['input-field', form.errors.code && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="text" />
                        <p v-if="form.errors.code" class="mt-1 text-xs text-hospital-danger">{{ form.errors.code }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">رقم العقد</label>
                        <input v-model="form.contract_no" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الهاتف</label>
                        <input v-model="form.phone" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المسؤول</label>
                        <input v-model="form.contact_person" class="input-field" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">نسبة التغطية %</label>
                        <input v-model.number="form.coverage_pct" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">نسبة الخصم %</label>
                        <input v-model.number="form.disc_pct" class="input-field" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الحالة</label>
                        <select v-model="form.status" class="input-field">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">البريد الإلكتروني</label>
                        <input v-model="form.email" :class="['input-field', form.errors.email && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="email" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-hospital-danger">{{ form.errors.email }}</p>
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">ملاحظات</label>
                        <textarea v-model="form.notes" class="input-field resize-none" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-hospital-border pt-4">
                    <button type="button" class="btn-secondary" @click="closeModal">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : editingCompany ? 'حفظ التعديلات' : 'إضافة الشركة' }}
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal v-model="showDeleteModal" title="تأكيد الحذف" size="sm" @close="showDeleteModal = false">
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
                <button class="btn-secondary" @click="showDeleteModal = false">إلغاء</button>
                <button class="flex items-center gap-2 rounded-lg bg-hospital-danger px-4 py-2 text-sm font-medium text-white hover:bg-hospital-danger/90" @click="deleteCompany">
                    <Trash2 class="h-4 w-4" />
                    تأكيد الحذف
                </button>
            </div>
        </Modal>

        <!-- New Claim Modal -->
        <Modal v-model="showClaimModal" title="إنشاء مطالبة جديدة" size="lg" @close="closeClaimModal">
            <form class="space-y-4" @submit.prevent="submitClaim">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">شركة التأمين <span class="text-hospital-danger">*</span></label>
                        <select v-model="claimForm.insurance_company_id" :class="['input-field', claimForm.errors.insurance_company_id && 'border-hospital-danger ring-1 ring-hospital-danger/30']" required>
                            <option value="">— اختر الشركة —</option>
                            <option v-for="c in companies.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="claimForm.errors.insurance_company_id" class="mt-1 text-xs text-hospital-danger">{{ claimForm.errors.insurance_company_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">اسم المريض <span class="text-hospital-danger">*</span></label>
                        <input v-model="claimForm.patient_name" :class="['input-field', claimForm.errors.patient_name && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="text" required />
                        <p v-if="claimForm.errors.patient_name" class="mt-1 text-xs text-hospital-danger">{{ claimForm.errors.patient_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">رقم الملف</label>
                        <input v-model="claimForm.file_no" class="input-field" type="text" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الخدمة <span class="text-hospital-danger">*</span></label>
                        <input v-model="claimForm.service_name" :class="['input-field', claimForm.errors.service_name && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="text" required />
                        <p v-if="claimForm.errors.service_name" class="mt-1 text-xs text-hospital-danger">{{ claimForm.errors.service_name }}</p>
                    </div>

                    <!-- Amount fields with dividing line -->
                    <div class="col-span-2 mt-1 border-t border-hospital-border pt-3">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المبالغ المالية</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs text-hospital-text-3">إجمالي الفاتورة (ج) <span class="text-hospital-danger">*</span></label>
                                <input v-model.number="claimForm.invoice_amount" :class="['input-field', claimForm.errors.invoice_amount && 'border-hospital-danger ring-1 ring-hospital-danger/30']" type="number" min="0" step="0.01" required />
                                <p v-if="claimForm.errors.invoice_amount" class="mt-1 text-xs text-hospital-danger">{{ claimForm.errors.invoice_amount }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-hospital-text-3">الخصم (ج)</label>
                                <input v-model.number="claimForm.discount" class="input-field" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-hospital-text-3">حصة التأمين (ج)</label>
                                <input v-model.number="claimForm.insurance_share" class="input-field" type="number" min="0" step="0.01" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs text-hospital-text-3">حصة المريض (ج)</label>
                                <input v-model.number="claimForm.patient_share" class="input-field" type="number" min="0" step="0.01" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">تاريخ الخدمة <span class="text-hospital-danger">*</span></label>
                        <input v-model="claimForm.service_date" class="input-field" type="date" required />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">ملاحظات</label>
                        <textarea v-model="claimForm.notes" class="input-field resize-none" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-hospital-border pt-4">
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
                <!-- Status selector with visual indicators -->
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الحالة الجديدة</label>
                    <div class="grid grid-cols-5 gap-2">
                        <button
                            v-for="(label, key) in claimStatusLabels"
                            :key="key"
                            type="button"
                            class="rounded-lg border px-2 py-2 text-center text-xs font-medium transition-all"
                            :class="statusForm.status === key
                                ? 'border-hospital-primary bg-hospital-primary text-white shadow-sm'
                                : 'border-hospital-border bg-white text-hospital-text-3 hover:border-hospital-primary/50 hover:text-hospital-primary'"
                            @click="statusForm.status = key as Claim['status']"
                        >
                            {{ label }}
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div v-if="statusForm.status === 'submitted'">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">تاريخ الإرسال</label>
                        <input v-model="statusForm.submission_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'approved' || statusForm.status === 'paid'">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المبلغ المعتمد (ج)</label>
                        <input v-model.number="statusForm.approved_amount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="statusForm.status === 'approved'">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">تاريخ الاعتماد</label>
                        <input v-model="statusForm.approval_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'paid'">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">المبلغ المسدد (ج)</label>
                        <input v-model.number="statusForm.paid_amount" class="input-field" type="number" min="0" step="0.01" />
                    </div>
                    <div v-if="statusForm.status === 'paid'">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">تاريخ السداد</label>
                        <input v-model="statusForm.payment_date" class="input-field" type="date" />
                    </div>
                    <div v-if="statusForm.status === 'rejected'" class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">سبب الرفض</label>
                        <textarea v-model="statusForm.rejection_reason" class="input-field resize-none" rows="2" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-hospital-text-3">ملاحظات</label>
                        <textarea v-model="statusForm.notes" class="input-field resize-none" rows="2" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-hospital-border pt-4">
                    <button type="button" class="btn-secondary" @click="showStatusModal = false">إلغاء</button>
                    <button type="submit" class="btn-primary" :disabled="statusForm.processing">
                        {{ statusForm.processing ? 'جارٍ الحفظ...' : 'حفظ التغييرات' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
