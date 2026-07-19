<?php

use App\Models\Player;
use App\Services\ScoringService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Remove legacy sponsor questions from existing installations as well
        // as fresh seeds. Answers cascade with their question; every affected
        // player is rescored so rankings remain mathematically correct.
        if (Schema::hasTable('questions')) {
            $legacyQuestionIds = DB::table('questions')
                ->where('category', 'visa')
                ->orWhereRaw('LOWER(text) LIKE ?', ['%visa%'])
                ->orWhereRaw('LOWER(text) LIKE ?', ['%m-pesa%'])
                ->orWhereRaw('LOWER(text) LIKE ?', ['%mpesa%'])
                ->orWhereRaw('LOWER(text) LIKE ?', ['%safaricom%'])
                ->orWhereRaw('LOWER(options) LIKE ?', ['%visa%'])
                ->orWhereRaw('LOWER(options) LIKE ?', ['%m-pesa%'])
                ->orWhereRaw('LOWER(options) LIKE ?', ['%mpesa%'])
                ->orWhereRaw('LOWER(options) LIKE ?', ['%safaricom%'])
                ->pluck('id');

            if ($legacyQuestionIds->isNotEmpty()) {
                $affectedPlayerIds = Schema::hasTable('answers')
                    ? DB::table('answers')->whereIn('question_id', $legacyQuestionIds)->distinct()->pluck('player_id')
                    : collect();

                if (Schema::hasTable('event_state')) {
                    DB::table('event_state')->whereIn('current_question_id', $legacyQuestionIds)->update(['current_question_id' => null]);
                }

                DB::table('questions')->whereIn('id', $legacyQuestionIds)->delete();

                $scoring = app(ScoringService::class);
                Player::whereIn('id', $affectedPlayerIds)->each(fn (Player $player) => $scoring->recalculatePlayerTrivia($player));
            }
        }

        if (Schema::hasTable('trivia_rounds')) {
            DB::table('trivia_rounds')->where('category', 'visa')->update([
                'title' => 'Quick Fire',
                'category' => 'general_knowledge',
                'intro_message' => 'Start strong with sharp instincts and fast general-knowledge answers.',
            ]);
            DB::table('trivia_rounds')->where('title', 'Visa Final Whistle')->update([
                'title' => 'Final Whistle',
                'intro_message' => 'Bring everything together in the high-energy final round.',
            ]);
        }

        if (Schema::hasTable('players') && Schema::hasColumn('players', 'has_visa_card')) {
            Schema::table('players', fn (Blueprint $table) => $table->dropColumn('has_visa_card'));
        }
    }

    public function down(): void
    {
        // Removed sponsor questions and answers are intentionally not restored.
        if (Schema::hasTable('players') && !Schema::hasColumn('players', 'has_visa_card')) {
            Schema::table('players', fn (Blueprint $table) => $table->boolean('has_visa_card')->default(false));
        }
    }
};
