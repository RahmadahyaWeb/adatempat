<?php

namespace App\Models;

use App\Enums\AddOnPricingType;
use Database\Factories\SpaceAddOnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $space_id
 * @property string $name
 * @property string $price
 * @property AddOnPricingType $pricing_type
 * @property int $stock_quantity
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['space_id', 'name', 'price', 'pricing_type', 'stock_quantity', 'is_active'])]
class SpaceAddOn extends Model
{
    /** @use HasFactory<SpaceAddOnFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'pricing_type' => AddOnPricingType::class,
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Space, $this>
     */
    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
