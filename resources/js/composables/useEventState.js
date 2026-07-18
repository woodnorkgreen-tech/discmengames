import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

/**
 * Keeps the shared event state current. Polling remains the reliable baseline;
 * visibility and connectivity events force an immediate catch-up refresh.
 * All three client types (player, admin, screen) use this same composable.
 */
export function useEventState(intervalMs = 1500) {
    const phase               = ref('lobby')
    const question            = ref(null)
    const leaderboard         = ref([])
    const playerCount         = ref(0)
    const predictionCount     = ref(0)
    const recentPredictions   = ref([])
    const match               = ref({ home_team: 'Home Team', away_team: 'Away Team', home_squad: [], away_squad: [] })
    const round               = ref({ current: 0, total: 0, completed: 0 })
    const questionProgress    = ref({ current: 0, total: 0, completed: 0 })
    const roundsEnabled       = ref(false)
    const roundLeaderboard    = ref([])
    const scoringRules        = ref(null)
    const lastUpdatedAt       = ref(null)
    const loading             = ref(true)
    const error               = ref(null)

    let timer = null
    let requestInFlight = false

    async function fetchState() {
        if (requestInFlight) return
        requestInFlight = true
        try {
            const { data } = await axios.get('/api/state')
            phase.value             = data.phase
            question.value          = data.question
            leaderboard.value       = data.leaderboard
            playerCount.value       = data.player_count
            predictionCount.value   = data.prediction_count
            recentPredictions.value = data.recent_predictions ?? []
            match.value             = data.match ?? match.value
            round.value             = data.round ?? round.value
            questionProgress.value  = data.question_progress ?? questionProgress.value
            roundsEnabled.value     = Boolean(data.rounds_enabled)
            roundLeaderboard.value  = data.round_leaderboard ?? []
            scoringRules.value      = data.scoring_rules ?? scoringRules.value
            lastUpdatedAt.value     = new Date()
            error.value             = null
        } catch (e) {
            error.value = 'Connection issue — retrying…'
        } finally {
            loading.value = false
            requestInFlight = false
        }
    }

    function refreshWhenActive() {
        if (document.visibilityState === 'visible') fetchState()
    }

    onMounted(() => {
        fetchState()
        timer = setInterval(fetchState, intervalMs)
        document.addEventListener('visibilitychange', refreshWhenActive)
        window.addEventListener('online', fetchState)
    })

    onUnmounted(() => {
        clearInterval(timer)
        document.removeEventListener('visibilitychange', refreshWhenActive)
        window.removeEventListener('online', fetchState)
    })

    return { phase, question, leaderboard, playerCount, predictionCount, recentPredictions, match, round, questionProgress, roundsEnabled, roundLeaderboard, scoringRules, lastUpdatedAt, loading, error, fetchState }
}
