<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnerCustomField extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_HIDDEN_BY_ADMIN = 'hidden_by_admin';

    protected $fillable = [
        'venue_id',
        'field_key',
        'label',
        'input_type',
        'options_json',
        'moderation_status',
        'moderation_reason',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(VenueFieldValue::class, 'owner_custom_field_id');
    }
}
