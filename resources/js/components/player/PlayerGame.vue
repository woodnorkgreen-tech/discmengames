<template>
  <div class="event-surface min-h-dvh text-white flex flex-col" :class="{ 'lobby-bg': phase === 'lobby' }">

    <!-- Not registered guard -->
    <div v-if="!playerId" class="flex-1 flex items-center justify-center p-6 text-center">
      <div>
        <p class="text-gray-400 mb-4">Session expired or not registered.</p>
        <a href="/" class="text-discmen-accent underline text-lg">Register / Login →</a>
      </div>
    </div>

    <template v-else>

      <!-- ── Sticky player header ──────────────────────────────────────────── -->
      <header class="flex items-center justify-between px-4 sm:px-6 py-3 bg-[#100f0d]/90 backdrop-blur-xl border-b border-white/10 flex-shrink-0 pt-safe">
        <div class="flex min-w-0 items-center gap-2.5">
          <span class="client-header-mark discmen-logo-tile"><img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" /></span>
          <span class="hidden text-white/20 sm:inline" aria-hidden="true">×</span>
          <span class="hidden items-center gap-1.5 text-sm font-black tracking-tight text-white sm:flex"><span>FINAL WHISTLE</span><OnIcon /></span>
        </div>
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

          <span class="inline-flex items-center gap-2 rounded-full bg-discmen/15 border border-discmen/30 px-3 py-1 mb-7">
            <span class="h-1.5 w-1.5 rounded-full bg-discmen-accent animate-pulse" aria-hidden="true"></span>
            <span class="brand-kicker">Event lobby</span>
          </span>

          <div class="relative w-20 h-20 mx-auto mb-7" aria-hidden="true">
            <div class="absolute inset-0 rounded-full border-4 border-white/10"></div>
            <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-discmen-accent animate-spin"></div>
            <span class="absolute inset-0 flex items-center justify-center text-3xl">⚽</span>
          </div>

          <h2 class="text-2xl sm:text-3xl font-bold text-white mb-2">Hang tight, {{ playerNickname }}!</h2>
          <p class="text-gray-300 text-base sm:text-lg leading-relaxed">The game starts soon. Watch the big screen.</p>

          <div class="mt-7 inline-flex items-center gap-2 rounded-full bg-white/5 border border-white/10 px-4 py-2">
            <span class="h-2 w-2 rounded-full bg-discmen-accent animate-pulse" aria-hidden="true"></span>
            <span class="text-sm text-gray-300"><strong class="text-white font-bold">{{ playerCount }}</strong> players joined</span>
          </div>
        </div>
      </div>

      <!-- ── Predictions form ────────────────────────────────────────────── -->
      <PredictionsForm
        v-else-if="phase === 'predictions_open'"
        :player-id="playerId"
        :match="match"
        :scoring-rules="scoringRules"
        :read-only="adminPreview" />

      <!-- ── Trivia live ─────────────────────────────────────────────────── -->
      <TriviaQuestion
        v-else-if="phase === 'trivia_live' && question && !question.correct_answer"
        :question="question"
        :round="round"
        :question-progress="questionProgress"
        :player-id="playerId"
        :read-only="adminPreview"
        :scoring-rules="scoringRules"
        :saved-answer="lastSelectedAnswer"
        :key="question.id"
        @answered="onAnswered" />

      <!-- Round introduction — no question timer is consumed here. -->
      <div v-else-if="roundsEnabled && phase === 'trivia_live' && !question && round?.status === 'live'"
        class="flex-1 flex items-center justify-center p-6 text-center pb-safe">
        <div class="glass-card w-full max-w-lg rounded-3xl p-8 sm:p-11">
          <p class="brand-kicker">Round {{ round.number }} of {{ round.total }}</p>
          <div class="mx-auto my-6 flex h-20 w-20 items-center justify-center rounded-full border border-discmen-accent/30 bg-discmen/20 text-4xl">{{ round.number === 2 ? '⚽' : '⚡' }}</div>
          <h2 class="text-3xl font-black text-white sm:text-4xl">{{ round.title }}</h2>
          <p class="mt-4 text-base leading-relaxed text-gray-300">{{ round.intro_message }}</p>
          <p class="mt-7 text-sm font-bold text-discmen-accent">Watch the big screen. The first question is coming up.</p>
        </div>
      </div>

      <!-- ── Trivia reveal ───────────────────────────────────────────────── -->
      <div v-else-if="['trivia_live', 'trivia_reveal'].includes(phase) && question?.correct_answer"
        class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10 text-center pb-safe">
        <div v-if="answerResultLoading" class="text-gray-400 text-lg">Checking your answer…</div>
        <div v-else class="glass-card w-full max-w-lg rounded-3xl p-6 sm:p-9">
          <div v-if="answerResultKnown" class="text-5xl sm:text-6xl mb-3" aria-hidden="true">{{ lastAnswerCorrect ? '✓' : '×' }}</div>
          <p v-if="answerResultKnown && lastAnswerCorrect" class="text-discmen-accent text-2xl sm:text-3xl font-black">Correct!</p>
          <p v-else-if="answerResultKnown" class="text-red-400 text-2xl sm:text-3xl font-black">Incorrect</p>
          <p v-else class="text-gray-300 text-xl sm:text-2xl font-bold">No answer recorded</p>

          <div class="mt-6 space-y-3 text-left">
            <div v-if="answerResultKnown" class="rounded-xl border px-4 py-3"
              :class="lastAnswerCorrect ? 'border-discmen-accent/40 bg-discmen/15' : 'border-red-500/35 bg-red-500/10'">
              <p class="text-xs font-bold uppercase tracking-widest text-gray-500">Your choice</p>
              <p class="mt-1 font-bold text-white">{{ lastSelectedAnswer }}</p>
            </div>
            <div class="rounded-xl border border-discmen-accent/40 bg-discmen/15 px-4 py-3">
              <p class="text-xs font-bold uppercase tracking-widest text-discmen-accent">Correct answer</p>
              <p class="mt-1 font-black text-white">{{ question.correct_answer }}</p>
            </div>
          </div>

          <p v-if="answerResultKnown" class="mt-5 font-bold" :class="lastPoints > 0 ? 'text-discmen-accent' : 'text-gray-400'">+{{ lastPoints }} points</p>
          <div v-if="answerResultKnown && answerBreakdown" class="mt-4 grid grid-cols-2 gap-2 text-xs text-left">
            <div class="rounded-lg bg-white/5 px-3 py-2"><span class="text-gray-500">Correct</span><strong class="float-right text-white">+{{ answerBreakdown.correct }}</strong></div>
            <div class="rounded-lg bg-white/5 px-3 py-2"><span class="text-gray-500">Speed</span><strong class="float-right text-white">+{{ answerBreakdown.speed_bonus }}</strong></div>
            <div class="rounded-lg bg-white/5 px-3 py-2"><span class="text-gray-500">Streak</span><strong class="float-right text-white">+{{ answerBreakdown.streak_bonus }}</strong></div>
            <div class="rounded-lg bg-white/5 px-3 py-2"><span class="text-gray-500">Multiplier</span><strong class="float-right text-white">×{{ answerBreakdown.multiplier }}</strong></div>
          </div>
          <p class="mt-2 text-sm text-gray-500">Your score: <span class="font-bold text-white">{{ playerScore }}</span> pts</p>
          <p v-if="roundsEnabled && myRoundStanding" class="mt-1 text-sm text-gray-400">{{ round.title }}: <strong class="text-discmen-accent">{{ myRoundStanding.round_score.toLocaleString() }} pts · #{{ myRoundStanding.rank }}</strong></p>
        </div>
      </div>

      <!-- End-of-round celebration and independent award. -->
      <div v-else-if="roundsEnabled && phase === 'trivia_reveal' && !question && round?.status === 'completed'"
        class="flex-1 flex items-center justify-center p-6 text-center pb-safe">
        <div class="glass-card w-full max-w-lg rounded-3xl p-7 sm:p-10">
          <p class="brand-kicker">Round {{ round.number }} complete</p>
          <div class="my-4 text-5xl">🏆</div>
          <h2 class="text-2xl font-black text-white sm:text-3xl">{{ round.title }}</h2>
          <template v-if="myRoundStanding">
            <p class="mt-5 text-gray-400">Your round score</p>
            <p class="text-4xl font-black text-discmen-accent">{{ myRoundStanding.round_score.toLocaleString() }}</p>
            <p class="mt-2 text-sm font-bold text-white">Round rank #{{ myRoundStanding.rank }}</p>
          </template>
          <p class="mt-6 text-sm text-gray-400">The round champion is on the big screen. Get ready for what comes next.</p>
        </div>
      </div>

      <!-- ── Trivia complete ─────────────────────────────────────────────── -->
      <div v-else-if="phase === 'trivia_complete'"
        class="flex-1 flex flex-col items-center justify-center p-6 sm:p-10 text-center pb-safe">
        <img src="/images/brand/football-celebration.webp" alt="Football trivia"
          class="w-36 sm:w-44 aspect-[4/5] object-cover rounded-2xl mb-5 shadow-2xl" />
        <h2 class="text-2xl sm:text-3xl font-bold text-discmen-accent mb-3">Trivia complete</h2>
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
        <h2 class="text-2xl sm:text-3xl font-bold text-discmen-accent mb-2">Match Over!</h2>
        <p v-if="predictionResultLoading" class="text-gray-400 text-base">Calculating your prediction…</p>
        <div v-else-if="predictionResult?.breakdown" class="mt-4 w-full max-w-sm rounded-2xl border border-white/10 bg-white/5 p-4 text-left">
          <div v-for="item in predictionBreakdownRows" :key="item.key" class="flex items-center justify-between border-b border-white/5 py-2 last:border-0">
            <span class="text-sm text-gray-300">{{ item.label }}</span>
            <strong :class="item.points ? 'text-discmen-accent' : 'text-gray-600'">{{ item.points ? `+${item.points}` : '—' }}</strong>
          </div>
          <div class="mt-3 flex items-center justify-between text-lg"><strong>Total</strong><strong class="text-discmen-accent">{{ predictionResult.prediction_score.toLocaleString() }} pts</strong></div>
        </div>
        <p class="mt-3 text-gray-400 text-base">Watch the big screen for the Prediction Champion!</p>
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
        class="mt-6 w-full rounded-xl bg-discmen px-5 py-3 text-sm font-black text-white transition hover:bg-discmen">
        Got it
      </button>
    </PlayerModal>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
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

