<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $roundDefaults = [
            1 => ['Quick Fire', 'general_knowledge', 'Start strong with sharp instincts and fast general-knowledge answers.'],
            2 => ['Football IQ', 'fifa_world_cup', 'Read the game, trust your football instincts, and climb.'],
            3 => ['Final Whistle', null, 'Bring everything together in the high-energy final round.'],
        ];

        foreach ($roundDefaults as $position => [$title, $category, $intro]) {
            if (! DB::table('trivia_rounds')->where('position', $position)->exists()) {
                DB::table('trivia_rounds')->insert([
                    'position' => $position,
                    'title' => $title,
                    'category' => $category,
                    'intro_message' => $intro,
                    'status' => 'draft',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $roundIds = DB::table('trivia_rounds')->pluck('id', 'position');
        $questions = [
            ['general_knowledge', 'Which musical instrument traditionally has 88 keys?', ['Piano', 'Guitar', 'Violin', 'Saxophone'], 'Piano', 25, 1, 1],
            ['general_knowledge', 'Which Kenyan city is known as the "Silicon Savannah"?', ['Mombasa', 'Kisumu', 'Nairobi', 'Nakuru'], 'Nairobi', 25, 1, 2],
            ['general_knowledge', 'Which company owns Android?', ['Samsung', 'Microsoft', 'Google', 'Apple'], 'Google', 20, 1, 3],
            ['general_knowledge', 'Which is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 'Pacific', 20, 1, 4],
            ['general_knowledge', 'Which Kenyan athlete won Olympic gold in the men\'s 800 metres at both London 2012 and Rio 2016?', ['Emmanuel Wanyonyi', 'Ferguson Rotich', 'David Rudisha', 'Timothy Cheruiyot'], 'David Rudisha', 30, 3, 1],
            ['general_knowledge', 'What does VAR stand for in football?', ['Video Assistant Referee', 'Virtual Assistant Replay', 'Video Action Review', 'Verified Assistant Referee'], 'Video Assistant Referee', 25, 3, 2],
            ['general_knowledge', 'How many players from one team start a football match on the pitch?', ['9', '10', '11', '12'], '11', 20, 3, 3],
            ['general_knowledge', 'Which sportswear company supplies the official FIFA World Cup match ball?', ['Nike', 'Adidas', 'Puma', 'Umbro'], 'Adidas', 30, null, null],
            ['fifa_world_cup', 'Which country has won the FIFA World Cup the most times?', ['Germany', 'Italy', 'Argentina', 'Brazil'], 'Brazil', 25, 2, 1],
            ['fifa_world_cup', 'Which three countries hosted the FIFA World Cup 2026?', ['USA, Mexico & Canada', 'USA, Panama & Mexico', 'USA, Costa Rica & Canada', 'Canada, Brazil & USA'], 'USA, Mexico & Canada', 30, 2, 2],
            ['fifa_world_cup', 'How many teams participated in the FIFA World Cup 2026?', ['32', '40', '48', '64'], '48', 25, 2, 3],
            ['fifa_world_cup', 'Which country hosted the first FIFA World Cup in 1930?', ['Brazil', 'Uruguay', 'Italy', 'England'], 'Uruguay', 30, 2, 4],
            ['fifa_world_cup', 'What is the maximum duration of extra time in a knockout match?', ['20 minutes', '25 minutes', '30 minutes', '40 minutes'], '30 minutes', 20, null, null],
            ['fifa_world_cup', 'If a knockout match remains tied after extra time, what determines the winner?', ['Golden Goal', 'Coin Toss', 'Penalty Shootout', 'Replay'], 'Penalty Shootout', 20, null, null],
            ['fifa_world_cup', 'Which African nation has won the Africa Cup of Nations (AFCON) the most times?', ['Cameroon', 'Nigeria', 'Egypt', 'Ghana'], 'Egypt', 30, null, null],
            ['fifa_world_cup', 'Which country has appeared in every FIFA World Cup tournament?', ['Germany', 'Argentina', 'Brazil', 'Italy'], 'Brazil', 25, 3, 4],
        ];

        $nextOrder = ((int) DB::table('questions')->max('order_index')) + 1;

        foreach ($questions as [$category, $text, $options, $answer, $duration, $roundPosition, $questionPosition]) {
            $existing = DB::table('questions')->where('text', $text)->first();
            $roundId = $roundPosition ? ($roundIds[$roundPosition] ?? null) : null;
            $slotAvailable = $roundId && $questionPosition
                ? ! DB::table('questions')
                    ->where('trivia_round_id', $roundId)
                    ->where('round_position', $questionPosition)
                    ->exists()
                : false;

            if ($existing) {
                if ($slotAvailable && $existing->trivia_round_id === null) {
                    DB::table('questions')->where('id', $existing->id)->update([
                        'trivia_round_id' => $roundId,
                        'round_position' => $questionPosition,
                        'is_double_points' => $questionPosition === 4,
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            DB::table('questions')->insert([
                'order_index' => $nextOrder++,
                'trivia_round_id' => $slotAvailable ? $roundId : null,
                'round_position' => $slotAvailable ? $questionPosition : null,
                'category' => $category,
                'type' => 'multiple_choice',
                'text' => $text,
                'options' => json_encode($options, JSON_THROW_ON_ERROR),
                'correct_answer' => $answer,
                'duration_seconds' => $duration,
                'is_double_points' => $slotAvailable && $questionPosition === 4,
                'status' => 'draft',
                'activated_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Recovery data may acquire answers in production. Preserve it on rollback.
    }
};
