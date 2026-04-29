<?php

namespace Platform\Locations\Services;

use Illuminate\Support\Facades\Http;

/**
 * Geocoding via Nominatim (OpenStreetMap) — zentral fuer Locations-Modul.
 *
 * Wird genutzt von:
 *  - `Livewire\Manage::searchAddress()` (Autocomplete-Vorschlaege beim Tippen)
 *  - `Tools\CreateLocationTool` / `UpdateLocationTool` (Auto-Geocode beim
 *    Setzen von `adresse` ohne explizite Lat/Lng)
 *
 * Konfiguration via `config('locations.geocoding')` — siehe Setup-Doku.
 * Nominatim-Usage-Policy: max 1 Request/Sekunde, eindeutiger User-Agent.
 */
class GeocodingService
{
    /**
     * Liefert die Top-N Treffer zu einem Such-Query als flache Liste.
     *
     * @return array<int, array{display:string, lat:?float, lon:?float, type:string}>
     */
    public function searchSuggestions(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 3) {
            return [];
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
                return [];
            }

            return collect($response->json() ?? [])
                ->map(fn ($row) => [
                    'display' => (string) ($row['display_name'] ?? ''),
                    'lat'     => isset($row['lat']) ? (float) $row['lat'] : null,
                    'lon'     => isset($row['lon']) ? (float) $row['lon'] : null,
                    'type'    => (string) ($row['type'] ?? ''),
                ])
                ->filter(fn ($s) => $s['display'] !== '' && $s['lat'] !== null && $s['lon'] !== null)
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Liefert den besten Treffer (Top-1) als Lat/Lng + Display oder null,
     * wenn nichts gefunden wurde.
     *
     * @return array{lat:float, lng:float, display:string}|null
     */
    public function geocodeBest(string $query): ?array
    {
        $suggestions = $this->searchSuggestions($query);
        if (empty($suggestions)) {
            return null;
        }
        $top = $suggestions[0];
        if ($top['lat'] === null || $top['lon'] === null) {
            return null;
        }
        return [
            'lat'     => (float) $top['lat'],
            'lng'     => (float) $top['lon'],
            'display' => (string) $top['display'],
        ];
    }
}
