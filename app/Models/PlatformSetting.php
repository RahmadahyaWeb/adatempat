<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSetting extends Model
{
    protected $fillable = [
        'setting_key',
        'setting_value_json',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'setting_value_json' => 'array',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('setting_key', $key)->first();

        return $setting?->setting_value_json ?? $default;
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