const { phase, question, playerCount, match, round, questionProgress, roundsEnabled, roundLeaderboard, scoringRules, loading } = useEventState()

const lastAnswerCorrect   = ref(false)
const lastPoints          = ref(0)
const answerResultKnown   = ref(false)
const answerResultLoading = ref(false)
const lastSelectedAnswer  = ref(null)
const answerBreakdown     = ref(null)
const playerScore         = ref(parseInt(sessionStorage.getItem('player_score') ?? '0'))
const showPredictionsClosedModal = ref(false)
const myRoundStanding = computed(() => roundLeaderboard.value.find(entry => String(entry.id) === String(playerId.value)) ?? null)

const predictionResult = ref(null)
const predictionResultLoading = ref(false)
const predictionBreakdownRows = computed(() => {
  const values = predictionResult.value?.breakdown ?? {}
  return [
    ['outcome', 'Correct result'], ['exact_score_bonus', 'Exact score bonus'],
    ['halftime', 'Half-time result'], ['first_team', 'First team to score'],
    ['first_scorer', 'First goalscorer'], ['potm', 'Player of the Match'],
  ].map(([key, label]) => ({ key, label, points: values[key] ?? 0 }))
})

function onAnswered({ selectedOption }) {
  lastSelectedAnswer.value = selectedOption
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
    answerBreakdown.value     = data.breakdown ?? null
    playerScore.value         = data.total_score ?? playerScore.value
    sessionStorage.setItem('player_score', playerScore.value)
  } catch {
    answerResultKnown.value = false
  } finally {
    answerResultLoading.value = false
  }
}

