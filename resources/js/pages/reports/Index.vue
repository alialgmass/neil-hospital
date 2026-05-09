<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Banknote,
    BookOpen,
    Building2,
    Calculator,
    Calendar,
    CalendarCheck,
    Download,
    FileText,
    Package,
    Scissors,
    TrendingDown,
    TrendingUp,
    UmbrellaOff,
    Users,
    Wallet,
} from 'lucide-vue-next';

const categories = [
    {
        label: 'التقارير المالية',
        textColor: 'text-p',
        iconBg: 'bg-pp',
        reports: [
            { title: 'تقرير الإيرادات',          desc: 'إيرادات الفترة مقسمةً بالقسم والطبيب',     href: '/reports/income',           icon: TrendingUp },
            { title: 'قائمة الدخل والخسارة',     desc: 'الربح والخسارة والنتيجة المالية للفترة',   href: '/reports/profit-loss',      icon: BarChart3 },
            { title: 'إيرادات الأقسام',           desc: 'مقارنة إيرادات كل قسم بالتفصيل',          href: '/reports/dept-revenue',     icon: Calculator },
            { title: 'تحليل المصروفات',           desc: 'تفصيل المصروفات حسب البند والفئة',        href: '/reports/expense-analysis', icon: TrendingDown },
            { title: 'كشف الحساب',                desc: 'حركة الحساب المحاسبي بالأرصدة',           href: '/ledger/account-statement', icon: BookOpen },
        ],
    },
    {
        label: 'التقارير السريرية',
        textColor: 'text-s',
        iconBg: 'bg-sp',
        reports: [
            { title: 'التقرير اليومي',            desc: 'ملخص إيرادات وحالات اليوم فورياً',        href: '/reports/daily',     icon: Calendar },
            { title: 'تقرير الحالات',             desc: 'قائمة كل الحالات خلال الفترة المحددة',    href: '/reports/cases',     icon: FileText },
            { title: 'تقرير العمليات والجراحات',  desc: 'تفاصيل العمليات والليزك خلال الفترة',     href: '/reports/surgeries', icon: Scissors },
        ],
    },
    {
        label: 'الأطباء',
        textColor: 'text-a',
        iconBg: 'bg-ap',
        reports: [
            { title: 'مستحقات الأطباء',           desc: 'احتساب وعرض مستحقات كل طبيب بالتفصيل',  href: '/reports/doctor-claims',   icon: Users },
            { title: 'مدفوعات الأطباء',           desc: 'سجل الدفعات المصروفة للأطباء',           href: '/reports/doctor-payments', icon: Wallet },
        ],
    },
    {
        label: 'المخزن والتأمين',
        textColor: 'text-w',
        iconBg: 'bg-wp',
        reports: [
            { title: 'تقرير التأمين',             desc: 'مستحقات ومطالبات شركات التأمين',         href: '/reports/insurance',           icon: Building2 },
            { title: 'حركة المخزون',              desc: 'الوارد والصادر من المواد والمستلزمات',    href: '/reports/inventory-movement',  icon: Package },
            { title: 'أسعار الشراء',              desc: 'متوسطات ونطاق أسعار المشتريات',          href: '/reports/purchase-prices',     icon: Download },
            { title: 'تقرير انتهاء الصلاحية',    desc: 'أصناف منتهية أو قاربت على الانتهاء',     href: '/reports/expiry',              icon: AlertTriangle },
        ],
    },
    {
        label: 'الموارد البشرية',
        textColor: 'text-hospital-primary',
        iconBg: 'bg-pp',
        reports: [
            { title: 'تقرير الحضور والانصراف',   desc: 'سجل حضور وانصراف الموظفين للفترة المحددة',   href: '/reports/hr-attendance', icon: CalendarCheck },
            { title: 'تقرير الإجازات',            desc: 'سجل إجازات الموظفين وحالة كل طلب',           href: '/reports/hr-leaves',     icon: UmbrellaOff },
            { title: 'كشف الرواتب',               desc: 'ملخص رواتب الموظفين وخصوماتهم للشهر',        href: '/reports/hr-payroll',    icon: Banknote },
        ],
    },
];
</script>

<template>
    <Head title="التقارير" />

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-xl font-bold text-t">التقارير والإحصائيات</h1>
        <p class="mt-0.5 text-sm text-t3">عرض وتحليل بيانات مستشفى النور بشكل شامل</p>
    </div>

    <!-- Categories -->
    <div class="space-y-7">
        <div v-for="cat in categories" :key="cat.label">
            <!-- Category Header -->
            <div class="mb-3 flex items-center gap-3">
                <span class="text-xs font-bold uppercase tracking-wider" :class="cat.textColor">{{ cat.label }}</span>
                <div class="h-px flex-1 bg-br" />
            </div>

            <!-- Report Cards -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <Link
                    v-for="r in cat.reports"
                    :key="r.href"
                    :href="r.href"
                    class="group flex items-start gap-3 rounded-[var(--rl)] border border-br bg-sf p-4 shadow-[var(--sh)] transition-all hover:-translate-y-0.5 hover:border-p/30 hover:shadow-[var(--shl)]"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition-transform group-hover:scale-105"
                        :class="cat.iconBg"
                    >
                        <component :is="r.icon" class="h-5 w-5" :class="cat.textColor" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold text-t transition-colors group-hover:text-p">{{ r.title }}</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-t3">{{ r.desc }}</p>
                    </div>
                    <span class="mt-1 text-xs text-br transition-colors group-hover:text-p">←</span>
                </Link>
            </div>
        </div>
    </div>
</template>
