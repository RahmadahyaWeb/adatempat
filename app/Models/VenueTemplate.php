<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(TemplateField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }
}
