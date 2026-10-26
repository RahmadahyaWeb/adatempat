<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'template_field_id',
        'value_text',
        'value_number',
        'value_boolean',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:2',
            'value_boolean' => 'boolean',
            'value_json' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function templateField(): BelongsTo
    {
        return $this->belongsTo(TemplateField::class);
    }
}
