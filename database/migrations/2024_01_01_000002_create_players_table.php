<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();   // normalized, used as identity key
            $table->string('nickname', 50);
            $table->string('email', 100)->nullable();
            $table->boolean('consent')->default(false);
            $table->integer('trivia_score')->default(0);
            $table->integer('trivia_streak')->default(0);        // current consecutive correct
            $table->integer('trivia_correct_count')->default(0); // total correct, for tie-break
            $table->integer('trivia_double_correct')->default(0); // for tie-break #4
            $table->integer('prediction_score')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
