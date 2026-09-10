<?php

namespace App\Domains\Generations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    protected $fillable = ['provider', 'model', 'input_tokens', 'output_tokens', 'cost_usd', 'metadata'];

    protected function casts(): array
    {
        return ['cost_usd' => 'decimal:6', 'metadata' => 'array'];
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
