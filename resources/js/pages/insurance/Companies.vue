<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    Building2,
    Edit2,
    FileText,
    Plus,
    Shield,
    Trash2,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import Badge from '@/components/shared/Badge.vue';
import ModuleImportButton from '@/components/shared/ModuleImportButton.vue';
import StatCard from '@/components/shared/StatCard.vue';
import ClaimModal from './Partials/ClaimModal.vue';
import ClaimStatusModal from './Partials/ClaimStatusModal.vue';
import CompanyModal from './Partials/CompanyModal.vue';
import DeleteCompanyModal from './Partials/DeleteCompanyModal.vue';
import type { Claim, Company } from './Partials/types';
import { claimStatusLabels, claimStatusVariants } from './Partials/types';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    companies: { data: Company[]; links: unknown[] };
    claims: { data: Claim[]; links: unknown[] };
    filters: { search?: string; company_id?: string };
    claimFilters: {
        company_id?: string;
        from?: string;
        to?: string;
        service_id?: string;
        status?: string;
        dept?: string;
        doctor_id?: string;
        patient?: string;
    };
    stats: { monthly_claims_count: number; monthly_claims_total: number };
    doctors: { id: string; name: string }[];
    claimFilterServices: { id: string; name: string; dept: string }[];
}>();

const activeTab = ref<'companies' | 'pricelists' | 'contracts' | 'claims'>(
    'companies',
);

// Company modal
const showCompanyModal = ref(false);
const editingCompany = ref<Company | null>(null);

function openCreate() {
    editingCompany.value = null;
    showCompanyModal.value = true;
}
function openEdit(company: Company) {
    editingCompany.value = company;
    showCompanyModal.value = true;
}

// Delete modal
const showDeleteModal = ref(false);
const deletingCompanyId = ref<string | null>(null);

function confirmDelete(id: string) {
    deletingCompanyId.value = id;
    showDeleteModal.value = true;
}

// Claim modal
const showClaimModal = ref(false);

// Claim status modal
const showStatusModal = ref(false);
const editingClaim = ref<Claim | null>(null);

function openStatusModal(claim: Claim) {
    editingClaim.value = claim;
    showStatusModal.value = true;
}

function deleteClaim(id: number) {
    if (!confirm('هل تريد حذف هذه المطالبة؟')) {
        return;
    }

    router.delete(`/insurance/claims/${id}`);
}

// Search / filter
const searchQuery = ref(props.filters.search ?? '');

function search() {
    router.get(
        '/insurance',
        { search: searchQuery.value },
        { preserveState: true, replace: true },
    );
}

// Claims filters (combined AND)
const claimFilterState = ref({
    company_id: props.claimFilters.company_id ?? '',
    from: props.claimFilters.from ?? '',
    to: props.claimFilters.to ?? '',
    service_id: props.claimFilters.service_id ?? '',
    status: props.claimFilters.status ?? '',
    dept: props.claimFilters.dept ?? '',
    doctor_id: props.claimFilters.doctor_id ?? '',
    patient: props.claimFilters.patient ?? '',
});

function filterClaims() {
    router.get(
        '/insurance',
        Object.fromEntries(
            Object.entries(claimFilterState.value).filter(([, v]) => v),
        ),
        { preserveState: true, replace: true },
    );
}

function resetClaimFilters() {
    claimFilterState.value = {
        company_id: '',
        from: '',
        to: '',
        service_id: '',
        status: '',
        dept: '',
        doctor_id: '',
        patient: '',
    };
    filterClaims();
}

const exportMonth = ref(new Date().toISOString().slice(0, 7));

function exportClaimsForMonth() {
    const params = new URLSearchParams({ month: exportMonth.value });

    if (claimFilterState.value.company_id) {
        params.set('company_id', claimFilterState.value.company_id);
    }

    window.location.href = `/insurance/claims/export?${params.toString()}`;
}

const claimStatusOptions = Object.entries(claimStatusLabels);
const deptOptions: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
    pentacam: 'البنتكام',
};

const activeCount = computed(
    () => props.companies.data.filter((c) => c.status === 'active').length,
);

function companyInitials(name: string) {
    return name.trim().charAt(0);
}

const tabs = [
    {
        key: 'companies',
        label: 'شركات التأمين',
        icon: Building2,
        count: computed(() => props.companies.data.length),
    },
    { key: 'pricelists', label: 'قوائم الأسعار', icon: FileText, count: null },
    { key: 'contracts', label: 'العقود', icon: Shield, count: null },
    {
        key: 'claims',
        label: 'المطالبات',
        icon: TrendingUp,
        count: computed(() => props.claims.data.length),
    },
] as const;
</script>

