<script setup lang="ts">
import { ChevronDown, ChevronUp, FileText, Printer } from 'lucide-vue-next'
import Badge from '@/components/shared/Badge.vue'

interface PriceListItem {
    service_id: string
    price: number
    service?: { id: string; name: string; dept: string }
}

interface Company {
    id: string
    name: string
    coverage_pct: number
}

interface PriceList {
    id: string
    name: string
    type: string
    ins_coverage?: number
    discount_pct: number
    is_active: boolean
    company?: Company
    items: PriceListItem[]
}

defineProps<{
    list: PriceList
    expanded: boolean
}>()

const emit = defineEmits<{
    (e: 'toggle'): void
}>()

const deptLabels: Record<string, string> = {
    clinic: 'العيادة',
    labs: 'الفحوصات',
    surgery: 'العمليات',
    lasik: 'الليزك',
    laser: 'الليزر',
}

const typeConfig: Record<string, { label: string; variant: string }> = {
    cash:      { label: 'نقدي',  variant: 'active' },
    insurance: { label: 'تأمين', variant: 'info' },
    vip:       { label: 'VIP',   variant: 'warning' },
    special:   { label: 'خاص',  variant: 'partial' },
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-hospital-border bg-hospital-surface shadow-sm transition-shadow hover:shadow-md">
        <!-- Header -->
        <div class="flex cursor-pointer items-center justify-between px-5 py-4" @click="emit('toggle')">
            <div class="flex items-center gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-hospital-primary-pale">
                    <FileText class="h-5 w-5 text-hospital-primary" />
                </div>
                <div>
                    <p class="font-semibold text-hospital-text">{{ list.name }}</p>
                    <p class="text-xs text-hospital-text-3">{{ list.company?.name ?? '— بدون شركة تأمين' }}</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Badge :variant="(typeConfig[list.type]?.variant ?? 'info') as any">
                    {{ typeConfig[list.type]?.label ?? list.type }}
                </Badge>
                <span v-if="list.ins_coverage" class="rounded-lg bg-hospital-primary-pale px-2.5 py-0.5 text-xs font-bold text-hospital-primary">
                    تغطية {{ list.ins_coverage }}%
                </span>
                <span class="rounded-lg bg-gray-100 px-2.5 py-0.5 text-xs text-hospital-text-3">
                    {{ list.items.length }} خدمة
                </span>
                <button
                    class="rounded-lg p-1.5 text-hospital-text-3 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                    title="طباعة"
                    @click.stop="() => window.print()"
                >
                    <Printer class="h-4 w-4" />
                </button>
                <ChevronUp v-if="expanded" class="h-4 w-4 text-hospital-text-3" />
                <ChevronDown v-else class="h-4 w-4 text-hospital-text-3" />
            </div>
        </div>

        <!-- Items Table -->
        <div v-if="expanded" class="border-t border-hospital-border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/80">
                        <th class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">الخدمة</th>
                        <th class="px-5 py-2.5 text-right text-xs font-semibold uppercase tracking-wide text-hospital-text-3">القسم</th>
                        <th class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-hospital-text-3">السعر</th>
                        <th v-if="list.ins_coverage" class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-hospital-primary">يتحمله التأمين</th>
                        <th v-if="list.ins_coverage" class="px-5 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-hospital-warning">يتحمله المريض</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-hospital-border">
                    <tr v-for="item in list.items" :key="item.service_id" class="bg-white transition-colors hover:bg-hospital-primary/5">
                        <td class="px-5 py-3 font-medium text-hospital-text">{{ item.service?.name || item.service_id }}</td>
                        <td class="px-5 py-3 text-hospital-text-3">{{ deptLabels[item.service?.dept ?? ''] || '—' }}</td>
                        <td class="px-5 py-3 text-center font-semibold tabular-nums text-hospital-text">
                            {{ item.price.toFixed(2) }} <span class="text-xs text-hospital-text-3">ج</span>
                        </td>
                        <td v-if="list.ins_coverage" class="px-5 py-3 text-center font-semibold tabular-nums text-hospital-primary">
                            {{ ((item.price * (list.ins_coverage ?? 0)) / 100).toFixed(2) }} <span class="text-xs text-hospital-text-3">ج</span>
                        </td>
                        <td v-if="list.ins_coverage" class="px-5 py-3 text-center font-semibold tabular-nums text-hospital-warning">
                            {{ (item.price - (item.price * (list.ins_coverage ?? 0)) / 100).toFixed(2) }} <span class="text-xs text-hospital-text-3">ج</span>
                        </td>
                    </tr>
                    <tr v-if="list.items.length === 0">
                        <td class="px-5 py-8 text-center text-hospital-text-3" colspan="5">لا توجد خدمات في هذه القائمة</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
