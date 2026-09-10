<?php

namespace App\Domains\Generations\Models;

use App\Domains\Creative\Models\CreativeProject;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Generation extends Model
{
    protected $fillable = ['type', 'status', 'provider', 'model', 'prompt_hash', 'credits_reserved', 'credits_charged', 'cost_usd', 'processing_time_ms', 'output_media_id', 'error_message', 'feedback', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'cost_usd' => 'decimal:6'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creativeProject(): BelongsTo
    {
        return $this->belongsTo(CreativeProject::class);
    }

    public function outputMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'output_media_id');
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(GenerationJob::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(UsageLog::class);
    }
}
