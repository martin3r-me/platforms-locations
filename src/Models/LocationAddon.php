<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Optionaler Zusatzposten an einer Location (z. B. "Heizung 450 EUR pro Tag"). Einheit (unit) steuert die Default-Menge beim Einbuchen in QuotePositions.
 */
class LocationAddon extends Model
{
    use SoftDeletes;

    protected $table = 'locations_addons';

    public const UNIT_PRO_TAG = 'pro_tag';
    public const UNIT_PRO_VA_TAG = 'pro_va_tag';
    public const UNIT_EINMALIG = 'einmalig';
    public const UNIT_PRO_STUECK = 'pro_stueck';

    public const UNITS = [
        self::UNIT_PRO_TAG,
        self::UNIT_PRO_VA_TAG,
        self::UNIT_EINMALIG,
        self::UNIT_PRO_STUECK,
    ];

    public const UNIT_LABELS = [
        self::UNIT_PRO_TAG => 'pro Tag',
        self::UNIT_PRO_VA_TAG => 'pro VA-Tag',
        self::UNIT_EINMALIG => 'einmalig',
        self::UNIT_PRO_STUECK => 'pro Stueck',
    ];

    protected $fillable = [
        'uuid',
        'location_id',
        'label',
        'price_net',
        'unit',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'price_net' => 'decimal:2',
        'is_active' => 'boolean',
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

    public function unitLabel(): string
    {
        return self::UNIT_LABELS[$this->unit] ?? $this->unit;
    }
}
