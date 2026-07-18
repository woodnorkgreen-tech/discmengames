<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventState;
use App\Models\EventAudit;
use App\Models\Answer;
use App\Models\MatchResult;
use App\Models\MatchConfig;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\SportsPlayer;
use App\Models\SportsTeam;
use App\Models\TriviaRound;
use App\Services\ScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class AdminApiController extends Controller
{
    public function listPlayers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:80',
            'type' => 'nullable|in:all,real,simulated',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);
        $search = trim((string) ($data['search'] ?? ''));
        $type = $data['type'] ?? 'all';
        $perPage = $data['per_page'] ?? 20;

        $players = Player::query()
            ->withCount('answers')
            ->withExists('prediction')
            // Nickname is the sole identity — phone/email are no longer collected.
            ->when($search !== '', fn ($query) => $query->where('nickname', 'like', "%{$search}%"))
            ->when($type === 'real', fn ($query) => $query->where('is_simulated', false))
            ->when($type === 'simulated', fn ($query) => $query->where('is_simulated', true))
            ->latest('id')
            ->paginate($perPage);

        return response()->json($players);
    }

    public function showPlayer(Player $player): JsonResponse
    {
        $player->load([
            'prediction',
            'answers' => fn ($query) => $query->with('question:id,order_index,text,correct_answer,category')->orderByDesc('server_received_at'),
        ]);

        return response()->json([
            'player' => $player,
            'summary' => [
                'total_score' => $player->trivia_score + $player->prediction_score,
                'answers_count' => $player->answers->count(),
                'correct_count' => $player->answers->where('is_correct', true)->count(),
            ],
        ]);
    }

    public function listTeams(): JsonResponse
    {
        return response()->json([
            'data' => SportsTeam::with(['players' => fn ($query) => $query->where('active', true)])
                ->orderBy('name')->get(),
        ]);
    }

    public function storeTeam(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|unique:sports_teams,name',
            'code' => 'nullable|string|size:3|unique:sports_teams,code',
            'country_code' => 'nullable|string|size:2',
        ]);
        $data['code'] = isset($data['code']) ? strtoupper($data['code']) : null;
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
        $team = SportsTeam::create($data);
        EventAudit::record('team.created', $team, ['name' => $team->name]);

        return response()->json(['team' => $team->load('players')], 201);
    }

    public function updateTeam(Request $request, SportsTeam $team): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('sports_teams', 'name')->ignore($team)],
            'code' => ['nullable', 'string', 'size:3', Rule::unique('sports_teams', 'code')->ignore($team)],
            'country_code' => 'nullable|string|size:2',
        ]);
        $data['code'] = isset($data['code']) ? strtoupper($data['code']) : null;
        $data['country_code'] = isset($data['country_code']) ? strtoupper($data['country_code']) : null;
        $team->update($data);
        EventAudit::record('team.updated', $team, ['name' => $team->name]);

        return response()->json(['team' => $team->fresh()->load('players')]);
    }

    public function destroyTeam(SportsTeam $team): JsonResponse
    {
        EventAudit::record('team.deleted', $team, ['name' => $team->name]);
        $team->delete();

        return response()->json(['message' => 'Team deleted.']);
    }

    public function storeSportsPlayer(Request $request, SportsTeam $team): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sports_players', 'name')->where('sports_team_id', $team->id)],
            'position' => 'nullable|in:GK,DF,MF,FW',
            'shirt_number' => 'nullable|integer|min:1|max:99',
        ]);
        $player = $team->players()->create($data);
        EventAudit::record('team.player_added', $player, ['team' => $team->name, 'name' => $player->name]);

        return response()->json(['player' => $player], 201);
    }

    public function updateSportsPlayer(Request $request, SportsPlayer $sportsPlayer): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sports_players', 'name')->where('sports_team_id', $sportsPlayer->sports_team_id)->ignore($sportsPlayer)],
            'position' => 'nullable|in:GK,DF,MF,FW',
            'shirt_number' => 'nullable|integer|min:1|max:99',
            'active' => 'sometimes|boolean',
        ]);
        $sportsPlayer->update($data);

        return response()->json(['player' => $sportsPlayer->fresh()]);
    }

    public function destroySportsPlayer(SportsPlayer $sportsPlayer): JsonResponse
    {
        EventAudit::record('team.player_removed', $sportsPlayer, ['name' => $sportsPlayer->name]);
        $sportsPlayer->delete();

        return response()->json(['message' => 'Player removed.']);
    }

    public function simulatePlayers(Request $request, ScoringService $scoring): JsonResponse
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1|max:200',
            'include_answers' => 'sometimes|boolean',
            'answer_rate' => 'sometimes|integer|min:0|max:100',
            'correct_rate' => 'sometimes|integer|min:0|max:100',
        ]);
        $match = MatchConfig::current();
        $squad = $match->players();
        $includeAnswers = (bool) ($data['include_answers'] ?? true);
        $answerRate = (int) ($data['answer_rate'] ?? 100);
        $correctRate = (int) ($data['correct_rate'] ?? 70);
        $questions = $includeAnswers ? Question::orderBy('order_index')->get() : collect();

        if (count($squad) < 2) {
            return response()->json(['message' => 'Configure both match squads before simulating users.'], 422);
        }

        $simulation = DB::transaction(function () use ($data, $match, $squad, $questions, $answerRate, $correctRate, $scoring) {
            $created = 0;
            $answersCreated = 0;
            for ($i = 0; $i < $data['count']; $i++) {
                do {
                    $phone = '254799'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                } while (Player::where('phone', $phone)->exists());

                $player = Player::create([
                    'phone' => $phone,
                    'nickname' => 'Test Fan '.str_pad((string) (Player::where('is_simulated', true)->count() + 1), 3, '0', STR_PAD_LEFT),
                    'consent' => true,
                    'has_visa_card' => (bool) random_int(0, 1),
                    'is_simulated' => true,
                ]);

                $homeScore = random_int(0, 4);
                $awayScore = random_int(0, 4);
                $firstTeam = ($homeScore + $awayScore) === 0 ? 'none' : (random_int(0, 1) ? 'home' : 'away');
                $firstTeamSquad = $firstTeam === 'home' ? ($match->home_squad ?? []) : ($match->away_squad ?? []);
                Prediction::create([
                    'player_id' => $player->id,
                    'score_home' => $homeScore,
                    'score_away' => $awayScore,
                    'first_scorer' => $firstTeam === 'none' ? 'No goal / N/A' : $firstTeamSquad[array_rand($firstTeamSquad)],
                    'first_scoring_team' => $firstTeam,
                    'halftime_winner' => ['home', 'away', 'draw'][array_rand(['home', 'away', 'draw'])],
                    'fulltime_winner' => $homeScore > $awayScore ? 'home' : ($awayScore > $homeScore ? 'away' : 'draw'),
                    'potm' => $squad[array_rand($squad)],
                ]);

                $score = 0;
                $streak = 0;
                $correctCount = 0;
                $doubleCorrect = 0;
                foreach ($questions as $question) {
                    if (random_int(1, 100) > $answerRate || empty($question->options)) {
                        continue;
                    }

                    $isCorrect = random_int(1, 100) <= $correctRate;
                    $wrongOptions = array_values(array_diff($question->options, [$question->correct_answer]));
                    $selected = $isCorrect || !$wrongOptions
                        ? $question->correct_answer
                        : $wrongOptions[array_rand($wrongOptions)];
                    $isCorrect = $selected === $question->correct_answer;
                    $responseTime = random_int(700, max(700, $question->duration_seconds * 1000));
                    $points = 0;

                    if ($isCorrect) {
                        $streak++;
                        $correctCount++;
                        $doubleCorrect += $question->is_double_points ? 1 : 0;
                        $secondsRemaining = max(0, $question->duration_seconds - (int) ceil($responseTime / 1000));
                        $points = $scoring->calculateTriviaPoints(
                            $question->is_double_points,
                            $secondsRemaining,
                            $question->duration_seconds,
                            $streak,
                        );
                        $score += $points;
                    } else {
                        $streak = 0;
                    }

                    Answer::create([
                        'player_id' => $player->id,
                        'question_id' => $question->id,
                        'selected_option' => $selected,
                        'is_correct' => $isCorrect,
                        'points_awarded' => $points,
                        'response_time_ms' => $responseTime,
                        'server_received_at' => now(),
                    ]);
                    $answersCreated++;
                }

                $player->update([
                    'trivia_score' => $score,
                    'trivia_streak' => $streak,
                    'trivia_correct_count' => $correctCount,
                    'trivia_double_correct' => $doubleCorrect,
                ]);
                $created++;
            }

            return ['players' => $created, 'answers' => $answersCreated];
        });

        EventAudit::record('testing.players_simulated', null, $simulation);

        return response()->json([
            'message' => "{$simulation['players']} users, {$simulation['players']} predictions and {$simulation['answers']} answers simulated.",
            'created' => $simulation['players'],
            'answers_created' => $simulation['answers'],
            'simulated_total' => Player::where('is_simulated', true)->count(),
        ], 201);
    }

    public function testingStatus(): JsonResponse
    {
        return response()->json([
            'players' => Player::count(),
            'real_players' => Player::where('is_simulated', false)->count(),
            'simulated_players' => Player::where('is_simulated', true)->count(),
            'predictions' => Prediction::count(),
            'answers' => Answer::count(),
            'results' => MatchResult::count(),
            'questions' => Question::count(),
        ]);
    }

    /** Deterministic, read-only checks of the published scoring rules. */
    public function scoringRehearsal(ScoringService $scoring): JsonResponse
    {
        $predictionResult = new MatchResult([
            'score_home' => 2, 'score_away' => 1,
            'halftime_score_home' => 0, 'halftime_score_away' => 0,
            'first_scoring_team' => 'home', 'scorer' => 'Home Player', 'potm' => 'Away Player',
        ]);
        $perfect = new Prediction([
            'score_home' => 2, 'score_away' => 1, 'fulltime_winner' => 'home',
            'halftime_winner' => 'draw', 'first_scoring_team' => 'home',
            'first_scorer' => 'Home Player', 'potm' => 'Away Player',
        ]);
        $outcomeOnly = new Prediction([
            'score_home' => 3, 'score_away' => 1, 'fulltime_winner' => 'home',
            'halftime_winner' => 'away', 'first_scoring_team' => 'away',
            'first_scorer' => 'Different Player', 'potm' => 'Different Player',
        ]);
        $goallessResult = new MatchResult([
            'score_home' => 0, 'score_away' => 0,
            'halftime_score_home' => 0, 'halftime_score_away' => 0,
            'first_scoring_team' => 'none', 'scorer' => null, 'potm' => 'Home Player',
        ]);
        $goalless = new Prediction([
            'score_home' => 0, 'score_away' => 0, 'fulltime_winner' => 'draw',
            'halftime_winner' => 'draw', 'first_scoring_team' => 'none',
            'first_scorer' => 'No goal / N/A', 'potm' => 'Home Player',
        ]);

        $checks = [
            ['group' => 'Trivia', 'scenario' => 'Fast correct answer', 'expected' => 1200,
                'actual' => $scoring->calculateTriviaBreakdown(false, 500, 30000, 1)['total']],
            ['group' => 'Trivia', 'scenario' => 'Three-answer streak', 'expected' => 1400,
                'actual' => $scoring->calculateTriviaBreakdown(false, 500, 30000, 3)['total']],
            ['group' => 'Trivia', 'scenario' => 'Double question + streak', 'expected' => 2800,
                'actual' => $scoring->calculateTriviaBreakdown(true, 500, 30000, 3)['total']],
            ['group' => 'Trivia', 'scenario' => 'Correct at the deadline', 'expected' => 1000,
                'actual' => $scoring->calculateTriviaBreakdown(false, 30000, 30000, 1)['total']],
            ['group' => 'Prediction', 'scenario' => 'Perfect prediction', 'expected' => 1500,
                'actual' => $scoring->calculatePredictionScore($perfect, $predictionResult)],
            ['group' => 'Prediction', 'scenario' => 'Correct outcome only', 'expected' => 250,
                'actual' => $scoring->calculatePredictionScore($outcomeOnly, $predictionResult)],
            ['group' => 'Prediction', 'scenario' => 'Perfect goalless prediction', 'expected' => 1350,
                'actual' => $scoring->calculatePredictionScore($goalless, $goallessResult)],
        ];
        $checks = array_map(fn ($check) => $check + ['passed' => $check['actual'] === $check['expected']], $checks);

        return response()->json([
            'version' => $scoring->rules()['version'],
            'passed' => collect($checks)->every('passed'),
            'checks' => $checks,
        ]);
    }

    public function clearSimulatedPlayers(): JsonResponse
    {
        $count = Player::where('is_simulated', true)->count();
        Player::where('is_simulated', true)->delete();
        EventAudit::record('testing.simulated_cleared', null, ['count' => $count]);

        return response()->json(['message' => "{$count} simulated users cleared.", 'deleted' => $count]);
    }

    public function resetEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'confirmed' => 'required|accepted',
            'confirmation' => 'nullable|string|max:30',
            'remove_players' => 'sometimes|boolean',
        ]);
        $removePlayers = (bool) ($data['remove_players'] ?? false);

        if ($removePlayers && strtoupper(trim((string) ($data['confirmation'] ?? ''))) !== 'RESET EVENT') {
            return response()->json([
                'message' => 'Type RESET EVENT to confirm removing every registered player.',
            ], 422);
        }

        $summary = DB::transaction(function () use ($removePlayers) {
            $summary = [
                'answers' => Answer::count(),
                'predictions' => \App\Models\Prediction::count(),
                'players' => $removePlayers ? Player::count() : 0,
            ];

            Answer::query()->delete();
            \App\Models\Prediction::query()->delete();
            MatchResult::query()->delete();
            Question::query()->update(['status' => 'draft', 'activated_at' => null]);

            if ($removePlayers) {
                Player::query()->delete();
            } else {
                Player::query()->update([
                    'trivia_score' => 0,
                    'trivia_streak' => 0,
                    'trivia_correct_count' => 0,
                    'trivia_double_correct' => 0,
                    'prediction_score' => 0,
                ]);
            }

            EventState::setCurrent([
                'phase' => 'lobby',
                'current_question_id' => null,
                'current_round_id' => null,
                'show_phone_on_screen' => false,
            ]);
            TriviaRound::query()->update(['status' => 'draft']);

            return $summary;
        });

        EventAudit::record('event.reset', null, array_merge($summary, ['removed_players' => $removePlayers]));

        return response()->json(['message' => 'Event reset completed.', 'summary' => $summary]);
    }

    public function showMatchConfig(): JsonResponse
    {
        $config = MatchConfig::current();

        return response()->json([
            'config' => $config,
            'locked' => \App\Models\Prediction::exists(),
        ]);
    }

    public function updateMatchConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'home_team' => 'required|string|max:80|different:away_team',
            'away_team' => 'required|string|max:80',
            'home_squad' => 'required|array|min:1|max:30',
            'home_squad.*' => 'required|string|max:100|distinct',
            'away_squad' => 'required|array|min:1|max:30',
            'away_squad.*' => 'required|string|max:100|distinct',
            'kickoff_at' => 'nullable|date',
            'venue' => 'nullable|string|max:120',
            'force' => 'sometimes|boolean',
        ]);

        if (array_intersect($data['home_squad'], $data['away_squad'])) {
            return response()->json(['message' => 'A player cannot appear in both squads.'], 422);
        }

        if (\App\Models\Prediction::exists() && !($data['force'] ?? false)) {
            return response()->json([
                'message' => 'Match configuration is locked because predictions already exist.',
                'requires_confirmation' => true,
            ], 409);
        }

        unset($data['force']);
        $data['home_squad'] = array_values(array_map('trim', $data['home_squad']));
        $data['away_squad'] = array_values(array_map('trim', $data['away_squad']));

        $config = MatchConfig::current();
        $config->update($data);
        EventAudit::record('match.configuration_updated', $config, [
            'home_team' => $config->home_team,
            'away_team' => $config->away_team,
            'forced_after_predictions' => \App\Models\Prediction::exists(),
        ]);

        return response()->json(['config' => $config->fresh()]);
    }

    public function listAudits(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 50), 10), 100);

        return response()->json([
            'data' => EventAudit::query()
                ->latest('id')
                ->limit($limit)
                ->get()
                ->map(fn (EventAudit $audit) => [
                    'id' => $audit->id,
                    'action' => $audit->action,
                    'subject_type' => $audit->subject_type
                        ? class_basename($audit->subject_type)
                        : null,
                    'subject_id' => $audit->subject_id,
                    'context' => $audit->context ?? [],
                    'admin_ip' => $audit->admin_ip,
                    'created_at' => $audit->created_at?->toIso8601String(),
                ]),
        ]);
    }

    // ── Phase control ─────────────────────────────────────────────────────────

    public function setPhase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phase' => 'required|in:lobby,predictions_open,predictions_closed,trivia_complete,prediction_reveal',
        ]);

        if ($data['phase'] === 'predictions_open') {
            $result = MatchResult::current();
            if ($result->exists && $result->resolved) {
                return response()->json([
                    'message' => 'Cannot reopen predictions after the match result is final.',
                ], 422);
            }
        }

        EventState::setCurrent(['phase' => $data['phase']]);
        EventAudit::record('phase.changed', null, ['phase' => $data['phase']]);

        return response()->json(['phase' => $data['phase']]);
    }

    // ── Three-round trivia management ───────────────────────────────────────

    public function showRounds(): JsonResponse
    {
        $state = EventState::current();
        return response()->json([
            'enabled' => (bool) $state->rounds_enabled,
            'current_round_id' => $state->current_round_id,
            'rounds' => TriviaRound::with('questions')->orderBy('position')->get()
                ->map(fn (TriviaRound $round) => $this->roundPayload($round)),
            'unassigned' => Question::whereNull('trivia_round_id')->orderBy('order_index')->orderBy('id')->get(),
        ]);
    }

    public function updateRoundSettings(Request $request): JsonResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $state = EventState::current();

        if ((bool) $state->rounds_enabled !== $data['enabled']
            && (Answer::exists() || Question::where('status', 'live')->exists() || TriviaRound::where('status', 'live')->exists())) {
            return response()->json(['message' => 'Round mode cannot be changed after trivia answers or a live question exist. Reset the event first.'], 422);
        }

        if ($data['enabled']) TriviaRound::createRecommended();
        EventState::setCurrent([
            'rounds_enabled' => $data['enabled'],
            'current_round_id' => $data['enabled'] ? $state->current_round_id : null,
        ]);

        return $this->showRounds();
    }

    public function updateRound(Request $request, TriviaRound $round): JsonResponse
    {
        if ($round->status !== 'draft') {
            return response()->json(['message' => 'A live or completed round cannot be edited.'], 422);
        }
        $data = $request->validate([
            'title' => 'required|string|max:80',
            'category' => 'nullable|in:general_knowledge,fifa_world_cup,visa',
            'intro_message' => 'nullable|string|max:180',
        ]);
        $round->update($data);
        return response()->json(['round' => $this->roundPayload($round->fresh())]);
    }

    public function assignQuestionToRound(Request $request, Question $question): JsonResponse
    {
        if ($question->status !== 'draft' || $question->answers()->exists()) {
            return response()->json(['message' => 'Only unanswered draft questions can be reassigned.'], 422);
        }
        $data = $request->validate([
            'round_id' => 'nullable|exists:trivia_rounds,id',
            'position' => 'nullable|integer|min:1|max:100',
        ]);
        $roundId = $data['round_id'] ?? null;
        if ($roundId && TriviaRound::findOrFail($roundId)->status !== 'draft') {
            return response()->json(['message' => 'Questions cannot be added to a live or completed round.'], 422);
        }

        $question->update([
            'trivia_round_id' => $roundId,
            'round_position' => $roundId
                ? ($data['position'] ?? ((int) Question::where('trivia_round_id', $roundId)->max('round_position') + 1))
                : null,
        ]);
        if ($roundId) $this->normalizeRoundPositions((int) $roundId);

        return $this->showRounds();
    }

    public function reorderRoundQuestions(Request $request, TriviaRound $round): JsonResponse
    {
        if ($round->status !== 'draft') {
            return response()->json(['message' => 'A live or completed round cannot be reordered.'], 422);
        }
        $data = $request->validate([
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'integer|distinct|exists:questions,id',
        ]);
        $expected = $round->questions()->pluck('id')->sort()->values()->all();
        $received = collect($data['question_ids'])->sort()->values()->all();
        if ($expected !== $received) {
            return response()->json(['message' => 'The order must contain every question in this round exactly once.'], 422);
        }
        foreach ($data['question_ids'] as $index => $questionId) {
            Question::whereKey($questionId)->update(['round_position' => $index + 1]);
        }
        return response()->json(['round' => $this->roundPayload($round->fresh())]);
    }

    public function startRound(TriviaRound $round): JsonResponse
    {
        $state = EventState::current();
        if (!$state->rounds_enabled) return response()->json(['message' => 'Enable round mode first.'], 422);
        if ($round->status !== 'draft') return response()->json(['message' => 'Only a draft round can be started.'], 422);
        if (!$round->questions()->exists()) return response()->json(['message' => 'Assign at least one question before starting this round.'], 422);
        $readiness = $this->roundPayload($round);
        if (!$readiness['ready']) {
            return response()->json(['message' => 'Round is not ready: '.implode('; ', $readiness['issues'])], 422);
        }
        if ($round->questions()->where('status', '!=', 'draft')->exists()) {
            return response()->json(['message' => 'Every question in this round must be in draft status.'], 422);
        }
        if (TriviaRound::where('status', 'live')->where('id', '!=', $round->id)->exists()) {
            return response()->json(['message' => 'Complete the current live round first.'], 422);
        }

        $round->update(['status' => 'live']);
        EventState::setCurrent([
            'phase' => 'trivia_live',
            'current_round_id' => $round->id,
            'current_question_id' => null,
        ]);
        return response()->json(['round' => $this->roundPayload($round->fresh())]);
    }

    public function completeRound(TriviaRound $round): JsonResponse
    {
        if ($round->status !== 'live') return response()->json(['message' => 'Only the live round can be completed.'], 422);
        if ($round->questions()->whereIn('status', ['draft', 'live'])->exists()) {
            return response()->json(['message' => 'Reveal or skip every question in this round before completing it.'], 422);
        }

        $round->update(['status' => 'completed']);
        EventState::setCurrent([
            'phase' => 'trivia_reveal',
            'current_round_id' => $round->id,
            'current_question_id' => null,
        ]);
        return response()->json(['round' => $this->roundPayload($round->fresh())]);
    }

    // ── Question management ───────────────────────────────────────────────────

    public function listQuestions(): JsonResponse
    {
        return response()->json(
            Question::with('triviaRound:id,position,title')->orderBy('order_index')->get()
        );
    }

    public function storeQuestion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_index'      => 'required|integer',
            'trivia_round_id'  => 'nullable|exists:trivia_rounds,id',
            'round_position'   => 'nullable|integer|min:1|max:100',
            'type'             => 'required|in:multiple_choice,true_false',
            'text'             => 'required|string',
            'category'         => 'required|in:general_knowledge,fifa_world_cup,visa',
            'options'          => 'required|array|min:2|max:4',
            'options.*'        => 'required|string|max:255|distinct',
            'correct_answer'   => ['required', 'string', Rule::in($request->input('options', []))],
            'duration_seconds' => 'required|integer|min:5|max:120',
            'is_double_points' => 'boolean',
        ]);

        return response()->json(Question::create($data), 201);
    }

    public function updateQuestion(Request $request, Question $question, ScoringService $scoring): JsonResponse
    {
        if ($question->status === 'live') {
            return response()->json(['message' => 'Cannot edit a live question.'], 422);
        }
        $data = $request->validate([
            'order_index'      => 'integer',
            'trivia_round_id'  => 'nullable|exists:trivia_rounds,id',
            'round_position'   => 'nullable|integer|min:1|max:100',
            'category'         => 'in:general_knowledge,fifa_world_cup,visa',
            'type'             => 'in:multiple_choice,true_false',
            'text'             => 'string',
            'options'          => 'array|min:2|max:4',
            'options.*'        => 'required|string|max:255|distinct',
            'correct_answer'   => ['string', Rule::in($request->input('options', $question->options))],
            'duration_seconds' => 'integer|min:5|max:120',
            'is_double_points' => 'boolean',
        ]);

        $question->update($data);

        // Editing a closed question (e.g. correcting the answer key) must flow
        // through to scores, otherwise the change silently does nothing.
        if ($question->status === 'closed' && $question->answers()->exists()) {
            DB::transaction(fn () => $scoring->recalculateAllScoredPlayers());
            Cache::forget('public-event-state-v3');
        }

        return response()->json($question->fresh());
    }

    public function updateQuestionDuration(Request $request, Question $question): JsonResponse
    {
        $data = $request->validate([
            'duration_seconds' => 'required|integer|min:5|max:120',
            'restart_live' => 'sometimes|boolean',
        ]);

        if ($question->status === 'live' && !($data['restart_live'] ?? false)) {
            return response()->json(['message' => 'Confirm restart_live to change a live countdown.'], 422);
        }

        $changes = ['duration_seconds' => $data['duration_seconds']];
        if ($question->status === 'live') {
            $changes['activated_at'] = now();
        }
        $question->update($changes);
        Cache::forget('public-event-state-v3');
        EventAudit::record('question.duration_updated', $question, [
            'duration_seconds' => $question->duration_seconds,
            'live_timer_restarted' => $question->status === 'live',
        ]);

        return response()->json($question->fresh());
    }

    public function destroyQuestion(Question $question): JsonResponse
    {
        if ($question->status === 'live') {
            return response()->json(['message' => 'Cannot delete a live question.'], 422);
        }
        $question->delete();
        return response()->json(['deleted' => true]);
    }

    public function activateQuestion(Request $request, Question $question): JsonResponse
    {
        // Only a fresh draft may go live. A closed question has already been
        // revealed on the big screen; re-activating it would replay a known
        // answer with stale response times. Use "Return to draft" first if a
        // deliberate re-run is intended.
        if ($question->status !== 'draft') {
            return response()->json([
                'message' => 'Only a draft question can go live. Return it to draft first if you must re-run it.',
            ], 422);
        }

        $state = EventState::current();
        if ($state->rounds_enabled) {
            if (!$question->trivia_round_id) {
                return response()->json(['message' => 'Assign this question to a round before taking it live.'], 422);
            }
            if ((int) $state->current_round_id !== (int) $question->trivia_round_id
                || $question->triviaRound?->status !== 'live') {
                return response()->json(['message' => 'Start this question’s round before taking it live.'], 422);
            }
        }

        DB::transaction(function () use ($question) {
            Question::where('status', 'live')->where('id', '!=', $question->id)->update(['status' => 'closed']);
            $question->update(['status' => 'live', 'activated_at' => now()]);
            EventState::setCurrent([
                'phase'               => 'trivia_live',
                'current_question_id' => $question->id,
                'current_round_id' => $question->trivia_round_id,
            ]);
            EventAudit::record('question.activated', $question, ['text' => $question->text]);
        });

        return response()->json($question->fresh());
    }

    public function closeQuestion(Question $question): JsonResponse
    {
        if ($question->status !== 'live') {
            return response()->json(['message' => 'Only the live question can be revealed.'], 422);
        }

        DB::transaction(function () use ($question) {
            $question->update(['status' => 'closed']);
            EventState::setCurrent([
                'phase' => 'trivia_reveal',
                'current_question_id' => $question->id,
            ]);
            EventAudit::record('question.revealed', $question, ['correct_answer' => $question->correct_answer]);
        });

        return response()->json(['status' => 'closed']);
    }

    public function invalidateQuestion(Request $request, Question $question, ScoringService $scoring): JsonResponse
    {
        if (EventAudit::where('action', 'question.invalidated')
            ->where('subject_type', Question::class)
            ->where('subject_id', $question->id)
            ->exists()) {
            return response()->json(['message' => 'This question has already been invalidated.'], 422);
        }

        $data = $request->validate([
            'reason' => 'required|string|min:5|max:255',
        ]);

        $summary = DB::transaction(function () use ($question, $data, $scoring) {
            $answers = Answer::where('question_id', $question->id)->lockForUpdate()->get();
            $pointsReversed = (int) $answers->sum('points_awarded');

            $question->update(['status' => 'skipped']);

            // Recalculate every scored player: removing this question also restores
            // streak continuity for players who had skipped it, not just answerers.
            $scoring->recalculateAllScoredPlayers();

            $state = EventState::current();
            if ($state->current_question_id === $question->id) {
                EventState::setCurrent(['phase' => 'trivia_live', 'current_question_id' => null]);
            }

            EventAudit::record('question.invalidated', $question, [
                'reason' => $data['reason'],
                'answers_affected' => $answers->count(),
                'points_reversed' => $pointsReversed,
            ]);

            return ['answers_affected' => $answers->count(), 'points_reversed' => $pointsReversed];
        });

        return response()->json($summary + ['status' => 'skipped']);
    }

    public function skipQuestion(Question $question, ScoringService $scoring): JsonResponse
    {
        if (!in_array($question->status, ['draft', 'live'], true)) {
            return response()->json(['message' => 'Only a draft or live question can be skipped.'], 422);
        }

        DB::transaction(function () use ($question, $scoring) {
            $hadAnswers = $question->answers()->exists();
            $question->update(['status' => 'skipped']);

            // A skipped question no longer scores; rebuild affected totals so its
            // points and streak effects are removed cleanly.
            if ($hadAnswers) {
                $scoring->recalculateAllScoredPlayers();
            }

            // Only disturb the shared phase if this was the question on screen.
            $state = EventState::current();
            if ($state->current_question_id === $question->id) {
                EventState::setCurrent(['phase' => 'trivia_live', 'current_question_id' => null]);
            }
            EventAudit::record('question.skipped', $question, ['had_answers' => $hadAnswers]);
        });

        return response()->json(['status' => 'skipped']);
    }

    public function reopenQuestion(Question $question): JsonResponse
    {
        // Reset to draft so admin can re-activate it as live
        $question->update(['status' => 'draft', 'activated_at' => null]);

        return response()->json(['status' => 'draft']);
    }

    // ── Match result + prediction scoring ────────────────────────────────────

    public function setMatchResult(Request $request, ScoringService $scoring): JsonResponse
    {
        $matchConfig = MatchConfig::current();
        $players = $matchConfig->players();
        $data = $request->validate([
            'score_home' => 'required|integer|min:0|max:20',
            'score_away' => 'required|integer|min:0|max:20',
            'halftime_score_home' => 'required|integer|min:0|max:20',
            'halftime_score_away' => 'required|integer|min:0|max:20',
            'first_scoring_team' => 'required|in:home,away,none',
            'scorer' => 'nullable|string|max:100',
            'potm'       => 'nullable|string|max:100',
        ]);

        $request->validate([
            'scorer' => ['nullable', Rule::in($players)],
            'potm' => ['nullable', Rule::in($players)],
        ]);

        if ($data['halftime_score_home'] > $data['score_home'] || $data['halftime_score_away'] > $data['score_away']) {
            return response()->json(['message' => 'Half-time goals cannot exceed the final regulation-time score.'], 422);
        }
        $isGoalless = ($data['score_home'] + $data['score_away']) === 0;
        if (($isGoalless && $data['first_scoring_team'] !== 'none') || (!$isGoalless && $data['first_scoring_team'] === 'none')) {
            return response()->json(['message' => $isGoalless
                ? 'A 0–0 result must use “No team scored”.'
                : 'Select the team that scored first.'], 422);
        }
        if (($data['first_scoring_team'] === 'home' && $data['score_home'] === 0)
            || ($data['first_scoring_team'] === 'away' && $data['score_away'] === 0)) {
            return response()->json(['message' => 'The first-scoring team must have at least one goal in the final score.'], 422);
        }
        $expectedSquad = $data['first_scoring_team'] === 'home' ? ($matchConfig->home_squad ?? []) : ($matchConfig->away_squad ?? []);
        if ($isGoalless && !empty($data['scorer'])) {
            return response()->json(['message' => 'A 0–0 result cannot have a first goalscorer.'], 422);
        }
        if (!$isGoalless && (empty($data['scorer']) || !in_array($data['scorer'], $expectedSquad, true))) {
            return response()->json(['message' => 'Select a first goalscorer from the first-scoring team.'], 422);
        }

        $result = MatchResult::current();
        $result->fill(array_merge($data, ['resolved' => true]))->save();

        // A re-saved (corrected) result must re-score every prediction,
        // so clear the resolved guard before scoring.
        \App\Models\Prediction::query()->update(['resolved' => false]);
        $scored = $scoring->scorePredictions($result);

        EventState::setCurrent(['phase' => 'match_ended']);
        EventAudit::record('predictions.resolved', $result, ['predictions_scored' => $scored]);

        return response()->json([
            'result'          => $result,
            'predictions_scored' => $scored,
        ]);
    }

    // ── Player lookup by phone (for admin score adjustment) ──────────────────

    public function lookupPlayer(Request $request): JsonResponse
    {
        $data   = $request->validate(['nickname' => 'required|string|max:50']);
        $player = Player::whereRaw('LOWER(nickname) = ?', [mb_strtolower(trim($data['nickname']))])->first();

        if (!$player) {
            return response()->json(['message' => 'Player not found.'], 404);
        }

        return response()->json([
            'id'           => $player->id,
            'nickname'     => $player->nickname,
            'trivia_score' => $player->trivia_score,
        ]);
    }

    // ── Manual score adjustment (with audit) ─────────────────────────────────

    public function adjustScore(Request $request, Player $player, ScoringService $scoring): JsonResponse
    {
        $data = $request->validate([
            'adjustment' => 'required|integer',
            'reason'     => 'required|string|max:255',
        ]);

        \Illuminate\Support\Facades\Log::info('Admin score adjustment', [
            'player_id'  => $player->id,
            'nickname'   => $player->nickname,
            'adjustment' => $data['adjustment'],
            'reason'     => $data['reason'],
            'admin_ip'   => $request->ip(),
        ]);

        $previousScore = $player->trivia_score;
        // Accumulate into the dedicated adjustment column and rebuild the total
        // so the adjustment persists across future answer recalculations.
        $newScore = DB::transaction(function () use ($player, $data, $scoring) {
            $locked = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            $locked->update(['trivia_manual_adjustment' => $locked->trivia_manual_adjustment + $data['adjustment']]);
            $scoring->recalculatePlayerTrivia($locked);
            return $locked->fresh()->trivia_score;
        });
        EventAudit::record('score.adjusted', $player, [
            'adjustment_requested' => $data['adjustment'],
            'previous_score' => $previousScore,
            'new_score' => $newScore,
            'reason' => $data['reason'],
        ]);

        return response()->json([
            'id'           => $player->id,
            'nickname'     => $player->nickname,
            'trivia_score' => $newScore,
        ]);
    }

    private function roundPayload(TriviaRound $round): array
    {
        $round->loadMissing('questions');
        $questions = $round->questions->sortBy('round_position')->values();
        $issues = [];
        if ($questions->isEmpty()) $issues[] = 'No questions assigned';
        if ($questions->contains(fn ($question) => empty($question->correct_answer) || count($question->options ?? []) < 2)) {
            $issues[] = 'A question is incomplete';
        }
        if ($round->category && $questions->contains(fn ($question) => $question->category !== $round->category)) {
            $issues[] = 'A question does not match the round theme';
        }
        if ($questions->where('is_double_points', true)->count() === 0) $issues[] = 'No Visa Power Question selected';
        if ($questions->where('is_double_points', true)->count() > 1) $issues[] = 'More than one Power Question';

        return [
            'id' => $round->id,
            'position' => $round->position,
            'title' => $round->title,
            'category' => $round->category,
            'intro_message' => $round->intro_message,
            'status' => $round->status,
            'ready' => $issues === [],
            'issues' => $issues,
            'estimated_seconds' => $questions->sum('duration_seconds'),
            'questions' => $questions,
        ];
    }

    private function normalizeRoundPositions(int $roundId): void
    {
        Question::where('trivia_round_id', $roundId)
            ->orderByRaw('round_position IS NULL')
            ->orderBy('round_position')->orderBy('order_index')->orderBy('id')
            ->get()->each(fn (Question $question, int $index) => $question->update(['round_position' => $index + 1]));
    }
}
