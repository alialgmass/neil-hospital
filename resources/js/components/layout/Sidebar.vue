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
}

interface NavGroup {
    label: string;
    icon: unknown;
    items: NavEntry[];
}

const page = usePage<{ permissions?: string[] }>();
const permissions = computed<string[]>(() => (page.props.permissions as string[]) ?? []);

function can(permission: string): boolean {
    return permissions.value.includes('*') || permissions.value.includes(permission);
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
            { title: 'الحجز', href: '/booking', icon: CalendarPlus, permission: 'booking.view' },
            { title: 'العيادة', href: '/clinic', icon: Stethoscope, permission: 'clinic.view' },
            { title: 'الفحوصات', href: '/labs', icon: FlaskConical, permission: 'labs.view' },
            { title: 'العمليات', href: '/surgery', icon: Scissors, permission: 'surgery.view' },
            { title: 'الليزك', href: '/lasik', icon: Zap, permission: 'lasik.view' },
            { title: 'الليزر', href: '/laser', icon: Dot, permission: 'laser.view' },
        ],
    },
    {
        label: 'الأطباء',
        icon: UserCog,
        items: [
            { title: 'إدارة الأطباء', href: '/doctors', icon: UserCog, permission: 'doctors.view' },
            { title: 'مستحقات الأطباء', href: '/dr-claims', icon: Calculator, permission: 'drpayments.view' },
            { title: 'مدفوعات الأطباء', href: '/dr-payments', icon: Wallet, permission: 'drpayments.view' },
        ],
    },
    {
        label: 'المالية',
        icon: BadgeDollarSign,
        items: [
            { title: 'الخزنة', href: '/treasury', icon: Wallet, permission: 'treasury.view' },
            { title: 'قيود اليومية', href: '/journal', icon: BookOpen, permission: 'journal.view' },
            { title: 'الدليل المحاسبي', href: '/accounts', icon: Library, permission: 'journal.view' },
            { title: 'ميزان المراجعة', href: '/ledger/trial-balance', icon: Scale, permission: 'reports.financial' },
            { title: 'قائمة الدخل', href: '/ledger/income-statement', icon: TrendingUp, permission: 'reports.financial' },
            { title: 'كشف الحساب', href: '/ledger/account-statement', icon: FileText, permission: 'reports.financial' },
            { title: 'مراكز التكلفة', href: '/cost-centers', icon: PieChart, permission: 'journal.view' },
        ],
    },
    {
        label: 'المخزن والخدمات',
        icon: Package,
        items: [
            { title: 'الخدمات والأسعار', href: '/services', icon: Tags, permission: 'services.view' },
            { title: 'المخزن', href: '/inventory', icon: ShoppingCart, permission: 'inventory.view' },
            { title: 'الموردون', href: '/suppliers', icon: Truck, permission: 'inventory.view' },
            { title: 'فواتير الشراء', href: '/purchases', icon: Receipt, permission: 'inventory.view' },
            { title: 'أذون الصرف', href: '/stock-issue', icon: ClipboardList, permission: 'inventory.view' },
            { title: 'بنود المستلزمات', href: '/supply-bundles', icon: ClipboardList, permission: 'inventory.view' },
            { title: 'تسوية الجرد', href: '/stock-take', icon: ClipboardCheck, permission: 'inventory.view' },
            { title: 'شركات التأمين', href: '/insurance', icon: Building2, permission: 'insurance.view' },
        ],
    },
    {
        label: 'الموارد البشرية',
        icon: Users,
        items: [
            { title: 'الموظفون', href: '/employees', icon: UserSquare2, permission: 'hr.view' },
            { title: 'الحضور والانصراف', href: '/attendance', icon: CalendarCheck, permission: 'hr.view' },
            { title: 'الورديات', href: '/shifts', icon: Clock, permission: 'hr.view' },
            { title: 'تسليم الوردية', href: '/shift-handovers', icon: ArrowLeftRight, permission: 'hr.view' },
            { title: 'الإجازات', href: '/leaves', icon: UmbrellaOff, permission: 'hr.view' },
            { title: 'الرواتب', href: '/payroll', icon: Banknote, permission: 'hr.manage' },
        ],
    },
    {
        label: 'الإدارة',
        icon: Settings,
        items: [
            { title: 'التقارير', href: '/reports', icon: BarChart3, permission: 'reports.financial' },
            { title: 'الأرشيف الطبي', href: '/archive', icon: Archive, permission: 'reports.clinical' },
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
            items: group.items.filter((item) => !item.permission || can(item.permission)),
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

// Collapsible state — open groups by label
const openGroups = ref<Set<string>>(new Set(
    navGroups
        .filter((g) => g.items.some((item) => isActive(item.href)) || g.label === 'الرئيسية')
        .map((g) => g.label)
));

// If nothing active yet (fresh load), open first two groups
if (openGroups.value.size <= 1) {
    openGroups.value.add(navGroups[1]?.label ?? '');
}

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
    <nav class="nav flex flex-col py-2 overflow-y-auto h-full" dir="rtl">
        <template v-for="group in visibleGroups" :key="group.label">
            <!-- Group Header -->
            <button
                class="group-header flex w-full items-center gap-2 px-3 py-2 mt-1 text-right transition-colors hover:text-white/80"
                :class="groupHasActive(group) ? 'text-white/70' : 'text-white/35'"
                @click="toggleGroup(group.label)"
            >
                <component :is="group.icon" class="h-3 w-3 shrink-0" />
                <span class="flex-1 text-[9.5px] font-bold uppercase tracking-[0.9px]">{{ group.label }}</span>
                <ChevronDown
                    class="h-3 w-3 shrink-0 transition-transform duration-200"
                    :class="isOpen(group.label) ? 'rotate-0' : 'rotate-90'"
                />
            </button>

            <!-- Items -->
            <div
                class="overflow-hidden transition-all duration-200 ease-in-out"
                :class="isOpen(group.label) ? 'max-h-[600px] opacity-100' : 'max-h-0 opacity-0'"
            >
                <Link
                    v-for="item in group.items"
                    :key="item.href"
                    :href="item.href"
                    class="nav-item relative mx-2 mb-0.5 flex items-center gap-2.5 rounded-lg px-3 py-[7px] text-[12.5px] transition-all duration-150"
                    :class="isActive(item.href)
                        ? 'bg-white/14 font-semibold text-white shadow-sm'
                        : 'text-white/55 hover:bg-white/8 hover:text-white/90'"
                >
                    <!-- Active accent bar -->
                    <div
                        v-if="isActive(item.href)"
                        class="absolute inset-y-1.5 right-0 w-[3px] rounded-l-full bg-hospital-accent"
                    />

                    <component
                        :is="item.icon"
                        class="h-[15px] w-[15px] shrink-0 transition-colors"
                        :class="isActive(item.href) ? 'text-hospital-accent' : 'opacity-60'"
                    />
                    <span class="truncate">{{ item.title }}</span>
                </Link>
            </div>
        </template>

        <!-- Bottom padding -->
        <div class="h-2" />
    </nav>
</template>

<style scoped>
.nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.1) transparent;
}
.nav::-webkit-scrollbar {
    width: 3px;
}
.nav::-webkit-scrollbar-track {
    background: transparent;
}
.nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
}
.nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
.group-header {
    cursor: pointer;
    border: none;
    background: none;
}
</style>
