<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, Search, Plus, ChevronDown, LogOut, User, Settings } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type { Auth } from '@/types';

const page = usePage<{ 
    auth: Auth; 
    name?: string; 
    alert_count?: number;
    alerts?: { inventory: any[]; finance: any[] };
}>();

const user = computed(() => page.props.auth?.user);
const userName = computed(() => user.value?.name ?? 'المستخدم');
const userRole = computed(() => user.value?.role ?? 'مسؤول');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase());
const alertCount = computed(() => page.props.alert_count ?? 0);
const alerts = computed(() => page.props.alerts);

const searchQuery = ref('');
const userMenuOpen = ref(false);

function logout() {
    router.post('/logout');
}

function newBooking() {
    router.visit('/booking');
}

function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
}

function closeUserMenu() {
    userMenuOpen.value = false;
}
</script>

<template>
    <header class="topbar flex h-[58px] shrink-0 items-center justify-between border-b border-hospital-border bg-hospital-surface px-[22px] z-20">
        <!-- Topbar Left: Page Title -->
        <div class="topbar-left flex items-center gap-3 md:gap-[12px]">
            <h1 class="page-title font-sans text-base font-bold text-hospital-text" id="pageTitle">
                لوحة التحكم
            </h1>
        </div>

        <!-- Topbar Right: Search & Actions -->
        <div class="topbar-right flex items-center gap-2 md:gap-[8px]">
            <!-- Search Bar -->
            <div class="search-bar hidden md:flex items-center gap-[7px] w-full max-w-[280px] rounded-[7px] border border-hospital-border bg-hospital-surface px-[11px]">
                <Search class="h-[14px] w-[14px] text-hospital-text-3" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="بحث سريع..."
                    class="flex-1 bg-transparent py-[7px] px-1 text-[12px] text-hospital-text placeholder-hospital-text-3 focus:outline-none focus:ring-0"
                />
            </div>

            <!-- Notification Icon -->
            <button
                type="button"
                class="notif-btn relative flex h-[34px] w-[34px] items-center justify-center rounded-[7px] border border-hospital-border bg-hospital-surface text-hospital-text-3 transition-colors hover:bg-hospital-bg hover:text-hospital-primary"
                title="التنبيهات"
            >
                <Bell class="h-4 w-4" />
                <div v-if="alertCount > 0" class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-hospital-danger text-[9px] font-bold text-white border-2 border-hospital-surface">
                    {{ alertCount }}
                </div>
            </button>

            <!-- Quick Action: New Booking -->
            <button
                type="button"
                class="btn btn-p flex items-center gap-1.5 rounded-[7px] bg-hospital-primary px-[13px] py-[7px] text-[12px] font-medium text-white transition-all hover:bg-hospital-primary-light active:scale-95"
                @click="newBooking"
            >
                <Plus class="h-3.5 w-3.5" />
                <span>حجز جديد</span>
            </button>

            <!-- User Menu -->
            <div class="relative ml-2">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg py-1 transition-colors hover:opacity-80"
                    @click="toggleUserMenu"
                >
                    <div class="usr-av flex h-8 w-8 items-center justify-center rounded-full bg-hospital-primary-light text-[12px] font-bold text-white uppercase select-none">
                        {{ userInitial }}
                    </div>
                </button>

                <div
                    v-if="userMenuOpen"
                    class="absolute left-0 top-full z-[1001] mt-2 min-w-[180px] origin-top-left rounded-[var(--rl)] border border-hospital-border bg-white p-1.5 shadow-xl transition-all"
                >
                    <div class="px-3 py-2 border-b border-hospital-border mb-1">
                        <p class="text-xs font-bold text-hospital-text">{{ userName }}</p>
                        <p class="text-[10px] text-hospital-text-3">{{ userRole }}</p>
                    </div>
                    <a
                        href="/settings/profile"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium text-hospital-text-2 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                        @click="closeUserMenu"
                    >
                        <User class="h-4 w-4" />
                        الملف الشخصي
                    </a>
                    <a
                        href="/settings"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium text-hospital-text-2 transition-colors hover:bg-hospital-primary-pale hover:text-hospital-primary"
                        @click="closeUserMenu"
                    >
                        <Settings class="h-4 w-4" />
                        الإعدادات
                    </a>
                    <div class="my-1 border-t border-hospital-border"></div>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-xs font-medium text-hospital-danger transition-colors hover:bg-hospital-danger-pale"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        تسجيل الخروج
                    </button>
                </div>
                <!-- Overlay for click-outside -->
                <div v-if="userMenuOpen" class="fixed inset-0 z-[1000]" @click="closeUserMenu"></div>
            </div>
        </div>
    </header>
</template>
