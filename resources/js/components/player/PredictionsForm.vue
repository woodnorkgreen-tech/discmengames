<template>
  <div class="flex min-h-0 flex-1 justify-center overflow-hidden px-3 py-2 pb-safe sm:px-6 sm:py-4">
    <div class="flex h-full min-h-0 w-full max-w-xl flex-col justify-center">
      <header class="mb-2 shrink-0 text-center sm:mb-3">
        <p class="brand-kicker mb-1.5">Tap In with Visa</p>
        <h2 class="text-xl font-extrabold text-white sm:text-2xl">Predict the Final</h2>
        <p class="mt-0.5 text-xs text-gray-400 sm:text-sm">Six quick steps. You can edit until predictions close.</p>
        <p class="mx-auto mt-1.5 max-w-md rounded-full border border-visa-gold/20 bg-visa-gold/10 px-3 py-1 text-[10px] font-semibold text-visa-gold sm:text-[11px]">
          Score predictions use 90 minutes + stoppage time. Extra time and penalties do not count.
        </p>
        <details class="mx-auto mt-2 max-w-md rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-left text-xs text-gray-300">
          <summary class="cursor-pointer text-center font-black text-white">How prediction points work</summary>
          <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1">
            <span>Correct result</span><strong class="text-right text-visa-gold">+{{ predictionRules.outcome }}</strong>
            <span>Exact score bonus</span><strong class="text-right text-visa-gold">+{{ predictionRules.exact_score_bonus }}</strong>
            <span>Half-time result</span><strong class="text-right text-visa-gold">+{{ predictionRules.halftime }}</strong>
            <span>First team</span><strong class="text-right text-visa-gold">+{{ predictionRules.first_team }}</strong>
            <span>First goalscorer</span><strong class="text-right text-visa-gold">+{{ predictionRules.first_scorer }}</strong>
            <span>No goalscorer (0–0)</span><strong class="text-right text-visa-gold">+{{ predictionRules.no_scorer }}</strong>
            <span>Player of the Match</span><strong class="text-right text-visa-gold">+{{ predictionRules.potm }}</strong>
          </div>
          <p class="mt-2 text-center text-gray-500">Maximum {{ predictionRules.maximum.toLocaleString() }} points. Correct categories stack; equal totals share rank.</p>
        </details>
      </header>

      <form @submit.prevent="submit" class="glass-card flex min-h-0 flex-col overflow-hidden rounded-2xl">
        <div class="shrink-0 border-b border-white/10 px-4 py-2.5 sm:px-6 sm:py-3">
          <div class="mb-1.5 flex items-center justify-between text-[11px] font-bold sm:text-xs">
            <span class="uppercase tracking-widest text-visa-gold">Step {{ step }} of 6</span>
            <span class="text-gray-500">{{ stepTitles[step - 1] }}</span>
          </div>
          <div class="grid grid-cols-6 gap-1.5" aria-label="Prediction progress">
            <button v-for="number in 6" :key="number" type="button" @click="goToCompletedStep(number)"
              :aria-label="`Go to step ${number}: ${stepTitles[number - 1]}`"
              class="h-1.5 rounded-full transition"
              :class="number <= step ? 'bg-visa-gold' : 'bg-white/10'" />
          </div>
        </div>

        <div class="prediction-body min-h-0 flex-1 px-4 py-3 sm:px-6 sm:py-4">
          <div v-if="readOnly" class="mb-4 rounded-xl border border-purple-400/30 bg-purple-500/10 px-4 py-3 text-center text-sm font-bold text-purple-200">
            MC preview — interaction is disabled
          </div>
          <div v-if="!configReady" class="rounded-xl border border-visa-gold/30 bg-visa-gold/10 px-4 py-3 text-sm text-visa-gold">
            Match squads are being prepared. Prediction entry opens when fixture setup is complete.
          </div>
          <div v-if="loadingSaved" class="py-12 text-center text-sm text-gray-500">Loading your saved prediction…</div>

          <!-- Step 1: large, thumb-friendly score controls. -->
          <section v-else-if="step === 1" class="prediction-step" aria-labelledby="score-title">
            <div class="mb-3 text-center">
              <h3 id="score-title" class="text-lg font-black text-white sm:text-xl">What will the final score be?</h3>
              <p class="mt-1 text-xs text-gray-500">Use + and − or tap a common score below.</p>
            </div>
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 sm:gap-5">
              <ScoreStepper v-model="form.score_home" :team="match.home_team" :flag="teamFlag(match.home_team)" />
              <span class="pt-9 text-2xl font-black text-gray-600 sm:text-3xl">–</span>
              <ScoreStepper v-model="form.score_away" :team="match.away_team" :flag="teamFlag(match.away_team)" />
            </div>
            <div class="mt-3">
              <p class="mb-1.5 text-center text-[10px] font-bold uppercase tracking-widest text-gray-600">Quick picks</p>
              <div class="grid grid-cols-4 gap-2">
                <button v-for="score in quickScores" :key="score.join('-')" type="button" @click="setScore(score)"
                  class="min-h-9 rounded-lg border text-xs font-black transition active:scale-95 sm:min-h-10 sm:text-sm"
                  :class="isScore(score) ? 'border-visa-gold bg-visa/20 text-white' : 'border-white/10 bg-white/5 text-gray-400 hover:border-white/25'">
                  {{ score[0] }}–{{ score[1] }}
                </button>
              </div>
            </div>
            <div class="mt-3 rounded-xl border border-visa/20 bg-visa/10 px-4 py-2 text-center">
              <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Full-time winner · derived from your score</p>
              <div class="mt-1 flex justify-center">
                <img v-if="outcomeFlag(fulltimeWinner)" :src="outcomeFlag(fulltimeWinner)" :alt="outcomeLabel(fulltimeWinner)"
                  class="h-7 w-11 rounded object-cover shadow ring-2 ring-visa-gold/40" />
                <p v-else class="font-black text-visa-gold">Draw</p>
              </div>
            </div>
          </section>

          <!-- Step 2 -->
          <section v-else-if="step === 2" class="prediction-step" aria-labelledby="scorer-title">
            <div class="mb-3 text-center">
              <h3 id="scorer-title" class="text-lg font-black text-white sm:text-xl">Which team scores first?</h3>
              <p class="mt-1 text-xs text-gray-500">Choose the first team on the scoresheet.</p>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <button v-for="choice in teamChoices" :key="choice.value" type="button" @click="form.first_scoring_team = choice.value"
                :disabled="!teamCanScore(choice.value)"
                class="choice-card disabled:cursor-not-allowed disabled:opacity-30" :class="form.first_scoring_team === choice.value ? 'choice-card-active' : ''">
                <img v-if="choice.flag" :src="choice.flag" :alt="`${choice.label} flag`" class="h-9 w-14 rounded object-cover ring-1 ring-white/20" />
                <span :class="choice.flag ? 'sr-only' : 'font-black'">{{ choice.label }}</span>
              </button>
              <button type="button" @click="form.first_scoring_team = 'none'" :disabled="form.score_home + form.score_away > 0"
                class="choice-card sm:col-span-2 disabled:cursor-not-allowed disabled:opacity-30"
                :class="form.first_scoring_team === 'none' ? 'choice-card-active' : ''">No team scores · 0–0 only</button>
            </div>
          </section>

          <!-- Step 3: first player, constrained to the selected team -->
          <section v-else-if="step === 3" class="prediction-step" aria-labelledby="halftime-title">
            <div class="mb-3 text-center">
              <h3 class="text-lg font-black text-white sm:text-xl">Which player scores first?</h3>
              <p class="mt-1 text-xs text-gray-500">Only players from your selected first-scoring team are shown.</p>
            </div>
            <div v-if="form.first_scoring_team === 'none'" class="rounded-2xl border border-visa/20 bg-visa/10 px-5 py-8 text-center">
              <p class="text-2xl">✓</p>
              <p class="mt-2 font-black text-white">No goalscorer</p>
              <p class="mt-1 text-xs text-gray-400">Your predicted score is 0–0.</p>
            </div>
            <PlayerCardPicker v-else v-model="form.first_scorer" label="First goalscorer" :groups="firstScorerGroups"
              fallback-value="No goal / N/A" fallback-label="No goalscorer" :show-fallback="false" />
          </section>

          <!-- Step 4: half-time outcome -->
          <section v-else-if="step === 4" class="prediction-step" aria-labelledby="halftime-title">
            <div class="mb-3 text-center">
              <h3 id="halftime-title" class="text-lg font-black text-white sm:text-xl">Who leads at half-time?</h3>
              <p class="mt-1 text-xs text-gray-500">A level score at half-time counts as a draw.</p>
            </div>
            <div class="grid gap-2">
              <button v-for="choice in outcomeChoices" :key="choice.value" type="button" @click="form.halftime_winner = choice.value"
                :disabled="!halftimeOutcomePossible(choice.value)"
                class="choice-card disabled:cursor-not-allowed disabled:opacity-30" :class="form.halftime_winner === choice.value ? 'choice-card-active' : ''">
                <img v-if="choice.flag" :src="choice.flag" :alt="`${choice.label} flag`" class="h-8 w-12 rounded object-cover ring-1 ring-white/20" />
                <span :class="choice.flag ? 'sr-only' : 'font-black'">{{ choice.label }}</span>
              </button>
            </div>
          </section>

          <!-- Step 5 -->
          <section v-else-if="step === 5" class="prediction-step" aria-labelledby="potm-title">
            <div class="mb-3 text-center">
              <h3 id="potm-title" class="text-lg font-black text-white sm:text-xl">Who will be Player of the Match?</h3>
              <p class="mt-1 text-xs text-gray-500">Choose the player you expect to stand out.</p>
            </div>
            <PlayerCardPicker v-model="form.potm" label="Player of the match" :groups="playerGroups"
              fallback-value="TBD" fallback-label="Skip — no Player of the Match pick (0 pts)" />
          </section>

          <!-- Step 6: explicit review prevents accidental submissions. -->
          <section v-else class="prediction-step" aria-labelledby="review-title">
            <div class="mb-2 text-center">
              <h3 id="review-title" class="text-lg font-black text-white sm:text-xl">Review your prediction</h3>
              <p class="mt-1 text-xs text-gray-500">Check everything before saving.</p>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <button type="button" @click="step = 1" class="review-row">
                <span><span class="review-label">Final score</span><strong class="review-value">{{ form.score_home }} – {{ form.score_away }}</strong></span>
                <span class="text-xs font-bold text-visa-gold">Edit</span>
              </button>
              <button type="button" @click="step = 2" class="review-row">
                <span class="min-w-0"><span class="review-label">First team to score</span>
                  <img v-if="outcomeFlag(form.first_scoring_team)" :src="outcomeFlag(form.first_scoring_team)" :alt="firstTeamLabel" class="mt-1 h-7 w-11 rounded object-cover ring-1 ring-white/20" />
                  <strong v-else class="review-value truncate">{{ firstTeamLabel }}</strong>
                </span>
                <span class="text-xs font-bold text-visa-gold">Edit</span>
              </button>
              <button type="button" @click="step = 3" class="review-row">
                <span class="min-w-0"><span class="review-label">First goalscorer</span><strong class="review-value truncate">{{ form.first_scorer }}</strong></span>
                <span class="text-xs font-bold text-visa-gold">Edit</span>
              </button>
              <button type="button" @click="step = 4" class="review-row">
                <span class="min-w-0"><span class="review-label">Half-time winner</span>
                  <img v-if="outcomeFlag(form.halftime_winner)" :src="outcomeFlag(form.halftime_winner)" :alt="outcomeLabel(form.halftime_winner)" class="mt-1 h-7 w-11 rounded object-cover ring-1 ring-white/20" />
                  <strong v-else class="review-value truncate">Draw</strong>
                </span>
                <span class="text-xs font-bold text-visa-gold">Edit</span>
              </button>
              <div class="review-row">
                <span class="min-w-0"><span class="review-label">Full-time winner</span>
                  <img v-if="outcomeFlag(fulltimeWinner)" :src="outcomeFlag(fulltimeWinner)" :alt="outcomeLabel(fulltimeWinner)" class="mt-1 h-7 w-11 rounded object-cover ring-1 ring-white/20" />
                  <strong v-else class="review-value truncate">Draw</strong>
                </span>
                <span class="text-[10px] font-bold text-gray-500">From score</span>
              </div>
              <button type="button" @click="step = 5" class="review-row">
                <span class="min-w-0"><span class="review-label">Player of the Match</span><strong class="review-value truncate">{{ form.potm }}</strong></span>
                <span class="text-xs font-bold text-visa-gold">Edit</span>
              </button>
            </div>
            <div v-if="hasSavedPrediction" class="mt-2 rounded-xl border border-visa/20 bg-visa/10 px-3 py-2 text-xs text-gray-300">
              This will update your previously saved prediction.
            </div>
          </section>

          <p v-if="errorMsg" role="alert" class="mt-4 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-center text-sm text-red-300">{{ errorMsg }}</p>
        </div>

        <footer v-if="!loadingSaved && configReady" class="shrink-0 flex gap-2 border-t border-white/10 bg-[#070b2a]/95 px-4 py-2.5 backdrop-blur sm:px-6 sm:py-3">
          <button v-if="step > 1" type="button" @click="step--" :disabled="submitting"
            class="min-h-12 rounded-xl border border-white/15 px-5 font-bold text-gray-300 transition hover:border-white/30 disabled:opacity-50">
            Back
          </button>
          <button v-if="step < 6" type="button" @click="nextStep" :disabled="readOnly"
            class="min-h-12 flex-1 rounded-xl bg-visa px-5 font-black text-white transition hover:bg-visa disabled:opacity-50">
            Continue →
          </button>
          <button v-else type="submit" :disabled="submitting || readOnly"
            class="min-h-12 flex-1 rounded-xl bg-visa px-5 font-black text-white transition hover:bg-visa/80 disabled:opacity-50">
            {{ readOnly ? 'Preview only' : submitting ? 'Saving…' : hasSavedPrediction ? 'Update prediction' : 'Lock in prediction' }}
          </button>
        </footer>
      </form>
    </div>

    <!-- Prediction saved confirmation -->
    <PlayerModal v-if="showSavedModal" @dismiss="showSavedModal = false">
      <div class="text-4xl mb-3" aria-hidden="true">✓</div>
      <h3 class="text-xl font-black text-white mb-2">Prediction saved</h3>
      <div class="space-y-2 text-left mb-2">
        <div class="review-row !min-h-0 !py-2.5">
          <span class="review-label">Final score</span>
          <strong class="review-value">{{ form.score_home }} – {{ form.score_away }}</strong>
        </div>
        <div class="review-row !min-h-0 !py-2.5">
          <span class="review-label">First goalscorer</span>
          <strong class="review-value truncate">{{ form.first_scorer }}</strong>
        </div>
        <div class="review-row !min-h-0 !py-2.5">
          <span class="review-label">Player of the Match</span>
          <strong class="review-value truncate">{{ form.potm }}</strong>
        </div>
      </div>
      <p class="text-gray-500 text-xs sm:text-sm mb-6">You can edit it any time before predictions close.</p>
      <button v-if="!readOnly" type="button" @click="showSavedModal = false"
        class="w-full rounded-xl border border-white/15 bg-white/5 px-5 py-3 text-sm font-bold text-white transition hover:border-visa-gold hover:bg-visa/10">
        Edit prediction
      </button>
    </PlayerModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import axios from 'axios'
