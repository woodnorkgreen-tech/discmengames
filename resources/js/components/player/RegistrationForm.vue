<template>

  <!-- ═══════════════════════════════════════════════════════════════════
       LANDING SPLASH
  ════════════════════════════════════════════════════════════════════ -->
  <div v-if="view === 'landing'" class="landing-root min-h-dvh flex flex-col select-none">

    <!-- ── Top logo bar ────────────────────────────────────────────────── -->
    <header class="relative z-10 flex items-center justify-between px-5 sm:px-8 lg:px-12 pt-safe pt-5 sm:pt-8 pb-2">

      <div class="client-mark" aria-label="Discmen Entertainment">
        <img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" />
      </div>

      <span class="event-edition text-[.6rem] font-black uppercase tracking-[.2em] text-white/75 sm:text-sm sm:tracking-[.25em]">FIFA World Cup Final 2026</span>

      <span class="rounded-full border border-[#61C8D2]/30 bg-black/45 px-3 py-1.5 text-[.55rem] font-black uppercase tracking-[.22em] text-[#61C8D2] backdrop-blur sm:text-xs">Live fan hub</span>

    </header>

    <!-- ── Hero area — vertically centred in the upper ~60% of the screen ── -->
    <!-- Bottom padding preserves the players and trophy in the artwork. -->
    <main class="relative z-10 flex-1 flex flex-col items-center lg:items-start justify-center px-6 sm:px-10 lg:px-16 text-center lg:text-left hero-content">

      <span class="font-black uppercase tracking-[.08em] text-discmen-accent drop-shadow mb-4"
        style="font-size: clamp(1rem, 2vw, 1.5rem)">Discmen Final Whistle</span>

      <h1 class="text-white font-black leading-[1.12] mb-5 max-w-3xl tracking-[-0.03em]"
        style="font-size: clamp(1.7rem, 4vw, 4rem); text-shadow: 0 3px 24px rgba(0,0,0,0.5)">
        Tap into the action.<br />
        <span class="uppercase text-discmen-accent">Predict the Final. Own the Moment.</span>
      </h1>

      <p class="max-w-xl text-white/72 text-sm sm:text-base lg:text-lg leading-relaxed mb-8">
        Join the live Discmen fan experience for Argentina vs Spain. Predict the score, test your football knowledge, and climb the leaderboard.
      </p>

      <button @click="view = 'register'"
        class="play-btn w-full max-w-xs py-4 rounded-xl font-extrabold text-base sm:text-lg transition active:scale-95">
        Join the game <span aria-hidden="true">→</span>
      </button>

      <button @click="view = 'login'"
        class="mt-5 text-white/50 text-xs sm:text-sm hover:text-white transition underline-offset-2 hover:underline pb-safe">
        Already registered? <span class="text-white font-semibold">Sign in</span>
      </button>
    </main>

  </div>

  <!-- Returning players can restore their profile after closing the browser or changing device. -->
  <div v-else-if="view === 'login'" class="event-surface min-h-dvh flex items-center justify-center p-4 sm:p-6 pt-safe pb-safe">
    <div class="w-full max-w-md">
      <button @click="view = 'landing'; errorMsg = ''" class="mb-5 flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-300">← Back</button>
      <div class="mb-6 text-center">
        <span class="auth-client-logo discmen-logo-tile" aria-label="Discmen Entertainment">
          <img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" />
        </span>
        <p class="brand-kicker mb-2">Returning player</p>
        <h1 class="mb-1 text-2xl font-extrabold text-white sm:text-3xl">Welcome back</h1>
        <p class="text-sm text-white/60 sm:text-base">Use the nickname and game PIN you registered with.</p>
        <p class="mt-2 text-xs text-white/40">Older profile without a PIN? Enter a new 4-digit PIN once to secure it.</p>
      </div>

      <form @submit.prevent="login" class="glass-card space-y-5 rounded-2xl p-6 sm:p-8">
        <div>
          <label for="login-nickname" class="mb-1.5 block text-sm font-medium text-gray-300">Nickname</label>
          <input id="login-nickname" v-model="loginForm.nickname" type="text" minlength="2" maxlength="50" required autocomplete="username"
            placeholder="Your event nickname" class="field-control px-4 py-3.5 text-base placeholder-white/30" />
        </div>
        <div>
          <label for="login-pin" class="mb-1.5 block text-sm font-medium text-gray-300">4-digit game PIN</label>
          <input id="login-pin" v-model="loginForm.pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required autocomplete="current-password"
            placeholder="••••" class="field-control px-4 py-3.5 text-center text-xl tracking-[.5em] placeholder-white/30" />
        </div>
        <p v-if="errorMsg" class="text-center text-sm text-red-400">{{ errorMsg }}</p>
        <button type="submit" :disabled="submitting"
          class="w-full rounded-xl bg-discmen py-4 text-base font-bold text-white transition hover:bg-discmen/80 disabled:opacity-50">
          {{ submitting ? 'Signing in…' : 'Sign in →' }}
        </button>
        <button type="button" @click="view = 'register'; errorMsg = ''" class="w-full text-sm text-gray-400 hover:text-white">
          New player? Create a profile
        </button>
      </form>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════
       REGISTRATION FORM
  ════════════════════════════════════════════════════════════════════ -->
  <div v-else-if="view === 'register'" class="event-surface min-h-dvh flex items-center justify-center p-4 sm:p-6 pt-safe pb-safe">
    <div class="w-full max-w-md sm:max-w-lg">

      <!-- Back to landing -->
      <button @click="view = 'landing'" class="flex items-center gap-1 text-gray-500 hover:text-gray-300 text-sm mb-5 transition">
        ← Back
      </button>

      <!-- Header -->
      <div class="text-center mb-6">
        <span class="auth-client-logo discmen-logo-tile" aria-label="Discmen Entertainment">
          <img src="/images/client/discmen-entertainment-logo.png" alt="Discmen Entertainment" />
        </span>
        <p class="brand-kicker mb-2">Player registration</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1">Join the game</h1>
        <p class="text-white/60 text-sm sm:text-base">Create your event profile in under a minute.</p>
      </div>

      <!-- Success state -->
      <div v-if="registered" class="bg-discmen/20 border border-discmen-accent/40 rounded-2xl p-8 text-center">
        <div class="text-5xl mb-4">🎉</div>
        <h2 class="text-2xl font-bold text-discmen-accent mb-2">You're in!</h2>
        <p class="text-gray-300 mb-1">Welcome, <strong>{{ nickname }}</strong></p>
        <p class="text-gray-400 text-sm mb-6">Watch the big screen for predictions and trivia rounds.</p>
        <button @click="goToPlay"
          class="w-full bg-discmen hover:bg-discmen/80 text-white font-bold py-4 rounded-xl transition text-base">
          Go to Game →
        </button>
      </div>

      <!-- Registration form -->
      <form v-else @submit.prevent="submit" class="glass-card rounded-2xl p-6 sm:p-8 space-y-4 sm:space-y-5">

        <div>
          <label for="register-nickname" class="block text-sm sm:text-base font-medium text-gray-300 mb-1.5">Nickname *</label>
          <input id="register-nickname" v-model="form.nickname" type="text" maxlength="50" minlength="2" required
            placeholder="What should we call you?"
            autocomplete="nickname" class="field-control px-4 py-3.5 text-base placeholder-white/30" />
          <p class="mt-1.5 text-xs text-gray-500">
            Your nickname is your identity for the whole event — no phone number or email needed.
            Pick something unique; it appears on the big screen.
          </p>
        </div>

        <div>
          <label for="register-pin" class="block text-sm sm:text-base font-medium text-gray-300 mb-1.5">Create a 4-digit game PIN *</label>
          <input id="register-pin" v-model="form.pin" type="password" inputmode="numeric" pattern="[0-9]{4}" minlength="4" maxlength="4" required
            autocomplete="new-password" placeholder="••••"
            class="field-control px-4 py-3.5 text-center text-xl tracking-[.5em] placeholder-white/30" />
          <p class="mt-1.5 text-xs text-gray-500">Remember this PIN. It lets you return on this or another device.</p>
        </div>

        <label class="flex items-start gap-3 cursor-pointer">
          <input v-model="form.consent" type="checkbox" required
            class="mt-0.5 w-5 h-5 rounded accent-discmen flex-shrink-0" />
          <span class="text-sm text-gray-400 leading-snug">
            I agree to take part in this event's games and accept the event rules *
          </span>
        </label>

        <p v-if="errorMsg" class="text-red-400 text-sm text-center">{{ errorMsg }}</p>

        <button type="submit" :disabled="submitting"
          class="w-full bg-discmen hover:bg-discmen/80 disabled:opacity-50 text-white font-bold py-4 rounded-xl transition text-base sm:text-lg">
          {{ submitting ? 'Creating your profile…' : 'Create profile →' }}
        </button>

        <p class="text-center text-xs text-gray-500 leading-snug">
          Your progress is saved. Use your nickname and PIN whenever you need to sign back in.
        </p>
      </form>

    </div>
  </div>

