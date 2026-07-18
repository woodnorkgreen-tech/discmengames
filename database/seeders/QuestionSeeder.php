<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\TriviaRound;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Question::truncate();
        TriviaRound::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        TriviaRound::createRecommended();
        $roundIds = TriviaRound::orderBy('position')->pluck('id', 'position');

        $questions = [
            // General knowledge and football warm-up
            ['general_knowledge', 'What does NFC stand for in contactless payments?', ['Near Field Communication', 'Network Financial Connection', 'New Finance Channel', 'National Fast Connectivity'], 'Near Field Communication', 30],
            ['general_knowledge', 'Which Kenyan city is known as the "Silicon Savannah"?', ['Mombasa', 'Kisumu', 'Nairobi', 'Nakuru'], 'Nairobi', 25],
            ['general_knowledge', 'Which company owns Android?', ['Samsung', 'Microsoft', 'Google', 'Apple'], 'Google', 20],
            ['general_knowledge', 'Which is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 'Pacific', 20],
            ['general_knowledge', 'Which Kenyan athlete won Olympic gold in the men\'s 800 metres at both London 2012 and Rio 2016?', ['Emmanuel Wanyonyi', 'Ferguson Rotich', 'David Rudisha', 'Timothy Cheruiyot'], 'David Rudisha', 30],
            ['general_knowledge', 'What does VAR stand for in football?', ['Video Assistant Referee', 'Virtual Assistant Replay', 'Video Action Review', 'Verified Assistant Referee'], 'Video Assistant Referee', 25],
            ['general_knowledge', 'Which payment technology allows a customer to simply tap their card or phone to pay?', ['Magnetic Stripe', 'Contactless Payments', 'SWIFT', 'RTGS'], 'Contactless Payments', 25],
            ['general_knowledge', 'Which company sponsors the "Player of the Match" award at the FIFA World Cup?', ['Coca-Cola', 'Visa', 'Adidas', 'Hyundai'], 'Adidas', 30],

            // FIFA World Cup and final-match knowledge
            ['fifa_world_cup', 'Which country has won the FIFA World Cup the most times?', ['Germany', 'Italy', 'Argentina', 'Brazil'], 'Brazil', 25],
            ['fifa_world_cup', 'Which three countries hosted the FIFA World Cup 2026?', ['USA, Mexico & Canada', 'USA, Panama & Mexico', 'USA, Costa Rica & Canada', 'Canada, Brazil & USA'], 'USA, Mexico & Canada', 30],
            ['fifa_world_cup', 'How many teams participated in the FIFA World Cup 2026?', ['32', '40', '48', '64'], '48', 25],
            ['fifa_world_cup', 'Which country hosted the first FIFA World Cup in 1930?', ['Brazil', 'Uruguay', 'Italy', 'England'], 'Uruguay', 30],
            ['fifa_world_cup', 'What is the maximum duration of extra time in a knockout match?', ['20 minutes', '25 minutes', '30 minutes', '40 minutes'], '30 minutes', 20],
            ['fifa_world_cup', 'If a knockout match remains tied after extra time, what determines the winner?', ['Golden Goal', 'Coin Toss', 'Penalty Shootout', 'Replay'], 'Penalty Shootout', 20],
            ['fifa_world_cup', 'Which African nation has won the Africa Cup of Nations (AFCON) the most times?', ['Cameroon', 'Nigeria', 'Egypt', 'Ghana'], 'Egypt', 30],
            ['fifa_world_cup', 'Which country has appeared in every FIFA World Cup tournament?', ['Germany', 'Argentina', 'Brazil', 'Italy'], 'Brazil', 25],

            // Visa brand round
            ['visa', 'What is Visa Kenya\'s official social media handle?', ['@visakenya254', '@visa_kenya', '@visaEA', '@visaafrica'], '@visa_kenya', 30],
            ['visa', 'Visa connects people and businesses across approximately how many countries and territories?', ['Over 100', 'Over 150', 'Over 200', 'Over 300'], 'Over 200', 25],
            ['visa', 'Where can you use your Visa card?', ['Restaurants', 'Supermarkets', 'Online stores', 'All of the above'], 'All of the above', 20],
            ['visa', 'What technology allows merchants to accept contactless payments using a compatible smartphone without a separate card machine?', ['QR Pay', 'Visa Tap to Phone', 'Scan & Go', 'Mobile POS Lite'], 'Visa Tap to Phone', 30],
            ['visa', 'Nairobi serves as Visa\'s hub for which region?', ['Kenya', 'East Africa', 'Southern Africa', 'Horn of Africa'], 'East Africa', 25],
            ['visa', 'True or False: Visa is not a bank and does not issue cards directly to customers — banks and financial institutions do.', ['True', 'False'], 'True', 20, true],
            ['visa', 'What is the name of Visa\'s platform that helps businesses and customers move money quickly around the world?', ['Visa Direct', 'Visa Instant', 'Visa Flow', 'Visa Express'], 'Visa Direct', 30, true],
        ];

        // Four questions per round. Everything else stays in the unassigned
        // bank as a reviewed alternate instead of making the live show too long.
        $assignments = [
            16 => [1, 1], 17 => [1, 2], 18 => [1, 3], 19 => [1, 4],
            8 => [2, 1], 9 => [2, 2], 10 => [2, 3], 11 => [2, 4],
            0 => [3, 1], 5 => [3, 2], 6 => [3, 3], 15 => [3, 4],
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
