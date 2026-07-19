<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    protected $hidden = ['session_token_hash', 'login_pin_hash'];

    protected $fillable = [
        'phone', 'nickname', 'email', 'consent', 'is_simulated', 'login_pin_hash',
        'trivia_score', 'trivia_streak', 'trivia_correct_count',
        'trivia_double_correct', 'trivia_manual_adjustment', 'prediction_score',
    ];

    protected $casts = [
        'consent' => 'boolean',
        'is_simulated' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class);
    }

    public function issueSessionToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->forceFill(['session_token_hash' => hash('sha256', $token)])->save();
        return $token;
    }
}
