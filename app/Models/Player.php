<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Player extends Model
{
    protected $hidden = ['session_token_hash', 'login_pin_hash'];

    protected $fillable = [
        'phone', 'nickname', 'email', 'consent', 'has_visa_card', 'is_simulated', 'login_pin_hash',
        'trivia_score', 'trivia_streak', 'trivia_correct_count',
        'trivia_double_correct', 'prediction_score',
    ];

    protected $casts = [
        'consent' => 'boolean',
        'has_visa_card' => 'boolean',
        'is_simulated' => 'boolean',
        'show_phone_on_screen' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function prediction(): HasOne
    {
        return $this->hasOne(Prediction::class);
    }

    /** Normalise phone: strip spaces, leading zeros → +254 */
    public static function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        }
        return $phone;
    }

    public function issueSessionToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->forceFill(['session_token_hash' => hash('sha256', $token)])->save();
        return $token;
    }
}
