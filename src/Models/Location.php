<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Platform\ActivityLog\Traits\LogsActivity;
use Platform\Core\Contracts\HasFileContext;
use Platform\Core\Traits\HasContextFileReferences;
use Symfony\Component\Uid\UuidV7;

/**
 * @ai.description Eine Location/Raum als Stammdatensatz. Enthält Kapazitäten, Gruppe, Adresse und Kürzel und dient als buchbare Einheit.
 */
class Location extends Model implements HasFileContext
{
    use SoftDeletes, LogsActivity, HasContextFileReferences;

    protected $table = 'locations_locations';

    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
        'site_id',
        'name',
        'kuerzel',
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
        'beschreibung',
        'anlaesse',
        'booklet_share_token',
        'booklet_share_expires_at',
        'booklet_options',
    ];

    protected $casts = [
        'uuid' => 'string',
        'site_id' => 'integer',
        'pax_min' => 'integer',
        'pax_max' => 'integer',
        'mehrfachbelegung' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'sort_order' => 'integer',
        'groesse_qm' => 'decimal:2',
        'barrierefrei' => 'boolean',
        'anlaesse' => 'array',
        'booklet_share_expires_at' => 'datetime',
        'booklet_options' => 'array',
    ];

    protected $hidden = [
        'booklet_share_token',
    ];

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Route-Model-Binding laeuft ueber die UUID-Spalte, nicht ueber `id`.
     * Ohne diesen Override castet PHP eine URL-UUID wie
     * `019da04e-620d-7c08-a446-61d8d5f315d4` zu (int) 19 und Laravel laed
     * stillschweigend die falsche Location (id=19) — der klassische
     * "ich editiere ploetzlich einen anderen Datensatz"-Bug.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
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

    /**
     * Normalisiert ein Kuerzel: TRIM + UPPER, max 20 Zeichen.
     * Damit sind "ksh", " KSH ", "Ksh" identisch zu "KSH" — passend zum
     * UNIQUE INDEX (team_id, kuerzel).
     */
    public static function normalizeKuerzel(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return mb_substr(mb_strtoupper(trim($value)), 0, 20);
    }

    public function setKuerzelAttribute(?string $value): void
    {
        $this->attributes['kuerzel'] = self::normalizeKuerzel($value);
    }

    /**
     * Loest ein Kuerzel innerhalb eines Teams zu einer Location auf.
     * Kuerzel ist nur per Team eindeutig — team_id ist daher Pflicht.
     * Liefert null, wenn keine aktive Location zum Kuerzel existiert.
     */
    public static function resolveByKuerzel(string $kuerzel, int $teamId): ?self
    {
        $normalized = self::normalizeKuerzel($kuerzel);
        if ($normalized === null || $normalized === '') {
            return null;
        }
        return self::query()
            ->where('team_id', $teamId)
            ->where('kuerzel', $normalized)
            ->first();
    }

    /**
     * Liefert die Liste aller aktiven Kuerzel eines Teams, alphabetisch sortiert.
     * Praktisch fuer LOCATION_NOT_FOUND-Fehlermeldungen, damit der Caller direkt
     * sieht, welche Kuerzel tatsaechlich existieren.
     *
     * @return array<int,string>
     */
    public static function knownKuerzel(int $teamId): array
    {
        return self::query()
            ->where('team_id', $teamId)
            ->whereNotNull('kuerzel')
            ->orderBy('kuerzel')
            ->pluck('kuerzel')
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Generischer Resolver. Akzeptiert int, numerischen String, UUID-String
     * oder Kuerzel-String und liefert ein ResolveResult mit:
     *   - location:    ?self
     *   - matched_by:  'id' | 'uuid' | 'kuerzel' | null
     *   - normalized:  Wert nach Normalisierung (z.B. uppercased Kuerzel)
     *
     * Reihenfolge: numerisch -> ID, UUID-Format -> uuid, sonst -> kuerzel.
     * team_id ist nur fuer Kuerzel-Aufloesung Pflicht; bei id/uuid ist sie
     * optional und wird nur genutzt, um falsche Tenant-Treffer auszuschliessen.
     *
     * @return array{location: ?self, matched_by: ?string, normalized: ?string}
     */
    public static function resolveRef(int|string $ref, ?int $teamId = null): array
    {
        if (is_int($ref) || (is_string($ref) && preg_match('/^\d+$/', $ref))) {
            $id = (int) $ref;
            $q = self::query()->where('id', $id);
            if ($teamId !== null) {
                $q->where('team_id', $teamId);
            }
            return ['location' => $q->first(), 'matched_by' => 'id', 'normalized' => (string) $id];
        }

        $ref = (string) $ref;
        // UUID v4-ish or v7 — 8-4-4-4-12 hex pattern
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $ref)) {
            $q = self::query()->where('uuid', $ref);
            if ($teamId !== null) {
                $q->where('team_id', $teamId);
            }
            return ['location' => $q->first(), 'matched_by' => 'uuid', 'normalized' => $ref];
        }

        if ($teamId === null) {
            return ['location' => null, 'matched_by' => null, 'normalized' => null];
        }

        $location = self::resolveByKuerzel($ref, $teamId);
        return [
            'location'   => $location,
            'matched_by' => $location ? 'kuerzel' : null,
            'normalized' => self::normalizeKuerzel($ref),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(LocationSite::class, 'site_id');
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
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
     * Memo fuer floorPlanPath() — das Directory-Listing (S3-API-Call) soll
     * pro Model-Instanz nur einmal laufen, auch wenn floorPlanIsImage(),
     * floorPlanUrl() etc. es mehrfach brauchen.
     *
     * @var array{path: ?string}|null
     */
    private ?array $floorPlanPathMemo = null;

    /**
     * Disk-relativer Pfad der aktuell hinterlegten Grundriss-Datei (oder null).
     * Scannt das Verzeichnis und gibt die erste gefundene Datei zurueck.
     */
    public function floorPlanPath(): ?string
    {
        if ($this->floorPlanPathMemo !== null) {
            return $this->floorPlanPathMemo['path'];
        }

        try {
            $disk = Storage::disk($this->floorPlanDisk());
            $files = $disk->files($this->floorPlanDirectory());
            $path = $files[0] ?? null;
        } catch (\Throwable $e) {
            $path = null;
        }

        $this->floorPlanPathMemo = ['path' => $path];

        return $path;
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
     * Sperrzeiten (tagesgenau) — gelten in Verfuegbarkeits-Checks als belegt.
     */
    public function blockings(): HasMany
    {
        return $this->hasMany(LocationBlocking::class)->orderBy('start_date');
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
            'article_number' => $p->article_number,
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

    // ================= Kunden-Booklet (Public Share) =================

    /**
     * Default-Gueltigkeitsdauer eines neu erzeugten Share-Tokens in Tagen.
     */
    public const BOOKLET_SHARE_DEFAULT_DAYS = 30;

    /**
     * Erzeugt einen neuen Share-Token fuer das Kunden-Booklet und speichert
     * Ablaufdatum. Ueberschreibt einen ggf. bestehenden Token (der alte Link
     * wird damit ungueltig).
     */
    public function generateBookletShareToken(?int $validDays = null): string
    {
        $days = $validDays ?? self::BOOKLET_SHARE_DEFAULT_DAYS;
        do {
            $token = \Illuminate\Support\Str::random(40);
        } while (self::query()->where('booklet_share_token', $token)->exists());

        $this->forceFill([
            'booklet_share_token' => $token,
            'booklet_share_expires_at' => now()->addDays($days),
        ])->save();

        return $token;
    }

    /**
     * Setzt das Ablaufdatum auf einen neuen Wert (z. B. UI-Inline-Edit) ohne
     * den Token zu rotieren.
     */
    public function extendBookletShare(\DateTimeInterface $newExpiry): void
    {
        $this->forceFill(['booklet_share_expires_at' => $newExpiry])->save();
    }

    /**
     * Loescht Token + Expiry und macht damit den oeffentlichen Link tot.
     */
    public function revokeBookletShare(): void
    {
        $this->forceFill([
            'booklet_share_token' => null,
            'booklet_share_expires_at' => null,
        ])->save();
    }

    public function hasBookletShare(): bool
    {
        return !empty($this->booklet_share_token);
    }

    public function bookletShareIsExpired(): bool
    {
        if (!$this->hasBookletShare()) {
            return true;
        }
        return $this->booklet_share_expires_at !== null
            && $this->booklet_share_expires_at->isPast();
    }

    public function bookletShareIsActive(): bool
    {
        return $this->hasBookletShare() && !$this->bookletShareIsExpired();
    }

    /**
     * Liefert die Public-URL des Booklets als HTML-View (zum Teilen mit
     * Kunden). Erfordert einen aktiven Token.
     */
    public function bookletPublicUrl(): ?string
    {
        if (!$this->hasBookletShare()) {
            return null;
        }
        return route('locations.booklet.public.show', ['token' => $this->booklet_share_token]);
    }

    public function bookletPublicPdfUrl(): ?string
    {
        if (!$this->hasBookletShare()) {
            return null;
        }
        return route('locations.booklet.public.pdf', ['token' => $this->booklet_share_token]);
    }

    // ================= Booklet-Optionen (pro Location konfigurierbar) =================

    /**
     * Alle Booklet-Optionen + ihre Defaults.
     *
     * Name / Kuerzel / PAX / Flaeche sind bewusst NICHT optional — sie
     * gehoeren in jedes Booklet. Mietpreise und Add-ons sind default
     * `false`, damit der Default-Output kundentauglich bleibt; alle
     * anderen Sektionen sind default `true`.
     *
     * @var array<string, bool>
     */
    public const BOOKLET_OPTION_DEFAULTS = [
        // Eckdaten-Felder (Gruppe ist obsolet — die Site uebernimmt diese Rolle)
        'show_hallennummer'   => true,
        'show_mehrfachbelegung' => true,
        'show_barrierefrei'   => true,
        // Inhalts-Sektionen
        'show_site'           => true,
        'show_beschreibung'   => true,
        'show_photos'         => true,
        'show_grundriss'      => true,
        'show_bestuhlungen'   => true,
        'show_anlaesse'       => true,
        'show_adresse'        => true,
        // Preis-Sektionen (default aus -> kundentaugliches Booklet)
        'show_mietpreise'     => false,
        'show_addons'         => false,
    ];

    /**
     * Liefert die effektiven Booklet-Optionen: Defaults gemerged mit dem,
     * was in der DB-Spalte `booklet_options` steht. Unbekannte Keys werden
     * verworfen, fehlende Keys auf Default gesetzt.
     *
     * @return array<string, bool>
     */
    public function bookletOptions(): array
    {
        $current = is_array($this->booklet_options) ? $this->booklet_options : [];
        $merged = [];
        foreach (self::BOOKLET_OPTION_DEFAULTS as $key => $default) {
            $merged[$key] = array_key_exists($key, $current)
                ? (bool) $current[$key]
                : $default;
        }
        return $merged;
    }
}
