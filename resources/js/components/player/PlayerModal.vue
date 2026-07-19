<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-[100] flex items-end justify-center bg-black/70 backdrop-blur-sm p-0 sm:items-center sm:p-5"
      @click.self="$emit('dismiss')">
      <div class="glass-card modal-in w-full max-h-[calc(100dvh-1rem)] overflow-hidden rounded-t-3xl p-5 text-center sm:max-w-sm sm:rounded-2xl sm:p-7 pb-safe" role="dialog" aria-modal="true">
        <span class="modal-client-logo discmen-logo-tile" aria-label="Discmen Entertainment">
          <img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" />
        </span>
        <slot />
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineEmits(['dismiss'])
</script>

<style scoped>
.modal-client-logo {
  width: 3.5rem;
  height: 3rem;
  margin: 0 auto .7rem;
  padding: .2rem;
}
.modal-in { animation: modal-in .3s cubic-bezier(.22, 1, .36, 1) both; }
@keyframes modal-in {
  from { opacity: 0; transform: translateY(18px) scale(.97); }
  to   { opacity: 1; transform: none; }
}
@media (prefers-reduced-motion: reduce) {
  .modal-in { animation: none; }
}
</style>
