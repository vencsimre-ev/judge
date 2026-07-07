<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar',
    ];

    protected $hidden = [
        'remember_token',
    ];

    public function scoreSheets(): HasMany
    {
        return $this->hasMany(ScoreSheet::class);
    }
}
