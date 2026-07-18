<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="cn('bg-sf text-t flex flex-col rounded-[var(--rl)] border border-br shadow-[var(--sh)] overflow-hidden mb-4', props.class)"
  >
    <slot />
  </div>
</template>
