<?php

namespace App\Console\Commands;

use App\Models\Answer;
use App\Models\EventState;
use App\Models\MatchConfig;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\Prediction;
use App\Models\Question;
use App\Services\ScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class EventWriteLoadTest extends Command
{
    protected $signature = 'event:write-load-test
        {--users=200 : Temporary simulated players}
        {--url=https://discmen-final-whistle.ddev.site : Base URL receiving real API requests}
        {--confirm : Confirm temporary local gameplay mutations and cleanup}';

    protected $description = 'Load-test authenticated prediction and trivia writes using temporary local data';

    public function handle(ScoringService $scoring): int
    {
        $users = (int) $this->option('users');
        $baseUrl = rtrim((string) $this->option('url'), '/');

        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');
            return self::FAILURE;
        }
        if (!$this->option('confirm')) {
            $this->error('Pass --confirm after verifying this is an isolated local/test event.');
            return self::FAILURE;
        }
        if ($users < 1 || $users > 500) {
            $this->error('--users must be between 1 and 500.');
            return self::FAILURE;
        }

        $state = EventState::current();
        if ($state->phase !== 'lobby' || Answer::exists() || Prediction::exists()
            || MatchResult::exists() || Question::where('status', 'live')->exists()) {
            $this->error('The event must be an empty lobby with no answers, predictions, result, or live question.');
            return self::FAILURE;
        }

        $config = MatchConfig::current();
        $homePlayer = $config->home_squad[0] ?? null;
        if (!$homePlayer || empty($config->away_squad)) {
            $this->error('Configure both match squads before running the write load test.');
            return self::FAILURE;
        }

        $playerIds = [];
        $questionId = null;
        $resultId = null;
        $prefix = 'LoadTest-'.now()->format('YmdHis').'-';

        try {
            $players = collect(range(1, $users))->map(function ($index) use ($prefix, &$playerIds) {
                $player = Player::create([
                    'nickname' => $prefix.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                    'consent' => true,
                    'is_simulated' => true,
                ]);
                $token = $player->issueSessionToken();
                $playerIds[] = $player->id;
                return ['player' => $player, 'token' => $token];
            });

            EventState::setCurrent(['phase' => 'predictions_open', 'current_question_id' => null]);
            $predictionPayload = [
                'score_home' => 1, 'score_away' => 0,
                'first_scoring_team' => 'home', 'first_scorer' => $homePlayer,
                'halftime_winner' => 'draw', 'potm' => $homePlayer,
            ];
            [$predictionResponses, $predictionMs] = $this->sendPool($players, fn ($entry) => [
                'path' => '/api/predictions',
                'payload' => ['player_id' => $entry['player']->id] + $predictionPayload,
            ], $baseUrl);
            $this->assertResponses('Prediction submissions', $predictionResponses, $predictionMs, $users);

            $question = Question::create([
                'order_index' => ((int) Question::max('order_index')) + 1000,
                'category' => 'general_knowledge', 'type' => 'multiple_choice',
                'text' => 'Authenticated write-load verification question',
                'options' => ['Yes', 'No'], 'correct_answer' => 'Yes',
                'duration_seconds' => 120, 'status' => 'live', 'activated_at' => now(),
            ]);
            $questionId = $question->id;
            EventState::setCurrent(['phase' => 'trivia_live', 'current_question_id' => $question->id]);

            [$answerResponses, $answerMs] = $this->sendPool($players, fn ($entry) => [
                'path' => '/api/answers',
                'payload' => [
                    'player_id' => $entry['player']->id, 'question_id' => $question->id,
                    'selected_option' => 'Yes', 'response_time_ms' => 0,
                ],
            ], $baseUrl);
            $this->assertResponses('Trivia submissions', $answerResponses, $answerMs, $users);

            $result = MatchResult::create([
                'score_home' => 1, 'score_away' => 0,
                'halftime_score_home' => 0, 'halftime_score_away' => 0,
                'first_scoring_team' => 'home', 'scorer' => $homePlayer,
                'potm' => $homePlayer, 'resolved' => true,
            ]);
            $resultId = $result->id;
            $scored = $scoring->scorePredictions($result);

            $checks = [
                'predictions saved' => Prediction::whereIn('player_id', $playerIds)->count() === $users,
                'answers saved once' => Answer::whereIn('player_id', $playerIds)->count() === $users,
                'all answers correct' => Answer::whereIn('player_id', $playerIds)->where('is_correct', true)->count() === $users,
                'all trivia scores positive' => Player::whereIn('id', $playerIds)->where('trivia_score', '>', 0)->count() === $users,
                'predictions scored' => $scored === $users,
                'all prediction totals 1,500' => Player::whereIn('id', $playerIds)->where('prediction_score', 1500)->count() === $users,
            ];
            foreach ($checks as $label => $passed) {
                $this->line(($passed ? '<fg=green>PASS</>' : '<fg=red>FAIL</>')." — {$label}");
            }

            return !in_array(false, $checks, true) ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        } finally {
            Player::whereIn('id', $playerIds)->delete();
            if ($questionId) Question::whereKey($questionId)->delete();
            if ($resultId) MatchResult::whereKey($resultId)->delete();
            EventState::setCurrent(['phase' => 'lobby', 'current_question_id' => null]);
            Cache::forget('public-event-state-v3');
            Cache::forget('public-prediction-feed');
            $this->info('Cleanup complete; temporary players and gameplay were removed.');
        }
    }

    private function sendPool($players, callable $scenario, string $baseUrl): array
    {
        if (!function_exists('curl_multi_init')) {
            throw new \RuntimeException('The PHP cURL extension is required for the write load test.');
        }

        $started = microtime(true);
        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($players as $index => $entry) {
            $request = $scenario($entry);
            $handle = curl_init($baseUrl.$request['path']);
            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($request['payload'], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'X-Player-Token: '.$entry['token'],
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_multi_add_handle($multiHandle, $handle);
            $handles[$index] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);
            if ($status !== CURLM_OK) {
                throw new \RuntimeException('Concurrent HTTP request failed: '.curl_multi_strerror($status));
            }
            if ($running && curl_multi_select($multiHandle, 1.0) === -1) {
                usleep(1000);
            }
        } while ($running);

        $responses = [];
        foreach ($handles as $handle) {
            $responses[] = [
                'status' => (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
                'error' => curl_error($handle),
                'body' => curl_multi_getcontent($handle),
            ];
            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }
        curl_multi_close($multiHandle);

        return [$responses, (microtime(true) - $started) * 1000];
    }

    private function assertResponses(string $label, array $responses, float $durationMs, int $expected): void
    {
        $successful = collect($responses)->filter(fn ($response) =>
            $response['error'] === '' && $response['status'] >= 200 && $response['status'] < 300
        )->count();
        $this->line(sprintf('%s: %d/%d successful in %.1f ms', $label, $successful, $expected, $durationMs));
        if ($successful !== $expected) {
            $statuses = collect($responses)->countBy(fn ($response) =>
                $response['error'] !== '' ? 'network: '.$response['error'] : (string) $response['status']
            )->all();
            throw new \RuntimeException($label.' failed: '.json_encode($statuses));
        }
    }
}
