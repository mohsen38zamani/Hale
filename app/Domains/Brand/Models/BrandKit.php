<?php

namespace App\Domains\Brand\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_family',
        'tone',
        'tagline',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Non-null identity attributes that are worth injecting into prompts.
     *
     * @return array<string, string>
     */
    public function promptIdentity(): array
    {
        return array_filter([
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'tone' => $this->tone,
            'tagline' => $this->tagline,
        ], fn ($value): bool => filled($value));
    }
}
