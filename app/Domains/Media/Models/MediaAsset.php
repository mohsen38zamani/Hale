<?php

namespace App\Domains\Media\Models;

use App\Domains\Products\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaAsset extends Model
{
    use HasFactory;

    protected $fillable = ['disk', 'path', 'thumbnail_path', 'mime', 'size', 'width', 'height'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_assets')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
