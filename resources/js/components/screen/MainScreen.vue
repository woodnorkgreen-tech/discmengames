<template>
  <!-- screen-root fixes the view to the viewport and prevents any scrolling on TV browsers -->
  <div class="screen-root screen-brand-bg text-white font-display select-none flex flex-col" :class="`phase-${phase}`">

    <div class="fixed right-3 top-3 z-50 flex items-center gap-2 print:hidden">
      <span v-if="linkMessage" class="rounded-full bg-black/70 px-3 py-2 text-xs font-bold text-white backdrop-blur">{{ linkMessage }}</span>
      <button type="button" @click="copyScreenLink" title="Copy public Main Screen link"
        class="rounded-full border border-white/15 bg-black/55 px-3 py-2 text-xs font-bold text-white backdrop-blur hover:bg-black/75 focus:outline-none focus:ring-2 focus:ring-discmen-accent">
        🔗 <span class="hidden sm:inline">Public link</span>
      </button>
      <button type="button" @click="toggleFullscreen" :title="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'"
        class="rounded-full border border-white/15 bg-black/55 px-3 py-2 text-xs font-bold text-white backdrop-blur hover:bg-black/75 focus:outline-none focus:ring-2 focus:ring-discmen-accent">
        {{ isFullscreen ? '✕' : '⛶' }} <span class="hidden sm:inline">{{ isFullscreen ? 'Exit' : 'Fullscreen' }}</span>
      </button>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         LOBBY / PREDICTIONS
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-if="['lobby', 'predictions_open', 'predictions_closed'].includes(phase)">
      <div class="flex-1 flex flex-col items-center justify-center px-8 lg:px-16 text-center">

        <!-- Title — clamp scales from small monitors to 4K -->
        <p class="mb-3 font-black uppercase tracking-[.32em] text-[#61C8D2]"
          style="font-size: clamp(.65rem, 1.05vw, 1.25rem)">Discmen Entertainment presents</p>
        <h1 class="font-black italic uppercase text-white tracking-tight leading-none mb-3"
          style="font-size: clamp(3rem, 7vw, 9rem)">
          <span>Final Whistle</span>
        </h1>
        <div class="mb-8 flex items-center justify-center lg:mb-12">
          <span class="rounded-full border border-[#61C8D2]/25 bg-black/30 px-6 py-2 font-black uppercase tracking-[.18em] text-[#61C8D2] backdrop-blur"
            style="font-size: clamp(.75rem, 1.35vw, 1.55rem)">The live football fan experience</span>
        </div>

        <!-- Broadcast layout: join left, event status centre, recent activity right -->
        <div class="broadcast-grid mb-6 grid w-full grid-cols-[minmax(12rem,.85fr)_minmax(18rem,1.25fr)_minmax(15rem,1fr)] items-center gap-6 lg:gap-10">
          <!-- LEFT: QR — large enough to scan from across the room -->
          <div class="flex flex-col items-center flex-shrink-0">
            <div class="client-qr-stage">
              <div class="client-qr-frame">
                <canvas ref="qrCanvas" :width="qrSize" :height="qrSize" class="client-qr-code"></canvas>
              </div>
            </div>
            <p class="font-black uppercase tracking-[.04em] text-white leading-tight mt-4"
              style="font-size: clamp(1.3rem, 2.4vw, 3.2rem)">
              Scan to play
            </p>
            <p class="text-gray-400 mt-1" style="font-size: clamp(0.8rem, 1.2vw, 1.6rem)">
              Register &amp; make your predictions
            </p>
          </div>

          <!-- CENTRE: kick-off and prediction state remain on the screen axis -->
          <div class="flex-1 flex flex-col items-center text-center">
            <div class="rounded-2xl border px-6 py-3"
              :class="phase === 'predictions_closed' ? 'border-orange-400/30 bg-orange-400/10' : 'border-discmen-accent/30 bg-discmen/20'">
              <p class="font-black uppercase tracking-widest"
                :class="phase === 'predictions_closed' ? 'text-orange-400' : 'text-discmen-accent'"
                style="font-size: clamp(.65rem, 1vw, 1rem)">
                {{ phase === 'predictions_closed' ? '🔒 Predictions closed' : '● Predictions open' }}
              </p>
              <p class="mt-1 font-black tabular-nums text-white" style="font-size: clamp(1.1rem, 2.1vw, 2.7rem)">
                {{ predictionCount }} locked in
              </p>
            </div>
            <div v-if="phase === 'predictions_open'" class="mt-4 max-w-xl rounded-xl border border-discmen-accent/25 bg-discmen-accent/10 px-4 py-3">
              <p class="font-black uppercase tracking-widest text-discmen-accent" style="font-size: clamp(.6rem,.9vw,.9rem)">Prediction rule</p>
              <p class="mt-1 font-semibold leading-snug text-white" style="font-size: clamp(.8rem,1.25vw,1.35rem)">
                Final score means the score after 90 minutes + stoppage time.
              </p>
              <p class="mt-1 text-gray-400" style="font-size: clamp(.65rem,.95vw,1rem)">Extra time and penalty shootouts do not count.</p>
            </div>
            <div v-if="match.kickoff_at && kickoffCountdown" class="mt-4 w-full max-w-lg rounded-2xl border border-white/10 bg-black/30 px-6 py-4">
              <p class="text-gray-500 uppercase tracking-widest" style="font-size: clamp(.6rem, 1vw, 1rem)">Kick-off countdown</p>
              <p class="font-black tabular-nums text-white" style="font-size: clamp(1.35rem, 2.8vw, 3.6rem)">{{ kickoffCountdown }}</p>
              <p class="text-gray-500" style="font-size: clamp(.6rem, .9vw, .9rem)">{{ match.venue }}</p>
            </div>
          </div>

          <!-- RIGHT: latest locked-in entries, newest first -->
          <aside class="broadcast-latest min-w-0 rounded-2xl border border-white/10 bg-black/30 p-4 text-left lg:p-5">
            <div class="mb-3 flex items-center justify-between gap-3 border-b border-white/10 pb-3">
              <div>
                <p class="font-black uppercase tracking-widest text-white" style="font-size: clamp(.65rem,1vw,1rem)">Latest locked in</p>
                <p class="mt-0.5 text-gray-500" style="font-size: clamp(.55rem,.8vw,.8rem)">{{ predictionFeed.length }} players · newest first</p>
              </div>
              <span class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full bg-discmen-accent"></span>
            </div>
            <TransitionGroup v-if="predictionFeed.length" name="ticker" tag="ol" class="prediction-feed-scroll max-h-[42vh] space-y-2 overflow-y-auto overscroll-contain pr-1">
              <li v-for="(entry, idx) in predictionFeed" :key="entry.id"
                class="flex min-w-0 items-center gap-3 rounded-xl border border-white/5 bg-white/5 px-3 py-2.5">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-discmen/30 text-xs font-black text-discmen-accent">{{ idx + 1 }}</span>
                <div class="min-w-0">
                  <p class="truncate font-bold text-white" style="font-size: clamp(.75rem,1.2vw,1.3rem)">{{ entry.nickname }}</p>
                  <p class="text-gray-500" style="font-size: clamp(.55rem,.75vw,.75rem)">Prediction locked ✓</p>
                </div>
              </li>
            </TransitionGroup>
            <p v-else class="py-8 text-center text-sm text-gray-600">Waiting for the first prediction…</p>
          </aside>
        </div>

        <p class="mt-6 text-gray-700" style="font-size: clamp(0.75rem, 1.2vw, 1.5rem)">
          {{ playerCount }} players registered
        </p>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         TRIVIA LIVE
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="roundsEnabled && phase === 'trivia_live' && !question && round?.status === 'live'">
      <div class="phase-enter flex-1 flex flex-col items-center justify-center px-10 text-center">
        <p class="font-black uppercase tracking-[.3em] text-discmen-accent" style="font-size: clamp(.9rem,1.5vw,1.7rem)">Round {{ round.number }} of {{ round.total }}</p>
        <div class="my-7 flex h-28 w-28 items-center justify-center rounded-full border border-discmen-accent/30 bg-discmen/20 text-6xl lg:h-36 lg:w-36 lg:text-7xl">{{ round.number === 2 ? '⚽' : '⚡' }}</div>
        <h2 class="font-black text-white" style="font-size: clamp(3rem,7vw,8rem)">{{ round.title }}</h2>
        <p class="mt-5 max-w-4xl text-gray-300" style="font-size: clamp(1rem,2vw,2.4rem)">{{ round.intro_message }}</p>
        <p class="mt-8 font-bold uppercase tracking-widest text-white/40" style="font-size: clamp(.8rem,1.2vw,1.3rem)">{{ questionProgress.total }} questions · One Power Question</p>
      </div>
    </template>

    <template v-else-if="phase === 'trivia_live' && question">
      <div class="phase-enter flex-1 flex flex-col px-8 lg:px-16 pt-6 lg:pt-10 pb-4">

        <!-- Top bar: double-points badge + huge countdown -->
        <div class="flex items-center justify-between mb-6 lg:mb-8 flex-shrink-0">
          <div class="flex items-center gap-4">
          <span class="rounded-full border border-white/10 bg-black/20 px-4 py-2 font-bold uppercase tracking-widest text-gray-300"
            style="font-size: clamp(0.65rem, 1.1vw, 1.1rem)">
            <template v-if="roundsEnabled">Round {{ round.number }} / {{ round.total }} · {{ round.title }}</template>
            <template v-else>Question {{ round.current }} / {{ round.total }}</template>
          </span>
          <span v-if="question.is_double_points"
            class="bg-discmen-accent text-black font-black uppercase tracking-widest animate-pulse rounded-full px-5 py-2"
            style="font-size: clamp(0.75rem, 1.5vw, 1.5rem)">
            ⚡ DOUBLE POINTS ⚡
          </span>
          <span v-else class="text-gray-700" style="font-size: clamp(0.75rem, 1.2vw, 1.2rem)">
            Question {{ questionProgress.current || question.order_index }} / {{ questionProgress.total || round.total }}
          </span>
          </div>

          <!-- Countdown ring — viewport-sized so it's always visible from the back row -->
          <div class="flex items-center gap-4 lg:gap-7">
            <div class="text-right">
              <Transition name="count" mode="out-in">
                <p :key="question.answer_count" class="font-black text-discmen-accent tabular-nums" style="font-size: clamp(1.4rem, 3vw, 3.5rem)">{{ question.answer_count ?? 0 }}</p>
              </Transition>
              <p class="text-gray-500 uppercase tracking-wider" style="font-size: clamp(.55rem, .9vw, .9rem)">Answers live</p>
            </div>
          <div class="relative flex-shrink-0" :style="{ width: timerSize, height: timerSize }">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="52" fill="none" stroke="#1f2937" stroke-width="8" />
              <circle cx="60" cy="60" r="52" fill="none"
                :stroke="timerColor"
                stroke-width="8"
                stroke-linecap="round"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                style="transition: stroke-dashoffset 1s linear;" />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center font-black"
              :class="timeLeft <= 5 ? 'text-red-400 animate-pulse' : 'text-white'"
              style="font-size: clamp(1.5rem, 4vw, 5rem)">
              {{ timeLeft }}
            </span>
          </div>
          </div>
        </div>

        <!-- Question text — the most important thing on the screen -->
        <p class="font-bold text-center leading-tight flex-1 flex items-center justify-center"
          style="font-size: clamp(1.5rem, 3.5vw, 5rem)">
          {{ question.text }}
        </p>

        <!-- Answer options grid -->
        <div class="grid gap-4 lg:gap-5 mt-6 flex-shrink-0"
          :class="question.options.length === 2 ? 'grid-cols-2' : 'grid-cols-2'">
          <div v-for="(opt, idx) in question.options" :key="idx"
            class="bg-white/10 border border-white/20 rounded-2xl flex items-center gap-4 px-5 lg:px-8 py-4 lg:py-6">
            <span class="rounded-full bg-white/20 font-black flex items-center justify-center flex-shrink-0"
              style="font-size: clamp(0.875rem, 1.8vw, 2rem);
                     width: clamp(2rem, 4vw, 4.5rem);
                     height: clamp(2rem, 4vw, 4.5rem)">
              {{ optLabels[idx] }}
            </span>
            <span class="font-semibold leading-snug" style="font-size: clamp(0.875rem, 2vw, 2.5rem)">
              {{ opt }}
            </span>
          </div>
        </div>

      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         TRIVIA REVEAL
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="phase === 'trivia_reveal' && question">
      <div class="flex-1 flex flex-col justify-center px-8 lg:px-16 pt-8 pb-4">
        <p class="text-center text-gray-400 mb-4 flex-shrink-0"
          style="font-size: clamp(0.875rem, 1.5vw, 1.8rem)">
          Answer Revealed
        </p>
        <p class="text-center font-bold mb-8 flex-shrink-0"
          style="font-size: clamp(1.25rem, 2.5vw, 3.5rem)">
          {{ question.text }}
        </p>

        <div class="grid gap-4 flex-shrink-0"
          :class="question.options.length === 2 ? 'grid-cols-2' : 'grid-cols-2'">
          <div v-for="(opt, idx) in question.options" :key="idx"
            :class="opt === question.correct_answer
              ? 'bg-discmen-accent/30 border-discmen-accent text-white'
              : 'bg-white/5 border-white/10 text-gray-500'"
            class="border-2 rounded-2xl flex items-center gap-4 px-5 lg:px-8 py-4 lg:py-5 transition-all">
            <span class="text-2xl lg:text-3xl flex-shrink-0">
              {{ opt === question.correct_answer ? '✅' : '' }}
            </span>
            <span class="font-semibold leading-snug"
              :class="opt === question.correct_answer ? 'font-black text-white' : ''"
              style="font-size: clamp(0.875rem, 2vw, 2.5rem)">
              {{ opt }}
            </span>
            <div class="ml-auto shrink-0 text-right">
              <p class="font-black tabular-nums" :class="opt === question.correct_answer ? 'text-discmen-accent' : 'text-gray-500'"
                style="font-size: clamp(.8rem, 1.5vw, 1.7rem)">{{ optionCount(opt) }}</p>
              <p class="text-gray-600" style="font-size: clamp(.55rem, .8vw, .8rem)">{{ optionPercent(opt) }}%</p>
            </div>
          </div>
        </div>

        <p class="mt-10 text-center font-bold uppercase tracking-widest text-white/40 flex-shrink-0"
          style="font-size: clamp(.7rem, 1.1vw, 1.2rem)">
          Standings revealed when the round ends
        </p>
      </div>
    </template>

    <template v-else-if="roundsEnabled && phase === 'trivia_reveal' && !question && round?.status === 'completed'">
      <div class="phase-enter flex-1 flex flex-col px-8 lg:px-16 pt-6 lg:pt-8 pb-4 overflow-hidden">
        <div class="mb-4 flex shrink-0 items-end justify-between gap-5">
          <div>
            <p class="font-black uppercase tracking-[.22em] text-discmen-accent" style="font-size: clamp(.7rem,1.1vw,1.1rem)">Round {{ round.number }} champion</p>
            <h2 class="mt-1 font-black text-white" style="font-size: clamp(1.7rem,3vw,3.8rem)">{{ round.title }}</h2>
          </div>
          <p class="text-right text-gray-400" style="font-size: clamp(.7rem,1vw,1rem)">Round points contribute to the overall trivia total</p>
        </div>
        <div class="min-h-0 flex-1">
          <Leaderboard :entries="roundLeaderboard" :title="`${round.title} standings`" />
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         TRIVIA COMPLETE
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="phase === 'trivia_complete'">
      <div class="phase-enter flex-1 flex flex-col px-8 lg:px-16 pt-6 lg:pt-8 pb-4 overflow-hidden">
        <h2 class="flex-shrink-0 text-center font-light uppercase tracking-[.15em] text-discmen-accent mb-3 lg:mb-4"
          style="font-size: clamp(1.1rem, 2.2vw, 2.4rem)">
          TRIVIA LEADERBOARD
        </h2>
        <div class="min-h-0 flex-1">
          <Leaderboard :entries="leaderboard" title="Trivia leaderboard" />
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         MATCH ENDED / PREDICTION REVEAL
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-else-if="['match_ended', 'prediction_reveal'].includes(phase)">
      <div class="flex-1 flex flex-col px-8 lg:px-16 pt-6 lg:pt-8 pb-4 overflow-hidden">
        <h2 class="flex-shrink-0 text-center font-light uppercase tracking-[.15em] text-discmen-accent mb-3 lg:mb-4"
          style="font-size: clamp(1.1rem, 2.2vw, 2.4rem)">
          PREDICTIONS LEADERBOARD
        </h2>
        <div class="min-h-0 flex-1">
          <Leaderboard :entries="leaderboard" title="Prediction leaderboard" />
        </div>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         FALLBACK
    ══════════════════════════════════════════════════════════════════════ -->
    <template v-else>
      <div class="flex-1 flex items-center justify-center">
        <p class="text-white font-black italic uppercase" style="font-size: clamp(1.5rem, 4vw, 5rem)">
          <span>Discmen Final Whistle</span>
        </p>
      </div>
    </template>

    <!-- ══════════════════════════════════════════════════════════════════════
         BOTTOM BRAND STRIP
    ══════════════════════════════════════════════════════════════════════ -->
    <div class="client-brand-strip flex-shrink-0 flex items-center justify-center gap-4 lg:gap-8 px-8"
      style="height: clamp(3.4rem, 6vh, 6.5rem)">
      <span class="screen-client-logo discmen-logo-tile" aria-label="Discmen Entertainment">
        <img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" />
      </span>
      <span class="text-white/30">·</span>
      <span class="text-white font-semibold tracking-widest opacity-80"
        style="font-size: clamp(0.6rem, 1.2vw, 1.2rem)">ARGENTINA vs SPAIN · FINAL</span>
    </div>

    <!-- Connection error -->
    <div v-if="error"
      class="fixed bottom-20 right-4 bg-red-600 text-white text-xs px-3 py-1.5 rounded-full opacity-80">
      {{ error }}
    </div>

  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import QRCode from 'qrcode'
