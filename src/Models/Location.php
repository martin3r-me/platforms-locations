<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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
        'groesse_qm',
        'hallennummer',
        'barrierefrei',
        'besonderheit',
        'anlaesse',
    ];

    protected $casts = [
        'uuid' => 'string',
        'pax_min' => 'integer',
        'pax_max' => 'integer',
        'mehrfachbelegung' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'sort_order' => 'integer',
        'groesse_qm' => 'decimal:2',
        'barrierefrei' => 'boolean',
        'anlaesse' => 'array',
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

    // ================= Grundriss (public API fuer andere Module) =================

    /**
     * Erlaubte Grundriss-Dateiendungen.
     */
    public const FLOOR_PLAN_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    /**
     * Name der Storage-Disk, auf der Grundrisse abgelegt werden.
     * S3 wenn Bucket konfiguriert ist, sonst Default-Disk.
     */
    public function floorPlanDisk(): string
    {
        if (config('filesystems.disks.s3.bucket')) {
            return 's3';
        }
        return (string) config('filesystems.default', 'public');
    }

    /**
     * Disk-relatives Verzeichnis, in dem der Grundriss dieser Location liegt.
     */
    public function floorPlanDirectory(): string
    {
        return "locations/grundrisse/{$this->uuid}";
    }

    /**
     * Disk-relativer Pfad der aktuell hinterlegten Grundriss-Datei (oder null).
     * Scannt das Verzeichnis und gibt die erste gefundene Datei zurueck.
     */
    public function floorPlanPath(): ?string
    {
        try {
            $disk = Storage::disk($this->floorPlanDisk());
            $files = $disk->files($this->floorPlanDirectory());
            return $files[0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function hasFloorPlan(): bool
    {
        return $this->floorPlanPath() !== null;
    }

    public function floorPlanFileName(): ?string
    {
        $path = $this->floorPlanPath();
        return $path ? basename($path) : null;
    }

    public function floorPlanExtension(): ?string
    {
        $name = $this->floorPlanFileName();
        if (!$name) {
            return null;
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        return $ext !== '' ? strtolower($ext) : null;
    }

    /**
     * Ist der Grundriss ein (in HTML/PDF einbettbares) Bild?
     */
    public function floorPlanIsImage(): bool
    {
        $ext = $this->floorPlanExtension();
        return $ext !== null && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true);
    }

    public function floorPlanIsPdf(): bool
    {
        return $this->floorPlanExtension() === 'pdf';
    }

    /**
     * Liefert eine (ggf. temporaere) URL zum Grundriss.
     * - Disk unterstuetzt temporaryUrls (z. B. S3): presigned URL
     * - sonst: public URL via Storage::url
     */
    public function floorPlanUrl(int $minutes = 60): ?string
    {
        $path = $this->floorPlanPath();
        if (!$path) {
            return null;
        }

        try {
            $disk = Storage::disk($this->floorPlanDisk());
            if ($disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($path, now()->addMinutes($minutes));
            }
            return $disk->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Roh-Inhalt des Grundrisses (z. B. fuer Einbettung als base64 im PDF).
     */
    public function floorPlanContents(): ?string
    {
        $path = $this->floorPlanPath();
        if (!$path) {
            return null;
        }
        try {
            $disk = Storage::disk($this->floorPlanDisk());
            $raw = $disk->get($path);
            return is_string($raw) ? $raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * MIME-Typ des Grundrisses (aus Endung abgeleitet).
     */
    public function floorPlanMimeType(): ?string
    {
        return match ($this->floorPlanExtension()) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }

    // ================= Pricing / Bestuhlung / Add-ons (Public API) =================

    public function pricings(): HasMany
    {
        return $this->hasMany(LocationPricing::class)->orderBy('sort_order')->orderBy('day_type_label');
    }

    public function seatingOptions(): HasMany
    {
        return $this->hasMany(LocationSeatingOption::class)->orderBy('sort_order')->orderBy('label');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(LocationAddon::class)->orderBy('sort_order')->orderBy('label');
    }

    /**
     * Liefert das (erste) Pricing zu einem Tag-Typ-Volltext (z. B. "Veranstaltungstag")
     * oder null, wenn fuer diese Location kein Eintrag gepflegt ist.
     */
    public function pricingForDayType(string $dayTypeLabel): ?LocationPricing
    {
        return $this->pricings()
            ->where('day_type_label', $dayTypeLabel)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Flache Liste aller Pricings als Array (fuer Picker / API-Returns).
     *
     * @return array<int, array{id:int, uuid:string, day_type_label:string, price_net:string, label:?string, display_label:string}>
     */
    public function pricingTable(): array
    {
        return $this->pricings()->get()->map(fn (LocationPricing $p) => [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'day_type_label' => $p->day_type_label,
            'price_net' => (string) $p->price_net,
            'label' => $p->label,
            'display_label' => $p->displayLabel(),
        ])->all();
    }

    /**
     * Aktive Add-ons (is_active=true) in Sort-Reihenfolge.
     */
    public function activeAddons(): Collection
    {
        return $this->addons()->where('is_active', true)->get();
    }

    // ================= Asset-Kategorien (S3, ohne DB) =================
    //
    // Buffetstationen, Bestuhlungsplaene, Fotos mit / ohne Bestuhlung.
    // Gemeinsame Implementierung in LocationAssetService — Pfadschema:
    //   locations/{uuid}/{category-slug}/{token}.{ext}
    //
    // Der Grundriss laeuft weiterhin separat ueber floorPlan*() oben
    // (eigener historischer Pfad locations/grundrisse/{uuid}/grundriss.{ext}),
    // damit bestehende Konsumenten (Events-Angebot/PdfFloorPlanMerger)
    // unveraendert weiterlaufen.

    /**
     * Liefert die Dateien einer Asset-Kategorie als Collection von Arrays
     * mit path/filename/size/mime/url/extension/is_image/is_pdf.
     */
    public function assetFiles(string $category): Collection
    {
        return app(\Platform\Locations\Services\LocationAssetService::class)
            ->listFiles($this, $category);
    }

    public function buffetFiles(): Collection
    {
        return $this->assetFiles(\Platform\Locations\Services\LocationAssetService::CATEGORY_BUFFET);
    }

    public function seatingPlanFiles(): Collection
    {
        return $this->assetFiles(\Platform\Locations\Services\LocationAssetService::CATEGORY_SEATING_PLANS);
    }

    public function photosWithSeating(): Collection
    {
        return $this->assetFiles(\Platform\Locations\Services\LocationAssetService::CATEGORY_PHOTOS_WITH_SEATS);
    }

    public function photosEmpty(): Collection
    {
        return $this->assetFiles(\Platform\Locations\Services\LocationAssetService::CATEGORY_PHOTOS_EMPTY);
    }

    /**
     * Statisch: alle bekannten Asset-Kategorien (Slug => Konfiguration).
     *
     * @return array<string, array{label:string, slug:string, multi:bool, extensions:array<int,string>, max_kb:int}>
     */
    public static function assetCategories(): array
    {
        return \Platform\Locations\Services\LocationAssetService::categories();
    }
}
