<script setup lang="ts">
import { FileText, Plus, Tag } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import AppLayout from '@/components/layout/AppLayout.vue'
import ModuleImportButton from '@/components/shared/ModuleImportButton.vue'
import { NO_PERMISSION_TITLE, usePermissions } from '@/composables/usePermissions'
import PriceListCard from './Partials/PriceListCard.vue'
import PriceListModal from './Partials/PriceListModal.vue'
import type { PriceList, PriceListCompany, PriceListService } from './Partials/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    priceLists: { data: PriceList[]; links: unknown[] }
    companies: PriceListCompany[]
    services: PriceListService[]
}>()

const { can } = usePermissions()
const canCreate = computed(() => can('insurance.write'))
const canEdit = computed(() => can('insurance.price_lists.edit'))

const showModal = ref(false)
const editingList = ref<PriceList | null>(null)
const expandedList = ref<string | null>(null)

function openCreate() {
    if (!canCreate.value) {
        return
    }

    editingList.value = null
    showModal.value = true
}

function openEdit(list: PriceList) {
    if (!canEdit.value) {
        return
    }

    editingList.value = list
    showModal.value = true
}

const totalActive = computed(() => props.priceLists.data.filter((p) => p.is_active).length)
const totalServices = computed(() => props.priceLists.data.reduce((s, p) => s + p.items.length, 0))

function toggleExpand(id: string) {
    expandedList.value = expandedList.value === id ? null : id
}
</script>

<template>
    <div class="space-y-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-hospital-text">قوائم الأسعار</h1>
                <p class="mt-0.5 text-sm text-hospital-text-3">إدارة قوائم أسعار شركات التأمين والزيارات</p>
            </div>
            <div class="flex items-center gap-2">
                <ModuleImportButton
                    permission="insurance.write"
                    label="قوائم الأسعار"
                    templateUrl="/insurance/price-lists/import-template"
                    importUrl="/insurance/price-lists/import"
                />
                <button
                    class="flex items-center gap-2 rounded-xl bg-hospital-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all hover:bg-hospital-primary/90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100"
                    :disabled="!canCreate"
                    :title="canCreate ? undefined : NO_PERMISSION_TITLE"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />
                    قائمة جديدة
                </button>
            </div>
        </div>

        <!-- Stats strip -->
        <div class="grid grid-cols-3 gap-4">
            <div class="flex items-center gap-3 rounded-xl border border-hospital-border bg-hospital-surface px-4 py-3 shadow-sm">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-hospital-primary-pale">
                    <FileText class="h-4 w-4 text-hospital-primary" />
                </div>
                <div>
                    <p class="text-xs text-hospital-text-3">إجمالي القوائم</p>
                    <p class="text-lg font-bold text-hospital-text">{{ priceLists.data.length }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-hospital-border bg-hospital-surface px-4 py-3 shadow-sm">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-hospital-success-pale">
                    <Tag class="h-4 w-4 text-hospital-success" />
                </div>
                <div>
                    <p class="text-xs text-hospital-text-3">قوائم نشطة</p>
                    <p class="text-lg font-bold text-hospital-text">{{ totalActive }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3 rounded-xl border border-hospital-border bg-hospital-surface px-4 py-3 shadow-sm">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-hospital-accent-pale">
                    <Tag class="h-4 w-4 text-hospital-accent" />
                </div>
                <div>
                    <p class="text-xs text-hospital-text-3">إجمالي الخدمات</p>
                    <p class="text-lg font-bold text-hospital-text">{{ totalServices }}</p>
                </div>
            </div>
        </div>

        <!-- Price Lists -->
        <div class="space-y-3">
            <PriceListCard
                v-for="list in priceLists.data"
                :key="list.id"
                :list="list"
                :expanded="expandedList === list.id"
                @toggle="toggleExpand(list.id)"
                @edit="openEdit(list)"
            />

            <!-- Empty State -->
            <div v-if="priceLists.data.length === 0" class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-hospital-border bg-hospital-surface py-16">
                <FileText class="mb-4 h-12 w-12 text-gray-300" />
                <p class="font-semibold text-gray-400">لا توجد قوائم أسعار</p>
                <p class="mt-1 text-sm text-gray-300">ابدأ بإنشاء أول قائمة أسعار</p>
                <button class="mt-4 flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary/90 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!canCreate" :title="canCreate ? undefined : NO_PERMISSION_TITLE" @click="openCreate">
                    <Plus class="h-4 w-4" />
                    قائمة جديدة
                </button>
            </div>
        </div>

        <PriceListModal
            v-model="showModal"
            :companies="companies"
            :services="services"
            :price-list="editingList"
        />
    </div>
</template>
