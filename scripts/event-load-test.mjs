#!/usr/bin/env node

const args = Object.fromEntries(process.argv.slice(2).map(value => {
  const [key, raw = 'true'] = value.replace(/^--/, '').split('=')
  return [key, raw]
}))

const baseUrl = (args.url ?? process.env.LOAD_TEST_URL ?? 'http://mpesa-visa.ddev.site').replace(/\/$/, '')
const expectedAttendance = Number(args.attendance ?? process.env.EXPECTED_EVENT_ATTENDANCE ?? 100)
const users = Number(args.users ?? expectedAttendance * 2)
const durationSeconds = Number(args.duration ?? process.env.LOAD_TEST_DURATION ?? 15)
const pollIntervalMs = Number(args['poll-ms'] ?? process.env.LOAD_TEST_POLL_MS ?? 1500)
const p95LimitMs = Number(args['p95-ms'] ?? process.env.LOAD_TEST_P95_MS ?? 750)
const p99LimitMs = Number(args['p99-ms'] ?? process.env.LOAD_TEST_P99_MS ?? 1500)
const maxErrorRate = Number(args['max-error-rate'] ?? process.env.LOAD_TEST_MAX_ERROR_RATE ?? 0.01)

if (![expectedAttendance, users, durationSeconds, p95LimitMs, p99LimitMs].every(Number.isFinite) || users < 1 || durationSeconds < 1) {
  console.error('Invalid arguments. Use --attendance=100 --users=200 --duration=15.')
  process.exit(2)
}

const samples = []
const statuses = new Map()
const errors = []
const deadline = Date.now() + durationSeconds * 1000

function percentile(values, percentage) {
  if (!values.length) return 0
  const sorted = [...values].sort((a, b) => a - b)
  return sorted[Math.min(sorted.length - 1, Math.ceil((percentage / 100) * sorted.length) - 1)]
}

async function request(path) {
  const started = performance.now()
  try {
    const response = await fetch(`${baseUrl}${path}`, {
      headers: { Accept: 'application/json', 'User-Agent': 'visa-event-load-test/2026.1' },
      signal: AbortSignal.timeout(Math.max(5000, p99LimitMs * 3)),
    })
    await response.arrayBuffer()
    const latency = performance.now() - started
    samples.push({ path, latency, ok: response.ok })
    statuses.set(response.status, (statuses.get(response.status) ?? 0) + 1)
    if (!response.ok && errors.length < 10) errors.push(`${path}: HTTP ${response.status}`)
  } catch (error) {
    samples.push({ path, latency: performance.now() - started, ok: false })
    statuses.set('network', (statuses.get('network') ?? 0) + 1)
    if (errors.length < 10) errors.push(`${path}: ${error.message}`)
  }
}

async function virtualUser() {
  while (Date.now() < deadline) {
    await request('/api/state')
    const jitter = Math.floor(Math.random() * 500) - 250
    await new Promise(resolve => setTimeout(resolve, Math.max(250, pollIntervalMs + jitter)))
  }
}

async function mainScreenFeed() {
  while (Date.now() < deadline) {
    await request('/api/predictions/feed')
    await new Promise(resolve => setTimeout(resolve, 3000))
  }
}

console.log(`Visa event load test: ${users} virtual users for ${durationSeconds}s`)
console.log(`Target: ${baseUrl} (2× expected attendance: ${expectedAttendance})`)

await request('/api/state')
if (!samples[0]?.ok) {
  console.error('Warm-up request failed. Check LOAD_TEST_URL or start DDEV.')
  console.error(errors.join('\n'))
  process.exit(2)
}
samples.length = 0
statuses.clear()
errors.length = 0

await Promise.all([
  ...Array.from({ length: users }, () => virtualUser()),
  mainScreenFeed(),
])

const latencies = samples.map(sample => sample.latency)
const failed = samples.filter(sample => !sample.ok).length
const errorRate = samples.length ? failed / samples.length : 1
const p50 = percentile(latencies, 50)
const p95 = percentile(latencies, 95)
const p99 = percentile(latencies, 99)
const max = latencies.length ? Math.max(...latencies) : 0
const passed = errorRate <= maxErrorRate && p95 <= p95LimitMs && p99 <= p99LimitMs

console.table({
  requests: samples.length,
  failed,
  error_rate: `${(errorRate * 100).toFixed(2)}%`,
  p50_ms: p50.toFixed(1),
  p95_ms: p95.toFixed(1),
  p99_ms: p99.toFixed(1),
  max_ms: max.toFixed(1),
})
console.log('Statuses:', Object.fromEntries(statuses))
console.log(`Thresholds: error ≤ ${(maxErrorRate * 100).toFixed(2)}%, p95 ≤ ${p95LimitMs}ms, p99 ≤ ${p99LimitMs}ms`)
if (errors.length) console.log('Sample errors:\n' + errors.join('\n'))
console.log(passed ? 'PASS — polling load is within event thresholds.' : 'FAIL — event thresholds were exceeded.')

process.exit(passed ? 0 : 1)
