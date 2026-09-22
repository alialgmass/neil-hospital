<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const hospitalName = computed(() => page.props.settings?.hospital_name ?? 'مستشفى النور');
const logoUrl = computed(() => page.props.settings?.hospital_logo_url ?? null);
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground overflow-hidden"
    >
        <img v-if="logoUrl" :src="logoUrl" alt="" class="size-full object-cover" />
        <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ hospitalName }}</span>
    </div>
</template>
