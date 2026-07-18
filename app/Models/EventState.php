<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EventState extends Model
{
    protected $table = 'event_state';

    protected $fillable = [
        'phase', 'current_question_id', 'rounds_enabled', 'current_round_id', 'show_phone_on_screen',
    ];

    protected $casts = [
        'show_phone_on_screen' => 'boolean',
        'rounds_enabled' => 'boolean',
    ];

    /** Always update row id=1 (single-row table). */
    public static function setCurrent(array $attributes): self
    {
        $state = self::firstOrCreate(['id' => 1], [
            'phase' => 'lobby',
            'current_question_id' => null,
            'rounds_enabled' => false,
            'current_round_id' => null,
            'show_phone_on_screen' => false,
        ]);
        $state->update($attributes);
        Cache::forget('public-event-state-v3');
        return $state->fresh();
    }

    public static function current(): self
    {
        $state = self::firstOrCreate(['id' => 1], [
            'phase' => 'lobby',
            'current_question_id' => null,
            'rounds_enabled' => false,
            'current_round_id' => null,
            'show_phone_on_screen' => false,
        ]);

        if ($state->show_phone_on_screen === null) {
            $state->update(['show_phone_on_screen' => false]);
        }

        return $state->fresh();
    }
}
