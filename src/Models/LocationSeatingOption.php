<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Bestuhlungsoption an einer Location als ca.-Schaetzwert (z. B. "Reihenbestuhlung bis 270 PAX"). Reine Information, keine harte Kapazitaets-Validierung.
 */
class LocationSeatingOption extends Model
{
    use SoftDeletes;

    protected $table = 'locations_seating_options';

    protected $fillable = [
        'uuid',
        'location_id',
        'label',
        'pax_max_ca',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'pax_max_ca' => 'integer',
        'sort_order' => 'integer',
    ];

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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
