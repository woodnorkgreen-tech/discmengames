<template>
  <form @submit.prevent="save(false)" class="space-y-4">
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-3">
      <label class="mb-1 block text-xs font-bold text-indigo-900">Place this question in</label>
      <select v-model="form.trivia_round_id" :disabled="roundsLoading || roundSelectionLocked"
        class="w-full rounded-xl border border-indigo-200 bg-white px-3 py-2.5 text-sm font-semibold focus:border-discmen focus:outline-none disabled:bg-gray-100">
        <option :value="null">Unassigned question bank</option>
        <option v-for="round in rounds" :key="round.id" :value="round.id" :disabled="round.status === 'completed'">
          Round {{ round.position }} · {{ round.title }}{{ round.status === 'live' ? ' (live — append next)' : round.status === 'completed' ? ' (completed)' : '' }}
        </option>
      </select>
      <p class="mt-1.5 text-[11px] leading-relaxed text-indigo-700">
        {{ roundHelp }}
      </p>
    </div>

    <div>
      <label class="mb-1 block text-xs text-gray-500">Question *</label>
      <textarea v-model="form.text" required rows="3" maxlength="1000" autofocus
        placeholder="Type the question exactly as players should see it"
        class="w-full resize-none rounded-xl border px-3 py-2 text-sm focus:border-discmen focus:outline-none" />
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
      <div class="sm:col-span-1">
        <label class="mb-1 block text-xs text-gray-500">Category *</label>
        <div class="flex gap-1.5">
          <select v-model="form.category" required :disabled="roundsLoading"
            class="min-w-0 flex-1 rounded-xl border px-3 py-2 text-sm focus:border-discmen focus:outline-none disabled:bg-gray-100">
            <option value="" disabled>Select category…</option>
            <option v-for="category in categories" :key="category.id" :value="category.key">{{ category.name }}</option>
          </select>
          <button type="button" @click="showNewCategory = !showNewCategory" title="Add category"
            class="rounded-xl border border-discmen/20 bg-discmen/5 px-2.5 text-sm font-black text-discmen">+</button>
        </div>
        <div v-if="showNewCategory" class="mt-2 flex gap-1.5 rounded-xl bg-gray-50 p-2">
          <input v-model="newCategoryName" maxlength="60" placeholder="New category name"
            @keydown.enter.prevent="createCategory"
            class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2 py-1.5 text-xs focus:border-discmen focus:outline-none" />
          <button type="button" @click="createCategory" :disabled="categorySaving || !newCategoryName.trim()"
            class="rounded-lg bg-discmen px-2.5 text-xs font-black text-white disabled:opacity-40">
            {{ categorySaving ? '…' : 'Add' }}
          </button>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-xs text-gray-500">Answer type</label>
        <select v-model="form.type" @change="onTypeChange"
          class="w-full rounded-xl border px-3 py-2 text-sm focus:border-discmen focus:outline-none">
          <option value="multiple_choice">Multiple choice</option>
          <option value="true_false">True / False</option>
        </select>
      </div>
      <div>
        <label class="mb-1 block text-xs text-gray-500">Answer time</label>
        <select v-model.number="form.duration_seconds"
          class="w-full rounded-xl border px-3 py-2 text-sm focus:border-discmen focus:outline-none">
          <option v-for="seconds in durationOptions" :key="seconds" :value="seconds">{{ seconds }} seconds</option>
        </select>
      </div>
    </div>
    <p class="-mt-2 text-[11px] text-gray-400">Choose a managed category or use + to create a new client theme.</p>

    <fieldset>
      <legend class="mb-2 text-xs font-bold text-gray-600">Answers — select the correct one *</legend>
      <div class="space-y-2">
        <label v-for="(option, index) in form.options" :key="index"
          class="flex items-center gap-2 rounded-xl border px-3 py-2 transition"
          :class="correctIndex === index ? 'border-green-400 bg-green-50' : 'border-gray-200 bg-white'">
          <input v-model="correctIndex" type="radio" :value="index" class="h-4 w-4 accent-green-600" />
          <span class="w-5 text-xs font-black text-gray-400">{{ labels[index] }}</span>
          <input v-model="form.options[index]" type="text" required maxlength="255"
            :disabled="form.type === 'true_false'"
            :placeholder="`Answer ${labels[index]}`"
            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm focus:outline-none disabled:text-gray-700" />
          <span v-if="correctIndex === index" class="text-[10px] font-black uppercase text-green-700">Correct</span>
        </label>
      </div>
    </fieldset>

    <label class="flex items-start gap-3 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5" :class="powerLocked ? 'opacity-60' : ''">
      <input v-model="form.is_double_points" type="checkbox" :disabled="powerLocked" class="mt-0.5 h-4 w-4 accent-amber-600" />
      <span>
        <span class="block text-sm font-bold text-amber-900">Make this the Power Question (2× points)</span>
        <span class="block text-[11px] leading-relaxed text-amber-700">{{ powerHelp }}</span>
      </span>
    </label>

    <p v-if="savedMsg" class="rounded-xl bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">{{ savedMsg }}</p>
    <p v-if="errorMsg" role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-600">{{ errorMsg }}</p>

    <div class="grid gap-2 pt-1" :class="initial?.id ? 'grid-cols-2' : 'sm:grid-cols-3'">
      <button type="button" @click="$emit('cancel')" :disabled="saving"
        class="rounded-xl bg-gray-100 px-3 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-200 disabled:opacity-50">
        Cancel
      </button>
      <button type="submit" :disabled="saving"
        class="rounded-xl bg-discmen px-3 py-2.5 text-sm font-bold text-white transition hover:bg-discmen/85 disabled:opacity-50">
        {{ saving ? 'Saving…' : initial?.id ? 'Save changes' : 'Save question' }}
      </button>
      <button v-if="!initial?.id" type="button" @click="save(true)" :disabled="saving"
        class="rounded-xl border border-discmen bg-white px-3 py-2.5 text-sm font-bold text-discmen transition hover:bg-discmen/5 disabled:opacity-50">
        Save &amp; add another
      </button>
    </div>
  </form>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
  initial: { type: Object, default: null },
  initialRoundId: { type: Number, default: null },
})
const emit = defineEmits(['saved', 'cancel', 'categories-changed'])

