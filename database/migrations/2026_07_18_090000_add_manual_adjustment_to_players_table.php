<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Manual score adjustments were written straight into `trivia_score`, which
     * `ScoringService::recalculatePlayerTrivia()` rebuilds from answers — so any
     * MC adjustment was silently wiped the next time the player's answers changed.
     * This dedicated column survives recalculation and is added on top of the
     * answer-derived base.
     */
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->integer('trivia_manual_adjustment')->default(0)->after('trivia_double_correct');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('trivia_manual_adjustment');
        });
    }
};
