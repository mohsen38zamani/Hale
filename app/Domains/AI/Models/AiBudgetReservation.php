<?php

namespace App\Domains\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiBudgetReservation extends Model
{
    protected $fillable = ['generation_id', 'ai_daily_budget_id', 'estimated_usd', 'settled_at'];

    protected function casts(): array
    {
        return ['estimated_usd' => 'decimal:6', 'settled_at' => 'datetime'];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(AiDailyBudget::class, 'ai_daily_budget_id');
    }
}
