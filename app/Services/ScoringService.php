<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\EventState;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\TriviaRound;
use Illuminate\Support\Facades\DB;

class ScoringService
{
    public function rules(): array
    {
        return [
            'version' => '2026.1',
            'trivia' => [
                'correct' => 1000,
                'speed_max' => 200,
                'network_grace_ms' => 1000,
                'streak_two' => 100,
                'streak_three_plus' => 200,
                'double_multiplier' => 2,
            ],
            'prediction' => [
                'outcome' => 250,
                'exact_score_bonus' => 400,
                'halftime' => 200,
                'first_team' => 150,
                'first_scorer' => 300,
                'no_scorer' => 150,
                'potm' => 200,
                'maximum' => 1500,
            ],
        ];
    }

    // ── Trivia scoring ────────────────────────────────────────────────────────

    /**
     * Score a single answer and persist the result atomically.
     * Returns points awarded (0 on wrong/late).
     */
    public function scoreAnswer(Player $player, Question $question, string $selectedOption, int $responseTimeMs): int
    {
        return DB::transaction(function () use ($player, $question, $selectedOption, $responseTimeMs) {
            Player::whereKey($player->id)->lockForUpdate()->firstOrFail();

            $answer = Answer::updateOrCreate([
                'player_id'        => $player->id,
                'question_id'      => $question->id,
            ], [
                'selected_option'  => $selectedOption,
                'is_correct'       => $selectedOption === $question->correct_answer,
                'response_time_ms' => $responseTimeMs,
                'server_received_at' => now(),
            ]);

            $this->recalculatePlayerTrivia($player);

            return (int) $answer->fresh()->points_awarded;
        });
    }

    /** Rebuild all trivia totals so changing a live answer cannot duplicate points or streaks. */
    public function recalculatePlayerTrivia(Player $player): void
    {
        $answers = Answer::where('player_id', $player->id)
            ->with('question')
            ->get()
            ->keyBy('question_id');
        $roundsEnabled = EventState::current()->rounds_enabled && TriviaRound::exists();
        $questions = $this->scoredQuestions($roundsEnabled);

        $score = $correctCount = $doubleCorrect = $streak = 0;
        $processed = [];
        $previousRoundId = null;

        foreach ($questions as $question) {
            if ($roundsEnabled && $question->trivia_round_id !== $previousRoundId) {
                $streak = 0;
                $previousRoundId = $question->trivia_round_id;
            }
            $answer = $answers->get($question->id);
            if (!$answer) {
                $streak = 0;
                continue;
            }
            $processed[] = $question->id;
            $isCorrect = $answer->selected_option === $question->correct_answer;
            $points = 0;

            if ($isCorrect) {
                $streak++;
                $correctCount++;
                $doubleCorrect += $question->is_double_points ? 1 : 0;
                $points = $this->calculateTriviaBreakdown(
                    $question->is_double_points,
                    $answer->response_time_ms,
                    $question->duration_seconds * 1000,
                    $streak,
                )['total'];
                $score += $points;
            } else {
                $streak = 0;
            }

            $answer->update(['is_correct' => $isCorrect, 'points_awarded' => $points]);
        }

        $answers->each(function (Answer $answer, $questionId) use ($processed) {
            if (!in_array((int) $questionId, $processed, true)) {
                $answer->update(['points_awarded' => 0]);
            }
        });

        // Manual MC adjustments live in their own column so they survive this
        // rebuild instead of being overwritten by the answer-derived total.
        $player->update([
            'trivia_score' => max(0, $score + (int) $player->trivia_manual_adjustment),
            'trivia_streak' => $streak,
            'trivia_correct_count' => $correctCount,
            'trivia_double_correct' => $doubleCorrect,
        ]);
    }

