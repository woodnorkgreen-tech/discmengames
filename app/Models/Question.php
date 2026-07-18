<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $fillable = [
        'order_index', 'trivia_round_id', 'round_position', 'category', 'type', 'text', 'options',
        'correct_answer', 'duration_seconds',
        'is_double_points', 'status', 'activated_at',
    ];

    protected $casts = [
        'options' => 'array',
        'is_double_points' => 'boolean',
        'activated_at' => 'datetime',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function triviaRound(): BelongsTo
    {
        return $this->belongsTo(TriviaRound::class);
    }

    public function secondsRemaining(): int
    {
        if (!$this->activated_at || $this->status !== 'live') {
            return $this->duration_seconds;
        }
        // Use raw timestamps and clamp negative elapsed to zero so a backward
        // clock correction (NTP) cannot hand out extra time via a sign flip.
        $elapsed = max(0, now()->getTimestamp() - $this->activated_at->getTimestamp());
        return (int) max(0, $this->duration_seconds - $elapsed);
    }
}
