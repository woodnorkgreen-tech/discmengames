<template>
  <!-- Compact variant: used inline during trivia reveal, where vertical space is tight. -->
  <section v-if="compact" class="flex h-full w-full flex-col" aria-live="polite">
    <div class="mb-3 flex items-center justify-between gap-4 lg:mb-4">
      <h3 class="font-black uppercase tracking-[.18em] text-gray-400"
        style="font-size: clamp(0.65rem, 1.2vw, 1.2rem)">
        {{ title }}
      </h3>
      <p class="rounded-full border border-white/10 bg-black/20 px-3 py-1 font-bold text-gray-500"
        style="font-size: clamp(.55rem,.8vw,.8rem)">
        Top {{ entries.length }} · Live
      </p>
    </div>

    <TransitionGroup name="leaderboard" tag="div" class="grid grid-cols-3 gap-2 lg:gap-4">
      <article v-for="entry in podium" :key="entryKey(entry)"
        class="relative min-w-0 overflow-hidden rounded-2xl border px-3 py-3 text-center lg:rounded-3xl lg:px-5 lg:py-5"
        :class="podiumClass(entry.rank)">
        <div class="mx-auto mb-1 flex h-8 w-8 items-center justify-center rounded-full bg-black/20 text-xl lg:h-11 lg:w-11 lg:text-3xl">
          {{ medal(entry.rank) }}
        </div>
        <p class="truncate font-black text-white" style="font-size: clamp(.8rem,1.7vw,2rem)">
          {{ entry.nickname }}
        </p>
        <p class="mt-1 font-black tabular-nums text-visa-gold" style="font-size: clamp(1rem,2.2vw,2.7rem)">
          {{ score(entry).toLocaleString() }} <span class="text-[.45em] uppercase tracking-wider text-white/40">pts</span>
        </p>
      </article>
    </TransitionGroup>

    <TransitionGroup v-if="standings.length" name="leaderboard" tag="div"
      class="mt-2 grid min-h-0 flex-1 grid-cols-2 content-start gap-2 overflow-hidden lg:mt-3 lg:gap-3">
      <article v-for="entry in standings" :key="entryKey(entry)"
        class="flex min-w-0 items-center gap-3 rounded-xl border border-white/10 bg-white/[.055] px-3 py-2 lg:rounded-2xl lg:px-5 lg:py-3">
        <span class="w-7 shrink-0 text-center font-black tabular-nums text-gray-500"
          style="font-size: clamp(.8rem,1.3vw,1.4rem)">{{ entry.rank }}</span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-bold text-white" style="font-size: clamp(.75rem,1.35vw,1.45rem)">
            {{ entry.nickname }}
          </p>
        </div>
        <span class="shrink-0 font-black tabular-nums text-visa-gold"
          style="font-size: clamp(.8rem,1.45vw,1.6rem)">{{ score(entry).toLocaleString() }}</span>
      </article>
    </TransitionGroup>

    <div v-if="!entries.length" class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-white/10 text-gray-600">
      Scores will appear here
    </div>
  </section>

  <!-- Full variant: dedicated leaderboard screens. -->
  <section v-else class="leaderboard-columns grid h-full w-full overflow-hidden rounded-3xl border border-white/10 bg-white/[.025]" aria-live="polite">
    <div class="flex min-h-0 flex-col items-center justify-center border-b border-white/10 px-5 py-5 text-center lg:border-b-0 lg:border-r lg:px-7 lg:py-6">
      <div v-if="winner" class="flex shrink-0 flex-col items-center">
        <div class="visa-winner-card" aria-label="Visa Final Whistle winner card">
          <span class="visa-card-orbit visa-card-orbit-one"></span>
          <span class="visa-card-orbit visa-card-orbit-two"></span>
          <div class="relative z-10 flex h-full flex-col justify-between text-left">
            <div class="flex items-start justify-between gap-4">
              <span class="visa-card-chip" aria-hidden="true"></span>
              <img src="/images/visa-logo.svg" alt="Visa" class="w-[30%] min-w-16 object-contain" />
            </div>
            <div>
              <p class="text-[clamp(.5rem,.7vw,.72rem)] font-black uppercase tracking-[.24em] text-white/55">Final Whistle</p>
              <div class="mt-1 flex items-end justify-between gap-3">
                <p class="truncate text-[clamp(.85rem,1.25vw,1.3rem)] font-black uppercase tracking-[.08em] text-white">Winner</p>
                <span class="text-[clamp(.8rem,1.3vw,1.4rem)]" aria-hidden="true">🏆</span>
              </div>
            </div>
          </div>
        </div>
        <p class="font-bold uppercase tracking-[.3em] text-gray-500" style="font-size: clamp(.6rem,.9vw,.9rem)">
          Champion
        </p>
        <p class="mt-2 max-w-full truncate font-black text-white" style="font-size: clamp(1.35rem,2.4vw,2.8rem)">
          {{ winner.nickname }}
        </p>
        <p class="mt-1 font-black leading-none tabular-nums text-visa-gold" style="font-size: clamp(1.8rem,3.2vw,3.8rem)">
          {{ score(winner).toLocaleString() }}
        </p>
        <p class="mt-1 font-bold uppercase tracking-widest text-gray-500" style="font-size: clamp(.55rem,.8vw,.85rem)">
          points
        </p>
      </div>

      <div v-else class="flex shrink-0 items-center justify-center text-center text-gray-600">
        Scores will appear here
      </div>

    </div>

    <div class="flex min-h-0 flex-col px-4 py-4 lg:px-6 lg:py-5">
      <div class="mb-2 flex flex-shrink-0 items-center justify-between gap-4 border-b border-white/10 pb-3 lg:mb-3">
        <span class="flex items-center gap-2 font-bold uppercase tracking-widest text-gray-500"
          style="font-size: clamp(.6rem,.85vw,.85rem)">
          <span class="h-2 w-2 rounded-full bg-visa-gold"></span> Live standings
        </span>
        <span class="font-bold text-gray-600" style="font-size: clamp(.55rem,.8vw,.8rem)">
          {{ entries.length }} ranked players
        </span>
      </div>

      <TransitionGroup v-if="entries.length" name="leaderboard" tag="div"
        class="leaderboard-scroll min-h-0 flex-1 overflow-y-auto pr-1"
        style="min-height: calc(12 * clamp(2.15rem, 3.45vh, 3rem))">
        <article v-for="entry in entries" :key="entryKey(entry)"
          class="flex min-w-0 items-center gap-3 border-b border-white/5 px-1 last:border-b-0"
          style="min-height: clamp(2.15rem, 3.45vh, 3rem)">
          <span class="w-8 shrink-0 text-center font-black tabular-nums" :class="rankColor(entry.rank)"
            style="font-size: clamp(.75rem,1.05vw,1.1rem)">
            {{ entry.rank }}
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate font-bold text-white" style="font-size: clamp(.72rem,1.05vw,1.15rem)">
              {{ entry.nickname }}
            </p>
          </div>
          <span class="shrink-0 font-black tabular-nums text-visa-gold"
            style="font-size: clamp(.75rem,1.15vw,1.25rem)">{{ score(entry).toLocaleString() }}</span>
        </article>
      </TransitionGroup>

      <div v-else class="flex flex-1 items-center justify-center text-gray-600">
        Scores will appear here
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  entries: { type: Array, default: () => [] },
  title: { type: String, default: 'Leaderboard' },
  compact: { type: Boolean, default: false },
})

