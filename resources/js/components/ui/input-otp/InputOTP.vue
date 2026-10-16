<script setup lang="ts">
import type { ComponentPublicInstance, HTMLAttributes } from "vue"
import { PinInputRoot } from "reka-ui"
import { computed, onMounted, useTemplateRef } from "vue"
import { cn } from "@/lib/utils"

const props = withDefaults(defineProps<{
  maxlength?: number
  disabled?: boolean
  autofocus?: boolean
  class?: HTMLAttributes["class"]
}>(), {
  maxlength: 6,
  disabled: false,
  autofocus: false,
})

const model = defineModel<string>({ default: "" })

// Reka keeps one array entry per box; the forms post the code as a plain string.
const digits = computed<number[]>({
  get: () => model.value.split("").map(Number),
  set: value => {
    model.value = value.join("").slice(0, props.maxlength)
  },
})

const root = useTemplateRef<ComponentPublicInstance>("root")

onMounted(() => {
  if (props.autofocus) {
    (root.value?.$el as HTMLElement | undefined)?.querySelector("input")?.focus()
  }
})
</script>

<template>
  <PinInputRoot
    ref="root"
    v-model="digits"
    type="number"
    otp
    :disabled="disabled"
    data-slot="input-otp"
    :class="cn('flex items-center gap-2 has-disabled:opacity-50', props.class)"
  >
    <slot />
  </PinInputRoot>
</template>
