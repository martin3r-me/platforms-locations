<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Mietpreis einer Location pro Tag-Typ (z. B. "Veranstaltungstag" 2.250 EUR). day_type_label matcht den Volltext aus events_settings (kein Slug).
 */
class LocationPricing extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'locations_pricings';

    protected $fillable = [
        'uuid',
        'location_id',
        'day_type_label',
        'price_net',
        'label',
        'article_number',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'price_net' => 'decimal:2',
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

    /**
     * Anzeige-Label fuer das Pricing (Default: "Miete <day_type_label>", wenn nicht ueberschrieben).
     */
    public function displayLabel(): string
    {
        return $this->label !== null && $this->label !== ''
            ? $this->label
            : 'Miete ' . $this->day_type_label;
    }
}
