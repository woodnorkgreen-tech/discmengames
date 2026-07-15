<template>
  <div class="event-surface min-h-dvh text-white flex flex-col" :class="{ 'lobby-bg': phase === 'lobby' }">

    <!-- Not registered guard -->
    <div v-if="!playerId" class="flex-1 flex items-center justify-center p-6 text-center">
      <div>
        <p class="text-gray-400 mb-4">Session expired or not registered.</p>
        <a href="/" class="text-safaricom-light underline text-lg">Register / Login →</a>
      </div>
    </div>

    <template v-else>

      <!-- ── Sticky player header ──────────────────────────────────────────── -->
      <header class="flex items-center justify-between px-4 sm:px-6 py-3 bg-[#07170f]/90 backdrop-blur-xl border-b border-white/10 flex-shrink-0 pt-safe">
        <span class="text-white italic font-black text-sm sm:text-base tracking-tight flex items-center gap-2"><span>GAME IKO</span><OnIcon /></span>
        <div class="flex items-center gap-3">
          <span v-if="adminPreview" class="rounded-full bg-purple-500/20 px-2.5 py-1 text-xs font-bold text-purple-200">MC Preview · Read only</span>
          <span v-else class="text-gray-300 text-sm sm:text-base font-semibold">{{ playerNickname }}</span>
          <button v-if="!adminPreview" @click="signOut"
            class="text-xs sm:text-sm text-gray-500 hover:text-red-400 border border-gray-700 hover:border-red-500 px-3 py-1.5 rounded-lg transition">
            Sign Out
          </button>
        </div>
      </header>

      <!-- ── Loading ──────────────────────────────────────────────────────── -->
      <div v-if="loading" class="flex-1 flex items-center justify-center">
        <div class="text-gray-500 text-lg">Connecting…</div>
      </div>

      <!-- ── Lobby / waiting ─────────────────────────────────────────────── -->
      <div v-else-if="phase === 'lobby'"
        class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10 text-center pb-safe">
        <div class="lobby-card glass-card w-full max-w-sm rounded-3xl px-7 py-10 sm:px-10 sm:py-12">

          <span class="inline-flex items-center gap-2 rounded-full bg-safaricom/15 border border-safaricom/30 px-3 py-1 mb-7">
            <span class="h-1.5 w-1.5 rounded-full bg-safaricom-light animate-pulse" aria-hidden="true"></span>
            <span class="brand-kicker">Event lobby</span>
          </span>

          <div class="relative w-20 h-20 mx-auto mb-7" aria-hidden="true">
            <div class="absolute inset-0 rounded-full border-4 border-white/10"></div>
            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-safaricom-light animate-spin"></div>
            <span class="absolute inset-0 flex items-center justify-center text-3xl">⚽</span>
          </div>

          <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Hang tight, {{ playerNickname }}!</h2>
          <p class="text-gray-300 text-base sm:text-lg leading-relaxed">The game starts soon. Watch the big screen.</p>

          <div class="mt-7 inline-flex items-center gap-2 rounded-full bg-white/5 border border-white/10 px-4 py-2">
            <span class="h-2 w-2 rounded-full bg-safaricom-light animate-pulse" aria-hidden="true"></span>
            <span class="text-sm text-gray-300"><strong class="text-white font-bold">{{ playerCount }}</strong> players joined</span>
          </div>
        </div>
      </div>

      <!-- ── Predictions form ────────────────────────────────────────────── -->
      <PredictionsForm
        v-else-if="phase === 'predictions_open'"
        :player-id="playerId"
        :match="match"
        :read-only="adminPreview" />

      <!-- ── Trivia live ─────────────────────────────────────────────────── -->
      <TriviaQuestion
        v-else-if="phase === 'trivia_live' && question && !question.correct_answer"
        :question="question"
        :round="round"
        :player-id="playerId"
        :read-only="adminPreview"
        :key="question.id"
        @answered="onAnswered" />

      <!-- ── Trivia reveal ───────────────────────────────────────────────── -->
      <div v-else-if="['trivia_live', 'trivia_reveal'].includes(phase) && question?.correct_answer"
        class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10 text-center pb-safe">
        <div v-if="answerResultLoading" class="text-gray-400 text-lg">Checking your answer…</div>
        <div v-else class="glass-card w-full max-w-lg rounded-3xl p-6 sm:p-9">
          <div v-if="answerResultKnown" class="text-5xl sm:text-6xl mb-3" aria-hidden="true">{{ lastAnswerCorrect ? '✓' : '×' }}</div>
          <p v-if="answerResultKnown && lastAnswerCorrect" class="text-safaricom-light text-2xl sm:text-3xl font-black">Correct!</p>
          <p v-else-if="answerResultKnown" class="text-mpesa text-2xl sm:text-3xl font-black">Incorrect</p>
          <p v-else class="text-gray-300 text-xl sm:text-2xl font-bold">No answer recorded</p>

          <div class="mt-6 space-y-3 text-left">
            <div v-if="answerResultKnown" class="rounded-xl border px-4 py-3"
              :class="lastAnswerCorrect ? 'border-safaricom-light/40 bg-safaricom/15' : 'border-mpesa/35 bg-mpesa/10'">
              <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Your choice</p>
              <p class="mt-1 font-bold text-white">{{ lastSelectedAnswer }}</p>
            </div>
            <div class="rounded-xl border border-safaricom-light/40 bg-safaricom/15 px-4 py-3">
              <p class="text-xs font-bold uppercase tracking-widest text-safaricom-light">Correct answer</p>
              <p class="mt-1 font-black text-white">{{ question.correct_answer }}</p>
            </div>
          </div>

          <p v-if="answerResultKnown" class="mt-5 font-bold" :class="lastPoints > 0 ? 'text-visa-gold' : 'text-gray-400'">+{{ lastPoints }} points</p>
          <p class="mt-2 text-sm text-gray-500">Your score: <span class="font-bold text-white">{{ playerScore }}</span> pts</p>
        </div>
      </div>

      <!-- ── Trivia complete ─────────────────────────────────────────────── -->
      <div v-else-if="phase === 'trivia_complete'"
        class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10 text-center pb-safe">
        <img src="/images/brand/football-celebration.webp" alt="Football trivia"
          class="w-36 sm:w-44 aspect-[4/5] object-cover rounded-2xl mb-5 shadow-2xl" />
        <h2 class="text-2xl sm:text-3xl font-bold text-visa-gold mb-3">Trivia complete</h2>
        <p class="text-gray-300 text-base sm:text-lg mb-1">
          Your score: <strong class="text-white text-2xl sm:text-3xl">{{ playerScore }}</strong> pts
        </p>
        <p class="text-gray-400 text-sm">Watch the big screen for the Trivia Champion!</p>
      </div>

      <!-- ── Match ended / prediction reveal ────────────────────────────── -->
      <div v-else-if="['match_ended', 'prediction_reveal'].includes(phase)"
        class="flex-1 flex flex-col items-center justify-center p-6 text-center pb-safe">
        <img src="/images/brand/world-cup-trophy.png" alt="World Cup trophy"
          class="w-32 sm:w-40 max-h-52 object-contain mb-5 drop-shadow-2xl" />
        <h2 class="text-2xl sm:text-3xl font-bold text-visa-gold mb-2">Match Over!</h2>
        <p class="text-gray-400 text-base">Watch the big screen for the Prediction Champion!</p>
      </div>

      <!-- ── Fallback ────────────────────────────────────────────────────── -->
      <div v-else class="flex-1 flex items-center justify-center text-gray-500 text-lg pb-safe">
        Waiting for next round…
      </div>

    </template>

    <!-- Predictions closed notice — fires once when the window shuts while this tab is open -->
    <PlayerModal v-if="showPredictionsClosedModal" @dismiss="showPredictionsClosedModal = false">
      <div class="text-4xl mb-3" aria-hidden="true">🔒</div>
      <h3 class="text-xl font-black text-white mb-2">Predictions are closed</h3>
      <p class="text-gray-300 text-sm sm:text-base">No more edits — the prediction window for this match has ended.</p>
      <button type="button" @click="showPredictionsClosedModal = false"
        class="mt-6 w-full rounded-xl bg-safaricom px-5 py-3 text-sm font-black text-white transition hover:bg-safaricom-dark">
        Got it
      </button>
    </PlayerModal>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'