import PlayerCardPicker from './PlayerCardPicker.vue'
import ScoreStepper from './ScoreStepper.vue'
import PlayerModal from './PlayerModal.vue'

const props = defineProps({
  playerId: { type: [String, Number], required: true },
  match: { type: Object, required: true },
  readOnly: { type: Boolean, default: false },
  scoringRules: { type: Object, default: null },
})
const emit = defineEmits(['submitted'])
const predictionRules = computed(() => props.scoringRules?.prediction ?? {
  outcome: 250, exact_score_bonus: 400, halftime: 200,
  first_team: 150, first_scorer: 300, no_scorer: 150, potm: 200, maximum: 1500,
})

const step = ref(1)
const stepTitles = ['Score & full-time result', 'First team to score', 'First goalscorer', 'Half-time result', 'Player of the Match', 'Review']
const quickScores = [[0, 0], [1, 0], [1, 1], [2, 0], [2, 1], [2, 2], [3, 1], [3, 2]]
const scorerOptions = computed(() => [...(props.match.home_squad ?? []), ...(props.match.away_squad ?? [])])
const playerGroups = computed(() => [
  { name: props.match.home_team, flag: teamFlag(props.match.home_team), players: props.match.home_squad ?? [] },
  { name: props.match.away_team, flag: teamFlag(props.match.away_team), players: props.match.away_squad ?? [] },
].filter(group => group.players.length))
const firstScorerGroups = computed(() => playerGroups.value.filter((group, index) =>
  form.first_scoring_team === 'home' ? index === 0 : index === 1
))
const configReady = computed(() => scorerOptions.value.length > 0)
const teamChoices = computed(() => [
  { value: 'home', label: props.match.home_team, flag: teamFlag(props.match.home_team) },
  { value: 'away', label: props.match.away_team, flag: teamFlag(props.match.away_team) },
])
const outcomeChoices = computed(() => [...teamChoices.value, { value: 'draw', label: 'Draw at half-time', flag: '' }])
const fulltimeWinner = computed(() => form.score_home > form.score_away ? 'home' : form.score_away > form.score_home ? 'away' : 'draw')
const firstTeamLabel = computed(() => form.first_scoring_team === 'none' ? 'No team scores' : outcomeLabel(form.first_scoring_team))

