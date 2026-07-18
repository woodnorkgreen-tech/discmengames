<?php

namespace Tests\Feature;

use App\Models\Answer;
use App\Models\EventAudit;
use App\Models\EventState;
use App\Models\MatchResult;
use App\Models\MatchConfig;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Models\SportsTeam;
use App\Models\TriviaRound;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EventReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_answer_is_scored_and_persisted(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();

        $response = $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 1200,
        ]);

        $response->assertOk()->assertJson(['selected_option' => 'Nairobi'])
            ->assertJsonMissingPath('is_correct')
            ->assertJsonMissingPath('points_awarded')
            ->assertJsonMissingPath('total_score');
        $this->assertDatabaseHas('answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'is_correct' => true,
        ]);
    }

    public function test_wrong_answer_is_not_reported_as_correct(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Mombasa',
            'response_time_ms' => 1500,
        ])->assertOk()
            ->assertJsonMissingPath('is_correct')
            ->assertJsonMissingPath('points_awarded')
            ->assertJsonMissingPath('total_score');
    }

    public function test_answer_can_be_changed_while_the_question_is_live(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();
        $headers = ['X-Player-Token' => $token];

        $this->withHeaders($headers)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 1000,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Mombasa',
            'response_time_ms' => 2000,
        ])->assertOk()->assertJson([
            'answer_updated' => true,
            'selected_option' => 'Mombasa',
        ])->assertJsonMissingPath('is_correct')
            ->assertJsonMissingPath('points_awarded')
            ->assertJsonMissingPath('total_score');

        $this->assertSame(1, Answer::count());
        $this->assertDatabaseHas('answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Mombasa',
            'is_correct' => false,
        ]);
    }

    public function test_late_answer_is_rejected(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion(['duration_seconds' => 10, 'activated_at' => now()->subSeconds(11)]);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 11000,
        ])->assertStatus(422);

        $this->assertSame(0, Answer::count());
    }

    public function test_admin_can_change_and_restart_a_live_question_countdown(): void
    {
        $question = $this->liveQuestion(['duration_seconds' => 30, 'activated_at' => now()->subSeconds(12)]);
        $endpoint = "/api/admin/questions/{$question->id}/duration";

        $this->patchJson($endpoint, ['duration_seconds' => 45, 'restart_live' => true])->assertUnauthorized();
        $this->withSession(['admin_logged_in' => true])->patchJson($endpoint, [
            'duration_seconds' => 45,
            'restart_live' => true,
        ])->assertOk()->assertJsonPath('duration_seconds', 45);

        $question->refresh();
        $this->assertSame(45, $question->duration_seconds);
        $this->assertGreaterThanOrEqual(44, $question->secondsRemaining());
        $this->assertDatabaseHas('event_audits', [
            'action' => 'question.duration_updated',
            'subject_id' => $question->id,
        ]);
    }

    public function test_server_time_not_client_time_controls_speed_points(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion(['duration_seconds' => 30, 'activated_at' => now()->subSeconds(20)]);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 0,
        ])->assertOk();

        $answer = Answer::firstOrFail();
        $this->assertGreaterThanOrEqual(19000, $answer->response_time_ms);
        $this->assertGreaterThanOrEqual(1000, $answer->points_awarded);
        $this->assertLessThan(1100, $answer->points_awarded);
    }

    public function test_player_cannot_submit_with_another_or_missing_token(): void
    {
        [$player] = $this->player();
        $question = $this->liveQuestion();

        $payload = [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 1000,
        ];

        $this->postJson('/api/answers', $payload)->assertUnauthorized();
        $this->withHeader('X-Player-Token', str_repeat('a', 64))
            ->postJson('/api/answers', $payload)
            ->assertUnauthorized();
    }

    public function test_invalidation_reverses_points_once_and_creates_audit_record(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion(['is_double_points' => true]);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 1000,
        ])->assertOk();

        $this->assertGreaterThan(0, $player->fresh()->trivia_score);

        $endpoint = "/api/admin/questions/{$question->id}/invalidate";
        $this->withSession(['admin_logged_in' => true])->postJson($endpoint, [
            'reason' => 'The question was ambiguous.',
        ])->assertOk()->assertJson(['status' => 'skipped']);

        $player->refresh();
        $this->assertSame(0, $player->trivia_score);
        $this->assertSame(0, $player->trivia_correct_count);
        $this->assertSame(0, $player->trivia_double_correct);
        $this->assertDatabaseHas('event_audits', [
            'action' => 'question.invalidated',
            'subject_id' => $question->id,
        ]);

        $this->withSession(['admin_logged_in' => true])->postJson($endpoint, [
            'reason' => 'Trying to reverse it twice.',
        ])->assertStatus(422);
    }

    public function test_admin_routes_reject_unauthenticated_requests(): void
    {
        $question = $this->liveQuestion();

        $this->postJson("/api/admin/questions/{$question->id}/invalidate", [
            'reason' => 'Unauthorized attempt.',
        ])->assertUnauthorized();
    }

    public function test_audit_history_is_protected_and_returns_newest_first(): void
    {
        EventAudit::record('phase.changed', null, ['phase' => 'lobby']);
        EventAudit::record('phase.changed', null, ['phase' => 'predictions_open']);

        $this->getJson('/api/admin/audits')->assertUnauthorized();

        $this->withSession(['admin_logged_in' => true])
            ->getJson('/api/admin/audits?limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.context.phase', 'predictions_open')
            ->assertJsonPath('data.1.context.phase', 'lobby');
    }

    public function test_prediction_scoring_is_idempotent(): void
    {
        [$player] = $this->player();
        Prediction::create([
            'player_id' => $player->id,
            'score_home' => 2,
            'score_away' => 1,
            'first_scorer' => 'Player One',
            'potm' => 'Player Two',
        ]);
        $result = MatchResult::create([
            'score_home' => 2,
            'score_away' => 1,
            'scorer' => 'Player One',
            'potm' => 'Player Two',
            'resolved' => true,
        ]);

        $service = app(ScoringService::class);
        $this->assertSame(1, $service->scorePredictions($result));
        $firstScore = $player->fresh()->prediction_score;
        $this->assertSame(1150, $firstScore);

        $this->assertSame(0, $service->scorePredictions($result));
        $this->assertSame($firstScore, $player->fresh()->prediction_score);
    }

    public function test_no_goal_prediction_earns_scorer_points_only_for_a_goalless_match(): void
    {
        [$player] = $this->player();
        $prediction = Prediction::create([
            'player_id' => $player->id,
            'score_home' => 1,
            'score_away' => 1,
            'first_scorer' => 'No goal / N/A',
            'potm' => 'TBD',
        ]);
        $service = app(ScoringService::class);

        $goalless = new MatchResult(['score_home' => 0, 'score_away' => 0, 'scorer' => null, 'potm' => null]);
        $this->assertSame(550, $service->calculatePredictionScore($prediction, $goalless));

        $scorerMissingDespiteGoals = new MatchResult(['score_home' => 1, 'score_away' => 1, 'scorer' => null, 'potm' => null]);
        $this->assertSame(650, $service->calculatePredictionScore($prediction, $scorerMissingDespiteGoals));

        $wrongOutcomeWithNoGoals = new MatchResult(['score_home' => 0, 'score_away' => 0, 'scorer' => null, 'potm' => null]);
        $prediction->score_home = 1;
        $prediction->score_away = 0;
        $this->assertSame(300, $service->calculatePredictionScore($prediction, $wrongOutcomeWithNoGoals));
    }

    public function test_correcting_the_match_result_rescores_predictions(): void
    {
        // Reproduce a reset that advanced auto-increment: the singleton must
        // still resolve to a single row, never a hardcoded id.
        MatchResult::create(['score_home' => 0, 'score_away' => 0, 'resolved' => true])->delete();

        MatchConfig::current()->update([
            'home_team' => 'England', 'away_team' => 'Argentina',
            'home_squad' => ['Harry Kane'], 'away_squad' => ['Lionel Messi'],
        ]);
        [$player] = $this->player();
        Prediction::create([
            'player_id' => $player->id, 'score_home' => 1, 'score_away' => 2,
            'first_scorer' => 'Lionel Messi', 'first_scoring_team' => 'away',
            'halftime_winner' => 'draw', 'fulltime_winner' => 'away', 'potm' => 'Lionel Messi',
        ]);
        $admin = $this->withSession(['admin_logged_in' => true]);

        // Fat-fingered entry — the player scores nothing
        $admin->postJson('/api/admin/match-result', [
            'score_home' => 2, 'score_away' => 1,
            'halftime_score_home' => 1, 'halftime_score_away' => 0,
            'first_scoring_team' => 'home', 'scorer' => 'Harry Kane', 'potm' => 'Harry Kane',
        ])->assertOk();
        $this->assertSame(0, $player->fresh()->prediction_score);

        // Corrected entry — exact 400 + FT 250 + first team 150 + scorer 300 + HT 200 + POTM 200
        $admin->postJson('/api/admin/match-result', [
            'score_home' => 1, 'score_away' => 2,
            'halftime_score_home' => 0, 'halftime_score_away' => 0,
            'first_scoring_team' => 'away', 'scorer' => 'Lionel Messi', 'potm' => 'Lionel Messi',
        ])->assertOk();

        $this->assertSame(1500, $player->fresh()->prediction_score);
        $this->assertSame(1, MatchResult::count());
    }

    public function test_match_configuration_is_protected_and_locks_after_predictions(): void
    {
        $payload = [
            'home_team' => 'Kenya', 'away_team' => 'Ghana',
            'home_squad' => ['Home One', 'Home Two'],
            'away_squad' => ['Away One', 'Away Two'],
            'kickoff_at' => null, 'venue' => 'National Stadium',
        ];

        $this->putJson('/api/admin/match-config', $payload)->assertUnauthorized();
        $this->withSession(['admin_logged_in' => true])->putJson('/api/admin/match-config', $payload)->assertOk();

        [$player] = $this->player();
        Prediction::create([
            'player_id' => $player->id, 'score_home' => 1, 'score_away' => 0,
            'first_scorer' => 'Home One', 'potm' => 'Home Two',
        ]);

        $this->withSession(['admin_logged_in' => true])
            ->putJson('/api/admin/match-config', array_merge($payload, ['home_team' => 'Changed']))
            ->assertStatus(409)
            ->assertJson(['requires_confirmation' => true]);
    }

    public function test_predictions_accept_only_players_from_shared_match_configuration(): void
    {
        MatchConfig::current()->update([
            'home_team' => 'Kenya', 'away_team' => 'Ghana',
            'home_squad' => ['Home One'], 'away_squad' => ['Away One'],
        ]);
        [$player, $token] = $this->player();
        EventState::setCurrent(['phase' => 'predictions_open']);

        $base = [
            'player_id' => $player->id, 'score_home' => 2, 'score_away' => 1,
            'first_scorer' => 'Home One', 'halftime_winner' => 'draw', 'potm' => 'Away One',
        ];

        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', array_merge($base, [
            'first_scoring_team' => 'invalid',
        ]))->assertStatus(422);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', array_merge($base, [
            'first_scoring_team' => 'away',
        ]))->assertStatus(422)->assertJsonFragment([
            'message' => 'The first goalscorer must belong to the selected first-scoring team.',
        ]);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', array_merge($base, [
            'first_scoring_team' => 'home',
        ]))->assertOk();
    }

    public function test_prediction_api_rejects_impossible_outcomes_and_lobby_submissions(): void
    {
        MatchConfig::current()->update([
            'home_team' => 'England', 'away_team' => 'Argentina',
            'home_squad' => ['Harry Kane'], 'away_squad' => ['Lionel Messi'],
        ]);
        [$player, $token] = $this->player();
        $headers = ['X-Player-Token' => $token];
        EventState::setCurrent(['phase' => 'predictions_open']);

        $base = [
            'player_id' => $player->id,
            'score_home' => 0,
            'score_away' => 1,
            'first_scoring_team' => 'home',
            'first_scorer' => 'Harry Kane',
            'halftime_winner' => 'draw',
            'potm' => 'Lionel Messi',
        ];
        $this->withHeaders($headers)->postJson('/api/predictions', $base)
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'A team predicted to score zero goals cannot score first.']);

        $this->withHeaders($headers)->postJson('/api/predictions', array_merge($base, [
            'score_away' => 0,
            'first_scoring_team' => 'none',
            'first_scorer' => 'No goal / N/A',
            'halftime_winner' => 'home',
        ]))->assertUnprocessable()
            ->assertJsonFragment(['message' => 'A team predicted to score zero goals cannot lead at half-time.']);

        EventState::setCurrent(['phase' => 'lobby']);
        $this->withHeaders($headers)->postJson('/api/predictions', array_merge($base, [
            'score_home' => 1,
            'first_scoring_team' => 'home',
        ]))->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Predictions are closed.']);
        $this->assertSame(0, Prediction::count());
    }

    public function test_player_can_securely_load_and_update_their_saved_prediction(): void
    {
        [$player, $token] = $this->player();
        MatchConfig::current()->update([
            'home_team' => 'England', 'away_team' => 'Argentina',
            'home_squad' => ['Harry Kane'], 'away_squad' => ['Lionel Messi'],
        ]);
        EventState::setCurrent(['phase' => 'predictions_open']);

        $payload = [
            'player_id' => $player->id, 'score_home' => 2, 'score_away' => 1,
            'first_scoring_team' => 'home', 'first_scorer' => 'Harry Kane',
            'halftime_winner' => 'draw', 'potm' => 'Lionel Messi',
        ];
        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', $payload)->assertOk();

        $this->withHeader('X-Player-Token', '')
            ->getJson("/api/predictions/current?player_id={$player->id}")
            ->assertUnauthorized();
        $this->withHeader('X-Player-Token', $token)
            ->getJson("/api/predictions/current?player_id={$player->id}")
            ->assertOk()
            ->assertJsonPath('prediction.score_home', 2)
            ->assertJsonPath('prediction.first_scoring_team', 'home')
            ->assertJsonPath('prediction.halftime_winner', 'draw')
            ->assertJsonPath('prediction.fulltime_winner', 'home');

        $payload['score_home'] = 1;
        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', $payload)->assertOk();
        $this->assertSame(1, Prediction::count());
        $this->assertDatabaseHas('predictions', ['player_id' => $player->id, 'score_home' => 1]);
    }

    public function test_big_screen_prediction_feed_returns_every_player_without_phone_data(): void
    {
        [$first] = $this->player();
        $second = Player::create([
            'phone' => '254711222333', 'nickname' => 'Second Fan', 'consent' => true,
        ]);
        foreach ([$first, $second] as $index => $player) {
            Prediction::create([
                'player_id' => $player->id,
                'score_home' => $index,
                'score_away' => 1,
                'first_scorer' => 'No goal / N/A',
                'potm' => 'TBD',
            ]);
        }

        $response = $this->getJson('/api/predictions/feed')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'entries');

        $this->assertEqualsCanonicalizing(['Test Player', 'Second Fan'], collect($response->json('entries'))->pluck('nickname')->all());
        $this->assertArrayNotHasKey('phone', $response->json('entries.0'));
        $this->assertArrayNotHasKey('phone_last4', $response->json('entries.0'));
    }

    public function test_admin_can_manage_reusable_teams_and_players(): void
    {
        $admin = $this->withSession(['admin_logged_in' => true]);

        $response = $admin->postJson('/api/admin/teams', [
            'name' => 'Kenya', 'code' => 'KEN', 'country_code' => 'KE',
        ])->assertCreated()->assertJsonPath('team.name', 'Kenya');

        $teamId = $response->json('team.id');
        $player = $admin->postJson("/api/admin/teams/{$teamId}/players", [
            'name' => 'Michael Olunga', 'position' => 'FW', 'shirt_number' => 14,
        ])->assertCreated()->assertJsonPath('player.position', 'FW');

        $admin->getJson('/api/admin/teams')->assertOk()
            ->assertJsonFragment(['name' => 'Michael Olunga']);

        $admin->deleteJson('/api/admin/sports-players/'.$player->json('player.id'))->assertOk();
        $this->assertDatabaseMissing('sports_players', ['name' => 'Michael Olunga']);
        $this->assertNotNull(SportsTeam::find($teamId));
    }

    public function test_simulated_users_can_be_created_and_cleared_without_deleting_real_players(): void
    {
        MatchConfig::current()->update([
            'home_team' => 'Kenya', 'away_team' => 'Ghana',
            'home_squad' => ['Home One'], 'away_squad' => ['Away One'],
        ]);
        Question::create([
            'order_index' => 1, 'type' => 'multiple_choice', 'text' => 'Simulation question?',
            'category' => 'general_knowledge', 'options' => ['Yes', 'No'],
            'correct_answer' => 'Yes', 'duration_seconds' => 30,
        ]);
        [$realPlayer] = $this->player();
        $admin = $this->withSession(['admin_logged_in' => true]);

        $admin->postJson('/api/admin/testing/simulate', [
            'count' => 5, 'include_answers' => true, 'answer_rate' => 100, 'correct_rate' => 100,
        ])->assertCreated()->assertJson(['created' => 5, 'answers_created' => 5, 'simulated_total' => 5]);
        $this->assertSame(5, Player::where('is_simulated', true)->count());
        $this->assertSame(5, Prediction::whereHas('player', fn ($query) => $query->where('is_simulated', true))->count());
        $this->assertSame(5, Answer::whereHas('player', fn ($query) => $query->where('is_simulated', true))->count());
        $this->assertTrue(Player::where('is_simulated', true)->get()->every(fn ($player) => $player->trivia_score > 0));

        $admin->deleteJson('/api/admin/testing/simulated-players')->assertOk()->assertJson(['deleted' => 5]);
        $this->assertSame(0, Player::where('is_simulated', true)->count());
        $this->assertNotNull($realPlayer->fresh());
    }

    public function test_event_reset_requires_confirmation_and_preserves_players_by_default(): void
    {
        [$player] = $this->player();
        $player->update(['trivia_score' => 500, 'prediction_score' => 300]);
        Prediction::create([
            'player_id' => $player->id, 'score_home' => 1, 'score_away' => 0,
            'first_scorer' => 'Home One', 'potm' => 'Home Two',
        ]);
        EventState::setCurrent(['phase' => 'predictions_closed']);
        $admin = $this->withSession(['admin_logged_in' => true]);

        $admin->postJson('/api/admin/testing/reset-event', ['confirmed' => false])->assertUnprocessable();
        $this->assertSame(1, Prediction::count());

        $admin->postJson('/api/admin/testing/reset-event', [
            'confirmed' => true, 'remove_players' => false,
        ])->assertOk();

        $this->assertSame(0, Prediction::count());
        $this->assertSame(0, $player->fresh()->trivia_score);
        $this->assertSame(0, $player->fresh()->prediction_score);
        $this->assertSame('lobby', EventState::current()->phase);
    }

    public function test_removing_all_players_requires_the_reset_phrase(): void
    {
        [$player] = $this->player();
        $admin = $this->withSession(['admin_logged_in' => true]);

        $admin->postJson('/api/admin/testing/reset-event', [
            'confirmed' => true, 'remove_players' => true, 'confirmation' => 'wrong',
        ])->assertUnprocessable();
        $this->assertNotNull($player->fresh());

        $admin->postJson('/api/admin/testing/reset-event', [
            'confirmed' => true, 'remove_players' => true, 'confirmation' => ' reset event ',
        ])->assertOk();
        $this->assertNull($player->fresh());
    }

    public function test_admin_can_search_players_and_review_prediction_and_answers(): void
    {
        [$player] = $this->player();
        $question = Question::create([
            'order_index' => 1, 'type' => 'multiple_choice', 'text' => 'Capital of Kenya?',
            'category' => 'general_knowledge', 'options' => ['Nairobi', 'Mombasa'],
            'correct_answer' => 'Nairobi', 'duration_seconds' => 30,
        ]);
        Answer::create([
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'is_correct' => true,
            'points_awarded' => 500, 'response_time_ms' => 1200,
        ]);
        Prediction::create([
            'player_id' => $player->id, 'score_home' => 2, 'score_away' => 1,
            'first_scorer' => 'Harry Kane', 'potm' => 'Jude Bellingham',
        ]);

        $this->getJson('/api/admin/players')->assertUnauthorized();
        $admin = $this->withSession(['admin_logged_in' => true]);
        $admin->getJson('/api/admin/players?search=Test%20Player')
            ->assertOk()->assertJsonPath('data.0.id', $player->id)
            ->assertJsonPath('data.0.answers_count', 1)
            ->assertJsonPath('data.0.prediction_exists', true);
        $admin->getJson("/api/admin/players/{$player->id}")
            ->assertOk()->assertJsonPath('player.prediction.first_scorer', 'Harry Kane')
            ->assertJsonPath('player.answers.0.selected_option', 'Nairobi')
            ->assertJsonPath('player.answers.0.question.correct_answer', 'Nairobi')
            ->assertJsonPath('summary.correct_count', 1);
    }

    public function test_testing_status_is_protected_and_reports_event_counts(): void
    {
        [$realPlayer] = $this->player();
        Player::create(['phone' => '254799000001', 'nickname' => 'Test Fan', 'consent' => true, 'is_simulated' => true]);
        Question::create([
            'order_index' => 1, 'type' => 'multiple_choice', 'text' => 'Status question?',
            'category' => 'general_knowledge', 'options' => ['Yes', 'No'],
            'correct_answer' => 'Yes', 'duration_seconds' => 30,
        ]);

        $this->getJson('/api/admin/testing/status')->assertUnauthorized();
        $this->withSession(['admin_logged_in' => true])->getJson('/api/admin/testing/status')
            ->assertOk()->assertJson([
                'players' => 2, 'real_players' => 1, 'simulated_players' => 1, 'questions' => 1,
            ]);
        $this->assertNotNull($realPlayer->fresh());
    }

    public function test_scoring_rehearsal_is_protected_read_only_and_all_checks_pass(): void
    {
        [$player] = $this->player();
        $before = [Player::count(), Answer::count(), Prediction::count(), MatchResult::count()];

        $this->getJson('/api/admin/testing/scoring-rehearsal')->assertUnauthorized();
        $response = $this->withSession(['admin_logged_in' => true])
            ->getJson('/api/admin/testing/scoring-rehearsal')
            ->assertOk()
            ->assertJsonPath('version', '2026.1')
            ->assertJsonPath('passed', true)
            ->assertJsonCount(7, 'checks');

        $this->assertTrue(collect($response->json('checks'))->every('passed'));
        $this->assertSame($before, [Player::count(), Answer::count(), Prediction::count(), MatchResult::count()]);
        $this->assertNotNull($player->fresh());
    }

    public function test_write_load_command_requires_confirmation_and_refuses_active_gameplay(): void
    {
        $this->assertSame(1, Artisan::call('event:write-load-test', ['--users' => 1]));

        EventState::setCurrent(['phase' => 'predictions_open']);
        [$player] = $this->player();
        Prediction::create([
            'player_id' => $player->id, 'score_home' => 1, 'score_away' => 0,
            'first_scorer' => 'Home Player', 'potm' => 'Home Player',
        ]);
        $before = [Player::count(), Prediction::count(), EventState::current()->phase];

        $this->assertSame(1, Artisan::call('event:write-load-test', [
            '--users' => 1, '--confirm' => true,
        ]));
        $this->assertSame($before, [Player::count(), Prediction::count(), EventState::current()->phase]);
    }

    public function test_main_screen_streams_safe_live_counts_reveal_distribution_and_correct_leaderboard(): void
    {
        [$player] = $this->player();
        $question = $this->liveQuestion();
        Answer::create([
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'is_correct' => true,
            'points_awarded' => 900, 'response_time_ms' => 1200,
        ]);

        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('question.answer_count', 1)
            ->assertJsonPath('round.current', 1)
            ->assertJsonPath('round.total', 1)
            ->assertJsonPath('question.answer_distribution', null)
            ->assertJsonPath('question.correct_answer', null);

        $question->update(['status' => 'closed']);
        EventState::setCurrent(['phase' => 'trivia_reveal', 'current_question_id' => $question->id]);
        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('question.correct_answer', 'Nairobi')
            ->assertJsonPath('question.answer_distribution.Nairobi', 1);

        $player->update(['prediction_score' => 750]);
        Prediction::create([
            'player_id' => $player->id,
            'score_home' => 1,
            'score_away' => 0,
            'first_scorer' => 'Home team',
            'first_scoring_team' => 'home',
            'halftime_winner' => 'draw',
            'fulltime_winner' => 'home',
            'potm' => 'TBD',
            'prediction_score' => 750,
            'resolved' => true,
        ]);
        EventState::setCurrent(['phase' => 'prediction_reveal', 'current_question_id' => null]);
        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('leaderboard.0.nickname', 'Test Player')
            ->assertJsonPath('leaderboard.0.prediction_score', 750);
    }

    public function test_expired_countdown_automatically_closes_and_reveals_without_mc_action(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion([
            'duration_seconds' => 10,
            'activated_at' => now()->subSeconds(11),
        ]);
        Answer::create([
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Mombasa',
            'is_correct' => false,
            'points_awarded' => 0,
            'response_time_ms' => 9000,
        ]);

        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('phase', 'trivia_reveal')
            ->assertJsonPath('question.seconds_remaining', 0)
            ->assertJsonPath('question.status', 'closed')
            ->assertJsonPath('question.correct_answer', 'Nairobi');

        $this->assertSame('closed', $question->fresh()->status);
        $this->assertDatabaseHas('event_audits', [
            'action' => 'question.auto_revealed',
            'subject_id' => $question->id,
        ]);

        $this->withHeader('X-Player-Token', $token)
            ->getJson("/api/answers/result?player_id={$player->id}&question_id={$question->id}")
            ->assertOk()
            ->assertJsonPath('selected_option', 'Mombasa')
            ->assertJsonPath('is_correct', false);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id,
            'question_id' => $question->id,
            'selected_option' => 'Nairobi',
            'response_time_ms' => 10000,
        ])->assertUnprocessable();
    }

    public function test_leaderboards_never_expose_phone_data(): void
    {
        [$player] = $this->player();
        $player->update(['trivia_score' => 100]);

        $response = $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('leaderboard.0.nickname', 'Test Player');

        $this->assertArrayNotHasKey('phone_last4', $response->json('leaderboard.0'));
        $this->assertArrayNotHasKey('phone', $response->json('leaderboard.0'));
    }

    public function test_player_view_admin_preview_requires_admin_session(): void
    {
        $this->get('/play?admin_preview=1')->assertOk()
            ->assertSee(':admin-preview="false"', false);

        $this->withSession(['admin_logged_in' => true])
            ->get('/play?admin_preview=1')->assertOk()
            ->assertSee(':admin-preview="true"', false);
    }

    public function test_venue_crowd_behind_one_shared_ip_registers_with_nickname_only(): void
    {
        // Public hosting: the whole venue shares one public IP. 30 different
        // guests registering within a minute must all succeed — nickname only,
        // no personal data collected.
        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/api/players', [
                'nickname' => 'Guest '.$i,
                'pin' => '2468',
                'consent' => true,
            ])->assertCreated();

            $this->assertNotEmpty($response->json('session_token'));
        }

        $this->assertSame(30, Player::count());
        $this->assertSame(0, Player::whereNotNull('phone')->count());
    }

    public function test_nicknames_are_unique_case_insensitively(): void
    {
        $this->postJson('/api/players', ['nickname' => 'Kevin', 'pin' => '2468', 'consent' => true])->assertCreated();

        $this->postJson('/api/players', ['nickname' => 'KEVIN', 'pin' => '2468', 'consent' => true])
            ->assertStatus(422);
        $this->postJson('/api/players', ['nickname' => '  kevin  ', 'pin' => '2468', 'consent' => true])
            ->assertStatus(422);

        $this->assertSame(1, Player::count());
    }

    public function test_registered_player_can_login_with_nickname_and_pin(): void
    {
        $registration = $this->postJson('/api/players', [
            'nickname' => 'Returning Fan',
            'pin' => '2468',
            'consent' => true,
        ])->assertCreated();

        $oldToken = $registration->json('session_token');
        $login = $this->postJson('/api/players/login', [
            'nickname' => ' returning fan ',
            'pin' => '2468',
        ])->assertOk()
            ->assertJsonPath('nickname', 'Returning Fan');

        $this->assertNotEmpty($login->json('session_token'));
        $this->assertNotSame($oldToken, $login->json('session_token'));

        $this->postJson('/api/players/login', [
            'nickname' => 'Returning Fan',
            'pin' => '1111',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Nickname or game PIN is incorrect.');
    }

    public function test_legacy_player_can_claim_a_pin_once(): void
    {
        Player::create(['nickname' => 'Legacy Fan', 'consent' => true]);

        $this->postJson('/api/players/login', [
            'nickname' => 'Legacy Fan',
            'pin' => '1357',
        ])->assertOk()->assertJsonPath('nickname', 'Legacy Fan');

        $this->postJson('/api/players/login', [
            'nickname' => 'Legacy Fan',
            'pin' => '9999',
        ])->assertUnprocessable();

        $this->postJson('/api/players/login', [
            'nickname' => 'Legacy Fan',
            'pin' => '1357',
        ])->assertOk();
    }

    public function test_trivia_formula_has_grace_streak_caps_and_true_double_points(): void
    {
        $scoring = app(ScoringService::class);

        $this->assertSame(1200, $scoring->calculateTriviaBreakdown(false, 0, 30000, 1)['total']);
        $this->assertSame(1200, $scoring->calculateTriviaBreakdown(false, 1000, 30000, 1)['total']);
        $this->assertSame(1300, $scoring->calculateTriviaBreakdown(false, 0, 30000, 2)['total']);
        $this->assertSame(1400, $scoring->calculateTriviaBreakdown(false, 0, 30000, 3)['total']);
        $this->assertSame(1400, $scoring->calculateTriviaBreakdown(false, 0, 30000, 8)['total']);
        $this->assertSame(2800, $scoring->calculateTriviaBreakdown(true, 0, 30000, 3)['total']);
        $this->assertSame(1000, $scoring->calculateTriviaBreakdown(false, 30000, 30000, 1)['total']);
    }

    public function test_missing_answer_breaks_a_trivia_streak(): void
    {
        [$player] = $this->player();
        $questions = collect([1, 2, 3])->map(fn ($order) => Question::create([
            'order_index' => $order, 'category' => 'visa', 'type' => 'multiple_choice',
            'text' => "Question {$order}?", 'options' => ['Yes', 'No'], 'correct_answer' => 'Yes',
            'duration_seconds' => 30, 'status' => 'closed',
        ]));
        foreach ([$questions[0], $questions[2]] as $question) {
            Answer::create([
                'player_id' => $player->id, 'question_id' => $question->id,
                'selected_option' => 'Yes', 'is_correct' => true,
                'response_time_ms' => 1000, 'points_awarded' => 0,
            ]);
        }

        app(ScoringService::class)->recalculatePlayerTrivia($player);

        $this->assertSame(1, $player->fresh()->trivia_streak);
        $this->assertSame(1200, Answer::where('question_id', $questions[2]->id)->value('points_awarded'));
    }

    public function test_admin_can_enable_configure_and_run_three_round_mode(): void
    {
        $admin = $this->withSession(['admin_logged_in' => true]);
        $admin->putJson('/api/admin/rounds/settings', ['enabled' => true])
            ->assertOk()->assertJsonPath('enabled', true)->assertJsonCount(3, 'rounds');

        $round = TriviaRound::orderBy('position')->firstOrFail();
        $question = Question::create([
            'order_index' => 1, 'category' => 'visa', 'type' => 'multiple_choice',
            'text' => 'Visa round question?', 'options' => ['Yes', 'No'], 'correct_answer' => 'Yes',
            'duration_seconds' => 30, 'is_double_points' => true, 'status' => 'draft',
        ]);
        $admin->putJson("/api/admin/questions/{$question->id}/round", ['round_id' => $round->id])
            ->assertOk()->assertJsonPath('rounds.0.questions.0.id', $question->id);
        $admin->postJson("/api/admin/rounds/{$round->id}/start")->assertOk();

        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('rounds_enabled', true)
            ->assertJsonPath('round.number', 1)
            ->assertJsonPath('round.title', 'Visa Smart Play')
            ->assertJsonPath('round.status', 'live')
            ->assertJsonPath('question', null)
            ->assertJsonPath('question_progress.total', 1);

        $admin->postJson("/api/admin/questions/{$question->id}/activate")->assertOk();
        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('question.id', $question->id)
            ->assertJsonPath('question_progress.current', 1)
            ->assertJsonPath('question_progress.total', 1);

        $admin->postJson("/api/admin/questions/{$question->id}/close")->assertOk();
        $admin->postJson("/api/admin/rounds/{$round->id}/complete")->assertOk();
        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('round.status', 'completed')
            ->assertJsonPath('question', null);
    }

    public function test_streak_resets_between_rounds_and_round_standings_are_independent(): void
    {
        [$player] = $this->player();
        TriviaRound::createRecommended();
        $rounds = TriviaRound::orderBy('position')->take(2)->get();
        EventState::setCurrent(['rounds_enabled' => true, 'current_round_id' => $rounds[1]->id]);

        $questions = collect([
            [$rounds[0]->id, 1], [$rounds[0]->id, 2], [$rounds[1]->id, 1],
        ])->map(fn ($definition, $index) => Question::create([
            'order_index' => $index + 1, 'trivia_round_id' => $definition[0], 'round_position' => $definition[1],
            'category' => 'visa', 'type' => 'multiple_choice', 'text' => "Round question {$index}?",
            'options' => ['Yes', 'No'], 'correct_answer' => 'Yes', 'duration_seconds' => 30, 'status' => 'closed',
        ]));
        foreach ($questions as $question) {
            Answer::create([
                'player_id' => $player->id, 'question_id' => $question->id,
                'selected_option' => 'Yes', 'is_correct' => true,
                'response_time_ms' => 1000, 'points_awarded' => 0,
            ]);
        }

        $scoring = app(ScoringService::class);
        $scoring->recalculatePlayerTrivia($player);

        $this->assertSame(1300, Answer::where('question_id', $questions[1]->id)->value('points_awarded'));
        $this->assertSame(1200, Answer::where('question_id', $questions[2]->id)->value('points_awarded'));
        $this->assertSame(2500, $scoring->roundLeaderboard($rounds[0], 10)[0]['round_score']);
        $this->assertSame(1200, $scoring->roundLeaderboard($rounds[1], 10)[0]['round_score']);
    }

    public function test_prediction_breakdown_reaches_exactly_fifteen_hundred_points(): void
    {
        [$player] = $this->player();
        $prediction = new Prediction([
            'player_id' => $player->id, 'score_home' => 2, 'score_away' => 1,
            'fulltime_winner' => 'home', 'halftime_winner' => 'draw',
            'first_scoring_team' => 'home', 'first_scorer' => 'Home Player', 'potm' => 'Away Player',
        ]);
        $result = new MatchResult([
            'score_home' => 2, 'score_away' => 1,
            'halftime_score_home' => 0, 'halftime_score_away' => 0,
            'first_scoring_team' => 'home', 'scorer' => 'Home Player', 'potm' => 'Away Player',
        ]);

        $breakdown = app(ScoringService::class)->calculatePredictionBreakdown($prediction, $result);

        $this->assertSame([
            'outcome' => 250, 'exact_score_bonus' => 400, 'halftime' => 200,
            'first_team' => 150, 'first_scorer' => 300, 'potm' => 200,
        ], $breakdown);
        $this->assertSame(1500, array_sum($breakdown));
    }

    public function test_live_answer_result_returns_selection_without_scoring_secrets(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();
        Answer::create([
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'is_correct' => true,
            'response_time_ms' => 1000, 'points_awarded' => 1200,
        ]);

        $this->withHeader('X-Player-Token', $token)
            ->getJson("/api/answers/result?player_id={$player->id}&question_id={$question->id}")
            ->assertOk()
            ->assertJson(['answered' => true, 'revealed' => false, 'selected_option' => 'Nairobi'])
            ->assertJsonMissingPath('is_correct')
            ->assertJsonMissingPath('points_awarded')
            ->assertJsonMissingPath('total_score');
    }

    public function test_equal_scores_share_the_same_leaderboard_rank(): void
    {
        [$first] = $this->player();
        $second = Player::create(['nickname' => 'Second Player', 'consent' => true]);
        foreach ([$first, $second] as $player) {
            $player->update(['trivia_score' => 1200, 'trivia_correct_count' => 1]);
            Prediction::create([
                'player_id' => $player->id, 'score_home' => 1, 'score_away' => 0,
                'first_scorer' => 'Home Player', 'potm' => 'Home Player',
                'prediction_score' => 650, 'resolved' => true,
            ]);
        }
        $scoring = app(ScoringService::class);

        $this->assertSame([1, 1], array_column($scoring->triviaLeaderboard(10), 'rank'));
        $this->assertSame([1, 1], array_column($scoring->predictionLeaderboard(10), 'rank'));
    }

    public function test_live_standings_are_withheld_until_the_reveal(): void
    {
        [$player] = $this->player();
        $question = $this->liveQuestion();
        Answer::create([
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'is_correct' => true,
            'points_awarded' => 1000, 'response_time_ms' => 1000,
        ]);
        $player->update(['trivia_score' => 1000]);

        // During a live question the leaderboard must be empty: a visible score
        // move would let a player confirm a guess and change their answer.
        $this->getJson('/api/state')->assertOk()->assertJsonPath('leaderboard', []);

        // Once revealed, the standings return.
        $question->update(['status' => 'closed']);
        EventState::setCurrent(['phase' => 'trivia_reveal', 'current_question_id' => $question->id]);
        $this->getJson('/api/state')->assertOk()
            ->assertJsonPath('leaderboard.0.nickname', 'Test Player');
    }

    public function test_manual_score_adjustment_survives_answer_recalculation(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();

        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'response_time_ms' => 1000,
        ])->assertOk();
        $base = $player->fresh()->trivia_score;
        $this->assertGreaterThan(0, $base);

        $this->withSession(['admin_logged_in' => true])
            ->postJson("/api/admin/players/{$player->id}/adjust-score", [
                'adjustment' => 500, 'reason' => 'Bonus for helping set up.',
            ])->assertOk()->assertJsonPath('trivia_score', $base + 500);

        // Changing the answer to a wrong one rebuilds the answer-derived total to
        // zero, but the manual +500 must persist through the recalculation.
        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Mombasa', 'response_time_ms' => 2000,
        ])->assertOk();

        $this->assertSame(500, $player->fresh()->trivia_score);
        $this->assertSame(500, $player->fresh()->trivia_manual_adjustment);
    }

    public function test_skipping_a_question_removes_its_points(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();
        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'response_time_ms' => 1000,
        ])->assertOk();
        $this->assertGreaterThan(0, $player->fresh()->trivia_score);

        $this->withSession(['admin_logged_in' => true])
            ->postJson("/api/admin/questions/{$question->id}/skip")
            ->assertOk()->assertJsonPath('status', 'skipped');

        $this->assertSame(0, $player->fresh()->trivia_score);
        $this->assertSame('skipped', $question->fresh()->status);
    }

    public function test_reopening_a_closed_question_reverses_its_points(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();
        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Nairobi', 'response_time_ms' => 1000,
        ])->assertOk();
        $question->update(['status' => 'closed']);
        $this->assertGreaterThan(0, $player->fresh()->trivia_score);

        $this->withSession(['admin_logged_in' => true])
            ->postJson("/api/admin/questions/{$question->id}/reopen")
            ->assertOk()->assertJsonPath('status', 'draft');

        $this->assertSame('draft', $question->fresh()->status);
        $this->assertSame(0, $player->fresh()->trivia_score);
        $this->assertDatabaseHas('event_audits', [
            'action' => 'question.reopened',
            'subject_id' => $question->id,
        ]);
    }

    public function test_a_question_in_a_completed_round_cannot_be_reopened(): void
    {
        $round = TriviaRound::create([
            'position' => 1, 'title' => 'Round One', 'category' => 'visa',
            'intro_message' => 'Go', 'status' => 'completed',
        ]);
        $question = $this->liveQuestion([
            'status' => 'closed', 'trivia_round_id' => $round->id, 'round_position' => 1,
        ]);

        $this->withSession(['admin_logged_in' => true])
            ->postJson("/api/admin/questions/{$question->id}/reopen")
            ->assertStatus(422);

        $this->assertSame('closed', $question->fresh()->status);
    }

    public function test_a_closed_question_cannot_be_reactivated_directly(): void
    {
        $question = $this->liveQuestion();
        $question->update(['status' => 'closed']);

        $this->withSession(['admin_logged_in' => true])
            ->postJson("/api/admin/questions/{$question->id}/activate")
            ->assertStatus(422);

        $this->assertSame('closed', $question->fresh()->status);
    }

    public function test_editing_a_closed_question_answer_key_rescores(): void
    {
        [$player, $token] = $this->player();
        $question = $this->liveQuestion();
        $this->withHeader('X-Player-Token', $token)->postJson('/api/answers', [
            'player_id' => $player->id, 'question_id' => $question->id,
            'selected_option' => 'Mombasa', 'response_time_ms' => 1000,
        ])->assertOk();
        $question->update(['status' => 'closed']);
        $this->assertSame(0, $player->fresh()->trivia_score);

        $this->withSession(['admin_logged_in' => true])
            ->putJson("/api/admin/questions/{$question->id}", ['correct_answer' => 'Mombasa'])
            ->assertOk();

        $this->assertGreaterThan(0, $player->fresh()->trivia_score);
        $this->assertTrue((bool) Answer::where('player_id', $player->id)
            ->where('question_id', $question->id)->value('is_correct'));
    }

    public function test_predictions_are_blocked_after_the_result_is_final(): void
    {
        MatchConfig::current()->update([
            'home_team' => 'Kenya', 'away_team' => 'Ghana',
            'home_squad' => ['Home One'], 'away_squad' => ['Away One'],
        ]);
        MatchResult::create([
            'score_home' => 1, 'score_away' => 0,
            'halftime_score_home' => 0, 'halftime_score_away' => 0,
            'first_scoring_team' => 'home', 'scorer' => 'Home One', 'resolved' => true,
        ]);
        [$player, $token] = $this->player();
        EventState::setCurrent(['phase' => 'predictions_open']);

        $this->withHeader('X-Player-Token', $token)->postJson('/api/predictions', [
            'player_id' => $player->id, 'score_home' => 1, 'score_away' => 0,
            'first_scoring_team' => 'home', 'first_scorer' => 'Home One',
            'halftime_winner' => 'draw', 'potm' => 'Away One',
        ])->assertStatus(422)->assertJsonFragment([
            'message' => 'The match result is final — predictions are closed.',
        ]);

        // And the admin cannot reopen the window either.
        $this->withSession(['admin_logged_in' => true])
            ->postJson('/api/admin/phase', ['phase' => 'predictions_open'])
            ->assertStatus(422);
    }

    private function player(): array
    {
        $player = Player::create([
            'nickname' => 'Test Player',
            'consent' => true,
        ]);

        return [$player, $player->issueSessionToken()];
    }

    private function liveQuestion(array $overrides = []): Question
    {
        $question = Question::create(array_merge([
            'order_index' => 1,
            'category' => 'general_knowledge',
            'type' => 'multiple_choice',
            'text' => 'Which Kenyan city is known as the Silicon Savannah?',
            'options' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru'],
            'correct_answer' => 'Nairobi',
            'duration_seconds' => 30,
            'is_double_points' => false,
            'status' => 'live',
            'activated_at' => now(),
        ], $overrides));

        EventState::setCurrent([
            'phase' => 'trivia_live',
            'current_question_id' => $question->id,
        ]);

        return $question;
    }
}
