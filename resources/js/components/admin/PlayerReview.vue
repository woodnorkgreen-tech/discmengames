<template>
  <section class="bg-white rounded-2xl shadow p-4 sm:p-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
      <div>
        <h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Player Review</h2>
        <p class="text-xs text-gray-400 mt-1">Review registrations, predictions and submitted answers.</p>
      </div>
      <button @click="loadPlayers(page)" :disabled="loading" class="self-start border border-gray-200 rounded-lg px-3 py-2 text-xs font-bold text-gray-600 disabled:opacity-50">
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>

    <div class="grid gap-2 sm:grid-cols-[1fr_10rem] mb-4">
      <input v-model="search" @input="scheduleSearch" type="search" placeholder="Search by nickname…"
        class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-discmen" />
      <select v-model="type" @change="loadPlayers(1)" class="border border-gray-300 rounded-xl px-3 py-3 text-sm bg-white">
        <option value="all">All players</option><option value="real">Real attendees</option><option value="simulated">Simulated</option>
      </select>
    </div>

    <p v-if="error" class="text-red-500 text-sm py-3">{{ error }}</p>
    <p v-else-if="!loading && !players.length" class="text-gray-400 text-sm text-center py-8">No matching players.</p>

    <div v-else class="space-y-2">
      <button v-for="player in players" :key="player.id" @click="openPlayer(player.id)"
        class="w-full border border-gray-200 hover:border-discmen/40 hover:bg-discmen/5 rounded-xl p-3 text-left transition">
        <div class="flex items-start gap-3">
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-black text-gray-600">{{ initials(player.nickname) }}</span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-bold text-gray-800 truncate">{{ player.nickname }}</p>
              <span v-if="player.is_simulated" class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700">TEST</span>
            </div>
            <p class="text-xs text-gray-500 mt-0.5">Player #{{ player.id }} · joined {{ new Date(player.created_at).toLocaleTimeString() }}</p>
          </div>
          <div class="shrink-0 text-right">
            <p class="text-sm font-black text-discmen">{{ (player.trivia_score + player.prediction_score).toLocaleString() }} pts</p>
            <p class="text-[10px] text-gray-400">{{ player.answers_count }} answers · {{ player.prediction_exists ? 'Prediction ✓' : 'No prediction' }}</p>
          </div>
        </div>
      </button>
    </div>

    <div v-if="lastPage > 1" class="mt-4 flex items-center justify-between gap-3">
      <button @click="loadPlayers(page - 1)" :disabled="page <= 1 || loading" class="border rounded-lg px-3 py-2 text-xs font-bold disabled:opacity-30">← Previous</button>
      <span class="text-xs text-gray-500">Page {{ page }} of {{ lastPage }} · {{ total }} players</span>
      <button @click="loadPlayers(page + 1)" :disabled="page >= lastPage || loading" class="border rounded-lg px-3 py-2 text-xs font-bold disabled:opacity-30">Next →</button>
    </div>
  </section>

  <div v-if="selectedPlayer" class="fixed inset-0 z-[100] flex items-end justify-center bg-black/60 p-0 sm:items-center sm:p-5" @click.self="selectedPlayer = null">
    <div class="max-h-[92dvh] w-full overflow-y-auto rounded-t-3xl bg-white p-5 shadow-2xl sm:max-w-2xl sm:rounded-2xl sm:p-6">
      <div class="flex items-start justify-between gap-4 mb-5">
        <div>
          <div class="flex items-center gap-2"><h3 class="text-xl font-black text-gray-900">{{ selectedPlayer.nickname }}</h3><span v-if="selectedPlayer.is_simulated" class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-bold text-purple-700">TEST</span></div>
          <p class="text-sm text-gray-500">Player #{{ selectedPlayer.id }}</p>
        </div>
        <button @click="selectedPlayer = null" class="h-9 w-9 rounded-full bg-gray-100 text-gray-500">✕</button>
      </div>

      <div class="grid grid-cols-3 gap-2 mb-5">
        <div class="rounded-xl bg-green-50 p-3 text-center"><p class="text-lg font-black text-discmen">{{ selectedSummary.total_score.toLocaleString() }}</p><p class="text-[10px] text-gray-500">Total points</p></div>
        <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-lg font-black text-gray-800">{{ selectedSummary.answers_count }}</p><p class="text-[10px] text-gray-500">Answers</p></div>
        <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-lg font-black text-gray-800">{{ selectedSummary.correct_count }}</p><p class="text-[10px] text-gray-500">Correct</p></div>
      </div>

      <div class="mb-5">
        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">Match prediction</h4>
        <div v-if="selectedPlayer.prediction" class="rounded-xl border border-gray-200 p-4 grid grid-cols-2 gap-3 text-sm">
          <div><p class="text-xs text-gray-400">Scoreline</p><p class="font-bold">{{ selectedPlayer.prediction.score_home }} – {{ selectedPlayer.prediction.score_away }}</p></div>
          <div><p class="text-xs text-gray-400">Prediction points</p><p class="font-bold">{{ selectedPlayer.prediction.prediction_score.toLocaleString() }}</p></div>
          <div><p class="text-xs text-gray-400">First team to score</p><p class="font-semibold">{{ predictionOutcome(selectedPlayer.prediction.first_scoring_team, true) }}</p></div>
          <div><p class="text-xs text-gray-400">First goalscorer</p><p class="font-semibold">{{ selectedPlayer.prediction.first_scorer }}</p></div>
          <div><p class="text-xs text-gray-400">Half-time result</p><p class="font-semibold">{{ predictionOutcome(selectedPlayer.prediction.halftime_winner) }}</p></div>
          <div><p class="text-xs text-gray-400">Full-time result</p><p class="font-semibold">{{ predictionOutcome(selectedPlayer.prediction.fulltime_winner) }}</p></div>
          <div><p class="text-xs text-gray-400">Player of match</p><p class="font-semibold">{{ selectedPlayer.prediction.potm }}</p></div>
        </div>
        <p v-else class="rounded-xl bg-gray-50 p-4 text-sm text-gray-400">No prediction submitted.</p>
      </div>

      <div>
        <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">Answer history</h4>
        <p v-if="!selectedPlayer.answers.length" class="rounded-xl bg-gray-50 p-4 text-sm text-gray-400">No answers submitted.</p>
        <div v-else class="space-y-2">
          <article v-for="answer in selectedPlayer.answers" :key="answer.id" class="rounded-xl border p-3" :class="answer.is_correct ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'">
            <div class="flex items-start justify-between gap-3"><p class="text-sm font-semibold text-gray-800">{{ answer.question?.text ?? 'Deleted question' }}</p><span class="shrink-0 text-xs font-black" :class="answer.is_correct ? 'text-green-700' : 'text-red-600'">{{ answer.is_correct ? 'CORRECT' : 'WRONG' }}</span></div>
            <div class="mt-2 grid gap-1 text-xs sm:grid-cols-2"><p class="text-gray-600">Selected: <strong>{{ answer.selected_option }}</strong></p><p class="text-gray-600">Correct: <strong>{{ answer.question?.correct_answer ?? '—' }}</strong></p><p class="text-gray-500">Awarded: {{ answer.points_awarded }} pts</p><p class="text-gray-500">Response: {{ answer.response_time_ms ? `${(answer.response_time_ms / 1000).toFixed(2)}s` : '—' }}</p></div>
          </article>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'

