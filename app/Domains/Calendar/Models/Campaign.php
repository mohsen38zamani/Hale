<?php

namespace App\Domains\Calendar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $fillable = ['user_id', 'name', 'starts_at', 'interval_days'];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'interval_days' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class, 'campaign_id');
    }
}
