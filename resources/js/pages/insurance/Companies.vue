<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue'
import Badge from '@/components/shared/Badge.vue'
import Modal from '@/components/shared/Modal.vue'
import { router, useForm } from '@inertiajs/vue3'
import { Edit2 } from 'lucide-vue-next'
import { computed, ref } from 'vue'

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

const props = defineProps<{
    companies: { data: Company[]; links: unknown[] }
    filters: { search?: string }
}>()

const activeTab = ref<'companies' | 'pricelists' | 'contracts' | 'claims' | 'approved'>('companies')
const showModal = ref(false)
const editingCompany = ref<Company | null>(null)
const claimsCompanyFilter = ref('')

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

const searchQuery = ref(props.filters.search ?? '')

const activeCount = computed(() => props.companies.data.filter((c) => c.status === 'active').length)

function search() {
    router.get('/insurance', { search: searchQuery.value }, { preserveState: true, replace: true })
}

function openCreate() {
    editingCompany.value = null
    form.reset()
    showModal.value = true
}

function openEdit(company: Company) {
    editingCompany.value = company
    Object.assign(form, {
        name: company.name,
        code: company.code ?? '',
        phone: company.phone ?? '',
        coverage_pct: company.coverage_pct,
        disc_pct: company.disc_pct,
        contact_person: company.contact_person ?? '',
        email: company.email ?? '',
        status: company.status,
        contract_no: company.contract_no ?? '',
    })
    showModal.value = true
}

function submit() {
    if (editingCompany.value) {
        form.put(`/insurance/${editingCompany.value.id}`, { onSuccess: () => { showModal.value = false } })
    } else {
        form.post('/insurance', { onSuccess: () => { showModal.value = false } })
    }
}

const tabs = [
    { key: 'companies', label: 'شركات التأمين' },
    { key: 'pricelists', label: 'قوائم الأسعار المرتبطة' },
    { key: 'contracts', label: 'العقود والاتفاقيات' },
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
                <div class="mt-1 text-2xl font-bold text-teal-700">0</div>
                <div class="mt-0.5 text-xs text-teal-500">مطالبة</div>
            </div>
            <div class="rounded-xl border border-orange-100 bg-orange-50 p-4">
                <div class="text-xs font-semibold text-orange-600">إجمالي المطالبات (ج)</div>
                <div class="mt-1 text-2xl font-bold text-orange-700">0</div>
                <div class="mt-0.5 text-xs text-orange-500">↑ هذا الشهر</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-5 flex gap-1 border-b border-gray-200">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="px-4 py-2 text-sm font-medium transition-colors"
                :class="activeTab === tab.key
                    ? 'border-b-2 border-hospital-primary text-hospital-primary'
                    : 'text-gray-500 hover:text-gray-700'"
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
                <button
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90"
                    @click="openCreate"
                >
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
                        <tr
                            v-for="company in companies.data"
                            :key="company.id"
                            class="border-t border-gray-100 hover:bg-gray-50"
                        >
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
                                <button class="text-gray-400 hover:text-blue-600" @click="openEdit(company)">
                                    <Edit2 class="h-4 w-4" />
                                </button>
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
                <a
                    href="/insurance/price-lists"
                    class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90"
                >
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
            <div class="mb-4 flex justify-end">
                <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90">
                    + عقد جديد
                </button>
            </div>
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
                <select v-model="claimsCompanyFilter" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none">
                    <option value="">كل الشركات</option>
                    <option v-for="c in companies.data" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div class="flex gap-2">
                    <button class="rounded-lg border border-hospital-primary px-3 py-2 text-sm text-hospital-primary hover:bg-hospital-primary/5">
                        🔄 مطالبات الشهر أوتوماتيك
                    </button>
                    <button class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90">
                        + مطالبة جديدة
                    </button>
                </div>
            </div>
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="px-4 py-3 font-semibold text-gray-700">سجل المطالبات</div>
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="px-4 py-8 text-center text-gray-400" colspan="8">لا توجد مطالبات</td>
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
                    <div class="px-4 py-6 text-center text-sm text-gray-400">
                        لم يتم تحديد خدمات معتمدة بعد
                    </div>
                </div>
            </div>
            <div v-if="companies.data.filter((c) => c.status === 'active').length === 0" class="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-400">
                لا توجد شركات تأمين نشطة
            </div>
        </div>

        <!-- Create / Edit Modal -->
        <Modal v-model="showModal" :title="editingCompany ? 'تعديل شركة التأمين' : 'إضافة شركة تأمين'" @close="showModal = false">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">اسم الشركة *</label>
                        <input v-model="form.name" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="text" required />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الكود</label>
                        <input v-model="form.code" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">رقم العقد</label>
                        <input v-model="form.contract_no" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الهاتف</label>
                        <input v-model="form.phone" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">المسؤول</label>
                        <input v-model="form.contact_person" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="text" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نسبة التغطية %</label>
                        <input v-model.number="form.coverage_pct" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">نسبة الخصم %</label>
                        <input v-model.number="form.disc_pct" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="number" min="0" max="100" step="0.01" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">الحالة</label>
                        <select v-model="form.status" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none">
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">البريد الإلكتروني</label>
                        <input v-model="form.email" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" type="email" />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm font-medium">ملاحظات</label>
                        <textarea v-model="form.notes" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-hospital-primary focus:outline-none" rows="2" />
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50" @click="showModal = false">إلغاء</button>
                    <button type="submit" class="rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white disabled:opacity-60" :disabled="form.processing">
                        {{ form.processing ? 'جارٍ الحفظ...' : 'حفظ' }}
                    </button>
                </div>
            </form>
        </Modal>
    </div>
</template>
