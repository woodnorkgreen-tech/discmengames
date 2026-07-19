<template>
  <section class="mb-5 overflow-hidden rounded-2xl border border-gray-200 bg-white">
    <button type="button" @click="expanded = !expanded"
      class="flex w-full items-center justify-between gap-4 p-4 text-left transition hover:bg-gray-50">
      <span>
        <span class="block text-xs font-black uppercase tracking-widest text-discmen">Category library</span>
        <span class="mt-1 block text-sm text-gray-500">Add reusable client themes before creating questions.</span>
      </span>
      <span class="flex items-center gap-2">
        <span class="rounded-full bg-discmen/10 px-2.5 py-1 text-xs font-black text-discmen">{{ categories.length }}</span>
        <span class="text-lg text-gray-400">{{ expanded ? '−' : '+' }}</span>
      </span>
    </button>

    <div v-if="expanded" class="space-y-4 border-t border-gray-100 p-4">
      <form @submit.prevent="createCategory" class="flex flex-col gap-2 sm:flex-row">
        <label class="min-w-0 flex-1">
          <span class="sr-only">New category name</span>
          <input v-model="newName" required maxlength="60" placeholder="e.g. Music & Entertainment"
            class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-discmen focus:outline-none" />
        </label>
        <button type="submit" :disabled="busy || !newName.trim()"
          class="rounded-xl bg-discmen px-4 py-2.5 text-sm font-black text-white disabled:opacity-40">
          {{ busy ? 'Adding…' : '+ Add category' }}
        </button>
      </form>

      <p v-if="message" class="rounded-xl bg-green-50 px-3 py-2 text-xs font-semibold text-green-700">{{ message }}</p>
      <p v-if="error" role="alert" class="rounded-xl bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ error }}</p>

      <div v-if="loading" class="py-5 text-center text-xs text-gray-400">Loading categories…</div>
      <div v-else class="grid gap-2 lg:grid-cols-2">
        <article v-for="category in categories" :key="category.id" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
          <template v-if="editingId === category.id">
            <form @submit.prevent="saveCategory(category)" class="flex gap-2">
              <input v-model="editName" required maxlength="60" aria-label="Category name"
                class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-sm focus:border-discmen focus:outline-none" />
              <button :disabled="busy" class="rounded-lg bg-discmen px-3 text-xs font-black text-white disabled:opacity-40">Save</button>
              <button type="button" @click="editingId = null" :disabled="busy" class="rounded-lg bg-white px-3 text-xs font-bold text-gray-500">Cancel</button>
            </form>
          </template>
          <template v-else-if="deletingId === category.id">
            <p class="text-xs font-bold text-red-700">Delete “{{ category.name }}”?</p>
            <p class="mt-1 text-[11px] text-gray-500">Only unused categories can be deleted.</p>
            <div class="mt-2 flex gap-2">
              <button type="button" @click="deleteCategory(category)" :disabled="busy"
                class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-black text-white disabled:opacity-40">Delete</button>
              <button type="button" @click="deletingId = null" :disabled="busy"
                class="rounded-lg bg-white px-3 py-1.5 text-xs font-bold text-gray-500">Cancel</button>
            </div>
          </template>
          <template v-else>
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h4 class="truncate text-sm font-black text-gray-800">{{ category.name }}</h4>
                <p class="mt-0.5 truncate font-mono text-[10px] text-gray-400">{{ category.key }}</p>
                <p class="mt-1 text-[11px] font-semibold text-gray-500">
                  {{ category.questions_count }} question{{ category.questions_count === 1 ? '' : 's' }} · {{ category.rounds_count }} round{{ category.rounds_count === 1 ? '' : 's' }}
                </p>
              </div>
              <div class="flex shrink-0 gap-1">
                <button type="button" @click="startEditing(category)" :disabled="busy"
                  class="rounded-lg bg-white px-2.5 py-1.5 text-xs font-bold text-gray-600 hover:bg-gray-100">Rename</button>
                <button type="button" @click="deletingId = category.id" :disabled="busy || category.is_system || category.questions_count > 0 || category.rounds_count > 0"
                  :title="category.is_system ? 'Core categories cannot be deleted' : category.questions_count > 0 || category.rounds_count > 0 ? 'Move assigned questions and rounds first' : 'Delete category'"
                  class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-bold text-red-600 disabled:cursor-not-allowed disabled:opacity-35">Delete</button>
              </div>
            </div>
          </template>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  refreshKey: { type: Number, default: 0 },
})
const emit = defineEmits(['changed'])

const categories = ref([])
const expanded = ref(false)
const loading = ref(true)
const busy = ref(false)
const newName = ref('')
const editingId = ref(null)
const editName = ref('')
const deletingId = ref(null)
const message = ref('')
const error = ref('')

onMounted(load)
watch(() => props.refreshKey, load)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/admin/question-categories')
    categories.value = data.data ?? []
  } catch (requestError) {
    error.value = requestError.response?.data?.message ?? 'Could not load categories.'
  } finally {
    loading.value = false
  }
}

async function createCategory() {
  if (!newName.value.trim() || busy.value) return
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    const { data } = await axios.post('/api/admin/question-categories', { name: newName.value.trim() })
    newName.value = ''
    message.value = `“${data.name}” is ready for questions and rounds.`
    await load()
    emit('changed', data)
  } catch (requestError) {
    error.value = requestError.response?.data?.message ?? 'Could not add the category.'
  } finally {
    busy.value = false
  }
}

function startEditing(category) {
  editingId.value = category.id
  editName.value = category.name
  deletingId.value = null
}

async function saveCategory(category) {
  if (!editName.value.trim() || busy.value) return
  busy.value = true
  error.value = ''
  try {
    await axios.put(`/api/admin/question-categories/${category.id}`, { name: editName.value.trim() })
    editingId.value = null
    message.value = 'Category name updated.'
    await load()
    emit('changed')
  } catch (requestError) {
    error.value = requestError.response?.data?.message ?? 'Could not rename the category.'
  } finally {
    busy.value = false
  }
}

async function deleteCategory(category) {
  if (busy.value) return
  busy.value = true
  error.value = ''
  try {
    await axios.delete(`/api/admin/question-categories/${category.id}`)
    deletingId.value = null
    message.value = 'Unused category deleted.'
    await load()
    emit('changed')
  } catch (requestError) {
    error.value = requestError.response?.data?.message ?? 'Could not delete the category.'
  } finally {
    busy.value = false
  }
}
</script>