import axios from 'axios'
import { useEventState } from '../../composables/useEventState'
import Leaderboard from './Leaderboard.vue'

const { phase, question, leaderboard, roundLeaderboard, playerCount, predictionCount, recentPredictions, match, round, questionProgress, roundsEnabled, error } = useEventState(1500)

// ── QR Code — size scales with viewport, capped for readability ───────────────
const qrCanvas = ref(null)
// Guests join via the same host that serves this screen. APP_URL (the meta tag)
// may be a dev hostname that venue phones cannot resolve — never encode it in the QR.
const appUrl   = window.location.origin
const screenUrl = `${window.location.origin}/screen`
const isFullscreen = ref(!!document.fullscreenElement)
const linkMessage = ref('')
const predictionFeed = ref([])
let linkMessageTimer = null
let predictionFeedTimer = null

async function loadPredictionFeed() {
  try {
    const { data } = await axios.get('/api/predictions/feed')
    predictionFeed.value = data.entries ?? []
  } catch {
    // Shared event polling continues; retain the last successful feed on transient errors.
  }
}

async function copyScreenLink() {
  try {
    await navigator.clipboard.writeText(screenUrl)
    linkMessage.value = 'Screen link copied'
  } catch {
    window.prompt('Copy the public Main Screen link:', screenUrl)
    linkMessage.value = 'Public link ready'
  }
  clearTimeout(linkMessageTimer)
  linkMessageTimer = setTimeout(() => { linkMessage.value = '' }, 2500)
}