import { useEventState } from '../../composables/useEventState'
import PredictionsForm from './PredictionsForm.vue'
import TriviaQuestion from './TriviaQuestion.vue'
import PlayerModal from './PlayerModal.vue'
import OnIcon from '../brand/OnIcon.vue'

const props = defineProps({ adminPreview: { type: Boolean, default: false } })
const adminPreview = props.adminPreview
const storedPlayerValue = (key) => localStorage.getItem(key) ?? sessionStorage.getItem(key)
const playerId       = ref(adminPreview ? 'preview' : storedPlayerValue('player_id'))
const playerNickname = ref(adminPreview ? 'MC Preview' : (storedPlayerValue('player_nickname') ?? 'Player'))

const { phase, question, playerCount, match, round, loading } = useEventState()

const lastAnswerCorrect   = ref(false)
const lastPoints          = ref(0)
const answerResultKnown   = ref(false)
const answerResultLoading = ref(false)
const lastSelectedAnswer  = ref(null)
const playerScore         = ref(parseInt(sessionStorage.getItem('player_score') ?? '0'))
const showPredictionsClosedModal = ref(false)

function onAnswered({ isCorrect, pointsAwarded, totalScore }) {
  answerResultKnown.value = true
  lastAnswerCorrect.value = isCorrect
  lastPoints.value        = pointsAwarded
  playerScore.value       = totalScore
  sessionStorage.setItem('player_score', totalScore)
}

