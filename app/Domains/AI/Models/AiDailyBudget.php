<?php

namespace App\Domains\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiDailyBudget extends Model
{
    protected $fillable = ['budget_date', 'reserved_usd', 'spent_usd'];

    protected function casts(): array
    {
        return ['budget_date' => 'date', 'reserved_usd' => 'decimal:6', 'spent_usd' => 'decimal:6'];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(AiBudgetReservation::class);
    }
}
