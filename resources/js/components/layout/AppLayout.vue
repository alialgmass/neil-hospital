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
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-hospital-bg font-sans">
        <!-- Sidebar — fixed width on desktop, hidden on mobile (TODO: mobile drawer) -->
        <aside class="flex w-64 shrink-0 flex-col bg-hospital-primary shadow-lg">
            <!-- Logo / Hospital Name -->
            <div class="flex items-center gap-3 px-5 py-4 border-b border-white/10">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-white/20 text-white font-bold text-lg select-none">
                    ن
                </div>
                <div class="leading-tight">
                    <p class="text-sm font-bold text-white">مستشفى النور</p>
                    <p class="text-[10px] text-white/60">طب وجراحة العيون</p>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex-1 overflow-y-auto">
                <HospitalSidebar />
            </div>
        </aside>

        <!-- Main column: topbar + page content -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <HospitalTopbar />
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
    <Toaster />
</template>
