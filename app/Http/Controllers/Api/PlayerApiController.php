<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventState;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\MatchConfig;
use App\Services\ScoringService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PlayerApiController extends Controller
{
    // ── Registration ──────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        // Data minimisation: the nickname is the whole identity. No phone,
        // no email — nothing personally identifying is collected.
        $data = $request->validate([
            'nickname'      => 'required|string|min:2|max:50',
            'pin'           => ['required', 'digits:4'],
            'consent'       => 'required|accepted',
            'has_visa_card' => 'boolean',
        ]);

        $nickname = trim($data['nickname']);

        $taken = Player::whereRaw('LOWER(nickname) = ?', [mb_strtolower($nickname)])->exists();
        if ($taken) {
            return response()->json([
                'message' => "\"{$nickname}\" is already taken — pick another nickname.",
            ], 422);
        }

        try {
            $player = Player::create([
                'nickname'      => $nickname,
                'consent'       => true,
                'has_visa_card' => $data['has_visa_card'] ?? false,
                'login_pin_hash' => Hash::make($data['pin']),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Simultaneous registration of the same nickname — first one wins
            return response()->json([
                'message' => "\"{$nickname}\" is already taken — pick another nickname.",
            ], 422);
        }

        return response()->json([
            'player_id' => $player->id,
            'nickname'  => $player->nickname,
            'session_token' => $player->issueSessionToken(),
            'message'   => 'You\'re in! 🎉',
        ], 201);
    }

    /** Restore a player on this or another device without collecting personal data. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nickname' => 'required|string|min:2|max:50',
            'pin' => ['required', 'digits:4'],
        ]);

        $nickname = mb_strtolower(trim($data['nickname']));
        $player = DB::transaction(function () use ($nickname, $data) {
            $player = Player::whereRaw('LOWER(nickname) = ?', [$nickname])->lockForUpdate()->first();
            if (!$player) return null;

            // One-time bridge for profiles created before PIN login existed.
            if (!$player->login_pin_hash) {
                $player->forceFill(['login_pin_hash' => Hash::make($data['pin'])])->save();
                return $player;
            }

            return Hash::check($data['pin'], $player->login_pin_hash) ? $player : null;
        });

        if (!$player) {
            return response()->json(['message' => 'Nickname or game PIN is incorrect.'], 422);
        }

        return response()->json([
            'player_id' => $player->id,
            'nickname' => $player->nickname,
            'session_token' => $player->issueSessionToken(),
            'message' => 'Welcome back!',
        ]);
    }

    // ── Answer submission ─────────────────────────────────────────────────────

    public function submitAnswer(Request $request, ScoringService $scoring): JsonResponse
    {
        $data = $request->validate([
            'player_id'       => 'required|exists:players,id',
            'question_id'     => 'required|exists:questions,id',
            'selected_option' => 'required|string|max:255',
            'response_time_ms'=> 'required|integer|min:0',
        ]);

        $state    = EventState::current();
        $question = Question::findOrFail($data['question_id']);

        $request->validate([
            'selected_option' => ['required', 'string', Rule::in($question->options)],
        ]);

        if ($state->phase !== 'trivia_live' || $question->status !== 'live' || $question->secondsRemaining() <= 0) {
            return response()->json(['message' => 'Question is no longer accepting answers.'], 422);
        }

        $player = Player::findOrFail($data['player_id']);
        $this->assertPlayerSession($request, $player);

        $existing = $player->answers()->where('question_id', $question->id)->first();
        // Never trust a phone's clock for scoring. The server activation time is authoritative.
        $serverResponseMs = $question->activated_at
            ? min(
                $question->duration_seconds * 1000,
                max(0, (int) $question->activated_at->diffInMilliseconds(now()))
            )
            : $question->duration_seconds * 1000;
        $points = $scoring->scoreAnswer($player, $question, $data['selected_option'], $serverResponseMs);

        return response()->json([
            'answer_updated' => (bool) $existing,
            'selected_option'=> $data['selected_option'],
            'is_correct'    => $data['selected_option'] === $question->correct_answer,
            'points_awarded'=> $points,
            'total_score'   => $player->fresh()->trivia_score,
            'message'       => $existing ? 'Answer updated. You can change it again while the timer is running.' : 'Answer saved. You can change it while the timer is running.',
        ]);
    }

    /** Return the authoritative saved result used by the player's reveal UI. */
    public function answerResult(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id'   => 'required|exists:players,id',
            'question_id' => 'required|exists:questions,id',
        ]);

        $player = Player::findOrFail($data['player_id']);
        $this->assertPlayerSession($request, $player);
        $answer = $player->answers()
            ->where('question_id', $data['question_id'])
            ->first();

        if (!$answer) {
            return response()->json([
                'answered'    => false,
                'total_score' => $player->trivia_score,
            ]);
        }

        return response()->json([
            'answered'       => true,
            'selected_option'=> $answer->selected_option,
            'is_correct'     => $answer->is_correct,
            'points_awarded' => $answer->points_awarded,
            'total_score'    => $player->trivia_score,
        ]);
    }

    // ── Predictions ───────────────────────────────────────────────────────────

    public function submitPrediction(Request $request): JsonResponse
    {
        $matchConfig = MatchConfig::current();
        $data = $request->validate([
            'player_id'    => 'required|exists:players,id',
            'score_home'   => 'required|integer|min:0|max:20',
            'score_away'   => 'required|integer|min:0|max:20',
            'first_scoring_team' => 'required|in:home,away,none',
            'first_scorer' => 'required|string|max:100',
            'halftime_winner' => 'required|in:home,away,draw',
            'potm'         => 'required|string|max:100',
        ]);

        $request->validate([
            'first_scorer' => ['required', Rule::in(array_merge($matchConfig->players(), ['No goal / N/A']))],
            'potm' => ['required', Rule::in(array_merge($matchConfig->players(), ['TBD']))],
        ]);

        $fulltimeWinner = $this->matchOutcome($data['score_home'], $data['score_away']);
        if ($data['first_scoring_team'] === 'none' && ($data['score_home'] + $data['score_away']) > 0) {
            return response()->json(['message' => '“No team scores” is only consistent with a 0–0 prediction.'], 422);
        }
        if ($data['first_scoring_team'] !== 'none' && ($data['score_home'] + $data['score_away']) === 0) {
            return response()->json(['message' => 'A 0–0 prediction must use “No team scores”.'], 422);
        }
        if (($data['first_scoring_team'] === 'home' && $data['score_home'] === 0)
            || ($data['first_scoring_team'] === 'away' && $data['score_away'] === 0)) {
            return response()->json(['message' => 'A team predicted to score zero goals cannot score first.'], 422);
        }
        if (($data['halftime_winner'] === 'home' && $data['score_home'] === 0)
            || ($data['halftime_winner'] === 'away' && $data['score_away'] === 0)) {
            return response()->json(['message' => 'A team predicted to score zero goals cannot lead at half-time.'], 422);
        }
        $expectedSquad = $data['first_scoring_team'] === 'home' ? ($matchConfig->home_squad ?? []) : ($matchConfig->away_squad ?? []);
        if ($data['first_scoring_team'] === 'none' && $data['first_scorer'] !== 'No goal / N/A') {
            return response()->json(['message' => 'A no-goal prediction cannot have a first goalscorer.'], 422);
        }
        if ($data['first_scoring_team'] !== 'none' && !in_array($data['first_scorer'], $expectedSquad, true)) {
            return response()->json(['message' => 'The first goalscorer must belong to the selected first-scoring team.'], 422);
        }

        $state = EventState::current();
        if ($state->phase !== 'predictions_open') {
            return response()->json(['message' => 'Predictions are closed.'], 422);
        }

        $player = Player::findOrFail($data['player_id']);
        $this->assertPlayerSession($request, $player);

        $prediction = Prediction::updateOrCreate(
            ['player_id' => $data['player_id']],
            [
                'score_home'   => $data['score_home'],
                'score_away'   => $data['score_away'],
                'first_scorer' => $data['first_scorer'],
                'first_scoring_team' => $data['first_scoring_team'],
                'halftime_winner' => $data['halftime_winner'],
                'fulltime_winner' => $fulltimeWinner,
                'potm'         => $data['potm'],
                'resolved'     => false,
            ]
        );
        Cache::forget('public-prediction-feed');

        return response()->json(['message' => 'Predictions locked in! ⚽', 'prediction_id' => $prediction->id]);
    }

    /** Public big-screen activity feed. Contains nicknames only, never phone numbers. */
    public function predictionFeed(): JsonResponse
    {
        $load = fn () =>
            Prediction::query()
                ->join('players', 'players.id', '=', 'predictions.player_id')
                ->latest('predictions.updated_at')
                ->get(['predictions.id', 'players.nickname', 'predictions.updated_at'])
                ->map(fn ($prediction) => [
                    'id' => $prediction->id,
                    'nickname' => $prediction->nickname,
                    'updated_at' => $prediction->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        $entries = app()->environment('testing')
            ? $load()
            : Cache::remember('public-prediction-feed', now()->addSeconds(2), $load);

        return response()->json(['count' => count($entries), 'entries' => $entries]);
    }

    public function currentPrediction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);
        $player = Player::findOrFail($data['player_id']);
        $this->assertPlayerSession($request, $player);
        $prediction = $player->prediction;

        return response()->json([
            'prediction' => $prediction ? [
                'score_home' => $prediction->score_home,
                'score_away' => $prediction->score_away,
                'first_scoring_team' => $prediction->first_scoring_team,
                'first_scorer' => $prediction->first_scorer,
                'halftime_winner' => $prediction->halftime_winner,
                'fulltime_winner' => $prediction->fulltime_winner,
                'potm' => $prediction->potm,
                'updated_at' => $prediction->updated_at?->toIso8601String(),
            ] : null,
        ]);
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    public function leaderboard(ScoringService $scoring): JsonResponse
    {
        return response()->json([
            'trivia'     => $scoring->triviaLeaderboard(10),
            'prediction' => $scoring->predictionLeaderboard(10),
        ]);
    }

    private function assertPlayerSession(Request $request, Player $player): void
    {
        $token = (string) $request->header('X-Player-Token', '');
        $valid = $token !== ''
            && $player->session_token_hash
            && hash_equals($player->session_token_hash, hash('sha256', $token));

        abort_unless($valid, 401, 'Your player session has expired. Please sign in again.');
    }

    private function matchOutcome(int $home, int $away): string
    {
        return match (true) {
            $home > $away => 'home',
            $away > $home => 'away',
            default => 'draw',
        };
    }
}