    /**
     * Rebuild trivia totals for every player who has answered anything.
     * Required after a question is skipped or invalidated: dropping a question
     * from scoring changes streak continuity even for players who never answered
     * it, so a per-answerer recalculation is not enough.
     */
    public function recalculateAllScoredPlayers(): void
    {
        Player::whereHas('answers')->cursor()->each(fn (Player $player) => $this->recalculatePlayerTrivia($player));
    }

    /**
     * Hardcoded trivia point formula — not admin-configurable.
     *
     * Compatibility helper for callers that work in seconds remaining.
     */
    public function calculateTriviaPoints(bool $isDouble, int $secondsRemaining, int $totalSeconds, int $streak): int
    {
        $elapsedMs = max(0, ($totalSeconds - $secondsRemaining) * 1000);
        return $this->calculateTriviaBreakdown($isDouble, $elapsedMs, $totalSeconds * 1000, $streak)['total'];
    }

    public function calculateTriviaBreakdown(bool $isDouble, int $responseTimeMs, int $durationMs, int $streak): array
    {
        $rules = $this->rules()['trivia'];
        $effectiveDuration = max(1, $durationMs - $rules['network_grace_ms']);
        $effectiveElapsed = max(0, $responseTimeMs - $rules['network_grace_ms']);
        $speedRatio = max(0, min(1, 1 - ($effectiveElapsed / $effectiveDuration)));
        $speedBonus = (int) round($rules['speed_max'] * $speedRatio);
        $streakBonus = $streak >= 3 ? $rules['streak_three_plus'] : ($streak === 2 ? $rules['streak_two'] : 0);
        $multiplier = $isDouble ? $rules['double_multiplier'] : 1;

        return [
            'correct' => $rules['correct'],
            'speed_bonus' => $speedBonus,
            'streak_bonus' => $streakBonus,
            'multiplier' => $multiplier,
            'total' => ($rules['correct'] + $speedBonus + $streakBonus) * $multiplier,
        ];
    }

    public function triviaBreakdownForAnswer(Answer $target): array
    {
        if (!$target->is_correct) {
            return ['correct' => 0, 'speed_bonus' => 0, 'streak_bonus' => 0, 'multiplier' => 1, 'total' => 0];
        }
        $answers = Answer::where('player_id', $target->player_id)->get()->keyBy('question_id');
        $roundsEnabled = EventState::current()->rounds_enabled && TriviaRound::exists();
        $streak = 0;
        $previousRoundId = null;
        foreach ($this->scoredQuestions($roundsEnabled) as $question) {
            if ($roundsEnabled && $question->trivia_round_id !== $previousRoundId) {
                $streak = 0;
                $previousRoundId = $question->trivia_round_id;
            }
            $answer = $answers->get($question->id);
            $streak = $answer && $answer->selected_option === $question->correct_answer ? $streak + 1 : 0;
            if ($question->id === $target->question_id) {
                return $this->calculateTriviaBreakdown(
                    $question->is_double_points,
                    $target->response_time_ms,
                    $question->duration_seconds * 1000,
                    $streak,
                );
            }
        }
        return ['correct' => 0, 'speed_bonus' => 0, 'streak_bonus' => 0, 'multiplier' => 1, 'total' => 0];
    }

    // ── Prediction scoring ────────────────────────────────────────────────────

    /**
     * Score all unresolved predictions against the stored match result.
     * Idempotent — safe to call multiple times (resolved flag guards re-scoring).
     */
    public function scorePredictions(MatchResult $result): int
    {
        $predictions = Prediction::where('resolved', false)->with('player')->get();

        foreach ($predictions as $prediction) {
            $score = $this->calculatePredictionScore($prediction, $result);

            DB::transaction(function () use ($prediction, $score) {
                $prediction->update(['prediction_score' => $score, 'resolved' => true]);
                $prediction->player->update(['prediction_score' => $score]);
            });
        }

        return $predictions->count();
    }