async function toggleFullscreen() {
  try {
    if (document.fullscreenElement) await document.exitFullscreen()
    else await document.documentElement.requestFullscreen()
  } catch {
    linkMessage.value = 'Fullscreen is blocked by this browser'
  }
}

function syncFullscreen() { isFullscreen.value = !!document.fullscreenElement }

// QR canvas: ~20% of the smaller viewport dimension, min 160, max 320
const qrSize = computed(() =>
  Math.min(Math.max(Math.round(Math.min(window.innerWidth, window.innerHeight) * 0.2), 160), 320)
)

onMounted(() => {
  document.addEventListener('fullscreenchange', syncFullscreen)
  loadPredictionFeed()
  predictionFeedTimer = setInterval(loadPredictionFeed, 3000)
  if (qrCanvas.value) {
    QRCode.toCanvas(qrCanvas.value, appUrl, {
      width:  qrSize.value,
      margin: 1,
      color: { dark: '#000000', light: '#ffffff' },
    })
  }
})

// ── Trivia timer ──────────────────────────────────────────────────────────────
const optLabels     = ['A', 'B', 'C', 'D']
const circumference = 2 * Math.PI * 52   // matches r="52" in the SVG

// Countdown ring: 10vw wide, capped at 160px so it doesn't eat the layout
const timerSize = `clamp(5rem, 10vw, 10rem)`

