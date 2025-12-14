<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordCompetitor extends Model
{
    protected $fillable = [
        'keyword_id',
        'name',
        'url',
        'rank',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
        ];
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