    /**
     * Points stack independently per outcome category.
     *
     * Exact score bonus        400 pts
     * Correct full-time result 250 pts
     * Correct first team       150 pts
     * Correct first scorer     300 pts
     * Correct half-time result 200 pts
     * Correct POTM             200 pts
     */
    public function calculatePredictionScore(Prediction $prediction, MatchResult $result): int
    {
        return array_sum($this->calculatePredictionBreakdown($prediction, $result));
    }

    public function calculatePredictionBreakdown(Prediction $prediction, MatchResult $result): array
    {
        $rules = $this->rules()['prediction'];
        $breakdown = [
            'outcome' => 0, 'exact_score_bonus' => 0, 'halftime' => 0,
            'first_team' => 0, 'first_scorer' => 0, 'potm' => 0,
        ];

        if ($prediction->score_home === $result->score_home && $prediction->score_away === $result->score_away) {
            $breakdown['exact_score_bonus'] = $rules['exact_score_bonus'];
        }
        $predictedOutcome = $prediction->fulltime_winner
            ?: $this->matchOutcome($prediction->score_home, $prediction->score_away);
        $actualOutcome = $this->matchOutcome($result->score_home, $result->score_away);
        if ($predictedOutcome === $actualOutcome) {
            $breakdown['outcome'] = $rules['outcome'];
        }

        // First team to score. "None" is valid only for a genuine final 0–0.
        $isGoalless = $result->score_home === 0 && $result->score_away === 0;
        $predictedFirstTeam = $prediction->first_scoring_team;
        if (($predictedFirstTeam === 'none' && $isGoalless)
            || ($predictedFirstTeam && $predictedFirstTeam === $result->first_scoring_team)
            // Compatibility for predictions submitted before the team-based migration.
            || (!$predictedFirstTeam && strtolower(trim($prediction->first_scorer)) === 'no goal / n/a' && $isGoalless)) {
            $breakdown['first_team'] = $rules['first_team'];
        }

        $predictedNoGoal = strtolower(trim($prediction->first_scorer)) === 'no goal / n/a';
        if (($predictedNoGoal && $isGoalless)
            || ($result->scorer && strtolower(trim($prediction->first_scorer)) === strtolower(trim($result->scorer)))) {
            $breakdown['first_scorer'] = $predictedNoGoal ? $rules['no_scorer'] : $rules['first_scorer'];
        }

        if ($prediction->halftime_winner && $result->halftime_score_home !== null && $result->halftime_score_away !== null
            && $prediction->halftime_winner === $this->matchOutcome($result->halftime_score_home, $result->halftime_score_away)) {
            $breakdown['halftime'] = $rules['halftime'];
        }

        // Player of the Match — skip if not yet resolved
        if ($result->potm && strtolower(trim($prediction->potm)) === strtolower(trim($result->potm))) {
            $breakdown['potm'] = $rules['potm'];
        }

        return $breakdown;
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    /**
     * Tie-break order:
     *  1. Highest total trivia score
     *  2. Highest correct-answer count
     *  3. Fastest average response time (correct answers only)
     *  4. Most double-points questions answered correctly
     */
    public function triviaLeaderboard(int $limit = 10): array
    {
        $previousSignature = null;
        $rank = 0;
        return Player::select('id', 'nickname', 'trivia_score', 'trivia_correct_count', 'trivia_double_correct')
            ->selectSub(
                Answer::selectRaw('AVG(response_time_ms)')
                    ->whereColumn('player_id', 'players.id')
                    ->where('is_correct', true),
                'avg_response_ms'
            )
            ->orderByDesc('trivia_score')
            ->orderByDesc('trivia_correct_count')
            ->orderBy('avg_response_ms')
            ->orderByDesc('trivia_double_correct')
            ->limit($limit)
            ->get()
            ->map(function ($p, $i) use (&$previousSignature, &$rank) {
                $signature = implode('|', [
                    $p->trivia_score, $p->trivia_correct_count,
                    $p->avg_response_ms ?? 'null', $p->trivia_double_correct,
                ]);
                if ($signature !== $previousSignature) $rank = $i + 1;
                $previousSignature = $signature;
                return [
                    'id' => $p->id, 'rank' => $rank, 'nickname' => $p->nickname,
                    'trivia_score' => $p->trivia_score, 'correct_count' => $p->trivia_correct_count,
                ];
            })
            ->toArray();
    }

    /** Round standings are derived from answer points; overall totals remain on players. */
    public function roundLeaderboard(TriviaRound $round, int $limit = 100): array
    {
        $roundId = $round->id;
        $players = Player::select('id', 'nickname')
            ->selectSub(
                Answer::selectRaw('COALESCE(SUM(points_awarded), 0)')
                    ->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->whereColumn('answers.player_id', 'players.id')
                    ->where('questions.trivia_round_id', $roundId),
                'round_score'
            )
            ->selectSub(
                Answer::selectRaw('COUNT(*)')
                    ->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->whereColumn('answers.player_id', 'players.id')
                    ->where('questions.trivia_round_id', $roundId)
                    ->where('answers.is_correct', true),
                'round_correct_count'
            )
            ->selectSub(
                Answer::selectRaw('AVG(response_time_ms)')
                    ->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->whereColumn('answers.player_id', 'players.id')
                    ->where('questions.trivia_round_id', $roundId)
                    ->where('answers.is_correct', true),
                'round_avg_response_ms'
            )
            ->orderByDesc('round_score')
            ->orderByDesc('round_correct_count')
            ->orderBy('round_avg_response_ms')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $previousSignature = null;
        $rank = 0;
        return $players->map(function ($player, $index) use (&$previousSignature, &$rank) {
            $score = (int) $player->round_score;
            $correct = (int) $player->round_correct_count;
            $average = $player->round_avg_response_ms === null ? null : (int) round($player->round_avg_response_ms);
            $signature = implode('|', [$score, $correct, $average ?? 'null']);
            if ($signature !== $previousSignature) $rank = $index + 1;
            $previousSignature = $signature;

            return [
                'id' => $player->id,
                'rank' => $rank,
                'nickname' => $player->nickname,
                'round_score' => $score,
                'correct_count' => $correct,
                'avg_response_ms' => $average,
            ];
        })->toArray();
    }

    public function predictionLeaderboard(int $limit = 10): array
    {
        $previousScore = null;
        $rank = 0;
        return Prediction::query()
            ->with('player:id,nickname,prediction_score')
            ->orderByDesc('prediction_score')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function ($prediction, $i) use (&$previousScore, &$rank) {
                if ($prediction->prediction_score !== $previousScore) $rank = $i + 1;
                $previousScore = $prediction->prediction_score;
                return [
                    'id' => $prediction->player->id, 'rank' => $rank,
                    'nickname' => $prediction->player->nickname,
                    'prediction_score' => $prediction->prediction_score,
                    'predicted_score' => "{$prediction->score_home}–{$prediction->score_away}",
                    'submitted_at' => $prediction->created_at?->toIso8601String(),
                ];
            })
            ->toArray();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function matchOutcome(int $home, int $away): string
    {
        return match(true) {
            $home > $away => 'home',
            $away > $home => 'away',
            default       => 'draw',
        };
    }

    private function scoredQuestions(bool $roundsEnabled)
    {
        $query = Question::whereIn('status', ['live', 'closed'])->with('triviaRound');
        if ($roundsEnabled) $query->whereNotNull('trivia_round_id');

        return $query->get()->sortBy(function (Question $question) use ($roundsEnabled) {
            return $roundsEnabled
                ? sprintf('%05d-%05d-%010d', $question->triviaRound?->position ?? 999, $question->round_position ?? 999, $question->id)
                : sprintf('%010d-%010d', $question->order_index, $question->id);
        })->values();
    }
}
