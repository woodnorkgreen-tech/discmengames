<template>
  <div class="min-w-0 text-center">
    <div class="mb-2 flex h-9 items-center justify-center gap-2">
      <img v-if="flag" :src="flag" :alt="`${team} flag`" class="h-7 w-11 rounded object-cover shadow ring-1 ring-white/20" />
      <span class="truncate text-xs font-bold text-gray-300" :class="flag ? 'sr-only' : ''">{{ team }}</span>
    </div>
    <div class="overflow-hidden rounded-2xl border border-white/10 bg-black/20">
      <button type="button" @click="change(1)" :aria-label="`Increase ${team} score`"
        class="flex min-h-12 w-full items-center justify-center border-b border-white/10 text-2xl font-black text-discmen-accent transition hover:bg-white/5 active:bg-discmen/20">+</button>
      <output class="flex h-20 items-center justify-center text-5xl font-black tabular-nums text-white sm:h-24 sm:text-6xl" :aria-label="`${team} ${modelValue}`">{{ modelValue }}</output>
      <button type="button" @click="change(-1)" :disabled="modelValue <= 0" :aria-label="`Decrease ${team} score`"
        class="flex min-h-12 w-full items-center justify-center border-t border-white/10 text-2xl font-black text-gray-400 transition hover:bg-white/5 active:bg-white/10 disabled:opacity-25">−</button>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: Number, default: 0 },
  team: { type: String, required: true },
  flag: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
function change(amount) { emit('update:modelValue', Math.min(20, Math.max(0, props.modelValue + amount))) }
</script>
