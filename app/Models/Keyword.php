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
        'name',
        'volume',
        'intent',
        'status',
        'keyword_type',
        'content_type',
        'strategic_role',
        'strategic_opportunity',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'integer',
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Keyword::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Keyword::class, 'parent_id')->orderBy('position');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(KeywordCompetitor::class)->orderBy('rank');
    }

    public function getIsPillarAttribute(): bool
    {
        return $this->parent_id === null;
    }
}