const form = reactive({ score_home: 0, score_away: 0, first_scoring_team: 'none', first_scorer: 'No goal / N/A', halftime_winner: '', potm: '' })
const submitting = ref(false)
const loadingSaved = ref(false)
const hasSavedPrediction = ref(false)
const errorMsg = ref('')
const showSavedModal = ref(false)

onMounted(loadSavedPrediction)

async function loadSavedPrediction() {
  if (props.readOnly || !props.playerId) return
  loadingSaved.value = true
  try {
    const { data } = await axios.get('/api/predictions/current', { params: { player_id: props.playerId } })
    if (data.prediction) {
      Object.assign(form, {
        score_home: data.prediction.score_home,
        score_away: data.prediction.score_away,
        first_scoring_team: data.prediction.first_scoring_team ?? '',
        first_scorer: data.prediction.first_scorer ?? '',
        halftime_winner: data.prediction.halftime_winner ?? '',
        potm: data.prediction.potm,
      })
      hasSavedPrediction.value = true
      showSavedModal.value = true
      step.value = 4
    }
  } catch (e) {
    errorMsg.value = e.response?.data?.message ?? 'Could not load your saved prediction.'
  } finally {
    loadingSaved.value = false
  }
}

function nextStep() {
  errorMsg.value = ''
  if (step.value === 2 && !form.first_scoring_team) return errorMsg.value = 'Choose which team scores first.'
  if (step.value === 3 && !form.first_scorer) return errorMsg.value = 'Choose the player who scores first.'
  if (step.value === 4 && !form.halftime_winner) return errorMsg.value = 'Choose the half-time result.'
  if (step.value === 5 && !form.potm) return errorMsg.value = 'Choose a Player of the Match or use the skip option.'
  step.value = Math.min(6, step.value + 1)
}
function goToCompletedStep(number) { if (number <= step.value) step.value = number }
function setScore([home, away]) { form.score_home = home; form.score_away = away }
function isScore([home, away]) { return form.score_home === home && form.score_away === away }
function teamCanScore(team) { return team === 'home' ? form.score_home > 0 : form.score_away > 0 }
function halftimeOutcomePossible(outcome) {
  if (outcome === 'home') return form.score_home > 0
  if (outcome === 'away') return form.score_away > 0
  return true
}
function outcomeLabel(value) {
  if (value === 'home') return `${props.match.home_team} win`
  if (value === 'away') return `${props.match.away_team} win`
  return 'Draw'
}
function outcomeFlag(value) {
  if (value === 'home') return teamFlag(props.match.home_team)
  if (value === 'away') return teamFlag(props.match.away_team)
  return ''
}