</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

// ── State machine ─────────────────────────────────────────────────────────────
const view = ref('landing')   // 'landing' | 'register' | 'login'

// ── Registration — nickname only, no personal data collected ─────────────────
const form = reactive({
  nickname:      '',
  pin:           '',
  consent:       false,
})
const loginForm = reactive({ nickname: '', pin: '' })

const registered = ref(false)
const submitting  = ref(false)
const errorMsg    = ref('')
const nickname    = ref('')

async function submit() {
  submitting.value = true
  errorMsg.value   = ''
  try {
    const { data } = await axios.post('/api/players', form)
    persistPlayer(data)
    nickname.value   = data.nickname
    registered.value = true
  } catch (e) {
    errorMsg.value = e.response?.data?.message ?? 'Something went wrong. Try again.'
  } finally {
    submitting.value = false
  }
}

function persistPlayer(data) {
  localStorage.setItem('player_id', data.player_id)
  localStorage.setItem('player_nickname', data.nickname)
  localStorage.setItem('player_session_token', data.session_token)
  // Keep the current tab compatible with older code while localStorage provides persistence.
  sessionStorage.setItem('player_id', data.player_id)
  sessionStorage.setItem('player_nickname', data.nickname)
  sessionStorage.setItem('player_session_token', data.session_token)
}

