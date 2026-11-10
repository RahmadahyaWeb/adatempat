<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $icon
 * @property bool $is_active
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class MasterSportType extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'master_sport_types';

    /**
     * Default model attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'icon',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
