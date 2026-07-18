<template>
  <div class="mb-5 overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white">
    <div class="flex flex-col gap-3 border-b border-indigo-100 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-black uppercase tracking-widest text-visa">Three-round experience</p>
        <h3 class="mt-1 text-lg font-black text-gray-900">Trivia Round Manager</h3>
        <p class="mt-1 max-w-2xl text-xs leading-relaxed text-gray-500">Create three mini-events, reset streaks fairly at each round, and celebrate a round champion before the overall winner.</p>
      </div>
      <button type="button" @click="toggleMode" :disabled="busy"
        class="min-w-36 rounded-xl px-4 py-3 text-sm font-black transition disabled:opacity-50"
        :class="enabled ? 'bg-visa text-white' : 'border border-gray-300 bg-white text-gray-700'">
        {{ busy ? 'Saving…' : enabled ? 'Round mode ON' : 'Enable round mode' }}
      </button>
    </div>

    <div v-if="error" class="m-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">{{ error }}</div>
    <div v-if="message" class="m-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">{{ message }}</div>

    <div v-if="enabled" class="space-y-4 p-4">
      <div class="grid gap-4 xl:grid-cols-3">
        <article v-for="round in rounds" :key="round.id" class="overflow-hidden rounded-2xl border bg-white shadow-sm"
          :class="round.status === 'live' ? 'border-green-400 ring-2 ring-green-100' : round.status === 'completed' ? 'border-indigo-300' : 'border-gray-200'">
          <div class="border-b border-gray-100 p-4">
            <div class="mb-3 flex items-center justify-between gap-2">
              <span class="rounded-full bg-visa px-2.5 py-1 text-xs font-black text-white">ROUND {{ round.position }}</span>
              <span class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase"
                :class="round.status === 'live' ? 'bg-green-100 text-green-700' : round.status === 'completed' ? 'bg-indigo-100 text-indigo-700' : round.ready ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                {{ round.status === 'draft' ? (round.ready ? 'ready' : 'needs review') : round.status }}
              </span>
            </div>
            <div class="space-y-2">
              <input v-model="round.title" :disabled="round.status !== 'draft'" aria-label="Round title"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-black text-gray-900 disabled:bg-gray-50" />
              <select v-model="round.category" :disabled="round.status !== 'draft'" aria-label="Round theme"
                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-semibold disabled:bg-gray-50">
                <option :value="null">Mixed Visa + football</option>
                <option value="visa">Visa</option>
                <option value="fifa_world_cup">Football</option>
                <option value="general_knowledge">General knowledge</option>
              </select>
              <textarea v-model="round.intro_message" :disabled="round.status !== 'draft'" rows="2" maxlength="180" aria-label="Round introduction"
                class="w-full resize-none rounded-lg border border-gray-200 px-3 py-2 text-xs text-gray-600 disabled:bg-gray-50" />
              <button v-if="round.status === 'draft'" type="button" @click="saveRound(round)" :disabled="busy"
                class="w-full rounded-lg bg-gray-900 px-3 py-2 text-xs font-bold text-white disabled:opacity-40">Save round details</button>
            </div>
          </div>

          <div class="space-y-2 p-3">
            <div v-if="!round.questions.length" class="rounded-xl border border-dashed border-gray-300 px-3 py-7 text-center text-xs text-gray-400">Assign questions below.</div>
            <div v-for="(question, index) in round.questions" :key="question.id" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
              <div class="flex items-start gap-2">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-black text-gray-500">{{ index + 1 }}</span>
                <p class="min-w-0 flex-1 text-xs font-semibold leading-snug text-gray-700">{{ question.text }}</p>
                <span v-if="question.is_double_points" title="Visa Power Question" class="shrink-0 text-sm">⚡</span>
              </div>
              <div v-if="round.status === 'draft'" class="mt-2 flex flex-wrap gap-1 pl-8">
                <button type="button" @click="move(round, index, -1)" :disabled="index === 0 || busy" class="rounded bg-white px-2 py-1 text-[10px] font-bold text-gray-500 disabled:opacity-30">↑</button>
                <button type="button" @click="move(round, index, 1)" :disabled="index === round.questions.length - 1 || busy" class="rounded bg-white px-2 py-1 text-[10px] font-bold text-gray-500 disabled:opacity-30">↓</button>
                <button type="button" @click="makePowerQuestion(round, question)" :disabled="busy" class="rounded bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700">⚡ Power</button>
                <button type="button" @click="assign(question, null)" :disabled="busy" class="ml-auto rounded bg-red-50 px-2 py-1 text-[10px] font-bold text-red-600">Unassign</button>
              </div>
            </div>

            <div class="flex items-center justify-between gap-2 border-t border-gray-100 pt-2 text-[10px] text-gray-500">
              <span>{{ round.questions.length }} questions · {{ formatDuration(round.estimated_seconds) }}</span>
              <span v-if="round.questions.filter(q => q.is_double_points).length === 1" class="font-bold text-amber-700">1 Power Question</span>
            </div>
            <ul v-if="round.issues.length" class="space-y-1 rounded-lg bg-amber-50 px-3 py-2 text-[10px] font-semibold text-amber-700">
              <li v-for="issue in round.issues" :key="issue">• {{ issue }}</li>
            </ul>

            <button v-if="round.status === 'draft'" type="button" @click="startRound(round)" :disabled="busy || !round.ready"
              class="w-full rounded-xl bg-green-600 px-3 py-2.5 text-xs font-black text-white disabled:cursor-not-allowed disabled:opacity-35">▶ Start round introduction</button>
            <button v-else-if="round.status === 'live'" type="button" @click="completeRound(round)" :disabled="busy"
              class="w-full rounded-xl bg-indigo-600 px-3 py-2.5 text-xs font-black text-white disabled:opacity-40">Complete round & show winner</button>
          </div>
        </article>
      </div>

      <section class="rounded-2xl border border-gray-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between gap-3">
          <div>
            <h4 class="text-sm font-black text-gray-800">Unassigned Question Bank</h4>
            <p class="text-xs text-gray-500">Unassigned questions never enter round gameplay.</p>
          </div>
          <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-black text-gray-600">{{ unassigned.length }}</span>
        </div>
        <template v-if="unassigned.length">
          <!-- Category filter tabs for quick selection -->
          <div class="mb-3 flex flex-wrap gap-2">
            <button v-for="tab in categoryTabs" :key="tab.key" type="button" @click="categoryFilter = tab.key"
              class="rounded-full px-3 py-1.5 text-xs font-bold transition"
              :class="categoryFilter === tab.key ? 'bg-visa text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
              {{ tab.label }} ({{ countFor(tab.key) }})
            </button>
          </div>
          <div v-if="filteredUnassigned.length" class="grid gap-2 md:grid-cols-2">
            <div v-for="question in filteredUnassigned" :key="question.id" class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-3">
              <div class="min-w-0 flex-1"><p class="truncate text-xs font-semibold text-gray-700">{{ question.text }}</p><p class="mt-1 text-[10px] uppercase text-gray-400">{{ categoryLabel(question.category) }}</p></div>
              <select :value="''" @change="assign(question, Number($event.target.value)); $event.target.value = ''" :disabled="busy"
                aria-label="Assign question to round" class="rounded-lg border border-gray-200 bg-white px-2 py-2 text-xs font-bold text-gray-700">
                <option value="">Assign…</option>
                <option v-for="round in draftRounds" :key="round.id" :value="round.id">Round {{ round.position }}</option>
              </select>
            </div>
          </div>
          <p v-else class="py-5 text-center text-xs font-semibold text-gray-500">No unassigned questions in this category.</p>
        </template>
        <p v-else class="py-5 text-center text-xs font-semibold text-green-700">Every question has been assigned.</p>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