async function login() {
  submitting.value = true
  errorMsg.value = ''
  try {
    const { data } = await axios.post('/api/players/login', loginForm)
    persistPlayer(data)
    window.location.href = '/play'
  } catch (e) {
    errorMsg.value = e.response?.data?.message ?? 'Unable to sign in. Try again.'
  } finally {
    submitting.value = false
  }
}

function goToPlay() {
  if (localStorage.getItem('player_session_token') || sessionStorage.getItem('player_session_token')) {
    window.location.href = '/play'
  } else {
    errorMsg.value = 'Sign in to continue.'
    view.value = 'login'
  }
}
</script>

<style scoped>
/* ── Landing: full-bleed background image ──────────────────────────────────
   Dark gradient overlay sits on top of the photo to keep text readable.
   Discmen black is the fallback while the image loads or if it is unavailable.
─────────────────────────────────────────────────────────────────────────── */
.landing-root {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  background-color: #100f0d;
  background-image:
    radial-gradient(circle at 88% 12%, rgba(97, 200, 210, .2), transparent 30%),
    radial-gradient(circle at 9% 23%, rgba(97, 200, 210, .16), transparent 31%),
    linear-gradient(to bottom, rgba(8, 12, 11, .38) 0%, rgba(9, 23, 23, .12) 36%, rgba(8, 17, 17, .34) 65%, rgba(7, 9, 8, .8) 100%),
    url('/images/backgrounds/discmen.jpeg');

  /* Portrait phones: keep the producer mark, stadium, players and trophy in frame. */
  background-size: cover;
  background-position: center top;
  background-repeat: no-repeat;
  box-shadow: inset 0 0 120px rgba(0, 0, 0, .34);
}

