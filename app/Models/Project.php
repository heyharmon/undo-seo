<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    protected $fillable = [
        'name',
        'user_id',
    ];

    /**
     * The user who owns this project.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All keywords in this project.
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    /**
     * Get only cluster parents (top-level keywords, excluding seed).
     */
    public function clusters(): HasMany
    {
        return $this->hasMany(Keyword::class)
            ->whereNull('parent_id')
            ->where('is_seed', false);
    }

    /**
     * Get the seed keyword for this project.
     */
    public function seedKeyword(): HasOne
    {
        return $this->hasOne(Keyword::class)->where('is_seed', true);
    }
}
