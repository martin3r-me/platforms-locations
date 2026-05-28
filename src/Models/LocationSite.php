<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasFileContext;
use Platform\Core\Traits\HasContextFileReferences;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Ein Standort/Site als optionaler Eltern-Container fuer Locations. Kann Adresse, GPS, Kontaktdaten, Beschreibung und Site-Bilder enthalten. Im Booklet erscheint die Site als Einleitungs-Seite vor den Eckdaten der jeweiligen Location.
 */
class LocationSite extends Model implements HasFileContext
{
    use SoftDeletes, LogsActivity, HasContextFileReferences;

    protected $table = 'locations_sites';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'name',
        'description',
        'street',
        'street_number',
        'postal_code',
        'city',
        'state',
        'country',
        'country_code',
        'latitude',
        'longitude',
        'timezone',
        'is_international',
        'phone',
        'email',
        'website',
        'notes',
        'done',
        'done_at',
        'sort_order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_international' => 'boolean',
        'done' => 'boolean',
        'done_at' => 'datetime',
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

    /**
     * Route-Model-Binding ueber UUID statt id — symmetrisch zu Location.
     * Aktuell laeuft die Route via {site} (kein Model-Name-Match), also
     * triggert Implicit-Binding heute nicht; das hier ist Defensive gegen
     * Umbenennungen oder eine spaetere Site-Klasse.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // ================= Relations =================

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class, 'site_id')->orderBy('sort_order')->orderBy('name');
    }

    // ================= HasFileContext =================

    public function getFileContextType(): string
    {
        return self::class;
    }

    public function getFileContextId(): int
    {
        return $this->id;
    }

    /**
     * Liefert alle Site-Bilder (Image-References, in Sort-Order). Wird im
     * Booklet als Einleitung verwendet.
     *
     * @return array<int, array{id:int, uuid:string, title:string, url:?string, thumbnail:?string}>
     */
    public function siteImageReferences(): array
    {
        try {
            return collect($this->getOrderedFileReferences())
                ->filter(fn ($ref) => $ref->contextFile?->isImage() ?? false)
                ->map(fn ($ref) => [
                    'id'        => $ref->id,
                    'uuid'      => $ref->uuid,
                    'title'     => $ref->title ?? $ref->contextFile?->original_name ?? '',
                    'url'       => $ref->url,
                    'thumbnail' => $ref->thumbnail_url,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    // ================= Scopes =================

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeInternational($query)
    {
        return $query->where('is_international', true);
    }

    public function scopeNational($query)
    {
        return $query->where('is_international', false);
    }

    public function scopeWithGps($query)
    {
        return $query->whereNotNull('latitude')->whereNotNull('longitude');
    }

    public function scopeDone($query)
    {
        return $query->where('done', true);
    }

    public function scopeNotDone($query)
    {
        return $query->where('done', false);
    }

    // ================= Computed =================

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            trim(($this->street ?? '') . ' ' . ($this->street_number ?? '')),
            trim(($this->postal_code ?? '') . ' ' . ($this->city ?? '')),
            $this->state,
            $this->country,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    public function getGpsCoordinatesAttribute(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return number_format($this->latitude, 6) . ', ' . number_format($this->longitude, 6);
    }
}