.landing-root::before {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background:
    linear-gradient(115deg, rgba(20, 119, 131, .18), transparent 38%),
    linear-gradient(180deg, rgba(7, 11, 10, .2), transparent 56%);
}

.landing-root::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  opacity: .18;
  background: repeating-linear-gradient(115deg, transparent 0 84px, rgba(255,255,255,.08) 85px, transparent 86px);
  mix-blend-mode: screen;
}

/* The exact client mark sits over the logo embedded in the supplied scene. */
.client-mark {
  display: flex;
  width: clamp(4.5rem, 9vw, 8.5rem);
  height: clamp(4rem, 7.5vw, 7rem);
  align-items: center;
  justify-content: center;
  padding: clamp(.35rem, .7vw, .7rem);
  overflow: hidden;
  border: 1px solid rgba(97, 200, 210, .28);
  border-radius: clamp(.8rem, 1.3vw, 1.25rem);
  background: rgba(16, 15, 13, .9);
  box-shadow: 0 12px 35px rgba(0, 0, 0, .38), inset 0 0 24px rgba(97, 200, 210, .05);
  backdrop-filter: blur(8px);
}
.client-mark img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.auth-client-logo {
  display: flex;
  width: 5rem;
  height: 4.3rem;
  margin: 0 auto .9rem;
}

/* Keep the event edition centred between the client mark and live status. */
.event-edition {
  position: absolute;
  left: 50%;
  width: 38vw;
  max-width: 9rem;
  transform: translateX(-50%);
  text-align: center;
  line-height: 1.15;
  white-space: normal;
}
@media (min-width: 640px) {
  .event-edition {
    width: auto;
    max-width: 56vw;
    white-space: nowrap;
  }
}

/* ── Landscape phones (e.g. iPhone rotated) ────────────────────────────────
   Cover still applies but we shift position to centre the composition       */
@media (orientation: landscape) and (max-height: 500px) {
  .landing-root {
    background-image:
      linear-gradient(90deg, rgba(7, 11, 10, .8), rgba(13, 52, 56, .36) 58%, rgba(7, 11, 10, .2)),
      url('/images/backgrounds/discmenbg.jpeg');
    background-position: center;
  }
  /* Push content higher so it doesn't clash with fans area on short screens */
  .hero-content {
    padding-bottom: 0;
  }
}

/* ── Tablets portrait: retain the phone artwork and its vertical framing ──── */
@media (min-width: 768px) and (orientation: portrait) {
  .landing-root {
    background-size: cover;
    background-position: center top;
  }
}

/* ── Tablets landscape + desktop: swap to the purpose-built 16:9 artwork ─── */
@media (min-width: 768px) and (orientation: landscape) {
  .landing-root {
    background-image:
      radial-gradient(circle at 88% 15%, rgba(97, 200, 210, .18), transparent 27%),
      linear-gradient(90deg, rgba(7, 11, 10, .92) 0%, rgba(13, 52, 56, .64) 47%, rgba(7, 11, 10, .12) 100%),
      url('/images/backgrounds/discmenbg.jpeg');
    background-size: cover;
    background-position: center;
  }
}

/* ── Hero area: reserve the lower portion for the player composition ───── */
.hero-content {
  padding-bottom: clamp(7rem, 29vh, 15rem);
  filter: drop-shadow(0 14px 38px rgba(0, 0, 0, .38));
}
@media (min-width: 1024px) and (orientation: landscape) {
  .hero-content {
    padding-bottom: 4rem;
    padding-right: 43%;
  }
}

/* ── Play button — client-cyan call-to-action ──────────────────────────── */
.play-btn {
  background: linear-gradient(135deg, #61c8d2 0%, #a8edf2 100%);
  box-shadow: 0 12px 32px rgba(97, 200, 210, .3), 0 2px 8px rgba(0,0,0,.35);
  color: #100f0d;
}
.play-btn:hover {
  background: linear-gradient(135deg, #fff 0%, #baf4f6 100%);
  box-shadow: 0 12px 40px rgba(97, 200, 210, .5), 0 2px 8px rgba(0,0,0,.4);
}
.play-btn:active {
  transform: scale(0.96);
}
</style>