async function loadSavedAnswerResult() {
  if (adminPreview || !playerId.value || !question.value?.id) return

  answerResultLoading.value = true
  try {
    const { data } = await axios.get('/api/answers/result', {
      params: { player_id: playerId.value, question_id: question.value.id },
    })
    answerResultKnown.value   = data.answered
    lastSelectedAnswer.value  = data.selected_option ?? null
    lastAnswerCorrect.value   = data.is_correct ?? false
    lastPoints.value          = data.points_awarded ?? 0
    playerScore.value         = data.total_score ?? playerScore.value
    sessionStorage.setItem('player_score', playerScore.value)
  } catch {
    answerResultKnown.value = false
  } finally {
    answerResultLoading.value = false
  }
}

watch([phase, question], ([currentPhase, currentQuestion], [previousPhase, previousQuestion] = []) => {
  const questionChanged = currentQuestion?.id !== previousQuestion?.id
  if (currentPhase === 'trivia_live' && questionChanged) {
    answerResultKnown.value = false
    lastSelectedAnswer.value = null
  }
  if (currentQuestion?.correct_answer && (questionChanged || !previousQuestion?.correct_answer || currentPhase !== previousPhase)) {
    loadSavedAnswerResult()
  }
}, { immediate: true })

watch(phase, (currentPhase, previousPhase) => {
  if (previousPhase === 'predictions_open' && currentPhase !== 'predictions_open' && !adminPreview) {
    showPredictionsClosedModal.value = true
  }
})

function signOut() {
  for (const key of ['player_id', 'player_nickname', 'player_session_token', 'player_score', 'prediction_submitted', 'last_prediction']) {
    sessionStorage.removeItem(key)
    localStorage.removeItem(key)
  }
  window.location.href = '/'
}
</script>

<style scoped>
/* ── Lobby: lighter overlay so the stadium/fan photo actually reads ──────── */
.lobby-bg {
  background-image:
    linear-gradient(180deg, rgba(2, 20, 11, .32) 0%, rgba(2, 20, 11, .16) 45%, rgba(2, 20, 11, .6) 100%),
    url('/images/backgrounds/event-portrait.webp');
}
@media (min-width: 900px) {
  .lobby-bg {
    background-image:
      linear-gradient(90deg, rgba(1, 18, 10, .58) 0%, rgba(1, 18, 10, .28) 48%, rgba(1, 18, 10, .1) 100%),
      url('/images/backgrounds/event-landscape.webp');
  }
}

.lobby-card {
  animation: lobby-in .55s cubic-bezier(.22, 1, .36, 1) both;
}
@keyframes lobby-in {
  from { opacity: 0; transform: translateY(14px) scale(.98); }
  to   { opacity: 1; transform: none; }
}
</style>