async function loadSavedLiveAnswer() {
  if (adminPreview || !playerId.value || !question.value?.id) return
  try {
    const { data } = await axios.get('/api/answers/result', {
      params: { player_id: playerId.value, question_id: question.value.id },
    })
    if (!data.revealed) lastSelectedAnswer.value = data.selected_option ?? null
  } catch {}
}

async function loadPredictionResult() {
  if (adminPreview || !playerId.value) return
  predictionResultLoading.value = true
  try {
    const { data } = await axios.get('/api/predictions/current', { params: { player_id: playerId.value } })
    predictionResult.value = data.prediction
  } finally {
    predictionResultLoading.value = false
  }
}

watch([phase, question], ([currentPhase, currentQuestion], [previousPhase, previousQuestion] = []) => {
  const questionChanged = currentQuestion?.id !== previousQuestion?.id
  if (currentPhase === 'trivia_live' && questionChanged) {
    answerResultKnown.value = false
    lastSelectedAnswer.value = null
    answerBreakdown.value = null
    loadSavedLiveAnswer()
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

watch(phase, currentPhase => {
  if (['match_ended', 'prediction_reveal'].includes(currentPhase)) loadPredictionResult()
}, { immediate: true })

function signOut() {
  for (const key of ['player_id', 'player_nickname', 'player_session_token', 'player_score', 'prediction_submitted', 'last_prediction']) {
    sessionStorage.removeItem(key)
    localStorage.removeItem(key)
  }
  window.location.href = '/'
}
</script>

<style scoped>
.client-header-mark {
  width: 3.15rem;
  height: 2.55rem;
  padding: .2rem;
  flex-shrink: 0;
  box-shadow: 0 0 22px rgba(97, 200, 210, .12);
}

/* ── Lobby: lighter overlay keeps the supplied stadium artwork visible ───── */
.lobby-bg {
  background-image:
    linear-gradient(180deg, rgba(2, 7, 25, .54) 0%, rgba(7, 11, 42, .28) 43%, rgba(2, 6, 22, .78) 100%),
    url('/images/backgrounds/discmen.jpeg');
}
@media (min-width: 900px) {
  .lobby-bg {
    background-image:
      linear-gradient(90deg, rgba(2, 7, 25, .7) 0%, rgba(7, 11, 42, .4) 48%, rgba(2, 6, 22, .18) 100%),
      linear-gradient(180deg, rgba(1, 4, 16, .32), transparent 60%, rgba(1, 4, 16, .5)),
      url('/images/backgrounds/discmenbg.jpeg');
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
