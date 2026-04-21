<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import {
    BarChart2,
    BookOpen,
    DollarSign,
    FileText,
    Package,
    Shield,
    TrendingDown,
    TrendingUp,
    Users,
    Wallet,
} from 'lucide-vue-next'
import AppLayout from '@/components/layout/AppLayout.vue'

defineOptions({ layout: AppLayout })

const reports = [
    { title: 'إيرادات الأقسام', description: 'تفصيل الإيرادات بالقسم والطبيب', href: '/reports/dept-revenue', icon: BarChart2, color: 'bg-blue-100 text-blue-600' },
    { title: 'تقرير الحالات', description: 'قائمة الحالات خلال الفترة', href: '/reports/cases', icon: FileText, color: 'bg-green-100 text-green-600' },
    { title: 'مستحقات الأطباء', description: 'حساب مستحقات كل طبيب', href: '/reports/doctor-claims', icon: Users, color: 'bg-purple-100 text-purple-600' },
    { title: 'مدفوعات الأطباء', description: 'سجل المدفوعات للأطباء', href: '/reports/doctor-payments', icon: Wallet, color: 'bg-indigo-100 text-indigo-600' },
    { title: 'تقرير التأمين', description: 'مستحقات شركات التأمين', href: '/reports/insurance', icon: Shield, color: 'bg-cyan-100 text-cyan-600' },
    { title: 'حركة المخزون', description: 'وارد وصادر المواد', href: '/reports/inventory-movement', icon: Package, color: 'bg-orange-100 text-orange-600' },
    { title: 'أسعار الشراء', description: 'متوسطات ونطاق أسعار المشتريات', href: '/reports/purchase-prices', icon: TrendingDown, color: 'bg-red-100 text-red-600' },
    { title: 'قائمة الدخل', description: 'الربح والخسارة خلال فترة', href: '/reports/profit-loss', icon: TrendingUp, color: 'bg-emerald-100 text-emerald-600' },
    { title: 'تحليل المصروفات', description: 'تفصيل المصروفات بالبند', href: '/reports/expense-analysis', icon: DollarSign, color: 'bg-yellow-100 text-yellow-600' },
    { title: 'كشف الحساب', description: 'كشف حركة الحساب المحاسبي', href: '/ledger/account-statement', icon: BookOpen, color: 'bg-gray-100 text-gray-600' },
]
</script>

<template>
    <div class="p-6">
        <h1 class="mb-6 text-2xl font-bold text-gray-800">التقارير والإحصائيات</h1>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Link
                v-for="report in reports"
                :key="report.href"
                :href="report.href"
                class="group flex items-start gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:shadow-md"
            >
                <div :class="['flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-lg', report.color]">
                    <component :is="report.icon" class="h-6 w-6" />
                </div>
                <div>
                    <p class="font-semibold text-gray-800 group-hover:text-blue-700">{{ report.title }}</p>
                    <p class="mt-1 text-sm text-gray-500">{{ report.description }}</p>
                </div>
            </Link>
        </div>
    </div>
</template>
