<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('category', 60)->default('general_knowledge')->change();
        });

        Schema::table('trivia_rounds', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->change();
        });
    }

    public function down(): void
    {
        // MySQL cannot restore the original enum while custom values exist.
        DB::table('questions')
            ->whereNotIn('category', ['general_knowledge', 'fifa_world_cup'])
            ->update(['category' => 'general_knowledge']);
        DB::table('trivia_rounds')
            ->whereNotNull('category')
            ->whereNotIn('category', ['general_knowledge', 'fifa_world_cup'])
            ->update(['category' => null]);

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('category', ['general_knowledge', 'fifa_world_cup'])
                ->default('general_knowledge')->change();
        });

        Schema::table('trivia_rounds', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->change();
        });
    }
};