const labels = ['A', 'B', 'C', 'D']
const durationOptions = [5, 10, 15, 20, 30, 45, 60, 90, 120]
const rounds = ref([])
const categories = ref([])
const roundsLoading = ref(true)
const saving = ref(false)
const categorySaving = ref(false)
const errorMsg = ref('')
const savedMsg = ref('')
const correctIndex = ref(-1)
const showNewCategory = ref(false)
const newCategoryName = ref('')

const form = reactive({
  text: '',
  category: 'general_knowledge',
  type: 'multiple_choice',
  options: ['', '', '', ''],
  duration_seconds: 30,
  is_double_points: false,
  trivia_round_id: props.initialRoundId,
})

const selectedRound = computed(() => rounds.value.find(round => round.id === Number(form.trivia_round_id)) ?? null)
const roundSelectionLocked = computed(() => Boolean(
  props.initial?.id && (props.initial.status !== 'draft' || props.initial.trivia_round?.status === 'completed')
))
const powerLocked = computed(() => selectedRound.value?.status === 'live' || Boolean(props.initial?.id && props.initial.status !== 'draft'))
const powerHelp = computed(() => powerLocked.value
  ? 'Power Question settings are locked after gameplay starts.'
  : 'If assigned to a round, it replaces that round’s previous Power Question.')
