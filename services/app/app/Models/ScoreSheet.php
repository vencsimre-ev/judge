<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoreSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category',
        'route',
        'judge_name',
        'image_path',
        'raw_ai_json',
        'status',
    ];

    protected $casts = [
        'raw_ai_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ScoreSheetRow::class)->orderBy('row_number');
    }
}
