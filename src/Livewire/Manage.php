<?php

namespace Platform\Locations\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Platform\Locations\Models\Location;

class Manage extends Component
{
    public bool $showModal = false;
    public ?string $editingId = null;

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
