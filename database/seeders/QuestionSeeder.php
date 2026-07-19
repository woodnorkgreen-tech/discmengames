<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\TriviaRound;
use App\Models\TriviaCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        TriviaCategory::firstOrCreate(['key' => 'general_knowledge'], ['name' => 'General Knowledge']);
        TriviaCategory::firstOrCreate(['key' => 'fifa_world_cup'], ['name' => 'Football / FIFA World Cup']);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Question::truncate();
        TriviaRound::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        TriviaRound::createRecommended();
        $roundIds = TriviaRound::orderBy('position')->pluck('id', 'position');

        $questions = [
            // General knowledge and football warm-up
            ['general_knowledge', 'Which musical instrument traditionally has 88 keys?', ['Piano', 'Guitar', 'Violin', 'Saxophone'], 'Piano', 25],
            ['general_knowledge', 'Which Kenyan city is known as the "Silicon Savannah"?', ['Mombasa', 'Kisumu', 'Nairobi', 'Nakuru'], 'Nairobi', 25],
            ['general_knowledge', 'Which company owns Android?', ['Samsung', 'Microsoft', 'Google', 'Apple'], 'Google', 20],
            ['general_knowledge', 'Which is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 'Pacific', 20],
            ['general_knowledge', 'Which Kenyan athlete won Olympic gold in the men\'s 800 metres at both London 2012 and Rio 2016?', ['Emmanuel Wanyonyi', 'Ferguson Rotich', 'David Rudisha', 'Timothy Cheruiyot'], 'David Rudisha', 30],
            ['general_knowledge', 'What does VAR stand for in football?', ['Video Assistant Referee', 'Virtual Assistant Replay', 'Video Action Review', 'Verified Assistant Referee'], 'Video Assistant Referee', 25],
            ['general_knowledge', 'How many players from one team start a football match on the pitch?', ['9', '10', '11', '12'], '11', 20],
            ['general_knowledge', 'Which sportswear company supplies the official FIFA World Cup match ball?', ['Nike', 'Adidas', 'Puma', 'Umbro'], 'Adidas', 30],

            // FIFA World Cup and final-match knowledge
            ['fifa_world_cup', 'Which country has won the FIFA World Cup the most times?', ['Germany', 'Italy', 'Argentina', 'Brazil'], 'Brazil', 25],
            ['fifa_world_cup', 'Which three countries hosted the FIFA World Cup 2026?', ['USA, Mexico & Canada', 'USA, Panama & Mexico', 'USA, Costa Rica & Canada', 'Canada, Brazil & USA'], 'USA, Mexico & Canada', 30],
            ['fifa_world_cup', 'How many teams participated in the FIFA World Cup 2026?', ['32', '40', '48', '64'], '48', 25],
            ['fifa_world_cup', 'Which country hosted the first FIFA World Cup in 1930?', ['Brazil', 'Uruguay', 'Italy', 'England'], 'Uruguay', 30],
            ['fifa_world_cup', 'What is the maximum duration of extra time in a knockout match?', ['20 minutes', '25 minutes', '30 minutes', '40 minutes'], '30 minutes', 20],
            ['fifa_world_cup', 'If a knockout match remains tied after extra time, what determines the winner?', ['Golden Goal', 'Coin Toss', 'Penalty Shootout', 'Replay'], 'Penalty Shootout', 20],
            ['fifa_world_cup', 'Which African nation has won the Africa Cup of Nations (AFCON) the most times?', ['Cameroon', 'Nigeria', 'Egypt', 'Ghana'], 'Egypt', 30],
            ['fifa_world_cup', 'Which country has appeared in every FIFA World Cup tournament?', ['Germany', 'Argentina', 'Brazil', 'Italy'], 'Brazil', 25],
        ];

        // Four questions per round. Everything else stays in the unassigned
        // bank as a reviewed alternate instead of making the live show too long.
        $assignments = [
            0 => [1, 1], 1 => [1, 2], 2 => [1, 3], 3 => [1, 4],
            8 => [2, 1], 9 => [2, 2], 10 => [2, 3], 11 => [2, 4],
            4 => [3, 1], 5 => [3, 2], 6 => [3, 3], 15 => [3, 4],
        ];

        foreach ($questions as $index => $question) {
            [$category, $text, $options, $answer, $duration, $doublePoints] = array_pad($question, 6, false);
            [$roundNumber, $roundPosition] = $assignments[$index] ?? [null, null];
            Question::create([
                'order_index' => $index + 1,
                'trivia_round_id' => $roundNumber ? $roundIds[$roundNumber] : null,
                'round_position' => $roundPosition,
                'category' => $category,
                'type' => count($options) === 2 ? 'true_false' : 'multiple_choice',
                'text' => $text,
                'options' => $options,
                'correct_answer' => $answer,
                'duration_seconds' => $duration,
                'is_double_points' => $roundPosition === 4 ? true : ($doublePoints ?? false),
                'status' => 'draft',
                'activated_at' => null,
            ]);
        }

        $this->command->info('Seeded '.count($questions).' questions, 3 ready rounds, and an alternate question bank.');
    }
}
