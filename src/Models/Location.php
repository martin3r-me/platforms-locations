<?php

namespace Platform\Locations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
}
