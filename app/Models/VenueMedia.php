<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenueMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'disk',
        'file_path',
        'mime_type',
        'file_size_bytes',
        'alt_text',
        'is_cover',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
