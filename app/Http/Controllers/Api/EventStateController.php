<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventState;
use App\Models\EventAudit;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\MatchConfig;
use App\Models\TriviaRound;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventStateController extends Controller
{
    public function show(ScoringService $scoring): JsonResponse
    {
        $payload = app()->environment('testing')
            ? $this->buildPayload($scoring)
            : Cache::remember('public-event-state-v3', now()->addSecond(), fn () => $this->buildPayload($scoring));

        return response()->json($payload)->header('Cache-Control', 'no-store');
    }

    private function buildPayload(ScoringService $scoring): array
    {
        $state    = $this->closeExpiredQuestion(EventState::current());
        $matchConfig = MatchConfig::current();
        $question = null;
        $round = ['current' => 0, 'total' => 0, 'completed' => 0];
        $questionProgress = ['current' => 0, 'total' => 0, 'completed' => 0];
        $currentTriviaRound = null;

        $roundQuestions = Question::where('status', '!=', 'skipped')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get(['id', 'status']);
        $round['total'] = $roundQuestions->count();
        $round['completed'] = $roundQuestions->where('status', 'closed')->count();

        if ($state->rounds_enabled) {
            $currentTriviaRound = $state->current_round_id ? TriviaRound::find($state->current_round_id) : null;
            $allRounds = TriviaRound::orderBy('position')->get();
            $round = $currentTriviaRound ? [
                'id' => $currentTriviaRound->id,
                'number' => $currentTriviaRound->position,
                'title' => $currentTriviaRound->title,
                'category' => $currentTriviaRound->category,
                'intro_message' => $currentTriviaRound->intro_message,
                'status' => $currentTriviaRound->status,
                'total' => $allRounds->count(),
                'completed' => $allRounds->where('status', 'completed')->count(),
            ] : [
                'id' => null, 'number' => 0, 'title' => null, 'category' => null,
                'intro_message' => null, 'status' => 'waiting',
                'total' => $allRounds->count(),
                'completed' => $allRounds->where('status', 'completed')->count(),
            ];

            if ($currentTriviaRound) {
                $questionsInRound = $currentTriviaRound->questions()->get(['id', 'status']);
                $questionProgress['total'] = $questionsInRound->count();
                $questionProgress['completed'] = $questionsInRound->whereIn('status', ['closed', 'skipped'])->count();
            }
        }

        if ($state->current_question_id) {
            $q = Question::find($state->current_question_id);
            if ($q) {
                if ($state->rounds_enabled && $currentTriviaRound) {
                    $questionsInRound = $currentTriviaRound->questions()->get(['id', 'status']);
                    $questionProgress['current'] = $questionsInRound->search(fn ($candidate) => $candidate->id === $q->id) + 1;
                } else {
                    $round['current'] = $roundQuestions->search(fn ($candidate) => $candidate->id === $q->id) + 1;
                    $questionProgress = $round;
                }
                $canRevealAnswers = in_array($state->phase, ['trivia_reveal', 'trivia_complete']);
                $question = [
                    'id'              => $q->id,
                    'order_index'     => $q->order_index,
                    'round_position'  => $q->round_position,
                    'category'        => $q->category,
                    'type'            => $q->type,
                    'text'            => $q->text,
                    'options'         => $q->options,
                    'duration_seconds'=> $q->duration_seconds,
                    'is_double_points'=> $q->is_double_points,
                    'seconds_remaining' => $q->status === 'live' ? $q->secondsRemaining() : 0,
                    'status'          => $q->status,
                    'answer_count'    => $q->answers()->count(),
                    'answer_distribution' => $canRevealAnswers
                        ? $q->answers()->selectRaw('selected_option, COUNT(*) as total')
                            ->groupBy('selected_option')->pluck('total', 'selected_option')
                        : null,
                    // Never expose the answer while submissions are still open.
                    'correct_answer'  => $canRevealAnswers
                        ? $q->correct_answer : null,
                ];
            }
        }

        // Recent prediction submitters for lobby ticker (last 20, newest first)
        $recentPredictions = Prediction::with('player')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->pluck('player.nickname')
            ->filter()
            ->values()
            ->toArray();

        return [
            'phase'               => $state->phase,
            'server_time'         => now()->toIso8601String(),
            'state_version'       => $state->updated_at?->getTimestampMs(),
            'scoring_rules'       => $scoring->rules(),
            'round'               => $round,
            'question_progress'   => $questionProgress,
            'rounds_enabled'      => (bool) $state->rounds_enabled,
            'question'            => $question,
            // The main screen can scroll a deep field; do not cap it to a top ten.
            // While a question is live the standings would leak answer correctness
            // (a player could confirm a guess by watching their score move and then
            // change their answer before the timer ends), so they are withheld until
            // the reveal.
            'leaderboard'         => match (true) {
                in_array($state->phase, ['match_ended', 'prediction_reveal']) => $scoring->predictionLeaderboard(500),
                $state->phase === 'trivia_live' => [],
                default => $scoring->triviaLeaderboard(500),
            },
            'round_leaderboard'   => $state->rounds_enabled && $currentTriviaRound && $state->phase !== 'trivia_live'
                ? $scoring->roundLeaderboard($currentTriviaRound, 500)
                : [],
            'player_count'        => Player::count(),
            'prediction_count'    => Prediction::count(),
            'recent_predictions'  => $recentPredictions,
            'match'                => [
                'home_team' => $matchConfig->home_team,
                'away_team' => $matchConfig->away_team,
                'home_squad' => $matchConfig->home_squad ?? [],
                'away_squad' => $matchConfig->away_squad ?? [],
                'kickoff_at' => $matchConfig->kickoff_at?->toIso8601String(),
                'venue' => $matchConfig->venue,
            ],
        ];
    }

    /** Persist countdown expiry so the admin, player devices and screen agree. */
    private function closeExpiredQuestion(EventState $state): EventState
    {
        if ($state->phase !== 'trivia_live' || !$state->current_question_id) {
            return $state;
        }

        return DB::transaction(function () use ($state) {
            $lockedState = EventState::whereKey($state->id)->lockForUpdate()->firstOrFail();
            if ($lockedState->phase !== 'trivia_live' || !$lockedState->current_question_id) {
                return $lockedState->fresh();
            }

            $question = Question::whereKey($lockedState->current_question_id)->lockForUpdate()->first();
            if (!$question || $question->status !== 'live' || $question->secondsRemaining() > 0) {
                return $lockedState->fresh();
            }

            $question->update(['status' => 'closed']);
            $lockedState->update(['phase' => 'trivia_reveal']);
            EventAudit::record('question.auto_revealed', $question, [
                'correct_answer' => $question->correct_answer,
                'reason' => 'countdown_expired',
            ]);
            Cache::forget('public-event-state-v3');

            return $lockedState->fresh();
        });
    }

}
