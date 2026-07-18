import { expect, test } from '@playwright/test'
import { readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'

function envValue(name) {
  const line = readFileSync('.env', 'utf8').split(/\r?\n/)
    .find((entry) => entry.startsWith(`${name}=`))
  return line?.slice(name.length + 1).trim().replace(/^(['"])(.*)\1$/, '$2') ?? ''
}

async function expectOk(response, operation) {
  expect(response.ok(), `${operation}: ${response.status()} ${await response.text()}`).toBeTruthy()
  return response.json()
}

test('complete event lifecycle across admin, player and main screen', async ({ browser }) => {
  const nickname = `E2E Fan ${Date.now()}`
  const admin = await browser.newContext()
  const player = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const screen = await browser.newContext({ viewport: { width: 1440, height: 900 } })
  const browserErrors = []
  let rehearsalQuestion = null
  let mutatedEvent = false
  let createdMatchResult = false

  try {
    const adminPage = await admin.newPage()
    adminPage.on('console', (message) => {
      if (message.type() === 'error') browserErrors.push(`admin: ${message.text()}`)
    })
    await adminPage.goto('/admin/login')
    await adminPage.locator('input[name="username"]').fill(envValue('ADMIN_USERNAME') || 'admin')
    await adminPage.locator('input[name="password"]').fill(envValue('ADMIN_PASSWORD'))
    await adminPage.getByRole('button', { name: /Sign In/ }).click()
    await expect(adminPage).toHaveURL(/\/admin$/)
    await expect(adminPage.getByRole('heading', { name: /VISA FINAL WHISTLE/i })).toBeVisible()

    const status = await expectOk(await adminPage.request.get('/api/admin/testing/status'), 'read test status')
    test.skip(status.predictions > 0 || status.answers > 0 || status.results > 0, 'Browser rehearsal requires an empty event and will not delete existing gameplay.')
    await expectOk(await adminPage.request.post('/api/admin/testing/reset-event', {
      data: { confirmed: true, remove_players: false },
    }), 'prepare empty lobby')
    mutatedEvent = true
    await expectOk(await adminPage.request.put('/api/admin/rounds/settings', {
      data: { enabled: false },
    }), 'use flat mode for the legacy lifecycle rehearsal')

    const questions = await expectOk(await adminPage.request.get('/api/admin/questions'), 'load questions')
    const question = questions.find((item) => item.options?.length >= 2)
    expect(question, 'At least one playable question is required').toBeTruthy()
    rehearsalQuestion = { id: question.id, duration: question.duration_seconds }

    const playerPage = await player.newPage()
    playerPage.on('console', (message) => {
      if (message.type() === 'error') browserErrors.push(`player: ${message.text()}`)
    })
    await playerPage.goto('/')
    await playerPage.getByRole('button', { name: /Join the game/ }).click()
    await playerPage.getByLabel('Nickname *').fill(nickname)
    await playerPage.getByLabel('Create a 4-digit game PIN *').fill('2607')
    await playerPage.getByText(/I have a Visa card/).click()
    await playerPage.getByText(/I agree to take part/).click()
    await playerPage.getByRole('button', { name: /Create profile/ }).click()
    await expect(playerPage.getByRole('heading', { name: /You're in/ })).toBeVisible()
    await playerPage.getByRole('button', { name: /Go to Game/ }).click()
    await expect(playerPage.getByText(nickname, { exact: true })).toBeVisible()

    const screenPage = await screen.newPage()
    screenPage.on('console', (message) => {
      if (message.type() === 'error') browserErrors.push(`screen: ${message.text()}`)
    })
    await screenPage.goto('/screen')
    await expectOk(await adminPage.request.post('/api/admin/phase', { data: { phase: 'predictions_open' } }), 'open predictions')
    await expect(playerPage.getByRole('heading', { name: /Predict the Final/ })).toBeVisible()
    await expect(screenPage.getByText(/Predictions open/i)).toBeVisible()

    const state = await expectOk(await playerPage.request.get('/api/state'), 'load fixture')
    const homePlayer = state.match.home_squad[0]
    const token = await playerPage.evaluate(() => localStorage.getItem('player_session_token'))
    const playerId = await playerPage.evaluate(() => Number(localStorage.getItem('player_id')))
    await expectOk(await playerPage.request.post('/api/predictions', {
      headers: { 'X-Player-Token': token },
      data: {
        player_id: playerId, score_home: 1, score_away: 0,
        first_scoring_team: 'home', first_scorer: homePlayer,
        halftime_winner: 'draw', potm: homePlayer,
      },
    }), 'submit prediction')

    await expectOk(await adminPage.request.post('/api/admin/phase', { data: { phase: 'predictions_closed' } }), 'close predictions')
    await expect(playerPage.getByRole('heading', { name: /Predictions are closed/ })).toBeVisible()
    await expect(screenPage.getByText(/Predictions closed/i)).toBeVisible()
    await playerPage.getByRole('button', { name: 'Got it' }).click()

    await expectOk(await adminPage.request.patch(`/api/admin/questions/${question.id}/duration`, {
      data: { duration_seconds: 8 },
    }), 'shorten rehearsal question')
    await expectOk(await adminPage.request.post(`/api/admin/questions/${question.id}/activate`), 'activate question')
    await expect(playerPage.getByText(question.text)).toBeVisible()
    await playerPage.getByRole('button', { name: new RegExp(question.correct_answer.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')) }).click()
    await expect(playerPage.getByText(/Answer saved/)).toBeVisible()
    await expect(screenPage.getByText(question.text)).toBeVisible()

    await expect(playerPage.getByText(/Time's up!/).first()).toBeVisible({ timeout: 18_000 })
    await expect(screenPage.getByText(/Answer Revealed/i)).toBeVisible({ timeout: 18_000 })

    await expectOk(await adminPage.request.post('/api/admin/phase', { data: { phase: 'trivia_complete' } }), 'complete trivia')
    await expectOk(await adminPage.request.post('/api/admin/match-result', {
      data: {
        score_home: 1, score_away: 0, halftime_score_home: 0, halftime_score_away: 0,
        first_scoring_team: 'home', scorer: homePlayer, potm: homePlayer,
      },
    }), 'resolve predictions')
    createdMatchResult = true
    await expectOk(await adminPage.request.post('/api/admin/phase', { data: { phase: 'prediction_reveal' } }), 'reveal predictions')
    await expect(screenPage.getByText(nickname)).toBeVisible({ timeout: 12_000 })
    expect(browserErrors, browserErrors.join('\n')).toEqual([])

    await expectOk(await adminPage.request.post('/api/admin/testing/reset-event', {
      data: { confirmed: true, remove_players: false },
    }), 'restore lobby')
    await expectOk(await adminPage.request.patch(`/api/admin/questions/${question.id}/duration`, {
      data: { duration_seconds: question.duration_seconds },
    }), 'restore question timer')
  } finally {
    await Promise.allSettled([admin.close(), player.close(), screen.close()])
    const restoreQuestion = rehearsalQuestion
      ? `App\\Models\\Question::whereKey(${rehearsalQuestion.id})->update(['duration_seconds'=>${rehearsalQuestion.duration},'status'=>'draft','activated_at'=>null]);`
      : ''
    const removeCreatedResult = createdMatchResult ? 'App\\Models\\MatchResult::query()->delete();' : ''
    const restoreEvent = mutatedEvent
      ? `App\\Models\\TriviaRound::query()->update(['status'=>'draft']); App\\Models\\EventState::setCurrent(['phase'=>'lobby','current_question_id'=>null,'current_round_id'=>null]); Illuminate\\Support\\Facades\\Cache::forget('public-event-state-v3');`
      : ''
    const cleanup = `App\\Models\\Player::where('nickname', '${nickname.replaceAll("'", "\\'")}')->delete(); ${removeCreatedResult} ${restoreQuestion} ${restoreEvent}`
    execFileSync('ddev', ['artisan', 'tinker', `--execute=${cleanup}`], { stdio: 'ignore' })
  }
})
