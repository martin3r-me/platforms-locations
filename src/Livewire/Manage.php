<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Platform\Locations\Models\Location;

class Manage extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public ?string $editingId = null;

    /** Livewire-TemporaryUploadedFile für Grundriss-Upload */
    public $grundriss = null;
    public bool $uploadingGrundriss = false;

    /** Relativer S3-Pfad des aktuell hinterlegten Grundrisses (oder null) */
    public ?string $grundrissPath = null;
    /** Original-Dateiname des Uploads (aus Dateinamen-Extension abgeleitet) */
    public ?string $grundrissFileName = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|string|max:20')]
    public string $kuerzel = '';

    #[Validate('nullable|string|max:255')]
    public ?string $gruppe = null;

    #[Validate('nullable|integer|min:0|max:65535')]
    public ?int $pax_min = null;

    #[Validate('nullable|integer|min:0|max:65535')]
    public ?int $pax_max = null;

    public bool $mehrfachbelegung = false;

    #[Validate('nullable|string|max:255')]
    public ?string $adresse = null;

    #[Validate('nullable|numeric|between:-90,90')]
    public ?float $latitude = null;

    #[Validate('nullable|numeric|between:-180,180')]
    public ?float $longitude = null;

    /** @var array<int,array<string,mixed>> */
    public array $addressSuggestions = [];

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        $location = Location::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail();

        $this->editingId        = $location->uuid;
        $this->name             = $location->name;
        $this->kuerzel          = $location->kuerzel;
        $this->gruppe           = $location->gruppe;
        $this->pax_min          = $location->pax_min;
        $this->pax_max          = $location->pax_max;
        $this->mehrfachbelegung = (bool) $location->mehrfachbelegung;
        $this->adresse          = $location->adresse;
        $this->latitude         = $location->latitude !== null ? (float) $location->latitude : null;
        $this->longitude        = $location->longitude !== null ? (float) $location->longitude : null;
        $this->addressSuggestions = [];

        $this->refreshGrundrissState($location->uuid);

        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'name', 'kuerzel', 'gruppe',
            'pax_min', 'pax_max', 'mehrfachbelegung',
            'adresse', 'latitude', 'longitude', 'addressSuggestions',
            'grundriss', 'uploadingGrundriss', 'grundrissPath', 'grundrissFileName',
        ]);
        $this->resetErrorBag();
    }

    public function updatedAdresse(?string $value): void
    {
        // Wenn der Nutzer die Adresse manuell ändert, sind die Koordinaten
        // ggf. nicht mehr gültig. Wir lassen sie stehen, bis er eine neue
        // Vorschlag-Zeile auswählt. Nur Vorschläge aktualisieren.
        $this->searchAddress($value);
    }

    public function searchAddress(?string $query): void
    {
        $query = trim((string) $query);
        if (mb_strlen($query) < 3) {
            $this->addressSuggestions = [];
            return;
        }

        $cfg = config('locations.geocoding', []);

        $userAgent = $cfg['user_agent']
            ?: ('Platform-Locations/1.0 (' . (string) config('app.name', 'platform') . ')');

        try {
            $response = Http::withHeaders([
                'User-Agent'      => $userAgent,
                'Accept-Language' => (string) ($cfg['language'] ?? 'de'),
            ])
                ->timeout(5)
                ->get(rtrim((string) ($cfg['nominatim_url'] ?? 'https://nominatim.openstreetmap.org'), '/') . '/search', [
                    'q'              => $query,
                    'format'         => 'jsonv2',
                    'addressdetails' => 1,
                    'limit'          => (int) ($cfg['limit'] ?? 6),
                    'countrycodes'   => (string) ($cfg['countrycodes'] ?? ''),
                ]);

            if (!$response->ok()) {
                $this->addressSuggestions = [];
                return;
            }

            $this->addressSuggestions = collect($response->json() ?? [])
                ->map(fn($row) => [
                    'display' => (string) ($row['display_name'] ?? ''),
                    'lat'     => isset($row['lat']) ? (float) $row['lat'] : null,
                    'lon'     => isset($row['lon']) ? (float) $row['lon'] : null,
                    'type'    => (string) ($row['type'] ?? ''),
                ])
                ->filter(fn($s) => $s['display'] !== '' && $s['lat'] !== null && $s['lon'] !== null)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            $this->addressSuggestions = [];
        }
    }

    public function selectSuggestion(int $index): void
    {
        $s = $this->addressSuggestions[$index] ?? null;
        if (!$s) {
            return;
        }

        $this->adresse   = $s['display'];
        $this->latitude  = $s['lat'];
        $this->longitude = $s['lon'];
        $this->addressSuggestions = [];

        $this->dispatch('locations:map-update', lat: $this->latitude, lng: $this->longitude);
    }

    public function clearCoordinates(): void
    {
        $this->latitude = null;
        $this->longitude = null;
        $this->dispatch('locations:map-update', lat: null, lng: null);
    }

    public function save(): void
    {
        $data = $this->validate();

        $user = Auth::user();
        $team = $user->currentTeam;

        if ($this->editingId) {
            $location = Location::where('team_id', $team->id)->where('uuid', $this->editingId)->firstOrFail();
            $location->update($data);
        } else {
            $data['team_id']    = $team->id;
            $data['user_id']    = $user->id;
            $data['sort_order'] = (Location::where('team_id', $team->id)->max('sort_order') ?? 0) + 1;
            Location::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function delete(string $uuid): void
    {
        $team = Auth::user()->currentTeam;
        Location::where('team_id', $team->id)->where('uuid', $uuid)->firstOrFail()->delete();
    }

    // ================= Grundriss-Upload (S3, ohne DB-Eintrag) =================

    protected const GRUNDRISS_MAX_KB = 20480; // 20 MB

    protected const GRUNDRISS_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

    protected function grundrissDisk(): string
    {
        if (config('filesystems.disks.s3.bucket')) {
            return 's3';
        }
        return config('filesystems.default', 'public');
    }

    protected function grundrissDir(string $uuid): string
    {
        return "locations/grundrisse/{$uuid}";
    }

    /**
     * Sucht im Grundriss-Verzeichnis der Location nach einer bestehenden Datei
     * und aktualisiert $grundrissPath / $grundrissFileName entsprechend.
     */
    protected function refreshGrundrissState(string $uuid): void
    {
        $this->grundrissPath = null;
        $this->grundrissFileName = null;

        try {
            $disk = Storage::disk($this->grundrissDisk());
            $files = $disk->files($this->grundrissDir($uuid));
            foreach ($files as $file) {
                $this->grundrissPath = $file;
                $this->grundrissFileName = basename($file);
                break;
            }
        } catch (\Throwable $e) {
            // kein Grundriss verfügbar → Properties bleiben null
        }
    }

    public function updatedGrundriss(): void
    {
        if (!$this->grundriss || !$this->editingId) {
            $this->grundriss = null;
            return;
        }

        $this->uploadingGrundriss = true;

        try {
            $team = Auth::user()->currentTeam;
            $location = Location::where('team_id', $team->id)
                ->where('uuid', $this->editingId)
                ->firstOrFail();

            $this->validate([
                'grundriss' => 'file|max:' . self::GRUNDRISS_MAX_KB
                    . '|mimes:' . implode(',', self::GRUNDRISS_EXTENSIONS),
            ], [
                'grundriss.max' => 'Die Datei ist zu groß (max. 20 MB).',
                'grundriss.mimes' => 'Erlaubte Formate: PDF, PNG, JPG, WEBP.',
            ]);

            $ext = strtolower($this->grundriss->getClientOriginalExtension() ?: 'pdf');
            if (!in_array($ext, self::GRUNDRISS_EXTENSIONS, true)) {
                $ext = 'pdf';
            }

            $diskName = $this->grundrissDisk();
            $disk = Storage::disk($diskName);
            $dir = $this->grundrissDir($location->uuid);

            // Bestehenden Grundriss (egal welche Extension) entfernen
            foreach ($disk->files($dir) as $file) {
                $disk->delete($file);
            }

            $filename = "grundriss.{$ext}";
            $stored = $this->grundriss->storeAs($dir, $filename, $diskName);

            if (!$stored || !$disk->exists($stored)) {
                throw new \RuntimeException("Grundriss konnte nicht gespeichert werden (disk={$diskName}).");
            }

            $this->refreshGrundrissState($location->uuid);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('[Locations] Grundriss-Upload fehlgeschlagen', [
                'error' => $e->getMessage(),
                'location_uuid' => $this->editingId,
            ]);
            $this->addError('grundriss', 'Upload fehlgeschlagen: ' . $e->getMessage());
        } finally {
            $this->grundriss = null;
            $this->uploadingGrundriss = false;
        }
    }

    public function deleteGrundriss(): void
    {
        if (!$this->editingId) {
            return;
        }

        $team = Auth::user()->currentTeam;
        $location = Location::where('team_id', $team->id)
            ->where('uuid', $this->editingId)
            ->firstOrFail();

        try {
            $disk = Storage::disk($this->grundrissDisk());
            $dir = $this->grundrissDir($location->uuid);
            foreach ($disk->files($dir) as $file) {
                $disk->delete($file);
            }
        } catch (\Throwable $e) {
            \Log::error('[Locations] Grundriss-Löschen fehlgeschlagen', [
                'error' => $e->getMessage(),
                'location_uuid' => $this->editingId,
            ]);
        }

        $this->refreshGrundrissState($location->uuid);
    }

    /**
     * Liefert eine temporäre URL für den aktuell hinterlegten Grundriss.
     * - S3 / temporary-URL-fähige Disks: presigned URL
     * - sonst: signed Route-URL (falls disk nicht public ist, dann fallback auf Storage::url)
     */
    public function getGrundrissUrlProperty(): ?string
    {
        if (!$this->grundrissPath) {
            return null;
        }

        $diskName = $this->grundrissDisk();
        $disk = Storage::disk($diskName);

        try {
            if ($disk->providesTemporaryUrls()) {
                return $disk->temporaryUrl($this->grundrissPath, now()->addMinutes(15));
            }
            return $disk->url($this->grundrissPath);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $locations = Location::where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('locations::livewire.manage', [
            'locations' => $locations,
        ])->layout('platform::layouts.app');
    }
}
