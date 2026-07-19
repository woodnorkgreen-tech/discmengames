<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trivia_categories', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('name', 60)->unique();
            $table->timestamps();
        });

        $defaults = [
            'general_knowledge' => 'General Knowledge',
            'fifa_world_cup' => 'Football / FIFA World Cup',
        ];
        $keys = collect(array_keys($defaults));
        if (Schema::hasTable('questions')) {
            $keys = $keys->merge(DB::table('questions')->whereNotNull('category')->distinct()->pluck('category'));
        }
        if (Schema::hasTable('trivia_rounds')) {
            $keys = $keys->merge(DB::table('trivia_rounds')->whereNotNull('category')->distinct()->pluck('category'));
        }

        foreach ($keys->filter()->unique() as $key) {
            $key = (string) $key;
            $baseName = $defaults[$key] ?? Str::headline(str_replace(['_', '-'], ' ', $key));
            $name = Str::limit($baseName, 60, '');
            $suffix = 2;
            while (DB::table('trivia_categories')->where('name', $name)->exists()) {
                $ending = " {$suffix}";
                $name = Str::limit($baseName, 60 - strlen($ending), '').$ending;
                $suffix++;
            }
            DB::table('trivia_categories')->insert([
                'key' => $key,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trivia_categories');
    }
};