const emit = defineEmits(['changed'])
const enabled = ref(false)
const rounds = ref([])
const unassigned = ref([])
const busy = ref(false)
const error = ref('')
const message = ref('')
const draftRounds = computed(() => rounds.value.filter(round => round.status === 'draft'))

// Unassigned-bank category filter for quick selection.
const categoryTabs = [
  { key: 'all', label: 'All' },
  { key: 'general_knowledge', label: 'General' },
  { key: 'fifa_world_cup', label: 'FIFA ⚽' },
  { key: 'visa', label: 'Visa' },
]
const categoryFilter = ref('all')
const countFor = (key) => key === 'all'
  ? unassigned.value.length
  : unassigned.value.filter(question => question.category === key).length
const filteredUnassigned = computed(() => categoryFilter.value === 'all'
  ? unassigned.value
  : unassigned.value.filter(question => question.category === categoryFilter.value))

onMounted(load)

function apply(data) {
  enabled.value = Boolean(data.enabled)
  rounds.value = data.rounds ?? []
  unassigned.value = data.unassigned ?? []
}

async function load() {
  try { apply((await axios.get('/api/admin/rounds')).data) }
  catch (e) { error.value = e.response?.data?.message ?? 'Could not load trivia rounds.' }
}

async function run(operation, success) {
  busy.value = true; error.value = ''; message.value = ''
  try {
    const response = await operation()
    if (response?.data?.rounds) apply(response.data)
    else await load()
    message.value = success
    emit('changed')
  } catch (e) { error.value = e.response?.data?.message ?? 'Round update failed.' }
  finally { busy.value = false }
}

function toggleMode() {
  run(() => axios.put('/api/admin/rounds/settings', { enabled: !enabled.value }), enabled.value ? 'Round mode disabled.' : 'Three-round mode created and enabled.')
}
function saveRound(round) {
  run(() => axios.put(`/api/admin/rounds/${round.id}`, { title: round.title, category: round.category || null, intro_message: round.intro_message || null }), `Round ${round.position} saved.`)
}
function assign(question, roundId) {
  run(() => axios.put(`/api/admin/questions/${question.id}/round`, { round_id: roundId }), roundId ? 'Question assigned.' : 'Question returned to the bank.')
}
function move(round, index, direction) {
  const reordered = [...round.questions]
  const destination = index + direction
  ;[reordered[index], reordered[destination]] = [reordered[destination], reordered[index]]
  run(() => axios.put(`/api/admin/rounds/${round.id}/questions/order`, { question_ids: reordered.map(question => question.id) }), 'Question order updated.')
}
function makePowerQuestion(round, selected) {
  run(() => Promise.all(round.questions.map(question => axios.put(`/api/admin/questions/${question.id}`, { is_double_points: question.id === selected.id }))), `Round ${round.position} Power Question selected.`)
}
function startRound(round) { run(() => axios.post(`/api/admin/rounds/${round.id}/start`), `Round ${round.position} introduction is live.`) }
function completeRound(round) { run(() => axios.post(`/api/admin/rounds/${round.id}/complete`), `Round ${round.position} complete. Round standings are live.`) }
function categoryLabel(category) { return ({ visa: 'Visa', fifa_world_cup: 'Football', general_knowledge: 'General' })[category] ?? 'Mixed' }
function formatDuration(seconds) { const minutes = Math.floor(seconds / 60); const rest = seconds % 60; return minutes ? `${minutes}m ${rest}s` : `${rest}s` }
</script>
