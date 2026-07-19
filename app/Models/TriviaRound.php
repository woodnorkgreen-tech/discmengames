<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriviaRound extends Model
{
    protected $fillable = [
        'position', 'title', 'category', 'intro_message', 'status',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('round_position')->orderBy('id');
    }

    public static function createRecommended(): void
    {
        if (self::exists()) return;

        $rounds = [
            [1, 'Quick Fire', 'general_knowledge', 'Start strong with sharp instincts and fast general-knowledge answers.'],
            [2, 'Football IQ', 'fifa_world_cup', 'Read the game, trust your football instincts, and climb.'],
            [3, 'Final Whistle', null, 'Bring everything together in the high-energy final round.'],
        ];

        foreach ($rounds as [$position, $title, $category, $intro]) {
            self::create([
                'position' => $position,
                'title' => $title,
                'category' => $category,
                'intro_message' => $intro,
                'status' => 'draft',
            ]);
        }
    }
}
