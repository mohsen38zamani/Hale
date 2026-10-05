<?php

namespace App\Domains\Editing\Models;

use App\Domains\Generations\Models\Generation;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageEdit extends Model
{
    public const OPERATIONS = ['remove_bg', 'upscale', 'expand', 'shadow'];

    public const SOURCE_TYPES = ['generation', 'product'];

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'operation',
        'options',
        'status',
        'error_message',
        'provider',
        'model',
        'cost_usd',
        'processing_time_ms',
        'output_media_id',
        'credits_spent',
        'metadata',
        'processing_lease_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'metadata' => 'array',
            'cost_usd' => 'decimal:6',
            'processing_lease_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outputMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'output_media_id');
    }

    /**
     * The generation this edit derives from, when the source is a generation.
     * Used by the UI to link back to the original output.
     */
    public function sourceGeneration(): BelongsTo
    {
        return $this->belongsTo(Generation::class, 'source_id');
    }
}
