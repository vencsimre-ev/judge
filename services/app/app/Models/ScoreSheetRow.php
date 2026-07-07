<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreSheetRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'score_sheet_id',
        'row_number',
        'start_time',
        'bib',
        'name',
        'country',
        'attempts_raw',
        'attempts_count',
        'zone_attempt',
        'top_attempt',
        'zone_column_value',
        'top_column_value',
        'confidence',
        'warnings',
    ];

    protected $casts = [
        'attempts_count' => 'integer',
        'zone_attempt' => 'integer',
        'top_attempt' => 'integer',
        'zone_column_value' => 'integer',
        'top_column_value' => 'integer',
        'confidence' => 'decimal:2',
        'warnings' => 'array',
    ];

    public function scoreSheet(): BelongsTo
    {
        return $this->belongsTo(ScoreSheet::class);
    }
}
