<?php

namespace App\Domains\Templates\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GenerationTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'settings',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'settings' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
