<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriviaCategory extends Model
{
    protected $fillable = ['key', 'name'];

    protected $appends = ['is_system'];

    public function getIsSystemAttribute(): bool
    {
        return in_array($this->key, ['general_knowledge', 'fifa_world_cup'], true);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'category', 'key');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(TriviaRound::class, 'category', 'key');
    }
}