<template>
    <div class="space-y-6 p-6">
        <!-- Stats -->
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
                :value="
                    stats.monthly_claims_total.toLocaleString('en-US') + ' ج'
                "
                :icon="TrendingUp"
                color="warning"
                change="↑ هذا الشهر"
                :change-positive="true"
            />
        </div>

        <!-- Main Card -->
        <div
            class="overflow-hidden rounded-2xl border border-hospital-border bg-hospital-surface shadow-sm"
        >
            <!-- Tab Bar -->
            <div
                class="flex border-b border-hospital-border bg-gray-50/60 px-4"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex items-center gap-2 border-b-2 px-4 py-3.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'border-hospital-primary text-hospital-primary'
                            : 'border-transparent text-hospital-text-3 hover:text-hospital-text'
                    "
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                    <span
                        v-if="tab.count !== null"
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                        :class="
                            activeTab === tab.key
                                ? 'bg-hospital-primary text-white'
                                : 'bg-gray-200 text-gray-500'
                        "
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <!-- ── Companies Tab ── -->
            <div v-if="activeTab === 'companies'" class="p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div class="relative w-72">
                        <input
                            v-model="searchQuery"
                            class="w-full rounded-lg border border-hospital-border bg-white py-2 pr-9 pl-3 text-sm text-hospital-text placeholder:text-hospital-text-3 focus:border-hospital-primary focus:ring-2 focus:ring-hospital-primary/20 focus:outline-none"
                            type="text"
                            placeholder="بحث باسم الشركة..."
                            @input="search"
                        />
                        <svg
                            class="absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-hospital-text-3"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </div>
                    <div class="flex items-center gap-2">
                        <ModuleImportButton
                            label="شركات التأمين"
                            templateUrl="/insurance/companies/import-template"
                            importUrl="/insurance/companies/import"
                        />
                        <button
                            class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90 active:scale-95"
                            @click="openCreate"
                        >
                            <Plus class="h-4 w-4" />
                            شركة جديدة
                        </button>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-hospital-border"
                >
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-hospital-border bg-gray-50/80"
                            >
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الشركة
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الكود / العقد
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الهاتف
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    التغطية
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الخصم
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الحالة
                                </th>
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
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-hospital-primary-pale text-sm font-bold text-hospital-primary"
                                        >
                                            {{ companyInitials(company.name) }}
                                        </div>
                                        <div>
                                            <div
                                                class="font-semibold text-hospital-text"
                                            >
                                                {{ company.name }}
                                            </div>
                                            <div
                                                v-if="company.contact_person"
                                                class="text-xs text-hospital-text-3"
                                            >
                                                {{ company.contact_person }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="font-mono text-xs text-hospital-text-3"
                                    >
                                        {{ company.code || '—' }}
                                    </div>
                                    <div class="text-xs text-hospital-text-3">
                                        {{ company.contract_no || '' }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 text-sm text-hospital-text-3"
                                >
                                    {{ company.phone || '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="inline-flex items-center justify-center rounded-lg bg-blue-50 px-2 py-0.5 text-sm font-bold text-blue-700"
                                    >
                                        {{ company.coverage_pct }}%
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-3 text-center text-sm text-hospital-text-3"
                                >
                                    {{ company.disc_pct }}%
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        :variant="
                                            company.status === 'active'
                                                ? 'active'
                                                : 'inactive'
                                        "
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
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
                                    <Building2
                                        class="mx-auto mb-3 h-10 w-10 text-gray-300"
                                    />
                                    <p class="font-medium text-gray-400">
                                        لا توجد شركات تأمين
                                    </p>
                                    <p class="mt-1 text-sm text-gray-300">
                                        ابدأ بإضافة شركة جديدة
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Price Lists Tab ── -->
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
                <div
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-hospital-border bg-gray-50/60 py-16"
                >
                    <FileText class="mb-4 h-12 w-12 text-gray-300" />
                    <p class="font-semibold text-gray-400">قوائم الأسعار</p>
                    <p class="mt-1 text-sm text-gray-300">
                        يمكنك إدارة قوائم الأسعار من الصفحة المخصصة لها
                    </p>
                </div>
            </div>

            <!-- ── Contracts Tab ── -->
            <div v-else-if="activeTab === 'contracts'" class="p-5">
                <div
                    class="flex flex-col items-center justify-center rounded-xl border border-dashed border-hospital-border bg-gray-50/60 py-16"
                >
                    <Shield class="mb-4 h-12 w-12 text-gray-300" />
                    <p class="font-semibold text-gray-400">
                        لا توجد عقود مسجلة
                    </p>
                    <p class="mt-1 text-sm text-gray-300">
                        سيتم إضافة إدارة العقود قريباً
                    </p>
                </div>
            </div>

            <!-- ── Claims Tab ── -->
            <div v-else-if="activeTab === 'claims'" class="p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-hospital-text">
                        فلترة المطالبات
                    </h3>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="exportMonth"
                            type="month"
                            class="rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                        />
                        <button
                            class="flex items-center gap-2 rounded-lg border border-hospital-border px-3 py-2 text-sm font-medium text-hospital-text hover:bg-hospital-bg"
                            @click="exportClaimsForMonth"
                        >
                            <FileText class="h-4 w-4" />
                            تصدير الشهر
                        </button>
                        <button
                            class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-hospital-primary/90 active:scale-95"
                            @click="showClaimModal = true"
                        >
                            <Plus class="h-4 w-4" />
                            مطالبة جديدة
                        </button>
                    </div>
                </div>

                <div
                    class="mb-4 grid grid-cols-2 gap-2.5 rounded-xl border border-hospital-border bg-gray-50/50 p-3 sm:grid-cols-4"
                >
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >من تاريخ</label
                        >
                        <input
                            v-model="claimFilterState.from"
                            type="date"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >إلى تاريخ</label
                        >
                        <input
                            v-model="claimFilterState.to"
                            type="date"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >شركة التأمين</label
                        >
                        <select
                            v-model="claimFilterState.company_id"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="c in companies.data"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >الخدمة</label
                        >
                        <select
                            v-model="claimFilterState.service_id"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="s in claimFilterServices"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ s.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >القسم</label
                        >
                        <select
                            v-model="claimFilterState.dept"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="(label, key) in deptOptions"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >حالة المطالبة</label
                        >
                        <select
                            v-model="claimFilterState.status"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="[key, label] in claimStatusOptions"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >الطبيب</label
                        >
                        <select
                            v-model="claimFilterState.doctor_id"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @change="filterClaims"
                        >
                            <option value="">الكل</option>
                            <option
                                v-for="d in doctors"
                                :key="d.id"
                                :value="d.id"
                            >
                                {{ d.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-medium text-hospital-text-3"
                            >المريض / رقم الملف</label
                        >
                        <input
                            v-model="claimFilterState.patient"
                            type="text"
                            placeholder="اسم المريض أو رقم الملف"
                            class="w-full rounded-lg border border-hospital-border bg-white px-2.5 py-1.5 text-xs"
                            @keyup.enter="filterClaims"
                        />
                    </div>
                    <div class="col-span-2 flex items-end gap-2 sm:col-span-4">
                        <button
                            class="rounded-lg bg-hospital-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-hospital-primary/90"
                            @click="filterClaims"
                        >
                            تطبيق
                        </button>
                        <button
                            class="rounded-lg border border-hospital-border px-3 py-1.5 text-xs font-medium text-hospital-text-3 hover:bg-white"
                            @click="resetClaimFilters"
                        >
                            إعادة تعيين
                        </button>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-hospital-border"
                >
                    <div
                        class="border-b border-hospital-border bg-gray-50/80 px-4 py-2.5"
                    >
                        <span
                            class="text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                            >سجل المطالبات</span
                        >
                    </div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-hospital-border bg-gray-50/40"
                            >
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الشركة
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    المريض
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الخدمة
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الفاتورة
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    التأمين
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    المريض
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    التاريخ
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold tracking-wide text-hospital-text-3 uppercase"
                                >
                                    الحالة
                                </th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hospital-border">
                            <tr
                                v-for="claim in claims.data"
                                :key="claim.id"
                                class="group bg-white transition-colors hover:bg-hospital-primary/5"
                            >
                                <td
                                    class="px-4 py-3 font-medium text-hospital-text"
                                >
                                    {{ claim.company?.name || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-hospital-text">
                                        {{ claim.patient_name }}
                                    </div>
                                    <div
                                        v-if="claim.file_no"
                                        class="font-mono text-xs text-hospital-text-3"
                                    >
                                        {{ claim.file_no }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-hospital-text-3">
                                    {{ claim.service_name }}
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-semibold text-hospital-text tabular-nums"
                                >
                                    {{ claim.invoice_amount.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3"
                                        >ج</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-semibold text-hospital-primary tabular-nums"
                                >
                                    {{ claim.insurance_share.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3"
                                        >ج</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-center font-semibold text-hospital-warning tabular-nums"
                                >
                                    {{ claim.patient_share.toFixed(2) }}
                                    <span class="text-xs text-hospital-text-3"
                                        >ج</span
                                    >
                                </td>
                                <td
                                    class="px-4 py-3 text-center text-xs text-hospital-text-3 tabular-nums"
                                >
                                    {{ claim.service_date }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge
                                        :variant="
                                            claimStatusVariants[
                                                claim.status
                                            ] as any
                                        "
                                    >
                                        {{ claimStatusLabels[claim.status] }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div
                                        class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100"
                                    >
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
                                    <TrendingUp
                                        class="mx-auto mb-3 h-10 w-10 text-gray-300"
                                    />
                                    <p class="font-medium text-gray-400">
                                        لا توجد مطالبات
                                    </p>
                                    <p class="mt-1 text-sm text-gray-300">
                                        ابدأ بإنشاء مطالبة جديدة
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <CompanyModal
            v-model="showCompanyModal"
            :editing-company="editingCompany"
        />
        <DeleteCompanyModal
            v-model="showDeleteModal"
            :company-id="deletingCompanyId"
            @success="deletingCompanyId = null"
        />
        <ClaimModal v-model="showClaimModal" :companies="companies.data" />
        <ClaimStatusModal
            v-model="showStatusModal"
            :claim="editingClaim"
            @success="editingClaim = null"
        />
    </div>
</template>
