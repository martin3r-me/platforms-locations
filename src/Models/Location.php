<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Location/Raum als Stammdatensatz. Enthält Kapazitäten, Gruppe, Adresse und Kürzel und dient als buchbare Einheit.
 */
class Location extends Model
{
    use SoftDeletes;

    protected $table = 'locations_locations';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'name',
        'kuerzel',
        'gruppe',
        'pax_min',
        'pax_max',
        'mehrfachbelegung',
        'adresse',
        'latitude',
        'longitude',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'pax_min' => 'integer',
        'pax_max' => 'integer',
        'mehrfachbelegung' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'sort_order' => 'integer',
    ];

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());

                $model->uuid = $uuid;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}
