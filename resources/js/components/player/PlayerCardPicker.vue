<template>
  <div class="space-y-3">
    <label class="block">
      <span class="mb-1.5 block text-xs font-bold uppercase tracking-widest text-gray-500">{{ label }}</span>
      <select :value="modelValue" @change="$emit('update:modelValue', $event.target.value)"
        class="field-control min-h-12 px-4 py-3 text-sm font-semibold">
        <option value="" disabled>Select a player…</option>
        <optgroup v-for="group in groups" :key="group.name" :label="group.name">
          <option v-for="player in group.players" :key="`${group.name}-${player}`" :value="player">{{ player }}</option>
        </optgroup>
        <option v-if="showFallback" :value="fallbackValue">{{ fallbackLabel }}</option>
      </select>
    </label>

    <div v-if="modelValue" class="flex items-center gap-3 rounded-xl border border-discmen-accent/35 bg-discmen-accent/10 px-3 py-2.5">
      <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-discmen-accent text-xs font-black text-gray-950">
        {{ initials(modelValue) }}
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-[10px] font-bold uppercase tracking-widest text-discmen-accent">Selected</p>
        <p class="truncate text-sm font-black text-white">{{ selectedLabel }}</p>
      </div>
      <span class="text-discmen-accent" aria-hidden="true">✓</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  groups: { type: Array, required: true },
  fallbackValue: { type: String, required: true },
  fallbackLabel: { type: String, required: true },
  showFallback: { type: Boolean, default: true },
})

defineEmits(['update:modelValue'])
const selectedLabel = computed(() => props.modelValue === props.fallbackValue ? props.fallbackLabel : props.modelValue)

function initials(name) {
  return name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase()
}
</script>
