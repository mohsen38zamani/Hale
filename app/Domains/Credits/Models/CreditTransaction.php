<?php

namespace App\Domains\Credits\Models;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'credit_account_id',
        'user_id',
        'generation_id',
        'type',
        'delivery_path',
        'amount',
        'internal_cost_usd',
        'balance_after',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'internal_cost_usd' => 'float',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class, 'credit_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
