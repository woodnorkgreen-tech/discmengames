<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $questions = require database_path('data/hard_general_knowledge_questions.php');
        $nextOrder = ((int) DB::table('questions')->max('order_index')) + 1;

        foreach ($questions as [$category, $text, $options, $answer, $duration]) {
            if (DB::table('questions')->where('text', $text)->exists()) {
                continue;
            }

            DB::table('questions')->insert([
                'order_index' => $nextOrder++,
                'trivia_round_id' => null,
                'round_position' => null,
                'category' => $category,
                'type' => 'multiple_choice',
                'text' => $text,
                'options' => json_encode($options, JSON_THROW_ON_ERROR),
                'correct_answer' => $answer,
                'duration_seconds' => $duration,
                'is_double_points' => false,
                'status' => 'draft',
                'activated_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $texts = array_column(
            require database_path('data/hard_general_knowledge_questions.php'),
            1,
        );

        DB::table('questions')->whereIn('text', $texts)->delete();
    }
};