watch(() => [form.score_home, form.score_away], ([home, away]) => {
  if (home + away === 0) form.first_scoring_team = 'none'
  else if (form.first_scoring_team === 'none') form.first_scoring_team = ''
  if ((form.first_scoring_team === 'home' && home === 0) || (form.first_scoring_team === 'away' && away === 0)) form.first_scoring_team = ''
  if ((form.halftime_winner === 'home' && home === 0) || (form.halftime_winner === 'away' && away === 0)) form.halftime_winner = ''
})
watch(() => form.first_scoring_team, (team, previous) => {
  if (team === 'none') form.first_scorer = 'No goal / N/A'
  else if (team !== previous) form.first_scorer = ''
})

function teamFlag(team) {
  return ({ argentina: '/images/flags/argentina.svg', spain: '/images/flags/spain.svg' })[String(team ?? '').trim().toLowerCase()] ?? ''
}

async function submit() {
  if (props.readOnly || submitting.value) return
  if (!form.first_scoring_team || !form.first_scorer || !form.halftime_winner || !form.potm) {
    step.value = !form.first_scoring_team ? 2 : !form.first_scorer ? 3 : !form.halftime_winner ? 4 : 5
    errorMsg.value = 'Complete this selection first.'; return
  }
  submitting.value = true; errorMsg.value = ''
  try {
    await axios.post('/api/predictions', { player_id: props.playerId, ...form })
    sessionStorage.setItem('prediction_submitted', '1')
    sessionStorage.setItem('last_prediction', JSON.stringify(form))
    hasSavedPrediction.value = true
    showSavedModal.value = true
    emit('submitted')
  } catch (e) {
    errorMsg.value = e.response?.data?.message ?? 'Could not save predictions. Check your connection and try again.'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.prediction-step { height:100%; animation: prediction-step-in .25s ease both; }
.review-row { display:flex; width:100%; min-height:3.15rem; align-items:center; justify-content:space-between; gap:.6rem; border:1px solid rgba(255,255,255,.1); border-radius:.75rem; background:rgba(255,255,255,.04); padding:.45rem .7rem; text-align:left; }
.review-label { display:block; color:#6b7280; font-size:.7rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
.review-value { display:block; margin-top:.1rem; color:#fff; font-size:.875rem; }
.choice-card { display:flex; min-height:3.35rem; align-items:center; justify-content:center; gap:.75rem; border:1px solid rgba(255,255,255,.1); border-radius:.85rem; background:rgba(255,255,255,.04); padding:.55rem .8rem; color:#d1d5db; transition:.2s; }
.choice-card:hover { border-color:rgba(255,255,255,.25); }
.choice-card-active { border-color:#35d06f; background:rgba(0,166,81,.18); color:#fff; box-shadow:0 0 0 1px rgba(53,208,111,.18); }
@keyframes prediction-step-in { from { opacity:0; transform:translateX(10px); } to { opacity:1; transform:none; } }
@media (max-height: 700px) {
  .prediction-body { padding-top:.55rem; padding-bottom:.55rem; }
  .review-row { min-height:2.65rem; padding:.3rem .6rem; }
  .review-label { font-size:.6rem; }
  .review-value { font-size:.78rem; }
  .choice-card { min-height:2.9rem; }
}
@media (prefers-reduced-motion: reduce) { .prediction-step { animation:none; } }
</style>
