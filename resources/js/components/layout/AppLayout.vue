<script setup lang="ts">
/**
 * Hospital shell layout — wraps every authenticated hospital page.
 * Composed of:
 *   - Fixed sidebar (hospital blue, RTL, role-aware nav)
 *   - Fixed topbar (search, new-booking, user menu)
 *   - Scrollable main content area
 */
import HospitalSidebar from '@/components/layout/Sidebar.vue';
import HospitalTopbar from '@/components/layout/Topbar.vue';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch } from 'vue';
import type { Auth } from '@/types';

interface PageProps {
    auth: Auth;
    flash: {
        success: string | null;
        error: string | null;
        warning: string | null;
        info: string | null;
    };
}

const page = usePage<PageProps>();
const user = computed(() => page.props.auth?.user);
const userName = computed(() => user.value?.name ?? 'المستخدم');
const userRole = computed(() => user.value?.role ?? 'مسؤول');
const userInitial = computed(() => userName.value.charAt(0).toUpperCase());

function showFlash(flash: PageProps['flash']) {
    if (flash.success) toast.success(flash.success);
    if (flash.error) toast.error(flash.error);
    if (flash.warning) toast.warning(flash.warning);
    if (flash.info) toast.info(flash.info);
}

onMounted(() => showFlash(page.props.flash));

watch(() => page.props.flash, showFlash, { deep: true });
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-hospital-bg font-sans" dir="rtl">
        <!-- Sidebar — 240px fixed width -->
        <aside class="sidebar relative flex w-[240px] shrink-0 flex-col bg-hospital-primary-dark overflow-hidden">
            <!-- Decorative circle from reference HTML -->
            <div class="absolute -top-[60px] -left-[60px] h-[220px] w-[220px] rounded-full bg-white/3 pointer-events-none"></div>

            <!-- Sidebar Logo Area -->
            <div class="sidebar-logo flex items-center gap-2.5 px-4 py-[18px] border-b border-white/8 z-10">
                <div class="logo-icon flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-[9px] bg-hospital-accent">
                    <svg width="20" height="14" viewBox="0 0 20 14" fill="none">
                        <path d="M10 0C6 0 2.7 2.3 1 5c1.7 2.7 5 5 9 5s7.3-2.3 9-5C17.3 2.3 14 0 10 0z" fill="rgba(255,255,255,0.25)"/>
                        <circle cx="10" cy="5" r="3" fill="rgba(255,255,255,0.6)"/>
                        <circle cx="10" cy="5" r="1.5" fill="#00B5A4"/>
                    </svg>
                </div>
                <div class="leading-[1.2]">
                    <p class="logo-name font-bold text-white text-sm">مستشفى النور</p>
                    <p class="logo-sub text-[10px] text-white/45">طب وجراحة العيون</p>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex-1 z-10 flex flex-col min-h-0 overflow-hidden">
                <HospitalSidebar />
            </div>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer flex items-center gap-2.5 px-4 py-3 border-t border-white/8 z-10">
                <div class="usr-av flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-hospital-primary-light text-xs font-bold text-white">
                    {{ userInitial }}
                </div>
                <div class="flex flex-col leading-tight min-w-0">
                    <span class="usr-name text-[11px] font-semibold text-white/85 truncate">{{ userName }}</span>
                    <span class="usr-role text-[10px] text-white/40">{{ userRole }}</span>
                </div>
            </div>
        </aside>

        <!-- Main column: topbar + page content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <HospitalTopbar />
            <main class="content flex-1 overflow-y-auto p-5 scroll-smooth">
                <slot />
            </main>
        </div>
    </div>
    <Toaster />
</template>
