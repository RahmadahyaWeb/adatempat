<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateField extends Model
{
    use HasFactory;

    public const TYPE_TEXT = 'text';
    public const TYPE_TEXTAREA = 'textarea';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_SELECT = 'select';
    public const TYPE_MULTISELECT = 'multiselect';

    protected $fillable = [
        'venue_template_id',
        'field_key',
        'label',
        'input_type',
        'unit_label',
        'options_json',
        'validation_rules_json',
        'is_filterable',
        'is_required',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options_json' => 'array',
            'validation_rules_json' => 'array',
            'is_filterable' => 'boolean',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function venueTemplate(): BelongsTo
    {
        return $this->belongsTo(VenueTemplate::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(VenueFieldValue::class);
    }
}
