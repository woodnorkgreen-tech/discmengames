<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trivia_rounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('position');
            $table->string('title', 80);
            $table->string('category', 40)->nullable();
            $table->string('intro_message', 180)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->unique('position');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('trivia_round_id')->nullable()->after('order_index')
                ->constrained('trivia_rounds')->nullOnDelete();
            $table->unsignedSmallInteger('round_position')->nullable()->after('trivia_round_id');
            $table->index(['trivia_round_id', 'round_position']);
        });

        Schema::table('event_state', function (Blueprint $table) {
            $table->boolean('rounds_enabled')->default(false)->after('current_question_id');
            $table->unsignedBigInteger('current_round_id')->nullable()->after('rounds_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('event_state', function (Blueprint $table) {
            $table->dropColumn(['rounds_enabled', 'current_round_id']);
        });
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['trivia_round_id', 'round_position']);
            $table->dropConstrainedForeignId('trivia_round_id');
            $table->dropColumn('round_position');
        });
        Schema::dropIfExists('trivia_rounds');
    }
};
