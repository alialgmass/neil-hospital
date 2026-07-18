<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card-header"
    :class="cn('flex items-center justify-between px-[17px] py-[13px] border-b border-br bg-sf2', props.class)"
  >
    <slot />
  </div>
</template>
