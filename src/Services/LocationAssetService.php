<?php

namespace Platform\Locations\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Locations\Models\Location;

/**
 * Generischer Service fuer Multi-File-Assets pro Location:
 *  - Buffetstationen
 *  - Bestuhlungsplaene
 *  - Fotos mit Bestuhlung
 *  - Fotos (leer)
 *
 * Wird flach im Storage abgelegt, kein DB-Tracking. Konsumenten
 * (Events-Angebote/Vertraege) erhalten URLs/Inhalte ueber die
 * Public-Methoden am Location-Model.
 *
 * Der bestehende Grundriss-Flow (locations/grundrisse/{uuid}/grundriss.{ext})
 * bleibt unangetastet, weil Konsumenten direkt darauf zugreifen
 * (siehe Location::floorPlanContents()/floorPlanIsPdf()).
 */
class LocationAssetService
{
    public const CATEGORY_BUFFET             = 'buffet';
    public const CATEGORY_SEATING_PLANS      = 'seating_plans';
    public const CATEGORY_PHOTOS_WITH_SEATS  = 'photos_with_seating';
    public const CATEGORY_PHOTOS_EMPTY       = 'photos_empty';

    /**
     * @return array<string, array{label:string, slug:string, multi:bool, extensions:array<int,string>, max_kb:int, mime_groups:array<int,string>}>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_BUFFET => [
                'label'       => 'Buffetstationen',
                'slug'        => 'buffet',
                'multi'       => true,
                'extensions'  => ['pdf', 'png', 'jpg', 'jpeg', 'webp'],
                'max_kb'      => 20480, // 20 MB
                'mime_groups' => ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'],
            ],
            self::CATEGORY_SEATING_PLANS => [
                'label'       => 'Bestuhlungsplaene',
                'slug'        => 'seating-plans',
                'multi'       => true,
                'extensions'  => ['pdf', 'png', 'jpg', 'jpeg', 'webp'],
                'max_kb'      => 20480,
                'mime_groups' => ['application/pdf', 'image/png', 'image/jpeg', 'image/webp'],
            ],
            self::CATEGORY_PHOTOS_WITH_SEATS => [
                'label'       => 'Fotos mit Bestuhlung',
                'slug'        => 'photos-with-seating',
                'multi'       => true,
                'extensions'  => ['png', 'jpg', 'jpeg', 'webp'],
                'max_kb'      => 15360, // 15 MB pro Foto
                'mime_groups' => ['image/png', 'image/jpeg', 'image/webp'],
            ],
            self::CATEGORY_PHOTOS_EMPTY => [
                'label'       => 'Fotos (leere Location)',
                'slug'        => 'photos-empty',
                'multi'       => true,
                'extensions'  => ['png', 'jpg', 'jpeg', 'webp'],
                'max_kb'      => 15360,
                'mime_groups' => ['image/png', 'image/jpeg', 'image/webp'],
            ],
        ];
    }

    /**
     * @throws \InvalidArgumentException wenn category unbekannt
     */
    public static function categoryConfig(string $category): array
    {
        $cats = self::categories();
        if (!isset($cats[$category])) {
            throw new \InvalidArgumentException("Unbekannte Asset-Kategorie '{$category}'.");
        }
        return $cats[$category];
    }

    public static function isValidCategory(string $category): bool
    {
        return array_key_exists($category, self::categories());
    }

    /**
     * Disk-Auswahl analog zum Grundriss-Flow.
     */
    public function disk(): string
    {
        if (config('filesystems.disks.s3.bucket')) {
            return 's3';
        }
        return (string) config('filesystems.default', 'public');
    }

    public function directory(Location $location, string $category): string
    {
        $cfg = self::categoryConfig($category);
        return "locations/{$location->uuid}/{$cfg['slug']}";
    }