const podium = computed(() => props.entries.slice(0, 3))
const standings = computed(() => props.entries.slice(3, 10))
// No entry has been scored yet (e.g. predictions before the match result is
// submitted) — don't crown a "champion" out of players who all sit at 0.
const winner = computed(() => {
  const top = props.entries[0]
  return top && score(top) > 0 ? top : null
})

function entryKey(entry) { return entry.id ?? `${entry.nickname}-${entry.rank}` }
function score(entry) { return Number(entry.round_score ?? entry.trivia_score ?? entry.prediction_score ?? 0) }
function medal(rank) { return ['🥇', '🥈', '🥉'][rank - 1] ?? rank }
function podiumClass(rank) {
  if (rank === 1) return 'border-visa-gold/50 bg-gradient-to-b from-visa-gold/20 to-white/5 shadow-[0_0_35px_rgba(247,182,0,.12)]'
  if (rank === 2) return 'border-white/20 bg-gradient-to-b from-white/15 to-white/5'
  return 'border-amber-700/30 bg-gradient-to-b from-amber-700/15 to-white/5'
}
function rankColor(rank) {
  if (rank === 1) return 'text-visa-gold'
  if (rank === 2) return 'text-gray-300'
  if (rank === 3) return 'text-amber-600'
  return 'text-gray-500'
}
</script>

<style scoped>
.leaderboard-move { transition: transform .45s cubic-bezier(.2,.8,.2,1); }
.leaderboard-enter-active, .leaderboard-leave-active { transition: all .3s ease; }
.leaderboard-enter-from { opacity: 0; transform: translateY(12px) scale(.98); }
.leaderboard-leave-to { opacity: 0; transform: translateY(-8px); }
.leaderboard-columns { grid-template-columns: minmax(13rem, 30%) 1fr; }
.visa-winner-card {
  position: relative;
  width: clamp(11rem, 19vw, 18rem);
  aspect-ratio: 1.586 / 1;
  margin-bottom: clamp(.75rem, 1.5vh, 1.25rem);
  overflow: hidden;
  border: 1px solid rgba(247,182,0,.48);
  border-radius: clamp(1rem, 1.5vw, 1.4rem);
  padding: clamp(.85rem, 1.4vw, 1.35rem);
  background: linear-gradient(145deg, #1434cb 0%, #1a1f71 54%, #080d43 100%);
  box-shadow: 0 22px 55px rgba(0,0,0,.38), 0 0 34px rgba(247,182,0,.1);
  transform: perspective(700px) rotateX(2deg);
}
.visa-card-chip {
  display: block;
  width: 17%;
  aspect-ratio: 1.25;
  border: 1px solid rgba(255,255,255,.38);
  border-radius: .3rem;
  background: linear-gradient(135deg, #ffe59a, #c99a24 48%, #f7d66a);
  box-shadow: inset 0 0 0 1px rgba(80,55,0,.18);
}
.visa-card-orbit { position: absolute; border: 1px solid rgba(247,182,0,.2); border-radius: 999px; }
.visa-card-orbit-one { width: 80%; aspect-ratio: 1; right: -34%; top: -42%; }
.visa-card-orbit-two { width: 66%; aspect-ratio: 1; right: -25%; top: -27%; }
.leaderboard-scroll { scrollbar-width: thin; scrollbar-color: rgba(247,182,0,.5) rgba(255,255,255,.06); }
.leaderboard-scroll::-webkit-scrollbar { width: 7px; }
.leaderboard-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.05); border-radius: 999px; }
.leaderboard-scroll::-webkit-scrollbar-thumb { background: rgba(247,182,0,.5); border-radius: 999px; }
@media (max-width: 900px), (orientation: portrait) {
  .leaderboard-columns { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
}
@media (prefers-reduced-motion: reduce) {
  .leaderboard-move, .leaderboard-enter-active, .leaderboard-leave-active { transition: none; }
}
</style>
