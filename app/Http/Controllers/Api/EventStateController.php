<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventState;
use App\Models\EventAudit;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\MatchConfig;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $state    = EventState::current();
        $matchConfig = MatchConfig::current();
        $question = null;
        $round = ['current' => 0, 'total' => 0, 'completed' => 0];

        $roundQuestions = Question::where('status', '!=', 'skipped')
            ->orderBy('order_index')
            ->orderBy('id')
            ->get(['id', 'status']);
        $round['total'] = $roundQuestions->count();
        $round['completed'] = $roundQuestions->where('status', 'closed')->count();

        if ($state->current_question_id) {
            $q = Question::find($state->current_question_id);
            if ($q) {
                $round['current'] = $roundQuestions->search(fn ($candidate) => $candidate->id === $q->id) + 1;
                // Expiry is a server-authoritative lock. Reveal immediately when
                // the timer reaches zero even if the MC has not clicked Close yet.
                $hasExpired = $q->status === 'live' && $q->secondsRemaining() <= 0;
                $canRevealAnswers = in_array($state->phase, ['trivia_reveal', 'trivia_complete']) || $hasExpired;
                $question = [
                    'id'              => $q->id,
                    'order_index'     => $q->order_index,
                    'type'            => $q->type,
                    'text'            => $q->text,
                    'options'         => $q->options,
                    'duration_seconds'=> $q->duration_seconds,
                    'is_double_points'=> $q->is_double_points,
                    'seconds_remaining' => $q->secondsRemaining(),
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
            'round'               => $round,
            'question'            => $question,
            'show_phone_on_screen'=> (bool) $state->show_phone_on_screen,
            'leaderboard'         => in_array($state->phase, ['match_ended', 'prediction_reveal'])
                ? $scoring->predictionLeaderboard(10)
                : $scoring->triviaLeaderboard(10),
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

    public function togglePhone(Request $request): JsonResponse
    {
        $enabled = DB::transaction(function () {
            EventState::current();
            $state = EventState::whereKey(1)->lockForUpdate()->firstOrFail();
            $enabled = !$state->show_phone_on_screen;
            $state->update(['show_phone_on_screen' => $enabled]);
            EventAudit::record('display.phone_suffix_toggled', $state, ['enabled' => $enabled]);
            return $enabled;
        });

        Cache::forget('public-event-state-v3');

        return response()->json(['show_phone_on_screen' => $enabled]);
    }

}
