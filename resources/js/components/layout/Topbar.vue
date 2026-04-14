<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Bell, Search, Plus, ChevronDown, LogOut, User, Settings } from 'lucide-vue-next';
import type { Auth } from '@/types';

const page = usePage<{ auth: Auth; name?: string }>();
const user = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.name ?? 'مستشفى النور');

const searchQuery = ref('');
const userMenuOpen = ref(false);

function logout() {
    router.post('/logout');
}

function newBooking() {
    router.visit('/booking/create');
}

function toggleUserMenu() {
    userMenuOpen.value = !userMenuOpen.value;
}

function closeUserMenu() {
    userMenuOpen.value = false;
}
</script>

<template>
    <header class="flex items-center justify-between gap-4 border-b border-hospital-border bg-hospital-surface px-6 py-3 shadow-sm">
        <!-- Search -->
        <div class="relative flex-1 max-w-xs">
            <Search class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-hospital-text-3 pointer-events-none" />
            <input
                v-model="searchQuery"
                type="search"
                placeholder="بحث..."
                class="w-full rounded-lg border border-hospital-border bg-hospital-bg py-2 pr-9 pl-4 text-sm text-hospital-text placeholder-hospital-text-3 focus:border-hospital-primary focus:outline-none focus:ring-2 focus:ring-hospital-primary/20"
            />
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3">
            <!-- New Booking Button -->
            <button
                type="button"
                class="flex items-center gap-2 rounded-lg bg-hospital-primary px-4 py-2 text-sm font-medium text-white hover:bg-hospital-primary-light transition-colors"
                @click="newBooking"
            >
                <Plus class="h-4 w-4" />
                <span>حجز جديد</span>
            </button>

            <!-- Notifications -->
            <button
                type="button"
                class="relative rounded-lg p-2 text-hospital-text-2 hover:bg-hospital-bg hover:text-hospital-primary transition-colors"
                title="الإشعارات"
            >
                <Bell class="h-5 w-5" />
                <!-- badge placeholder — wired up in Phase 12 -->
                <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-hospital-danger" />
            </button>

            <!-- User Menu -->
            <div class="relative">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-hospital-bg transition-colors"
                    @click="toggleUserMenu"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-hospital-primary text-white text-xs font-bold select-none">
                        {{ user?.name?.charAt(0) ?? 'A' }}
                    </div>
                    <span class="max-w-[120px] truncate font-medium text-hospital-text">{{ user?.name }}</span>
                    <ChevronDown class="h-4 w-4 text-hospital-text-3" />
                </button>

                <!-- Dropdown -->
                <div
                    v-if="userMenuOpen"
                    class="absolute left-0 top-full mt-1 z-50 min-w-[160px] rounded-xl border border-hospital-border bg-hospital-surface shadow-lg py-1"
                    @blur.capture="closeUserMenu"
                >
                    <a
                        href="/settings/profile"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-hospital-text hover:bg-hospital-bg"
                        @click="closeUserMenu"
                    >
                        <User class="h-4 w-4" />
                        الملف الشخصي
                    </a>
                    <a
                        href="/settings"
                        class="flex items-center gap-2 px-4 py-2 text-sm text-hospital-text hover:bg-hospital-bg"
                        @click="closeUserMenu"
                    >
                        <Settings class="h-4 w-4" />
                        الإعدادات
                    </a>
                    <hr class="my-1 border-hospital-border" />
                    <button
                        type="button"
                        class="flex w-full items-center gap-2 px-4 py-2 text-sm text-hospital-danger hover:bg-hospital-danger-pale"
                        @click="logout"
                    >
                        <LogOut class="h-4 w-4" />
                        تسجيل الخروج
                    </button>
                </div>

                <!-- Click-outside overlay -->
                <div
                    v-if="userMenuOpen"
                    class="fixed inset-0 z-40"
                    @click="closeUserMenu"
                />
            </div>
        </div>
    </header>
</template>
