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
} from 'lucide-vue-next';
import { computed } from 'vue';

interface NavGroup {
    label: string;
    items: NavEntry[];
}

interface NavEntry {
    title: string;
    href: string;
    icon: unknown;
    permission?: string;
}

const page = usePage<{ permissions?: string[] }>();
const permissions = computed<string[]>(() => (page.props.permissions as string[]) ?? []);

function can(permission: string): boolean {
    // admin has all permissions
    return permissions.value.includes('*') || permissions.value.includes(permission);
}

const navGroups: NavGroup[] = [
    {
        label: 'الرئيسية',
        items: [
            { title: 'لوحة التحكم', href: '/dashboard', icon: LayoutDashboard, permission: 'dashboard' },
        ],
    },
    {
        label: 'الأقسام الطبية',
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
        items: [
            { title: 'إدارة الأطباء', href: '/doctors', icon: UserCog, permission: 'doctors.view' },
            { title: 'مستحقات الأطباء', href: '/dr-payments', icon: Wallet, permission: 'drpayments.view' },
        ],
    },
    {
        label: 'المالية',
        items: [
            { title: 'الخزنة', href: '/treasury', icon: Wallet, permission: 'treasury.view' },
            { title: 'قيود اليومية', href: '/journal', icon: BookOpen, permission: 'journal.view' },
            { title: 'الدليل المحاسبي', href: '/accounts', icon: Library, permission: 'journal.view' },
            { title: 'ميزان المراجعة', href: '/ledger/trial-balance', icon: Scale, permission: 'reports.financial' },
            { title: 'قائمة الدخل', href: '/ledger/income-statement', icon: TrendingUp, permission: 'reports.financial' },
            { title: 'كشف الحساب', href: '/ledger/account-statement', icon: FileText, permission: 'reports.financial' },
        ],
    },
    {
        label: 'المخزن والخدمات',
        items: [
            { title: 'الخدمات والأسعار', href: '/services', icon: Tags, permission: 'services.view' },
            { title: 'المخزن', href: '/inventory', icon: ShoppingCart, permission: 'inventory.view' },
            { title: 'شركات التأمين', href: '/insurance', icon: Building2, permission: 'insurance.view' },
        ],
    },
    {
        label: 'الإدارة',
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
</script>

<template>
    <nav class="flex flex-col gap-1 px-3 py-4 overflow-y-auto h-full">
        <template v-for="group in visibleGroups" :key="group.label">
            <p class="px-2 pt-4 pb-1 text-xs font-semibold uppercase tracking-wider text-white/50 first:pt-0">
                {{ group.label }}
            </p>
            <Link
                v-for="item in group.items"
                :key="item.href"
                :href="item.href"
                :class="[
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                    isActive(item.href)
                        ? 'bg-white/20 text-white'
                        : 'text-white/80 hover:bg-white/10 hover:text-white',
                ]"
            >
                <component :is="item.icon" class="h-4 w-4 shrink-0" />
                <span>{{ item.title }}</span>
            </Link>
        </template>
    </nav>
</template>
