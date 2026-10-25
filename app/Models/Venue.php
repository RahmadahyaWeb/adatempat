<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Venue extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_ARCHIVED = 'archived';

    protected static function booted(): void
    {
        static::creating(function (Venue $venue): void {
            if (! $venue->public_id) {
                $venue->public_id = (string) Str::ulid();
            }
        });
    }

    protected $fillable = [
        'business_id',
        'category_id',
        'name',
        'slug',
        'description',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName()
    {
        return 'public_id';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('venues.status', self::STATUS_PUBLISHED)
            ->whereNotNull('venues.published_at')
            ->whereHas('business', function (Builder $businessQuery) {
                $businessQuery
                    ->where('status', Business::STATUS_ACTIVE)
                    ->whereNotNull('published_at');
            })
            ->whereHas('category', function (Builder $categoryQuery) {
                $categoryQuery->where('status', Category::STATUS_ACTIVE);
            });
    }
}
