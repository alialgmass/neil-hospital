<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    CalendarPlus,
    Stethoscope,
    FlaskConical,
    Scissors,
    Zap,
    Dot,
    UserCog,
    Wallet,
    FileSpreadsheet,
    FileUp,
    Calculator,
    BookOpen,
    Library,
    ShoppingCart,
    Building2,
    Tags,
    BarChart3,
    Archive,
    Users,
    Settings,
    Shield,
    TrendingUp,
    Scale,
    FileText,
    ClipboardList,
    PieChart,
    Truck,
    Receipt,
    ClipboardCheck,
    UserSquare2,
    CalendarCheck,
    Clock,
    ArrowLeftRight,
    UmbrellaOff,
    Banknote,
    ChevronDown,
    HeartPulse,
    BadgeDollarSign,
    Package,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface NavEntry {
    title: string;
    href: string;
    icon: unknown;
    permission?: string;
    module?: string;
}

interface NavGroup {
    label: string;
    icon: unknown;
    items: NavEntry[];
}

const page = usePage<{ permissions?: string[]; moduleStatus?: Record<string, boolean> }>();
const permissions = computed<string[]>(() => (page.props.permissions as string[]) ?? []);
const moduleStatus = computed<Record<string, boolean>>(() => (page.props.moduleStatus as Record<string, boolean>) ?? {});

function can(permission: string): boolean {
    return permissions.value.includes('*') || permissions.value.includes(permission);
}

function moduleEnabled(module: string): boolean {
    return moduleStatus.value[module] !== false;
}

const navGroups: NavGroup[] = [
    {
        label: 'الرئيسية',
        icon: LayoutDashboard,
        items: [
            { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutDashboard, permission: 'dashboard' },
        ],
    },
    {
        label: 'الأقسام الطبية',
        icon: HeartPulse,
        items: [
            { title: 'الحجز', href: '/booking', icon: CalendarPlus, permission: 'booking.view', module: 'booking' },
            { title: 'العيادة', href: '/clinic', icon: Stethoscope, permission: 'clinic.view', module: 'clinic' },
            { title: 'الفحوصات', href: '/labs', icon: FlaskConical, permission: 'labs.view', module: 'labs' },
            { title: 'العمليات', href: '/surgery', icon: Scissors, permission: 'surgery.view', module: 'surgery' },
            { title: 'الليزك', href: '/lasik', icon: Zap, permission: 'lasik.view', module: 'lasik' },
            { title: 'الليزر', href: '/laser', icon: Dot, permission: 'laser.view', module: 'laser' },
        ],
    },
    {
        label: 'الأطباء',
        icon: UserCog,
        items: [
            { title: 'إدارة الأطباء', href: '/doctors', icon: UserCog, permission: 'doctors.view', module: 'doctors' },
            { title: 'مستحقات الأطباء', href: '/dr-claims', icon: Calculator, permission: 'drpayments.view', module: 'doctors' },
            { title: 'مدفوعات الأطباء', href: '/dr-payments', icon: Wallet, permission: 'drpayments.view', module: 'doctors' },
        ],
    },
    {
        label: 'المالية',
        icon: BadgeDollarSign,
        items: [
            { title: 'الخزنة', href: '/treasury', icon: Wallet, permission: 'treasury.view', module: 'accounting' },
            { title: 'قيود اليومية', href: '/journal', icon: BookOpen, permission: 'journal.view', module: 'accounting' },
            { title: 'الدليل المحاسبي', href: '/accounts', icon: Library, permission: 'journal.view', module: 'accounting' },
            { title: 'ميزان المراجعة', href: '/ledger/trial-balance', icon: Scale, permission: 'reports.financial', module: 'accounting' },
            { title: 'قائمة الدخل', href: '/ledger/income-statement', icon: TrendingUp, permission: 'reports.financial', module: 'accounting' },
            { title: 'كشف الحساب', href: '/ledger/account-statement', icon: FileText, permission: 'reports.financial', module: 'accounting' },
            { title: 'مراكز التكلفة', href: '/cost-centers', icon: PieChart, permission: 'journal.view', module: 'accounting' },
        ],
    },
    {
        label: 'المخزن والخدمات',
        icon: Package,
        items: [
            { title: 'الخدمات والأسعار', href: '/services', icon: Tags, permission: 'services.view', module: 'inventory' },
            { title: 'المخزن', href: '/inventory', icon: ShoppingCart, permission: 'inventory.view', module: 'inventory' },
            { title: 'الموردون', href: '/suppliers', icon: Truck, permission: 'inventory.view', module: 'inventory' },
            { title: 'فواتير الشراء', href: '/purchases', icon: Receipt, permission: 'inventory.view', module: 'inventory' },
            { title: 'أذون الصرف', href: '/stock-issue', icon: ClipboardList, permission: 'inventory.view', module: 'inventory' },
            { title: 'بنود المستلزمات', href: '/supply-bundles', icon: ClipboardList, permission: 'inventory.view', module: 'inventory' },
            { title: 'تسوية الجرد', href: '/stock-take', icon: ClipboardCheck, permission: 'inventory.view', module: 'inventory' },
            { title: 'شركات التأمين', href: '/insurance', icon: Building2, permission: 'insurance.view', module: 'inventory' },
        ],
    },
    {
        label: 'الموارد البشرية',
        icon: Users,
        items: [
            { title: 'الموظفون', href: '/employees', icon: UserSquare2, permission: 'hr.view', module: 'hr' },
            { title: 'الحضور والانصراف', href: '/attendance', icon: CalendarCheck, permission: 'hr.view', module: 'hr' },
            { title: 'الورديات', href: '/shifts', icon: Clock, permission: 'hr.view', module: 'hr' },
            { title: 'تسليم الوردية', href: '/shift-handovers', icon: ArrowLeftRight, permission: 'hr.view', module: 'hr' },
            { title: 'الإجازات', href: '/leaves', icon: UmbrellaOff, permission: 'hr.view', module: 'hr' },
            { title: 'الرواتب', href: '/payroll', icon: Banknote, permission: 'hr.manage', module: 'hr' },
        ],
    },
    {
        label: 'الإدارة',
        icon: Settings,
        items: [
            { title: 'التقارير', href: '/reports', icon: BarChart3, permission: 'reports.financial', module: 'reports' },
            { title: 'الأرشيف الطبي', href: '/archive', icon: Archive, permission: 'reports.clinical', module: 'reports' },
            { title: 'تصدير الوحدات', href: '/module-exports', icon: FileSpreadsheet, permission: 'users.manage' },
            { title: 'استيراد الوحدات', href: '/module-imports', icon: FileUp, permission: 'users.manage' },
            { title: 'المستخدمون', href: '/users', icon: Users, permission: 'users.manage' },
            { title: 'الأدوار والصلاحيات', href: '/roles', icon: Shield, permission: 'users.manage' },
            { title: 'الإعدادات', href: '/settings', icon: Settings, permission: 'settings.manage' },
        ],
    },
];

