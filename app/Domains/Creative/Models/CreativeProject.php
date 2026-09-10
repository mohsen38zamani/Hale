<?php

namespace App\Domains\Creative\Models;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Generations\Models\Generation;
use App\Domains\Products\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreativeProject extends Model
{
    protected $fillable = ['product_id', 'goal', 'style', 'format', 'environment', 'video_duration_seconds', 'settings', 'brief', 'prompt'];

    protected function casts(): array
    {
        return ['goal' => CreativeGoal::class, 'style' => CreativeStyle::class, 'format' => CreativeFormat::class, 'settings' => 'array', 'brief' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }
}
