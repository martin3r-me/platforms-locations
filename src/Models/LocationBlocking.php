<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Sperrzeit einer Location (tagesgenau, z. B. "Renovierung Halle 3"). Gesperrte Tage gelten in Verfuegbarkeits-Checks und Auslastung als nicht buchbar.
 */
class LocationBlocking extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'locations_blockings';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'location_id',
        'start_date',
        'end_date',
        'reason',
    ];

    protected $casts = [
        'uuid' => 'string',
        'start_date' => 'date',
        'end_date' => 'date',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    /**
     * Sperren, die den Zeitraum [from, to] (inklusive Grenzen) ueberlappen.
     */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from);
    }

    /**
     * Deckt diese Sperre den gegebenen Tag (YYYY-MM-DD) ab?
     */
    public function coversDate(string $date): bool
    {
        return $this->start_date?->toDateString() <= $date
            && $this->end_date?->toDateString() >= $date;
    }
}
