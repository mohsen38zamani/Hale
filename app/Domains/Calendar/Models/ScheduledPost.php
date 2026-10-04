<?php

namespace App\Domains\Calendar\Models;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPost extends Model
{
    public const STATUSES = ['draft', 'scheduled', 'published'];

    protected $fillable = [
        'user_id',
        'campaign_id',
        'generation_id',
        'caption',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