const visibleGroups = computed(() =>
    navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) =>
                (!item.permission || can(item.permission)) && (!item.module || moduleEnabled(item.module)),
            ),
        }))
        .filter((group) => group.items.length > 0),
);

const currentPath = computed(() => page.url);

function isActive(href: string): boolean {
    if (href === '/dashboard') {
        return currentPath.value === '/dashboard';
    }

    return currentPath.value.startsWith(href);
}

function groupHasActive(group: NavGroup): boolean {
    return group.items.some((item) => isActive(item.href));
}

// Collapsible state — open all groups by default
const openGroups = ref<Set<string>>(new Set(
    navGroups.map((g) => g.label)
));


function toggleGroup(label: string) {
    if (openGroups.value.has(label)) {
        openGroups.value.delete(label);
    } else {
        openGroups.value.add(label);
    }
}

function isOpen(label: string): boolean {
    return openGroups.value.has(label);
}
</script>

<template>
    <nav class="nav flex flex-col py-3 overflow-y-auto flex-1 select-none" dir="rtl">
        <template v-for="group in visibleGroups" :key="group.label">
            <!-- Group Header -->
            <button
                class="group-header flex w-full items-center gap-2.5 px-4 py-2.5 mt-2 text-right transition-all duration-200 hover:text-white"
                :class="groupHasActive(group) ? 'text-white/80' : 'text-white/40'"
                @click="toggleGroup(group.label)"
            >
                <component :is="group.icon" class="h-4 w-4 shrink-0 transition-colors" />
                <span class="flex-1 text-[11px] font-bold uppercase tracking-[1px]">{{ group.label }}</span>
                <ChevronDown
                    class="h-3.5 w-3.5 shrink-0 transition-transform duration-300"
                    :class="isOpen(group.label) ? 'rotate-0' : 'rotate-180 opacity-40'"
                />
            </button>

            <!-- Items -->
            <div
                v-if="isOpen(group.label)"
                class="flex flex-col gap-0.5 mt-1"
            >
                <Link
                    v-for="item in group.items"
                    :key="item.href"
                    :href="item.href"
                    class="nav-item relative mx-2 mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] transition-all duration-200"
                    :class="isActive(item.href)
                        ? 'bg-white/15 font-bold text-white shadow-sm'
                        : 'text-white/60 hover:bg-white/10 hover:text-white'"
                >
                    <!-- Active accent bar -->
                    <div
                        v-if="isActive(item.href)"
                        class="absolute inset-y-2 right-0 w-[3px] rounded-l-full bg-hospital-accent shadow-[0_0_8px_rgba(0,181,164,0.3)]"
                    />

                    <component
                        :is="item.icon"
                        class="h-[17px] w-[17px] shrink-0 transition-colors"
                        :class="isActive(item.href) ? 'text-hospital-accent' : 'opacity-50'"
                    />
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </div>


        </template>

        <!-- Bottom padding -->
        <div class="h-6 shrink-0" />
    </nav>

</template>

<style scoped>
.nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
.nav::-webkit-scrollbar {
    width: 5px;
}
.nav::-webkit-scrollbar-track {
    background: transparent;
}
.nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
}
.nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}
.group-header {
    cursor: pointer;
    border: none;
    background: none;
}
</style>