    /**
     * Listet alle Dateien einer Kategorie an einer Location als Collection
     * von Arrays [path, filename, size, mime, url].
     *
     * @return Collection<int, array{path:string, filename:string, size:int, mime:string, url:?string, is_image:bool, is_pdf:bool, extension:string}>
     */
    public function listFiles(Location $location, string $category): Collection
    {
        if (!self::isValidCategory($category)) {
            return collect();
        }
        $diskName = $this->disk();
        $disk     = Storage::disk($diskName);
        $dir      = $this->directory($location, $category);

        try {
            $paths = $disk->files($dir);
        } catch (\Throwable $e) {
            return collect();
        }

        return collect($paths)
            ->map(function (string $path) use ($disk, $diskName) {
                $filename = basename($path);
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $mime     = self::mimeFromExtension($ext);
                $size     = (int) (function () use ($disk, $path) {
                    try { return $disk->size($path); } catch (\Throwable $e) { return 0; }
                })();
                return [
                    'path'      => $path,
                    'filename'  => $filename,
                    'size'      => $size,
                    'mime'      => $mime,
                    'extension' => $ext,
                    'is_image'  => in_array($ext, ['png','jpg','jpeg','webp'], true),
                    'is_pdf'    => $ext === 'pdf',
                    'url'       => $this->urlForPath($diskName, $path),
                ];
            })
            ->sortBy('filename')
            ->values();
    }

    /**
     * Speichert eine Datei einer Kategorie. Multi-Kategorien generieren einen
     * eindeutigen Token als Dateinamen, Single-Kategorien (derzeit keine in
     * diesem Service — Grundriss laeuft separat) wuerden vorher bereinigen.
     *
     * @return array{path:string, filename:string, url:?string}
     *
     * @throws \InvalidArgumentException bei Validierungs-Fehlern
     */
    public function upload(Location $location, string $category, UploadedFile $file): array
    {
        $cfg  = self::categoryConfig($category);
        $ext  = strtolower($file->getClientOriginalExtension() ?: '');
        if ($ext === '' || !in_array($ext, $cfg['extensions'], true)) {
            throw new \InvalidArgumentException(
                "Erlaubte Formate fuer '{$cfg['label']}': " . implode(', ', $cfg['extensions'])
            );
        }
        $sizeKb = (int) ceil($file->getSize() / 1024);
        if ($sizeKb > $cfg['max_kb']) {
            throw new \InvalidArgumentException(
                "Datei ist zu gross (" . round($sizeKb / 1024, 1) . " MB; max " . round($cfg['max_kb'] / 1024, 0) . " MB)."
            );
        }

        $diskName = $this->disk();
        $disk     = Storage::disk($diskName);
        $dir      = $this->directory($location, $category);

        if (!$cfg['multi']) {
            // Falls jemals eine Single-Kategorie hinzukommt: vorher leeren.
            foreach ($disk->files($dir) as $existing) {
                $disk->delete($existing);
            }
            $filename = "asset.{$ext}";
        } else {
            $filename = Str::random(24) . ".{$ext}";
        }

        $stored = $file->storeAs($dir, $filename, $diskName);
        if (!$stored || !$disk->exists($stored)) {
            throw new \RuntimeException("Datei konnte nicht gespeichert werden (disk={$diskName}).");
        }

        return [
            'path'     => $stored,
            'filename' => $filename,
            'url'      => $this->urlForPath($diskName, $stored),
        ];
    }

    /**
     * Loescht eine konkrete Datei einer Kategorie (per filename).
     */
    public function delete(Location $location, string $category, string $filename): bool
    {
        if (!self::isValidCategory($category)) return false;

        // filename absichern: keine Pfad-Traversal
        $clean = basename($filename);
        if ($clean === '' || $clean !== $filename) {
            return false;
        }

        $diskName = $this->disk();
        $disk     = Storage::disk($diskName);
        $dir      = $this->directory($location, $category);
        $path     = $dir . '/' . $clean;

        try {
            if (!$disk->exists($path)) return false;
            return (bool) $disk->delete($path);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Liefert eine (ggf. temporaere) URL fuer einen Disk-Pfad.
     * - Disks mit temporaryUrls (z. B. S3): presigned URL
     * - sonst: Storage::url() Fallback
     */
    public function urlForPath(string $diskName, string $path, int $minutes = 15): ?string
    {
        try {
            $disk = Storage::disk($diskName);
            if ($disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($path, now()->addMinutes($minutes));
            }
            return $disk->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Roh-Inhalt einer Datei (z. B. fuer Einbettung als base64 in PDFs).
     */
    public function contentsOf(Location $location, string $category, string $filename): ?string
    {
        $diskName = $this->disk();
        $disk     = Storage::disk($diskName);
        $clean    = basename($filename);
        if ($clean === '' || $clean !== $filename) return null;
        $path = $this->directory($location, $category) . '/' . $clean;
        try {
            $raw = $disk->get($path);
            return is_string($raw) ? $raw : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function mimeFromExtension(string $ext): string
    {
        return match (strtolower($ext)) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
