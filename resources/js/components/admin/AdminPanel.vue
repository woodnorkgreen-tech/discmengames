<template>
  <div class="min-h-dvh bg-gray-100 flex flex-col">

    <!-- ── Top navigation bar ──────────────────────────────────────────────── -->
    <header class="bg-white shadow-sm sticky top-0 z-50">
      <!-- Primary row: title + sign out -->
      <div class="flex items-center justify-between px-4 sm:px-6 py-2.5 border-b border-gray-100">
        <h1 class="font-black italic text-base sm:text-lg text-visa flex items-center gap-2"><span>VISA FINAL WHISTLE</span><span class="hidden sm:inline not-italic font-bold ml-1">— Admin</span></h1>

        <div class="flex items-center gap-2 sm:gap-4">
          <button type="button" @click="goToTestTools"
            class="rounded-lg bg-purple-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-purple-700 sm:px-3">
            <span class="sm:hidden">Test</span><span class="hidden sm:inline">Test Tools</span>
          </button>
          <!-- Live stats (hidden on very small screens) -->
          <div class="hidden sm:flex items-center gap-2 text-xs sm:text-sm text-gray-500">
            <span class="font-medium text-gray-700">{{ playerCount }}</span>
            <span class="text-gray-400">players</span>
            <span class="text-gray-300">·</span>
            <span class="font-medium text-gray-700">{{ predictionCount }}</span>
            <span class="text-gray-400">predictions</span>
          </div>
          <!-- Phase pill -->
          <span class="px-2 py-1 rounded-full text-xs font-bold uppercase"
            :class="phaseColors[phase] ?? 'bg-gray-200 text-gray-600'">
            {{ phase?.replace(/_/g, ' ') }}
          </span>
          <!-- Sign out -->
          <form method="POST" :action="logoutUrl">
            <input type="hidden" name="_token" :value="csrfToken" />
            <button type="submit"
              class="text-xs text-gray-400 hover:text-red-500 border border-gray-200 hover:border-red-300 px-3 py-1.5 rounded-lg transition">
              Sign Out
            </button>
          </form>
        </div>
      </div>

      <!-- Tab switcher row — full width on mobile, centred on larger screens -->
      <div class="flex border-b border-gray-100 overflow-x-auto scrollbar-none">
        <button v-for="tab in tabs" :key="tab.id"
          @click="activeTab = tab.id"
          :class="activeTab === tab.id
            ? 'border-visa text-visa bg-visa/5'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50'"
          class="flex-1 sm:flex-none sm:min-w-[120px] py-3 px-4 sm:px-6 text-sm font-semibold border-b-2 transition whitespace-nowrap text-center">
          {{ tab.label }}
        </button>
        <!-- Mobile-only stats below tabs -->
        <div class="sm:hidden flex items-center gap-2 px-4 text-xs text-gray-400 ml-auto flex-shrink-0">
          <span>{{ playerCount }}p</span>
          <span>·</span>
          <span>{{ predictionCount }}pred</span>
        </div>
      </div>
    </header>

    <!-- ── Iframe previews (Screen / Player) ──────────────────────────────── -->
    <div v-if="activeTab !== 'admin'" class="flex-1 relative">
      <iframe
        :src="activeTab === 'screen' ? '/screen' : '/play?admin_preview=1'"
        :key="activeTab"
        allow="fullscreen; clipboard-write"
        allowfullscreen
        class="w-full border-0 absolute inset-0 h-full"
        :title="activeTab === 'screen' ? 'Main Screen Preview' : 'Player View Preview'"
      />
      <div class="absolute top-3 left-3 bg-black/60 text-white text-xs px-3 py-1.5 rounded-full pointer-events-none">
        Preview — {{ activeTab === 'screen' ? 'Main Screen' : 'Player View' }}
      </div>
    </div>

    <!-- ── Admin controls ─────────────────────────────────────────────────── -->
    <div v-show="activeTab === 'admin'" class="max-w-5xl mx-auto w-full p-4 sm:p-6 space-y-5 sm:space-y-8">

      <nav class="sticky top-[6.6rem] sm:top-[6.8rem] z-40 -mx-4 sm:mx-0 bg-gray-100/95 backdrop-blur-sm py-2 px-4 sm:px-0" aria-label="Admin sections">
        <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
          <button v-for="section in adminSections" :key="section.id" type="button" @click="adminSection = section.id"
            class="shrink-0 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm font-bold transition"
            :class="adminSection === section.id ? 'bg-visa text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:border-visa/40'">
            <span aria-hidden="true">{{ section.icon }}</span> {{ section.label }}
          </button>
        </div>
      </nav>

      <!-- ── Phase Control ───────────────────────────────────────────────── -->
      <section v-show="adminSection === 'phase'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-600 mb-3 text-xs uppercase tracking-widest">Phase Control</h2>
        <p class="text-sm text-gray-500 mb-4">Use these controls for the event flow. Start and reveal individual trivia questions from the question bank below.</p>
        <div v-if="['predictions_open', 'predictions_closed'].includes(phase)" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
          <p class="text-xs font-black uppercase tracking-widest text-amber-700">MC rule announcement</p>
          <p class="mt-1 text-sm font-semibold text-gray-800">“Predictions are scored on the result after 90 minutes plus stoppage time. Extra time and penalty shootouts do not count.”</p>
          <p class="mt-1 text-xs text-gray-500">Read this before predictions close. Enter the same regulation-time score in Match Result.</p>
        </div>
        <div class="rounded-xl border border-visa/20 bg-visa/5 p-4 mb-4 flex items-center justify-between gap-4">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-visa">Recommended next action</p>
            <p class="text-sm font-semibold text-gray-800 mt-1">{{ nextAction.label }}</p>
            <p class="text-xs text-gray-500 mt-0.5">{{ nextAction.help }}</p>
          </div>
          <button v-if="nextAction.phase" @click="setPhase(nextAction.phase)"
            class="bg-visa text-white px-4 py-2.5 rounded-xl text-sm font-bold whitespace-nowrap">
            Continue →
          </button>
        </div>
        <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
          <button v-for="p in phases" :key="p.value"
            @click="setPhase(p.value)"
            :class="phase === p.value
              ? 'bg-visa text-white shadow-sm'
              : 'bg-gray-100 text-gray-600 hover:bg-gray-200 active:bg-gray-300'"
            class="px-3 sm:px-4 py-2.5 sm:py-2 rounded-xl text-xs sm:text-sm font-semibold transition text-center">
            {{ p.label }}
          </button>
        </div>

        <div v-if="liveQuestion" class="mt-5 overflow-hidden rounded-2xl border border-green-200 bg-green-50">
          <div class="flex items-start justify-between gap-4 border-b border-green-200 px-4 py-3">
            <div class="min-w-0">
              <p class="text-xs font-black uppercase tracking-widest text-green-700">Live countdown control</p>
              <p class="mt-1 truncate text-sm font-semibold text-gray-800">{{ liveQuestion.text }}</p>
            </div>
            <div class="shrink-0 text-right">
              <p class="text-2xl font-black tabular-nums text-green-700">{{ stateQuestion?.seconds_remaining ?? liveQuestion.duration_seconds }}s</p>
              <p class="text-[10px] uppercase tracking-wider text-green-600">remaining</p>
            </div>
          </div>
          <div class="space-y-3 p-4">
            <p class="text-xs text-gray-600">Choose a new duration to restart this live question. Existing answers remain saved and players can change them until the restarted timer ends.</p>
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
              <button v-for="seconds in durationOptions" :key="seconds" type="button"
                @click="setLiveDuration(seconds)" :disabled="countdownSaving"
                class="min-h-10 rounded-lg border text-xs font-black transition disabled:opacity-40"
                :class="liveQuestion.duration_seconds === seconds ? 'border-green-600 bg-green-600 text-white' : 'border-green-200 bg-white text-gray-700 hover:border-green-500'">
                {{ seconds }}s
              </button>
            </div>
            <form @submit.prevent="setLiveDuration(countdownDraft)" class="flex gap-2">
              <label class="min-w-0 flex-1 text-[11px] font-semibold text-gray-600">
                Custom duration (5–120 seconds)
                <input v-model.number="countdownDraft" type="number" min="5" max="120" required
                  class="mt-1 w-full rounded-xl border border-green-200 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-green-600 focus:outline-none" />
              </label>
              <button type="submit" :disabled="countdownSaving || countdownDraft < 5 || countdownDraft > 120"
                class="self-end rounded-xl bg-green-700 px-4 py-2.5 text-sm font-bold text-white disabled:opacity-40">
                {{ countdownSaving ? 'Restarting…' : 'Apply & restart' }}
              </button>
            </form>
            <p v-if="countdownMessage" class="text-xs font-semibold" :class="countdownError ? 'text-red-600' : 'text-green-700'">{{ countdownMessage }}</p>
          </div>
        </div>
      </section>

      <div v-show="adminSection === 'players'"><PlayerReview /></div>

      <section v-show="adminSection === 'match'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <div class="flex items-start justify-between gap-4 mb-4">
          <div>
            <h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Match Configuration</h2>
            <p class="text-xs text-gray-400 mt-1">Shared by player predictions and final result scoring.</p>
          </div>
          <span v-if="matchConfigLocked" class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full text-xs font-bold">Predictions exist</span>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-3 mb-4 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <div>
              <h3 class="text-sm font-bold text-gray-700">Team library</h3>
              <p class="text-xs text-gray-500">Load a saved squad, or manage teams and players below.</p>
            </div>
            <button type="button" @click="showTeamManager = !showTeamManager" class="text-xs font-bold text-visa">
              {{ showTeamManager ? 'Close' : 'Manage teams' }}
            </button>
          </div>
          <div class="grid sm:grid-cols-2 gap-2">
            <label class="text-xs text-gray-500">Load home squad
              <select @change="loadTeamIntoMatch($event.target.value, 'home')" class="mt-1 w-full bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm">
                <option value="">Select team…</option><option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }} ({{ team.players.length }})</option>
              </select>
            </label>
            <label class="text-xs text-gray-500">Load away squad
              <select @change="loadTeamIntoMatch($event.target.value, 'away')" class="mt-1 w-full bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm">
                <option value="">Select team…</option><option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }} ({{ team.players.length }})</option>
              </select>
            </label>
          </div>
          <div v-if="showTeamManager" class="border-t border-gray-200 pt-3 space-y-3">
            <form @submit.prevent="createTeam" class="grid grid-cols-[1fr_5rem_auto] gap-2">
              <input v-model="newTeam.name" required placeholder="New team name" class="min-w-0 bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm" />
              <input v-model="newTeam.code" maxlength="3" placeholder="Code" class="min-w-0 bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm uppercase" />
              <button class="bg-visa text-white rounded-lg px-3 text-sm font-bold">Add</button>
            </form>
            <select v-model="managedTeamId" class="w-full bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm">
              <option value="">Choose a team to edit…</option><option v-for="team in teams" :key="team.id" :value="team.id">{{ team.name }}</option>
            </select>
            <template v-if="managedTeam">
              <form @submit.prevent="addTeamPlayer" class="grid grid-cols-[1fr_4.5rem_4rem_auto] gap-2">
                <input v-model="newTeamPlayer.name" required placeholder="Player name" class="min-w-0 bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm" />
                <select v-model="newTeamPlayer.position" class="bg-white border border-gray-300 rounded-lg px-1 py-2 text-sm"><option value="">Pos</option><option v-for="p in ['GK','DF','MF','FW']" :key="p">{{ p }}</option></select>
                <input v-model.number="newTeamPlayer.shirt_number" type="number" min="1" max="99" placeholder="#" class="min-w-0 bg-white border border-gray-300 rounded-lg px-2 py-2 text-sm" />
                <button class="bg-gray-800 text-white rounded-lg px-3 text-sm font-bold">Add</button>
              </form>
              <div class="max-h-48 overflow-y-auto divide-y divide-gray-200 bg-white rounded-lg border border-gray-200">
                <div v-for="player in managedTeam.players" :key="player.id" class="flex items-center gap-2 px-3 py-2 text-sm">
                  <span class="w-6 text-gray-400">{{ player.shirt_number ?? '–' }}</span><span class="w-7 text-xs font-bold text-gray-500">{{ player.position ?? '–' }}</span><span class="flex-1">{{ player.name }}</span>
                  <button type="button" @click="removeTeamPlayer(player)" class="text-red-500 text-xs font-bold">Remove</button>
                </div>
              </div>
            </template>
            <p v-if="teamMessage" class="text-xs" :class="teamError ? 'text-red-500' : 'text-green-600'">{{ teamMessage }}</p>
          </div>
        </div>
        <form @submit.prevent="saveMatchConfig(false)" class="space-y-4">
          <div class="grid sm:grid-cols-3 gap-3">
            <label class="text-xs text-gray-500">Home team<input v-model="matchForm.home_team" required class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none" /></label>
            <label class="text-xs text-gray-500">Away team<input v-model="matchForm.away_team" required class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none" /></label>
          </div>
          <div class="grid sm:grid-cols-2 gap-3">
            <label class="text-xs text-gray-500">Home squad · one player per line<textarea v-model="matchForm.home_squad_text" rows="8" required class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none resize-y" /></label>
            <label class="text-xs text-gray-500">Away squad · one player per line<textarea v-model="matchForm.away_squad_text" rows="8" required class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none resize-y" /></label>
          </div>
          <div class="grid sm:grid-cols-2 gap-3">
            <label class="text-xs text-gray-500">Kick-off<input v-model="matchForm.kickoff_at" type="datetime-local" class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none" /></label>
            <label class="text-xs text-gray-500">Venue<input v-model="matchForm.venue" class="mt-1 w-full bg-white text-gray-900 border border-gray-300 rounded-xl px-3 py-3 text-sm focus:border-visa focus:outline-none" /></label>
          </div>
          <p v-if="matchConfigMessage" class="text-sm" :class="matchConfigError ? 'text-red-500' : 'text-green-600'">{{ matchConfigMessage }}</p>
          <button type="submit" :disabled="matchConfigSaving" class="w-full bg-visa text-white font-bold py-3.5 rounded-xl disabled:opacity-50">
            {{ matchConfigSaving ? 'Saving…' : 'Save match configuration' }}
          </button>
        </form>
      </section>

      <!-- ── Question Bank ───────────────────────────────────────────────── -->
      <section v-show="adminSection === 'questions'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <RoundManager @changed="loadQuestions" />
        <div class="flex items-center justify-between mb-3 sm:mb-4">
          <h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Question Bank</h2>
          <button @click="openAddQuestion"
            class="bg-visa text-white px-3 sm:px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold hover:bg-visa transition active:scale-95">
            + Add Question
          </button>
        </div>

        <!-- Category filter pills -->
        <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-4 overflow-x-auto pb-1">
          <button v-for="f in categoryFilters" :key="f.value"
            @click="activeCategory = f.value"
            :class="activeCategory === f.value
              ? f.activeClass
              : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
            class="px-2.5 sm:px-3 py-1.5 rounded-full text-xs font-semibold transition whitespace-nowrap">
            {{ f.label }}
            <span class="ml-1 opacity-60">({{ categoryCount(f.value) }})</span>
          </button>
        </div>

        <div v-if="filteredQuestions.length === 0" class="text-gray-400 text-sm text-center py-8">
          No questions yet — add the first one.
        </div>

        <div v-else class="space-y-2 sm:space-y-3">
          <div v-for="q in filteredQuestions" :key="q.id"
            class="border rounded-xl p-3 sm:p-4"
            :class="{
              'border-green-400 bg-green-50': q.status === 'live',
              'border-gray-200 bg-white': q.status !== 'live',
              'opacity-50': q.status === 'skipped',
            }">

            <!-- Top row: order + text -->
            <div class="flex items-start gap-3 mb-2">
              <span class="text-gray-400 text-xs font-mono w-5 pt-0.5 flex-shrink-0">{{ q.order_index }}</span>
              <p class="font-medium text-gray-800 text-sm flex-1 min-w-0 leading-snug">{{ q.text }}</p>
            </div>

            <!-- Meta + actions row -->
            <div class="flex items-center justify-between gap-2 pl-8">
              <!-- Badges -->
              <div class="flex items-center gap-1.5 flex-wrap">
                <span v-if="q.trivia_round" class="px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-xs font-black">
                  R{{ q.trivia_round.position }} · Q{{ q.round_position }}
                </span>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="categoryMeta[q.category]?.badgeClass ?? 'bg-gray-100 text-gray-500'">
                  {{ categoryMeta[q.category]?.short ?? q.category }}
                </span>
                <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-500 text-xs">
                  {{ q.type === 'multiple_choice' ? 'MC' : 'T/F' }}
                </span>
                <label class="inline-flex items-center gap-1 text-xs text-gray-500" title="Question countdown">
                  Timer
                  <select :value="q.duration_seconds" @change="updateQuestionDuration(q, $event)"
                    class="border border-gray-200 rounded-lg bg-white px-1.5 py-1 text-xs font-semibold text-gray-700 focus:border-visa focus:outline-none">
                    <option v-for="seconds in durationOptions" :key="seconds" :value="seconds">{{ seconds }}s</option>
                  </select>
                </label>
                <span v-if="q.is_double_points"
                  class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                  ⚡ 2×
                </span>
                <span class="px-2 py-0.5 rounded-full text-xs"
                  :class="{
                    'bg-green-100 text-green-700': q.status === 'live',
                    'bg-gray-100 text-gray-500':   q.status === 'draft',
                    'bg-blue-100 text-blue-700':   q.status === 'closed',
                    'bg-red-100 text-red-500':     q.status === 'skipped',
                  }">{{ q.status }}</span>
              </div>

              <!-- Action buttons — large touch targets -->
              <div class="flex gap-1.5 flex-shrink-0">
                <button v-if="q.status === 'draft'"
                  @click="activateQuestion(q)" title="Go Live"
                  class="bg-visa text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-visa transition active:scale-95">
                  ▶ Live
                </button>
                <button v-if="q.status === 'live'"
                  @click="closeQuestion(q)" title="Close"
                  class="bg-blue-500 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-blue-600 transition active:scale-95">
                  ■ Close
                </button>
                <button v-if="q.status === 'closed'"
                  @click="reopenQuestion(q)" title="Reopen"
                  class="bg-purple-500 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-purple-600 transition active:scale-95">
                  ↩ Return to draft
                </button>
                <button v-if="q.status === 'live'"
                  @click="skipQuestion(q)" title="Skip"
                  class="bg-orange-400 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-orange-500 transition active:scale-95">
                  ⏭
                </button>
                <button v-if="q.status !== 'live'"
                  @click="editQuestion(q)" title="Edit"
                  class="bg-gray-100 text-gray-600 px-3 py-2 rounded-lg text-xs font-semibold hover:bg-gray-200 transition active:scale-95">
                  ✏️
                </button>
                <button v-if="q.status !== 'live'"
                  @click="deleteQuestion(q)" title="Delete"
                  class="bg-red-50 text-red-500 px-3 py-2 rounded-lg text-xs font-semibold hover:bg-red-100 transition active:scale-95">
                  🗑️
                </button>
                <button v-if="q.status === 'closed'"
                  @click="invalidateQuestion(q)" title="Invalidate and reverse points"
                  class="bg-red-600 text-white px-3 py-2 rounded-lg text-xs font-semibold hover:bg-red-700 transition active:scale-95">
                  Invalidate
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ── Match Result ────────────────────────────────────────────────── -->
      <section v-show="adminSection === 'match'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-600 mb-4 text-xs uppercase tracking-widest">Match Result &amp; Prediction Scoring</h2>
        <form @submit.prevent="submitMatchResult" class="space-y-4">
          <div class="flex items-center gap-4">
            <div class="flex-1 text-center">
              <label class="block text-xs text-gray-400 mb-1">Home goals · 90 min + stoppage</label>
              <input v-model.number="result.score_home" type="number" min="0" max="20" required
                class="w-full border rounded-xl px-3 py-3 text-center text-2xl font-bold focus:outline-none focus:border-visa" />
            </div>
            <span class="text-gray-400 text-2xl font-bold mt-5">–</span>
            <div class="flex-1 text-center">
              <label class="block text-xs text-gray-400 mb-1">Away goals · 90 min + stoppage</label>
              <input v-model.number="result.score_away" type="number" min="0" max="20" required
                class="w-full border rounded-xl px-3 py-3 text-center text-2xl font-bold focus:outline-none focus:border-visa" />
            </div>
          </div>

          <div>
            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-500">Half-time score</p>
            <div class="flex items-center gap-4">
              <input v-model.number="result.halftime_score_home" type="number" min="0" :max="result.score_home" required aria-label="Home half-time goals"
                class="min-w-0 flex-1 rounded-xl border px-3 py-3 text-center text-xl font-bold focus:border-visa focus:outline-none" />
              <span class="font-bold text-gray-400">–</span>
              <input v-model.number="result.halftime_score_away" type="number" min="0" :max="result.score_away" required aria-label="Away half-time goals"
                class="min-w-0 flex-1 rounded-xl border px-3 py-3 text-center text-xl font-bold focus:border-visa focus:outline-none" />
            </div>
          </div>

          <div class="grid sm:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs text-gray-400 mb-1">Which team scored first?</label>
              <select v-model="result.first_scoring_team" required
                class="w-full border rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-visa">
                <option value="" disabled>Select result…</option>
                <option value="home">{{ matchForm.home_team }}</option>
                <option value="away">{{ matchForm.away_team }}</option>
                <option value="none">No team scored (0–0 only)</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Which player scored first?</label>
              <select v-model="result.scorer" :required="result.first_scoring_team !== 'none'"
                :disabled="!result.first_scoring_team || result.first_scoring_team === 'none'"
                class="w-full border rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-visa disabled:bg-gray-100 disabled:text-gray-400">
                <option value="">{{ result.first_scoring_team === 'none' ? 'No goalscorer' : 'Select player…' }}</option>
                <option v-for="p in resultScorerList" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-400 mb-1">Player of the Match</label>
              <select v-model="result.potm"
                class="w-full border rounded-xl px-3 py-3 text-sm focus:outline-none focus:border-visa">
                <option value="">TBD — resolve later</option>
                <option v-for="p in squadList" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
          </div>

          <p v-if="matchResultMsg" class="text-sm text-center font-medium"
            :class="matchResultSuccess ? 'text-green-600' : 'text-red-500'">
            {{ matchResultMsg }}
          </p>

          <button type="submit" :disabled="submittingResult"
            class="w-full bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white font-bold py-3.5 rounded-xl transition text-sm sm:text-base">
            {{ submittingResult ? 'Scoring predictions…' : '⚽ Resolve Predictions' }}
          </button>
        </form>
      </section>

      <!-- ── Display Settings ────────────────────────────────────────────── -->
      <section v-show="adminSection === 'display'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <h2 class="font-semibold text-gray-600 mb-4 text-xs uppercase tracking-widest">Display Settings</h2>

        <!-- QR Code -->
        <div class="pt-4 flex items-center gap-4 sm:gap-6">
          <div class="bg-white border rounded-xl p-2 shadow-sm flex-shrink-0">
            <canvas ref="qrCanvas" width="100" height="100"></canvas>
          </div>
          <div class="min-w-0">
            <p class="text-sm font-medium text-gray-700 mb-1">Player Registration QR</p>
            <p class="text-xs text-gray-400 break-all">{{ appUrl }}</p>
            <a :href="appUrl" target="_blank"
              class="text-xs text-visa hover:underline mt-1 inline-block">
              Open in new tab →
            </a>
          </div>
        </div>
      </section>

      <!-- ── Event Testing / Reset ──────────────────────────────────────── -->
      <section v-show="adminSection === 'testing'" id="event-test-tools" class="bg-white rounded-2xl shadow p-4 sm:p-5 scroll-mt-40">
        <div class="mb-4">
          <div class="flex items-start justify-between gap-3">
            <div><h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Event Test Tools</h2><p class="text-xs text-gray-400 mt-1">Load-test the screens safely, then remove test data without touching real attendees.</p></div>
            <button @click="loadTestingStatus" class="text-xs font-bold text-visa">Refresh counts</button>
          </div>
        </div>

        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-4">
          <div v-for="metric in testingMetrics" :key="metric.label" class="rounded-xl bg-gray-50 border border-gray-100 p-2.5 text-center">
            <p class="text-lg font-black text-gray-800">{{ metric.value }}</p><p class="text-[10px] text-gray-500">{{ metric.label }}</p>
          </div>
        </div>

        <div class="mb-4 overflow-hidden rounded-2xl border" :class="scoringRehearsal.passed === false ? 'border-red-300' : 'border-visa/20'">
          <div class="flex items-center justify-between gap-3 bg-gray-50 px-4 py-3">
            <div>
              <p class="text-sm font-black text-gray-800">Scoring rehearsal <span v-if="scoringRehearsal.version" class="text-xs text-gray-400">v{{ scoringRehearsal.version }}</span></p>
              <p class="mt-0.5 text-xs text-gray-500">Read-only checks. No players, answers or predictions are changed.</p>
            </div>
            <button @click="runScoringRehearsal" :disabled="scoringRehearsal.loading"
              class="shrink-0 rounded-xl bg-visa px-4 py-2.5 text-xs font-black text-white disabled:opacity-50">
              {{ scoringRehearsal.loading ? 'Running…' : 'Run checks' }}
            </button>
          </div>
          <div v-if="scoringRehearsal.checks.length" class="divide-y divide-gray-100">
            <div v-for="check in scoringRehearsal.checks" :key="`${check.group}-${check.scenario}`" class="grid grid-cols-[1fr_auto_auto] items-center gap-3 px-4 py-2.5 text-xs">
              <div><span class="font-black text-gray-400">{{ check.group }}</span><p class="font-semibold text-gray-700">{{ check.scenario }}</p></div>
              <div class="text-right text-gray-500"><span class="block text-[10px] uppercase">Expected</span><strong>{{ check.expected.toLocaleString() }}</strong></div>
              <div class="min-w-16 rounded-lg px-2 py-1.5 text-right" :class="check.passed ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                <span class="block text-[10px] uppercase">Actual</span><strong>{{ check.actual.toLocaleString() }} {{ check.passed ? '✓' : '✕' }}</strong>
              </div>
            </div>
          </div>
          <p v-if="scoringRehearsal.error" class="px-4 py-3 text-sm font-semibold text-red-600">{{ scoringRehearsal.error }}</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-xl border border-gray-200 p-4 space-y-3">
            <div>
              <p class="text-sm font-bold text-gray-800">Simulate users</p>
              <p class="text-xs text-gray-500 mt-1">Creates test fans with valid predictions and realistic answers across the question bank.</p>
            </div>
            <div class="flex gap-2">
              <input v-model.number="testTools.count" type="number" min="1" max="200" aria-label="Number of simulated users"
                class="min-w-0 flex-1 border border-gray-300 rounded-xl px-3 py-3 text-sm" />
              <button @click="simulateUsers" :disabled="testTools.busy"
                class="bg-visa text-white px-4 rounded-xl text-sm font-bold disabled:opacity-50">Simulate</button>
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-600">
              <input v-model="testTools.includeAnswers" type="checkbox" class="accent-visa" /> Simulate trivia answers
            </label>
            <div v-if="testTools.includeAnswers" class="grid grid-cols-2 gap-2">
              <label class="text-[11px] text-gray-500">Questions answered %
                <input v-model.number="testTools.answerRate" type="number" min="0" max="100" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm" />
              </label>
              <label class="text-[11px] text-gray-500">Correct answers %
                <input v-model.number="testTools.correctRate" type="number" min="0" max="100" class="mt-1 w-full border rounded-lg px-2 py-2 text-sm" />
              </label>
            </div>
            <button @click="clearSimulatedUsers" :disabled="testTools.busy"
              class="w-full border border-red-200 bg-red-50 text-red-600 py-2.5 rounded-xl text-sm font-bold disabled:opacity-50">
              Clear simulated users
            </button>
          </div>

          <div class="rounded-xl border border-red-200 bg-red-50/40 p-4 space-y-3">
            <div>
              <p class="text-sm font-bold text-red-700">Reset event gameplay</p>
              <p class="text-xs text-gray-600 mt-1">Clears answers, predictions, results and scores; returns questions to draft and phase to lobby.</p>
            </div>
            <label class="flex items-start gap-2 text-xs text-gray-600">
              <input v-model="testTools.removePlayers" type="checkbox" class="mt-0.5 accent-red-600" />
              Also remove every registered player (real and simulated)
            </label>
            <input v-if="testTools.removePlayers" v-model="testTools.confirmation" placeholder="Type RESET EVENT to remove all players"
              class="w-full border border-red-200 rounded-xl px-3 py-3 text-sm" />
            <p v-if="!testTools.removePlayers" class="rounded-lg bg-white px-3 py-2 text-xs text-gray-600">
              Registered players will be kept. Their event scores will return to zero.
            </p>
            <button @click="resetEvent" :disabled="testTools.busy || (testTools.removePlayers && normalizedResetConfirmation !== 'RESET EVENT')"
              class="w-full bg-red-600 text-white py-2.5 rounded-xl text-sm font-bold disabled:opacity-40">
              {{ testTools.busy ? 'Resetting…' : (testTools.removePlayers ? 'Clear entire event' : 'Reset gameplay now') }}
            </button>
          </div>
        </div>
        <p v-if="testTools.message" class="mt-3 text-sm text-center font-semibold"
          :class="testTools.error ? 'text-red-600' : 'text-green-600'">{{ testTools.message }}</p>
      </section>

      <!-- ── Emergency / Score Adjustment ───────────────────────────────── -->
      <section v-show="adminSection === 'operations'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <div class="mb-4"><h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Event Operations</h2><p class="text-xs text-gray-400 mt-1">Live status, recovery actions and audited score corrections.</p></div>

        <div class="grid grid-cols-3 gap-2 mb-4">
          <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-sm font-black uppercase text-visa">{{ phase?.replace(/_/g, ' ') }}</p><p class="text-[10px] text-gray-500">Current phase</p></div>
          <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-lg font-black text-gray-800">{{ playerCount }}</p><p class="text-[10px] text-gray-500">Players</p></div>
          <div class="rounded-xl bg-gray-50 p-3 text-center"><p class="text-lg font-black text-gray-800">{{ predictionCount }}</p><p class="text-[10px] text-gray-500">Predictions</p></div>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 mb-4">
          <p class="text-sm font-bold text-gray-800 mb-1">Recovery actions</p><p class="text-xs text-gray-500 mb-3">Every action asks for confirmation and is recorded in Audit.</p>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <button @click="setPhase('predictions_closed')" class="rounded-lg bg-orange-600 px-3 py-2.5 text-xs font-bold text-white">Close predictions</button>
            <button @click="closeQuestion(liveQuestion)" :disabled="!liveQuestion" class="rounded-lg bg-purple-600 px-3 py-2.5 text-xs font-bold text-white disabled:opacity-30">Stop live question</button>
            <button @click="setPhase('trivia_complete')" class="rounded-lg bg-blue-700 px-3 py-2.5 text-xs font-bold text-white">Trivia complete</button>
            <button @click="setPhase('lobby')" class="rounded-lg bg-gray-700 px-3 py-2.5 text-xs font-bold text-white">Return to lobby</button>
          </div>
          <button @click="refreshOperations" class="mt-2 w-full rounded-lg border border-gray-200 bg-white py-2.5 text-xs font-bold text-gray-700">Refresh all operational data</button>
        </div>

        <div class="border border-gray-200 rounded-xl p-4 space-y-3">
          <p class="text-sm font-medium text-gray-700">Manual Score Adjustment</p>

          <div v-if="!adjust.player" class="space-y-2">
            <div class="flex gap-2">
              <input v-model="adjust.nickname" type="text" placeholder="Player nickname"
                class="flex-1 border rounded-xl px-3 py-3 text-sm focus:outline-none" />
              <button @click="lookupPlayer" :disabled="adjust.looking"
                class="bg-gray-800 text-white px-4 py-3 rounded-xl text-sm font-semibold hover:bg-gray-700 disabled:opacity-50 transition">
                {{ adjust.looking ? '…' : 'Find' }}
              </button>
            </div>
            <p v-if="adjust.lookupError" class="text-red-500 text-xs">{{ adjust.lookupError }}</p>
          </div>

          <template v-else>
            <div class="bg-gray-50 rounded-xl px-4 py-3 flex items-center justify-between">
              <div>
                <p class="font-semibold text-gray-800">{{ adjust.player.nickname }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Current score: {{ adjust.player.trivia_score }} pts</p>
              </div>
              <button @click="adjust.player = null; adjust.nickname = ''"
                class="text-gray-400 hover:text-gray-600 text-lg w-8 h-8 flex items-center justify-center">✕</button>
            </div>
            <input v-model.number="adjust.amount" type="number" placeholder="±pts (e.g. +100 or -50)"
              class="w-full border rounded-xl px-3 py-3 text-sm focus:outline-none text-center" />
            <input v-model="adjust.reason" type="text" placeholder="Reason (required — audit logged)"
              class="w-full border rounded-xl px-3 py-3 text-sm focus:outline-none" />
            <button @click="applyAdjustment" :disabled="!adjust.reason.trim() || adjust.applying"
              class="w-full bg-gray-800 text-white py-3 rounded-xl text-sm font-semibold hover:bg-gray-700 disabled:opacity-50 transition">
              {{ adjust.applying ? 'Applying…' : `Apply ${adjust.amount > 0 ? '+' : ''}${adjust.amount || 0} pts` }}
            </button>
            <p v-if="adjust.successMsg" class="text-sm text-center font-medium"
              :class="adjust.successMsg.startsWith('✅') ? 'text-green-600' : 'text-red-500'">
              {{ adjust.successMsg }}
            </p>
          </template>
        </div>
      </section>

      <!-- ── Event Audit History ────────────────────────────────────────── -->
      <section v-show="adminSection === 'audit'" class="bg-white rounded-2xl shadow p-4 sm:p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
          <div>
            <h2 class="font-semibold text-gray-600 text-xs uppercase tracking-widest">Event Audit History</h2>
            <p class="text-xs text-gray-400 mt-1">Authoritative operator actions, newest first.</p>
          </div>
          <button @click="loadAudits" :disabled="auditsLoading"
            class="border border-gray-200 text-gray-600 px-3 py-2 rounded-lg text-xs font-semibold hover:bg-gray-50 disabled:opacity-50">
            {{ auditsLoading ? 'Refreshing…' : 'Refresh' }}
          </button>
        </div>

        <p v-if="auditsError" class="text-red-500 text-sm py-3">{{ auditsError }}</p>
        <p v-else-if="!audits.length" class="text-gray-400 text-sm text-center py-8">No operator actions recorded yet.</p>
        <div v-else class="divide-y divide-gray-100 max-h-[32rem] overflow-y-auto">
          <article v-for="audit in audits" :key="audit.id" class="py-3 flex gap-3">
            <span class="mt-1 w-2 h-2 rounded-full flex-shrink-0" :class="auditDotClass(audit.action)"></span>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-3">
                <p class="text-sm font-semibold text-gray-800">{{ auditLabel(audit.action) }}</p>
                <time class="text-[11px] text-gray-400 whitespace-nowrap">{{ formatAuditTime(audit.created_at) }}</time>
              </div>
              <p v-if="audit.subject_type" class="text-xs text-gray-500 mt-0.5">
                {{ audit.subject_type }} #{{ audit.subject_id }}
              </p>
              <dl v-if="Object.keys(audit.context || {}).length" class="mt-2 grid sm:grid-cols-2 gap-x-4 gap-y-1">
                <div v-for="(value, key) in audit.context" :key="key" class="text-xs min-w-0">
                  <dt class="inline text-gray-400">{{ key.replace(/_/g, ' ') }}:</dt>
                  <dd class="inline text-gray-600 ml-1 break-words">{{ value }}</dd>
                </div>
              </dl>
              <p class="text-[11px] text-gray-400 mt-1.5">Operator IP: {{ audit.admin_ip || 'unknown' }}</p>
            </div>
          </article>
        </div>
      </section>

      <!-- Bottom spacer for mobile navigation systems -->
      <div class="h-4 pb-safe"></div>

    </div>

    <!-- ── Question modal ──────────────────────────────────────────────────── -->
    <div v-if="showModal"
      class="fixed inset-0 bg-black/60 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4"
      @click.self="showModal = false">
      <!-- Sheet slides up on mobile, centered card on tablet+ -->
      <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-2xl shadow-xl
                  max-h-[92dvh] sm:max-h-[90dvh] overflow-y-auto
                  p-6 sm:p-6 pb-safe">
        <!-- Drag handle (mobile only) -->
        <div class="w-10 h-1 bg-gray-200 rounded-full mx-auto mb-5 sm:hidden"></div>
        <h3 class="font-bold text-lg mb-4">{{ editingQuestion?.id ? 'Edit' : 'Add' }} Question</h3>
        <QuestionForm
          :initial="editingQuestion"
          @saved="onQuestionSaved"
          @cancel="showModal = false" />
      </div>
    </div>

    <!-- Phase changes use an in-app dialog so the operator stays in context. -->
    <div v-if="phaseDialog.open"
      class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-950/70 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      role="presentation" @click.self="closePhaseDialog">
      <section role="dialog" aria-modal="true" aria-labelledby="phase-dialog-title"
        class="w-full rounded-t-3xl bg-white p-6 pb-safe shadow-2xl sm:max-w-md sm:rounded-2xl sm:p-7">
        <div class="mx-auto mb-5 h-1 w-10 rounded-full bg-gray-200 sm:hidden"></div>
        <div class="mb-5 flex items-start gap-4">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-visa/10 text-xl" aria-hidden="true">⇄</span>
          <div>
            <p class="text-xs font-black uppercase tracking-[.18em] text-visa">Confirm phase change</p>
            <h2 id="phase-dialog-title" class="mt-1 text-xl font-black text-gray-900">
              Switch to “{{ phaseLabel(phaseDialog.target) }}”?
            </h2>
          </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="font-semibold text-gray-500">Current</span>
            <span class="rounded-full px-2.5 py-1 text-xs font-black uppercase" :class="phaseColors[phase] ?? 'bg-gray-200 text-gray-600'">
              {{ phaseLabel(phase) }}
            </span>
          </div>
          <div class="my-3 border-t border-gray-200"></div>
          <div class="flex items-center justify-between gap-3 text-sm">
            <span class="font-semibold text-gray-500">Next</span>
            <span class="rounded-full px-2.5 py-1 text-xs font-black uppercase" :class="phaseColors[phaseDialog.target] ?? 'bg-gray-200 text-gray-600'">
              {{ phaseLabel(phaseDialog.target) }}
            </span>
          </div>
        </div>

        <p v-if="phaseDialog.target === 'predictions_closed'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
          Players will no longer be able to submit or edit predictions.
        </p>
        <p v-else-if="phaseDialog.target === 'prediction_reveal'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
          Prediction results and the final leaderboard will be shown publicly.
        </p>
        <p v-else-if="phaseDialog.target === 'lobby'" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
          The public screen will return to the event lobby.
        </p>

        <p v-if="phaseDialog.error" role="alert" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {{ phaseDialog.error }}
        </p>

        <div class="mt-6 grid grid-cols-2 gap-3">
          <button type="button" @click="closePhaseDialog" :disabled="phaseDialog.saving"
            class="min-h-12 rounded-xl border border-gray-200 bg-white px-4 font-bold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
            Cancel
          </button>
          <button type="button" @click="confirmPhaseChange" :disabled="phaseDialog.saving"
            class="min-h-12 rounded-xl bg-visa px-4 font-black text-white transition hover:bg-visa/90 disabled:opacity-50">
            {{ phaseDialog.saving ? 'Switching…' : 'Switch phase' }}
          </button>
        </div>
      </section>
    </div>

    <!-- Question activation confirmation. -->
    <div v-if="questionDialog.open && questionDialog.question"
      class="fixed inset-0 z-[60] flex items-end justify-center bg-gray-950/70 p-0 backdrop-blur-sm sm:items-center sm:p-4"
      role="presentation" @click.self="closeQuestionDialog">
      <section role="dialog" aria-modal="true" aria-labelledby="question-live-dialog-title"
        class="w-full rounded-t-3xl bg-white p-6 pb-safe shadow-2xl sm:max-w-lg sm:rounded-2xl sm:p-7">
        <div class="mx-auto mb-5 h-1 w-10 rounded-full bg-gray-200 sm:hidden"></div>
        <div class="mb-5 flex items-start gap-4">
          <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-visa/10 text-xl text-visa" aria-hidden="true">▶</span>
          <div>
            <p class="text-xs font-black uppercase tracking-[.18em] text-visa">Confirm live question</p>
            <h2 id="question-live-dialog-title" class="mt-1 text-xl font-black text-gray-900">Send this question live?</h2>
          </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 sm:p-5">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-xs font-bold"
              :class="categoryMeta[questionDialog.question.category]?.badgeClass ?? 'bg-gray-200 text-gray-600'">
              {{ categoryMeta[questionDialog.question.category]?.short ?? questionDialog.question.category }}
            </span>
            <span class="rounded-full bg-white px-2.5 py-1 text-xs font-bold text-gray-600 ring-1 ring-gray-200">
              {{ questionDialog.question.duration_seconds }} seconds
            </span>
            <span v-if="questionDialog.question.is_double_points"
              class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-800">
              ⚡ Double points
            </span>
          </div>
          <p class="text-base font-bold leading-relaxed text-gray-900 sm:text-lg">{{ questionDialog.question.text }}</p>
          <ol class="mt-4 grid gap-2 sm:grid-cols-2">
            <li v-for="(option, index) in questionDialog.question.options" :key="`${index}-${option}`"
              class="flex min-w-0 items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition"
              :class="option === questionDialog.question.correct_answer
                ? 'border-green-500 bg-green-50 text-green-900 ring-2 ring-green-500/20'
                : 'border-gray-200 bg-white text-gray-700'">
              <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-black"
                :class="option === questionDialog.question.correct_answer ? 'bg-green-600 text-white' : 'bg-visa/10 text-visa'">
                {{ ['A', 'B', 'C', 'D'][index] }}
              </span>
              <span class="min-w-0 flex-1 break-words" :class="option === questionDialog.question.correct_answer ? 'font-bold' : ''">
                {{ option }}
              </span>
              <span v-if="option === questionDialog.question.correct_answer"
                class="shrink-0 rounded-full bg-green-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                ✓ Correct
              </span>
            </li>
          </ol>
        </div>

        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800">
          The countdown starts immediately and the question will appear on player devices and the main screen.
        </p>
        <p v-if="questionDialog.error" role="alert" class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
          {{ questionDialog.error }}
        </p>

        <div class="mt-6 grid grid-cols-2 gap-3">
          <button type="button" @click="closeQuestionDialog" :disabled="questionDialog.saving"
            class="min-h-12 rounded-xl border border-gray-200 bg-white px-4 font-bold text-gray-600 transition hover:bg-gray-50 disabled:opacity-50">
            Cancel
          </button>
          <button type="button" @click="confirmQuestionActivation" :disabled="questionDialog.saving"
            class="min-h-12 rounded-xl bg-visa px-4 font-black text-white transition hover:bg-visa/90 disabled:opacity-50">
            {{ questionDialog.saving ? 'Going live…' : '▶ Go live now' }}
          </button>
        </div>
      </section>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import axios from 'axios'
import QRCode from 'qrcode'
import { useEventState } from '../../composables/useEventState'
import QuestionForm from './QuestionForm.vue'
import PlayerReview from './PlayerReview.vue'
import RoundManager from './RoundManager.vue'

const { phase, question: stateQuestion, playerCount, predictionCount, fetchState } = useEventState()

const qrCanvas  = ref(null)
const appUrl    = document.querySelector('meta[name="app-url"]')?.content ?? window.location.origin
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? ''
const logoutUrl = appUrl + '/admin/logout'
const phaseDialog = reactive({ open: false, target: '', saving: false, error: '' })
const questionDialog = reactive({ open: false, question: null, saving: false, error: '' })

async function goToTestTools() {
  activeTab.value = 'admin'
  adminSection.value = 'testing'
  await nextTick()
  document.getElementById('event-test-tools')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

onMounted(() => {
  if (qrCanvas.value) {
    QRCode.toCanvas(qrCanvas.value, appUrl, { width: 100, margin: 1 })
  }
})

const activeTab = ref('admin')
const adminSection = ref('phase')
const adminSections = [
  { id: 'phase', label: 'Phase', icon: '▶' },
  { id: 'players', label: 'Players', icon: '👥' },
  { id: 'match', label: 'Match', icon: '⚽' },
  { id: 'questions', label: 'Questions', icon: '?' },
  { id: 'display', label: 'Display', icon: '▣' },
  { id: 'testing', label: 'Testing', icon: '◈' },
  { id: 'operations', label: 'Operations', icon: '!' },
  { id: 'audit', label: 'Audit', icon: '≡' },
]
const tabs = [
  { id: 'admin',  label: '⚙️ Admin' },
  { id: 'screen', label: '📺 Main Screen' },
  { id: 'player', label: '📱 Player View' },
]

const questions       = ref([])
const audits          = ref([])
const auditsLoading   = ref(false)
const auditsError     = ref('')
const matchConfigLocked = ref(false)
const matchConfigSaving = ref(false)
const matchConfigMessage = ref('')
const matchConfigError = ref(false)
const matchForm = reactive({ home_team: '', away_team: '', home_squad_text: '', away_squad_text: '', kickoff_at: '', venue: '' })
const teams = ref([])
const showTeamManager = ref(false)
const managedTeamId = ref('')
const newTeam = reactive({ name: '', code: '' })
const newTeamPlayer = reactive({ name: '', position: '', shirt_number: null })
const teamMessage = ref('')
const teamError = ref(false)
const managedTeam = computed(() => teams.value.find(team => team.id === Number(managedTeamId.value)))
const showModal       = ref(false)
const countdownDraft = ref(30)
const countdownSaving = ref(false)
const countdownMessage = ref('')
const countdownError = ref(false)
const editingQuestion = ref(null)

const activeCategory = ref('all')

const categoryMeta = {
  general_knowledge: { short: 'General',  badgeClass: 'bg-gray-200 text-gray-700' },
  fifa_world_cup:    { short: 'FIFA ⚽',   badgeClass: 'bg-blue-100 text-blue-700' },
  visa:              { short: 'Visa',      badgeClass: 'bg-indigo-100 text-indigo-700' },
}

const categoryFilters = [
  { value: 'all',               label: 'All',      activeClass: 'bg-gray-800 text-white' },
  { value: 'general_knowledge', label: 'General',  activeClass: 'bg-gray-500 text-white' },
  { value: 'fifa_world_cup',    label: 'FIFA ⚽',   activeClass: 'bg-blue-500 text-white' },
  { value: 'visa',              label: 'Visa',     activeClass: 'bg-indigo-500 text-white' },
]
const durationOptions = [5, 10, 15, 20, 30, 45, 60, 90, 120]

const filteredQuestions = computed(() =>
  activeCategory.value === 'all'
    ? questions.value
    : questions.value.filter(q => q.category === activeCategory.value)
)

const nextAction = computed(() => ({
  lobby:              { phase: 'predictions_open', label: 'Open predictions', help: 'Players can submit their match predictions.' },
  predictions_open:   { phase: 'predictions_closed', label: 'Close predictions', help: 'Use this at kick-off before starting trivia.' },
  predictions_closed: { phase: null, label: 'Start a question from the question bank', help: 'Review the question, then press Live.' },
  trivia_live:        { phase: null, label: 'Close and reveal the live question', help: 'Use the Close button on the live question.' },
  trivia_reveal:      { phase: null, label: 'Start the next draft question', help: 'Select the next reviewed question and press Live.' },
  trivia_complete:    { phase: 'prediction_reveal', label: 'Reveal prediction results when ready', help: 'Resolve the match result first.' },
  match_ended:        { phase: 'prediction_reveal', label: 'Reveal the prediction winner', help: 'Prediction scores have been calculated.' },
  prediction_reveal:  { phase: 'lobby', label: 'Return to lobby', help: 'Only use this when the event is complete.' },
}[phase.value] ?? { phase: null, label: 'Review the current event state', help: 'No automatic action is available.' }))

function categoryCount(cat) {
  return cat === 'all'
    ? questions.value.length
    : questions.value.filter(q => q.category === cat).length
}

const phases = [
  { value: 'lobby',              label: 'Lobby' },
  { value: 'predictions_open',   label: 'Open Predictions' },
  { value: 'predictions_closed', label: 'Close Predictions' },
  { value: 'trivia_complete',    label: 'Trivia Complete' },
  { value: 'prediction_reveal',  label: 'Reveal Predictions' },
]

const phaseColors = {
  lobby:              'bg-gray-200 text-gray-600',
  predictions_open:   'bg-blue-100 text-blue-700',
  predictions_closed: 'bg-orange-100 text-orange-700',
  trivia_live:        'bg-green-100 text-green-700',
  trivia_reveal:      'bg-purple-100 text-purple-700',
  trivia_complete:    'bg-purple-200 text-purple-800',
  match_ended:        'bg-yellow-100 text-yellow-700',
  prediction_reveal:  'bg-visa/10 text-visa',
}

const result           = reactive({ score_home: 0, score_away: 0, halftime_score_home: 0, halftime_score_away: 0, first_scoring_team: 'none', scorer: '', potm: '' })
const submittingResult = ref(false)
const matchResultMsg   = ref('')
const matchResultSuccess = ref(false)

const adjust = reactive({
  nickname: '', looking: false, lookupError: '',
  player: null,
  amount: 0, reason: '', applying: false, successMsg: '',
})
const testTools = reactive({ count: 25, includeAnswers: true, answerRate: 100, correctRate: 70, busy: false, removePlayers: false, confirmation: '', message: '', error: false })
const scoringRehearsal = reactive({ loading: false, passed: null, version: '', checks: [], error: '' })
const testingStatus = reactive({ players: 0, real_players: 0, simulated_players: 0, predictions: 0, answers: 0, questions: 0 })
const normalizedResetConfirmation = computed(() => testTools.confirmation.trim().toUpperCase())
const testingMetrics = computed(() => [
  { label: 'All players', value: testingStatus.players },
  { label: 'Real', value: testingStatus.real_players },
  { label: 'Test', value: testingStatus.simulated_players },
  { label: 'Predictions', value: testingStatus.predictions },
  { label: 'Answers', value: testingStatus.answers },
  { label: 'Questions', value: testingStatus.questions },
])
const liveQuestion = computed(() => questions.value.find(question => question.status === 'live') ?? null)

watch(liveQuestion, question => {
  if (question) countdownDraft.value = question.duration_seconds
})

const squadList = computed(() => [...lines(matchForm.home_squad_text), ...lines(matchForm.away_squad_text)])
const resultScorerList = computed(() => result.first_scoring_team === 'home'
  ? lines(matchForm.home_squad_text)
  : result.first_scoring_team === 'away' ? lines(matchForm.away_squad_text) : [])

watch(() => result.first_scoring_team, () => { result.scorer = '' })

onMounted(() => {
  loadQuestions()
  loadAudits()
  loadMatchConfig()
  loadTeams()
  loadTestingStatus()
})

async function loadTestingStatus() {
  const { data } = await axios.get('/api/admin/testing/status')
  Object.assign(testingStatus, data)
}

async function runScoringRehearsal() {
  scoringRehearsal.loading = true
  scoringRehearsal.error = ''
  try {
    const { data } = await axios.get('/api/admin/testing/scoring-rehearsal')
    Object.assign(scoringRehearsal, {
      passed: data.passed, version: data.version, checks: data.checks ?? [],
    })
  } catch (e) {
    scoringRehearsal.error = e.response?.data?.message ?? 'Could not run scoring checks.'
  } finally {
    scoringRehearsal.loading = false
  }
}

async function refreshOperations() {
  await Promise.all([fetchState(), loadQuestions(), loadAudits(), loadTestingStatus()])
}

async function loadQuestions() {
  const { data } = await axios.get('/api/admin/questions')
  questions.value = data
}

async function loadAudits() {
  auditsLoading.value = true
  auditsError.value = ''
  try {
    const { data } = await axios.get('/api/admin/audits', { params: { limit: 50 } })
    audits.value = data.data ?? []
  } catch (e) {
    auditsError.value = e.response?.data?.message ?? 'Could not load audit history.'
  } finally {
    auditsLoading.value = false
  }
}

function lines(value) {
  return [...new Set((value ?? '').split(/\r?\n/).map(v => v.trim()).filter(Boolean))]
}

async function loadTeams() {
  const { data } = await axios.get('/api/admin/teams')
  teams.value = data.data ?? []
}

function loadTeamIntoMatch(id, side) {
  const team = teams.value.find(item => item.id === Number(id))
  if (!team) return
  matchForm[`${side}_team`] = team.name
  matchForm[`${side}_squad_text`] = team.players.map(player => player.name).join('\n')
  matchConfigMessage.value = `${team.name} loaded as ${side} team. Save the match configuration to apply it.`
  matchConfigError.value = false
}

async function createTeam() {
  teamMessage.value = ''; teamError.value = false
  try {
    const { data } = await axios.post('/api/admin/teams', { name: newTeam.name.trim(), code: newTeam.code.trim() || null })
    newTeam.name = ''; newTeam.code = ''
    await loadTeams(); managedTeamId.value = data.team.id
    teamMessage.value = 'Team created.'
  } catch (e) {
    teamError.value = true; teamMessage.value = e.response?.data?.message ?? 'Could not create team.'
  }
}

async function addTeamPlayer() {
  teamMessage.value = ''; teamError.value = false
  try {
    await axios.post(`/api/admin/teams/${managedTeam.value.id}/players`, {
      name: newTeamPlayer.name.trim(), position: newTeamPlayer.position || null, shirt_number: newTeamPlayer.shirt_number || null,
    })
    Object.assign(newTeamPlayer, { name: '', position: '', shirt_number: null })
    await loadTeams(); teamMessage.value = 'Player added.'
  } catch (e) {
    teamError.value = true; teamMessage.value = e.response?.data?.message ?? 'Could not add player.'
  }
}

async function removeTeamPlayer(player) {
  if (!confirm(`Remove ${player.name} from ${managedTeam.value.name}?`)) return
  await axios.delete(`/api/admin/sports-players/${player.id}`)
  await loadTeams(); teamMessage.value = 'Player removed.'; teamError.value = false
}

async function simulateUsers() {
  testTools.busy = true; testTools.message = ''; testTools.error = false
  try {
    const { data } = await axios.post('/api/admin/testing/simulate', {
      count: testTools.count,
      include_answers: testTools.includeAnswers,
      answer_rate: testTools.answerRate,
      correct_rate: testTools.correctRate,
    })
    testTools.message = data.message
    await Promise.all([fetchState(), loadAudits(), loadMatchConfig(), loadTestingStatus()])
  } catch (e) {
    testTools.error = true; testTools.message = e.response?.data?.message ?? 'Could not simulate users.'
  } finally {
    testTools.busy = false
  }
}

async function clearSimulatedUsers() {
  if (!confirm('Remove all simulated test users and their predictions? Real attendees will be kept.')) return
  testTools.busy = true; testTools.message = ''; testTools.error = false
  try {
    const { data } = await axios.delete('/api/admin/testing/simulated-players')
    testTools.message = data.message
    await Promise.all([fetchState(), loadAudits(), loadMatchConfig(), loadTestingStatus()])
  } catch (e) {
    testTools.error = true; testTools.message = e.response?.data?.message ?? 'Could not clear simulated users.'
  } finally {
    testTools.busy = false
  }
}

async function resetEvent() {
  const scope = testTools.removePlayers ? 'including ALL registered players' : 'while keeping registered players'
  if (!confirm(`Reset all event gameplay ${scope}? This cannot be undone.`)) return
  testTools.busy = true; testTools.message = ''; testTools.error = false
  try {
    const { data } = await axios.post('/api/admin/testing/reset-event', {
      confirmed: true,
      confirmation: normalizedResetConfirmation.value || null,
      remove_players: testTools.removePlayers,
    })
    const summary = data.summary ?? {}
    testTools.message = `${data.message} Cleared ${summary.answers ?? 0} answers and ${summary.predictions ?? 0} predictions.`
    testTools.confirmation = ''; testTools.removePlayers = false
    Object.assign(result, { score_home: 0, score_away: 0, halftime_score_home: 0, halftime_score_away: 0, first_scoring_team: 'none', scorer: '', potm: '' })
    await Promise.all([fetchState(), loadQuestions(), loadAudits(), loadMatchConfig(), loadTestingStatus()])
  } catch (e) {
    testTools.error = true; testTools.message = e.response?.data?.message ?? 'Could not reset the event.'
  } finally {
    testTools.busy = false
  }
}

async function loadMatchConfig() {
  const { data } = await axios.get('/api/admin/match-config')
  const config = data.config
  matchConfigLocked.value = data.locked
  Object.assign(matchForm, {
    home_team: config.home_team,
    away_team: config.away_team,
    home_squad_text: (config.home_squad ?? []).join('\n'),
    away_squad_text: (config.away_squad ?? []).join('\n'),
    kickoff_at: config.kickoff_at ? config.kickoff_at.slice(0, 16) : '',
    venue: config.venue ?? '',
  })
}

async function saveMatchConfig(force) {
  matchConfigSaving.value = true
  matchConfigMessage.value = ''
  matchConfigError.value = false
  try {
    await axios.put('/api/admin/match-config', {
      home_team: matchForm.home_team.trim(), away_team: matchForm.away_team.trim(),
      home_squad: lines(matchForm.home_squad_text), away_squad: lines(matchForm.away_squad_text),
      kickoff_at: matchForm.kickoff_at || null, venue: matchForm.venue.trim() || null, force,
    })
    matchConfigMessage.value = 'Match configuration saved.'
    await loadMatchConfig()
    await fetchState()
    await loadAudits()
  } catch (e) {
    if (e.response?.status === 409 && e.response?.data?.requires_confirmation
      && confirm('Predictions already exist. Changing the squads can invalidate existing selections. Save anyway?')) {
      matchConfigSaving.value = false
      return saveMatchConfig(true)
    }
    matchConfigError.value = true
    matchConfigMessage.value = e.response?.data?.message ?? 'Could not save match configuration.'
  } finally {
    matchConfigSaving.value = false
  }
}

function auditLabel(action) {
  return ({
    'phase.changed': 'Event phase changed',
    'question.activated': 'Question went live',
    'question.revealed': 'Answer revealed',
    'question.invalidated': 'Question invalidated',
    'predictions.resolved': 'Predictions resolved',
    'score.adjusted': 'Player score adjusted',
    'match.configuration_updated': 'Match configuration updated',
  })[action] ?? action.replace(/[._]/g, ' ')
}

function auditDotClass(action) {
  if (action.includes('invalidated')) return 'bg-red-500'
  if (action.includes('adjusted')) return 'bg-amber-500'
  if (action.includes('revealed') || action.includes('resolved')) return 'bg-visa-gold'
  return 'bg-visa'
}

function formatAuditTime(value) {
  if (!value) return '—'
  return new Intl.DateTimeFormat(undefined, {
    month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit',
  }).format(new Date(value))
}

function phaseLabel(value) {
  return String(value ?? '').replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase())
}

function setPhase(target) {
  if (!target || target === phase.value) return
  Object.assign(phaseDialog, { open: true, target, saving: false, error: '' })
}

function closePhaseDialog() {
  if (phaseDialog.saving) return
  Object.assign(phaseDialog, { open: false, target: '', error: '' })
}

async function confirmPhaseChange() {
  if (!phaseDialog.target || phaseDialog.saving) return
  phaseDialog.saving = true
  phaseDialog.error = ''
  try {
    await axios.post('/api/admin/phase', { phase: phaseDialog.target })
    await fetchState()
    await loadAudits()
    Object.assign(phaseDialog, { open: false, target: '', saving: false, error: '' })
  } catch (e) {
    phaseDialog.error = e.response?.data?.message ?? 'Could not change the event phase.'
    phaseDialog.saving = false
  }
}

function activateQuestion(question) {
  Object.assign(questionDialog, { open: true, question, saving: false, error: '' })
}

function closeQuestionDialog() {
  if (questionDialog.saving) return
  Object.assign(questionDialog, { open: false, question: null, error: '' })
}

async function confirmQuestionActivation() {
  const question = questionDialog.question
  if (!question?.id || questionDialog.saving) return
  questionDialog.saving = true
  questionDialog.error = ''
  try {
    await axios.post(`/api/admin/questions/${question.id}/activate`)
    await Promise.all([loadQuestions(), fetchState(), loadAudits()])
    Object.assign(questionDialog, { open: false, question: null, saving: false, error: '' })
  } catch (e) {
    questionDialog.error = e.response?.data?.message ?? 'Could not send this question live.'
    questionDialog.saving = false
  }
}

async function updateQuestionDuration(q, event) {
  const duration = Number(event?.target?.value ?? event)
  const previous = q.duration_seconds
  if (duration === previous) return

  if (q.status === 'live' && !confirm(`Restart the live countdown at ${duration} seconds?\n\nSaved answers remain and players can change them before the restarted timer ends.`)) {
    event.target.value = previous
    return
  }

  try {
    const { data } = await axios.patch(`/api/admin/questions/${q.id}/duration`, {
      duration_seconds: duration,
      restart_live: q.status === 'live',
    })
    Object.assign(q, data)
    await Promise.all([fetchState(), loadAudits()])
  } catch (e) {
    event.target.value = previous
    alert(e.response?.data?.message ?? 'Could not update the countdown duration.')
  }
}

async function setLiveDuration(duration) {
  if (!liveQuestion.value || countdownSaving.value) return
  duration = Number(duration)
  if (!Number.isInteger(duration) || duration < 5 || duration > 120) {
    countdownError.value = true
    countdownMessage.value = 'Enter a whole number from 5 to 120 seconds.'
    return
  }
  if (duration === liveQuestion.value.duration_seconds
    && !confirm(`Restart the current ${duration}-second countdown from the beginning?`)) return
  if (duration !== liveQuestion.value.duration_seconds
    && !confirm(`Change this live question to ${duration} seconds and restart the countdown?\n\nExisting answers will remain saved.`)) return

  countdownSaving.value = true
  countdownMessage.value = ''
  countdownError.value = false
  try {
    const { data } = await axios.patch(`/api/admin/questions/${liveQuestion.value.id}/duration`, {
      duration_seconds: duration,
      restart_live: true,
    })
    Object.assign(liveQuestion.value, data)
    countdownDraft.value = data.duration_seconds
    countdownMessage.value = `Live countdown restarted at ${data.duration_seconds} seconds.`
    await Promise.all([fetchState(), loadAudits()])
  } catch (e) {
    countdownError.value = true
    countdownMessage.value = e.response?.data?.message ?? 'Could not restart the countdown.'
  } finally {
    countdownSaving.value = false
  }
}

async function closeQuestion(q) {
  if (!q || !confirm(`Close and reveal "${q.text.slice(0, 80)}"?`)) return
  await axios.post(`/api/admin/questions/${q.id}/close`)
  await loadQuestions()
  fetchState()
  loadAudits()
}

async function skipQuestion(q) {
  if (!confirm('Skip this question?')) return
  await axios.post(`/api/admin/questions/${q.id}/skip`)
  await loadQuestions()
  fetchState()
}

async function deleteQuestion(q) {
  if (!confirm(`Delete:\n"${q.text.slice(0, 80)}"`)) return
  await axios.delete(`/api/admin/questions/${q.id}`)
  await loadQuestions()
}

function openAddQuestion() {
  editingQuestion.value = null
  showModal.value = true
}

function editQuestion(q) {
  editingQuestion.value = q
  showModal.value = true
}

async function onQuestionSaved() {
  showModal.value = false
  await loadQuestions()
}

async function submitMatchResult() {
  submittingResult.value = true
  matchResultMsg.value   = ''
  try {
    const { data } = await axios.post('/api/admin/match-result', {
      score_home: result.score_home,
      score_away: result.score_away,
      halftime_score_home: result.halftime_score_home,
      halftime_score_away: result.halftime_score_away,
      first_scoring_team: result.first_scoring_team,
      scorer: result.scorer || null,
      potm:       result.potm   || null,
    })
    matchResultMsg.value     = `✅ Scored ${data.predictions_scored} predictions.`
    matchResultSuccess.value = true
    fetchState()
    loadAudits()
  } catch (e) {
    matchResultMsg.value     = e.response?.data?.message ?? 'Error.'
    matchResultSuccess.value = false
  } finally {
    submittingResult.value = false
  }
}

async function reopenQuestion(q) {
  if (!confirm('Return this question to draft? Existing player answers and scores will be retained.')) return
  await axios.post(`/api/admin/questions/${q.id}/reopen`)
  await loadQuestions()
}

async function invalidateQuestion(q) {
  const reason = prompt('Reason for invalidating this question (required):')
  if (!reason?.trim()) return
  if (!confirm(`Invalidate this question and reverse every point awarded for it?\n\n${q.text}`)) return
  try {
    const { data } = await axios.post(`/api/admin/questions/${q.id}/invalidate`, { reason: reason.trim() })
    alert(`Question invalidated. ${data.answers_affected} answers affected; ${data.points_reversed} points reversed.`)
    await loadQuestions()
    await fetchState()
    await loadAudits()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Could not invalidate the question.')
  }
}

async function lookupPlayer() {
  adjust.looking     = true
  adjust.lookupError = ''
  try {
    const { data } = await axios.post('/api/admin/players/lookup', { nickname: adjust.nickname })
    adjust.player = data
  } catch (e) {
    adjust.lookupError = e.response?.data?.message ?? 'Not found.'
  } finally {
    adjust.looking = false
  }
}

async function applyAdjustment() {
  adjust.applying   = true
  adjust.successMsg = ''
  try {
    const { data } = await axios.post(`/api/admin/players/${adjust.player.id}/adjust-score`, {
      adjustment: adjust.amount,
      reason:     adjust.reason,
    })
    adjust.player.trivia_score = data.trivia_score
    adjust.successMsg = `✅ Done. New score: ${data.trivia_score} pts`
    adjust.amount = 0
    adjust.reason = ''
    loadAudits()
  } catch (e) {
    adjust.successMsg = '❌ ' + (e.response?.data?.message ?? 'Error.')
  } finally {
    adjust.applying = false
  }
}
</script>