const timeLeft   = ref(0)
const nowTick = ref(Date.now())
const kickoffCountdown = computed(() => {
  if (!match.value?.kickoff_at) return ''
  const remaining = Math.max(0, new Date(match.value.kickoff_at).getTime() - nowTick.value)
  if (remaining <= 0) return 'KICK-OFF TIME'
  const seconds = Math.floor(remaining / 1000)
  const days = Math.floor(seconds / 86400)
  const hours = Math.floor((seconds % 86400) / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60
  return [days ? `${days}d` : null, `${String(hours).padStart(2, '0')}h`, `${String(minutes).padStart(2, '0')}m`, `${String(secs).padStart(2, '0')}s`].filter(Boolean).join(' ')
})
const totalQuestionAnswers = computed(() => Object.values(question.value?.answer_distribution ?? {}).reduce((sum, value) => sum + Number(value), 0))
function optionCount(option) { return Number(question.value?.answer_distribution?.[option] ?? 0) }
function optionPercent(option) { return totalQuestionAnswers.value ? Math.round(optionCount(option) / totalQuestionAnswers.value * 100) : 0 }
const dashOffset = computed(() => {
  const total = question.value?.duration_seconds ?? 1
  return circumference * (1 - timeLeft.value / total)
})
const timerColor = computed(() => {
  const total = question.value?.duration_seconds ?? 1
  const ratio = timeLeft.value / total
  if (ratio > 0.5) return '#61C8D2'
  if (ratio > 0.25) return '#61C8D2'
  return '#C8102E'
})

let triviaTimer = null
let clockTimer = null

onMounted(() => { clockTimer = setInterval(() => { nowTick.value = Date.now() }, 1000) })

watch(question, (q) => {
  clearInterval(triviaTimer)
  if (q && phase.value === 'trivia_live') {
    timeLeft.value = q.seconds_remaining ?? q.duration_seconds
    triviaTimer = setInterval(() => {
      if (timeLeft.value > 0) timeLeft.value--
      else clearInterval(triviaTimer)
    }, 1000)
  }
}, { immediate: true })

onUnmounted(() => {
  clearInterval(triviaTimer)
  clearInterval(clockTimer)
  clearTimeout(linkMessageTimer)
  clearInterval(predictionFeedTimer)
  document.removeEventListener('fullscreenchange', syncFullscreen)
})
</script>

<style scoped>
.client-qr-stage {
  position: relative;
  z-index: 0;
  padding: clamp(.3rem, .7vw, .7rem);
}
.client-qr-stage::before,
.client-qr-stage::after {
  content: '';
  position: absolute;
  z-index: -1;
  pointer-events: none;
}
.client-qr-stage::before {
  inset: 4% -7%;
  border: clamp(3px, .42vw, 7px) solid rgba(97, 200, 210, .82);
  border-radius: clamp(1.7rem, 2.6vw, 3rem);
  transform: rotate(-2deg);
  box-shadow: 0 0 38px rgba(97, 200, 210, .22);
}
.client-qr-stage::after {
  width: 42%;
  height: 18%;
  right: -12%;
  bottom: 4%;
  background: repeating-linear-gradient(-18deg, rgba(97, 200, 210, .95) 0 5px, transparent 6px 11px);
  transform: skewX(-18deg);
  opacity: .8;
}
.client-qr-frame {
  position: relative;
  padding: clamp(.65rem, 1vw, 1.05rem);
  overflow: hidden;
  border: clamp(4px, .5vw, 9px) solid #fff;
  border-radius: clamp(1.5rem, 2.3vw, 2.7rem);
  background: var(--discmen-black);
  box-shadow: 0 24px 60px rgba(0, 0, 0, .48), inset 0 0 0 2px rgba(97, 200, 210, .13);
}
.client-qr-code {
  display: block;
  max-width: min(20vw, 320px);
  height: auto;
  border-radius: clamp(.5rem, .8vw, .9rem);
}
.client-brand-strip {
  border-top: 1px solid rgba(97, 200, 210, .22);
  background: linear-gradient(90deg, #100f0d 0%, #173f44 42%, #147783 70%, #100f0d 100%);
  box-shadow: 0 -12px 35px rgba(0, 0, 0, .28);
}
.screen-client-logo {
  width: clamp(3.8rem, 6vw, 7rem);
  height: clamp(2.8rem, 4.8vh, 5rem);
  padding: clamp(.2rem, .35vw, .45rem);
  border-radius: clamp(.55rem, .8vw, .9rem);
  box-shadow: 0 0 28px rgba(97, 200, 210, .16);
}
.screen-brand-bg {
  isolation: isolate;
  background-color: #100f0d;
  background-image:
    linear-gradient(90deg, rgba(7, 11, 10, .9) 0%, rgba(15, 55, 59, .68) 54%, rgba(9, 30, 31, .38) 100%),
    linear-gradient(180deg, rgba(7, 10, 9, .46) 0%, transparent 51%, rgba(7, 10, 9, .74) 100%),
    url('/images/backgrounds/discmenbg.jpeg');
  background-size: cover;
  background-position: center;
  box-shadow: inset 0 0 180px rgba(0, 0, 0, .48);
}
.screen-brand-bg::before {
  content: '';
  position: absolute;
  inset: -20%;
  z-index: -1;
  pointer-events: none;
  background:
    radial-gradient(circle at 12% 34%, rgba(97, 200, 210, .22), transparent 27%),
    radial-gradient(circle at 82% 22%, rgba(255, 255, 255, .1), transparent 26%);
  animation: stadium-lights 12s ease-in-out infinite alternate;
}
.screen-brand-bg::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  opacity: .14;
  background: repeating-linear-gradient(115deg, transparent 0 105px, rgba(255,255,255,.08) 106px, transparent 108px);
  mix-blend-mode: screen;
}
.screen-brand-bg.phase-lobby,
.screen-brand-bg.phase-predictions_open,
.screen-brand-bg.phase-predictions_closed {
  background-image:
    linear-gradient(90deg, rgba(7, 11, 10, .8) 0%, rgba(15, 55, 59, .52) 52%, rgba(9, 30, 31, .2) 100%),
    linear-gradient(180deg, rgba(7, 10, 9, .34), transparent 58%, rgba(7, 10, 9, .6)),
    url('/images/backgrounds/discmenbg.jpeg');
}
.screen-brand-bg.phase-trivia_live,
.screen-brand-bg.phase-trivia_reveal,
.screen-brand-bg.phase-trivia_complete,
.screen-brand-bg.phase-prediction_reveal,
.screen-brand-bg.phase-match_ended {
  background-image:
    linear-gradient(90deg, rgba(7, 11, 10, .95), rgba(15, 55, 59, .82) 58%, rgba(9, 30, 31, .64)),
    linear-gradient(180deg, rgba(7, 10, 9, .62), transparent 48%, rgba(7, 10, 9, .84)),
    url('/images/backgrounds/discmenbg.jpeg');
}
.ticker-enter-active, .ticker-leave-active { transition: all 0.5s ease; }
.ticker-enter-from { opacity: 0; transform: translateY(14px); }
.ticker-leave-to   { opacity: 0; transform: translateY(-14px); }
.prediction-feed-scroll { scrollbar-width: thin; scrollbar-color: rgba(97,200,210,.65) rgba(255,255,255,.06); }
.prediction-feed-scroll::-webkit-scrollbar { width: 7px; }
.prediction-feed-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.05); border-radius: 999px; }
.prediction-feed-scroll::-webkit-scrollbar-thumb { background: rgba(97,200,210,.65); border-radius: 999px; }
.phase-enter { animation: phase-in .45s cubic-bezier(.2,.8,.2,1) both; }
.count-enter-active, .count-leave-active { transition: all .2s ease; }
.count-enter-from { opacity: 0; transform: translateY(12px) scale(1.15); }
.count-leave-to { opacity: 0; transform: translateY(-10px); }
@media (max-width: 900px), (orientation: portrait) {
  .broadcast-grid { grid-template-columns: minmax(11rem,.8fr) minmax(17rem,1.2fr); align-items: center; }
  .broadcast-latest { grid-column: 1 / -1; }
  .broadcast-latest ol { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
}
@keyframes phase-in {
  from { opacity: 0; transform: translateY(18px) scale(.985); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes stadium-lights {
  from { transform: translate3d(-1.5%, -1%, 0) scale(1); opacity: .7; }
  to { transform: translate3d(1.5%, 1%, 0) scale(1.05); opacity: 1; }
}
@media (prefers-reduced-motion: reduce) {
  .phase-enter { animation: none; }
  .screen-brand-bg::before { animation: none; }
  .ticker-enter-active, .ticker-leave-active, .count-enter-active, .count-leave-active { transition: none; }
}
</style>