const roundHelp = computed(() => {
  if (roundSelectionLocked.value) return 'Round placement is locked after a question has entered gameplay.'
  if (!selectedRound.value) return 'Keep it in the bank if the client has not decided where it belongs yet.'
  if (selectedRound.value.status === 'live') return 'This will be appended after the questions already scheduled in the live round.'
  return `This will be added as the next question in Round ${selectedRound.value.position}.`
})

onMounted(async () => {
  hydrateInitial()
  try {
    const { data } = await axios.get('/api/admin/rounds')
    rounds.value = data.rounds ?? []
    categories.value = data.categories ?? []
    if (!props.initial && props.initialRoundId) {
      const initialRound = rounds.value.find(round => round.id === props.initialRoundId)
      if (initialRound?.category) form.category = initialRound.category
    }
  } catch (error) {
    errorMsg.value = error.response?.data?.message ?? 'Could not load round choices.'
  } finally {
    roundsLoading.value = false
  }
})

function hydrateInitial() {
  if (!props.initial) return
  Object.assign(form, {
    text: props.initial.text,
    category: props.initial.category ?? 'general_knowledge',
    type: props.initial.type,
    options: [...props.initial.options],
    duration_seconds: props.initial.duration_seconds,
    is_double_points: props.initial.is_double_points,
    trivia_round_id: props.initial.trivia_round_id ?? null,
  })
  correctIndex.value = form.options.findIndex(option => option === props.initial.correct_answer)
}

function onTypeChange() {
  form.options = form.type === 'true_false' ? ['True', 'False'] : ['', '', '', '']
  correctIndex.value = -1
}

function resetForNextQuestion() {
  form.text = ''
  form.options = form.type === 'true_false' ? ['True', 'False'] : ['', '', '', '']
  form.is_double_points = false
  correctIndex.value = -1
}

async function createCategory() {
  if (!newCategoryName.value.trim() || categorySaving.value) return
  categorySaving.value = true
  errorMsg.value = ''
  try {
    const { data } = await axios.post('/api/admin/question-categories', { name: newCategoryName.value.trim() })
    categories.value = [...categories.value, data].sort((a, b) => a.name.localeCompare(b.name))
    form.category = data.key
    newCategoryName.value = ''
    showNewCategory.value = false
    emit('categories-changed', data)
  } catch (error) {
    errorMsg.value = error.response?.data?.message ?? 'The category could not be added.'
  } finally {
    categorySaving.value = false
  }
}

async function save(addAnother) {
  errorMsg.value = ''
  savedMsg.value = ''

  if (!form.text.trim()) {
    errorMsg.value = 'Enter the question text.'
    return
  }
  const options = form.options.map(option => option.trim())
  if (correctIndex.value < 0 || !options[correctIndex.value]) {
    errorMsg.value = 'Select the correct answer.'
    return
  }
  if (options.some(option => !option)) {
    errorMsg.value = 'Fill in every answer option.'
    return
  }
  if (new Set(options.map(option => option.toLocaleLowerCase())).size !== options.length) {
    errorMsg.value = 'Each answer option must be different.'
    return
  }
  if (!form.category.trim()) {
    errorMsg.value = 'Enter a category.'
    return
  }

  saving.value = true
  try {
    const payload = {
      ...form,
      text: form.text.trim(),
      category: form.category.trim(),
      options,
      correct_answer: options[correctIndex.value],
    }

    if (props.initial?.id) {
      await axios.put(`/api/admin/questions/${props.initial.id}`, payload)
    } else {
      await axios.post('/api/admin/questions', payload)
    }

    emit('saved', { keepOpen: Boolean(addAnother), roundId: form.trivia_round_id })
    if (addAnother) {
      resetForNextQuestion()
      savedMsg.value = selectedRound.value
        ? `Saved to Round ${selectedRound.value.position}. Add the next question.`
        : 'Saved to the question bank. Add the next question.'
    }
  } catch (error) {
    errorMsg.value = error.response?.data?.message ?? 'The question could not be saved.'
  } finally {
    saving.value = false
  }
}
</script>
