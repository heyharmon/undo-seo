<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    protected $fillable = [
        'project_id',
        'parent_id',
        'keyword',
        'search_volume',
        'difficulty',
        'is_seed',
    ];

    protected $casts = [
        'is_seed' => 'boolean',
        'search_volume' => 'integer',
        'difficulty' => 'integer',
    ];

    /**
     * The project this keyword belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The parent keyword (for cluster children).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'parent_id');
    }

    /**
     * Child keywords in this cluster.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Keyword::class, 'parent_id');
    }

    /**
     * Check if this keyword is a cluster parent.
     */
    public function isClusterParent(): bool
    {
        return $this->parent_id === null && !$this->is_seed;
    }

    /**
     * Get the difficulty label based on score thresholds.
     */
    public function getDifficultyLabelAttribute(): ?string
    {
        if ($this->difficulty === null) return null;
        if ($this->difficulty < 30) return 'Easy';
        if ($this->difficulty < 60) return 'Doable';
        return 'Hard';
    }
}
