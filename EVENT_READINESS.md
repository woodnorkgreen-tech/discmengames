# Discmen Final Whistle Event Readiness Runbook

This document separates what is safe today from work that should be completed before a high-attendance production event.

## Current safe operating flow

1. Reset test data, then configure the match and squads.
2. Review every question, option, correct answer, order, category, and timer.
3. Simulate at least the expected attendance and complete a full rehearsal.
4. Clear simulated players and verify the lobby is empty.
5. Open predictions, close them at kick-off, then activate one question at a time.
6. Wait for the timer to reach zero, close the question, discuss the reveal, then activate the next question.
7. Resolve the match result before revealing the prediction champion.

### Three-round trivia mode

Round mode is optional and the original flat question flow remains available. When enabled in **Admin → Questions → Trivia Round Manager**, the default structure is:

1. **Quick Fire** — four general-knowledge questions.
2. **Football IQ** — four football questions.
3. **Final Whistle** — four mixed general and football questions.

Each round must have exactly one clearly marked Power Question before it can start. Start the round introduction first, then take its assigned questions live in order. Reveal or skip every assigned question before selecting **Complete round & show winner**. Streaks reset between rounds; round points still add directly to the overall trivia total, and equal results share rank.

Never reset the event, force-edit squads, invalidate a question, or restart a live timer without the MC and technical operator agreeing verbally.

### Knockout-match scoring rule

The predicted score and correct-outcome points use the score after **90 minutes plus stoppage time only**. Extra time and penalty shootouts do not count. The MC must announce this before predictions close, and the operator must enter the regulation-time score—not the after-extra-time or shootout result—when resolving predictions.

## Event-day roles

- **MC:** reads questions and maintains audience energy.
- **Game operator:** controls phases, questions, reveals, and timers. This person should not also present.
- **Technical operator:** watches connectivity, server health, projector, logs, and backups.
- **Floor support:** helps guests register without giving them admin access.

## Mandatory preflight (T-60 minutes)

- Confirm the public URL and QR code work on at least two mobile networks.
- Confirm HTTPS, server time, timezone, database space, and application logs.
- Open `/screen` on the actual display computer, select fullscreen, and disable sleep/notifications.
- Log into admin on a separate laptop and confirm the session remains active.
- Test one real phone through registration, prediction, answer change, timeout, reveal, and leaderboard.
- Confirm every asset loads with browser developer tools showing no 404/500 responses.
- Rehearse the full question set with simulated attendance.
- Export or snapshot the database immediately after final configuration.
- Keep a wired network option and a mobile hotspot available.

## Highest-risk gaps still to implement

### P0 — before a large event

- **Load test the real hosting stack.** Polling is now server-cached for one second, but PHP workers, database connections, and bandwidth must be tested at 2× expected attendance.
- **Add health telemetry.** Admin needs server latency, last successful state update, connected/recent clients, error rate, queue status, database availability, and disk space.
- **Add an emergency pause/resume.** Pausing must freeze the authoritative server timer, not merely the browser animation.
- **Add a staged round start.** Use a server-side `starts_at` value for a synchronized “Round X — 3, 2, 1” entry. A browser-only countdown would consume answer time unfairly.
- **Add automatic database snapshots and a restore rehearsal.** A backup that has not been restored in rehearsal is not a recovery plan.
- **Configure production workers correctly.** Use Redis for cache/session, multiple PHP workers, a persistent database, HTTPS, and process supervision.

### P1 — operator workload and recovery

- One-click “close and reveal” when the timer expires, with an optional configurable auto-reveal delay.
- A next-question queue showing “ready / missing answer / duplicate order / too long / already used”.
- Undo the last safe operator action, backed by an audit snapshot.
- A read-only secondary admin/producer screen so more than one person can monitor without issuing commands.
- Export players, answers, predictions, scores, and audit history as CSV.
- Rate-limit and log repeated failed player-session and answer requests.

### P1 — engagement

- Server-synchronized round intro: category, round number, point value, and 3-2-1 animation.
- Between-round leaderboard movement: “up 3 places”, streak badges, fastest correct answer, and participation percentage.
- Milestones: 100 players joined, prediction count goals, perfect-round callouts, and surprise double-points rounds.
- Reveal animation sequence: lock answers → show distribution → reveal correct choice → animate leaderboard changes.
- Optional sound cues controlled from the main-screen computer, always with mute and volume controls.
- MC prompt notes per question, separate from text visible to players.

### P2 — scale and polish

- Laravel Reverb + Redis broadcasting for phase/question/count changes, while retaining slow polling as automatic fallback.
- Installable PWA shell and clearer offline/reconnecting states.
- Localization, text-size checks, color-independent answer states, and reduced-motion support on every animated view.
- Sponsor content scheduling that never interrupts an active question.

## Incident playbooks

### Players report stale screens

1. Do not activate the next question.
2. Check whether admin and `/screen` show the same phase.
3. Confirm the public `/api/state` responds successfully and note latency.
4. Ask affected users to keep the page open; online/visibility recovery triggers an immediate refresh.
5. If widespread, pause the show verbally and switch networks before resuming.

### Main screen fails

1. Keep the current question closed.
2. Open the public screen link on the standby laptop.
3. Enter fullscreen and verify its phase against admin.
4. Resume only when timer and round agree.

### Wrong question or answer is discovered

1. Close the question immediately.
2. Do not manually adjust many individual scores.
3. Use invalidate to reverse it once and preserve the audit record.
4. Explain the cancellation, then move to the next reviewed question.

### Admin loses connection

1. The current server timer continues; do not assume it paused.
2. Reconnect or use the standby authenticated admin device.
3. Verify phase and current question before clicking anything.
4. Never use reset as a recovery action.

## Go/no-go rule

Do not go live until a rehearsal at twice expected users completes without 500 responses, incorrect scoring, timer disagreement greater than two seconds, or an unrecoverable operator action.

### Automated polling load test

Run this against a non-production event environment while DDEV is available:

```bash
npm run load:test -- --attendance=100 --duration=30
```

The harness automatically uses twice the stated attendance (`200` users above). Each virtual player polls `/api/state` at the real 1.5-second client interval, while one simulated main screen polls `/api/predictions/feed` every three seconds. It does not create or change gameplay data. The go/no-go thresholds are no more than 1% errors, p95 at or below 750 ms, and p99 at or below 1,500 ms.

Override the target or thresholds when needed:

```bash
LOAD_TEST_URL=https://discmen-final-whistle.ddev.site npm run load:test -- --users=300 --duration=60 --p95-ms=750 --p99-ms=1500
```

### Authenticated write load test

Run only in an empty local/test lobby after both squads are configured. The command refuses production and active gameplay, creates temporary simulated players, submits predictions and trivia answers through the real authenticated APIs, verifies scoring, and cleans up its data:

```bash
ddev artisan event:write-load-test --users=200 --confirm
```

### Browser lifecycle rehearsal

Run only against an empty local/test event. This drives the rendered admin, mobile-player, and main-screen views through registration, predictions, trivia countdown and answer, automatic reveal, match resolution, and the final leaderboard. It restores the lobby, original question timer, and temporary rehearsal player afterward.

```bash
npm run test:e2e
```