const players = ref([]), loading = ref(false), error = ref(''), search = ref(''), type = ref('all')
const page = ref(1), lastPage = ref(1), total = ref(0), selectedPlayer = ref(null), selectedSummary = ref({ total_score: 0, answers_count: 0, correct_count: 0 })
let searchTimer

onMounted(() => loadPlayers())

function initials(name) { return String(name ?? '').split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase() }
function predictionOutcome(value, firstTeam = false) {
  if (value === 'home') return 'Home team'
  if (value === 'away') return 'Away team'
  if (value === 'draw') return 'Draw'
  if (value === 'none') return 'No team scored'
  return firstTeam ? 'Legacy player selection' : 'Not captured'
}
function scheduleSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadPlayers(1), 300) }

async function loadPlayers(targetPage = 1) {
  loading.value = true; error.value = ''
  try {
    const { data } = await axios.get('/api/admin/players', { params: { page: targetPage, search: search.value || undefined, type: type.value } })
    players.value = data.data ?? []; page.value = data.current_page; lastPage.value = data.last_page; total.value = data.total
  } catch (e) { error.value = e.response?.data?.message ?? 'Could not load players.' }
  finally { loading.value = false }
}

async function openPlayer(id) {
  try {
    const { data } = await axios.get(`/api/admin/players/${id}`)
    selectedPlayer.value = data.player; selectedSummary.value = data.summary
  } catch (e) { error.value = e.response?.data?.message ?? 'Could not load player details.' }
}
</script>
