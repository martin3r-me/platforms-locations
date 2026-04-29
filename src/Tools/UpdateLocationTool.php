<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\Location;
use Platform\Locations\Services\GeocodingService;
use Platform\Locations\Tools\Concerns\NormalizesLocationFields;
use Platform\Locations\Tools\Concerns\RecommendsMissingLocationFields;

/**
 * Aktualisiert eine Location. Nur übergebene Felder werden geändert.
 */
class UpdateLocationTool implements ToolContract, ToolMetadataContract
{
    use NormalizesLocationFields;
    use RecommendsMissingLocationFields;

    /** @var array<int,string> Identifikations-Felder + akzeptierte Update-Felder (fuer ignored_fields-Diff) */
    protected const KNOWN_FIELDS = [
        'location_id', 'uuid',
        'name', 'kuerzel', 'gruppe', 'pax_min', 'pax_max',
        'mehrfachbelegung', 'adresse', 'latitude', 'longitude', 'sort_order',
        'groesse_qm', 'hallennummer', 'barrierefrei',
        'besonderheit', 'beschreibung', 'anlaesse',
        'geocode',
    ];

    public function getName(): string
    {
        return 'locations.locations.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/{id} - Aktualisiert eine Location. REST-Parameter: location_id (integer) ODER uuid (string) - mindestens einer. Übrige Felder optional: name, kuerzel, gruppe, pax_min, pax_max (max inkl. Personal), mehrfachbelegung, adresse (Aenderung triggert per Default einen Nominatim-Geocode -> Lat/Lng werden neu gesetzt; mit geocode=false oder explizitem latitude/longitude wird der Lookup uebersprungen), latitude, longitude, sort_order, groesse_qm, hallennummer, barrierefrei, besonderheit (kurz), beschreibung (langer Marketing-/Historie-Fließtext), anlaesse (Array), geocode (boolean, Default true). Nur übergebene Werte werden geändert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Location. Alternative zu uuid.',
                ],
                'uuid' => [
                    'type' => 'string',
                    'description' => 'UUID der Location. Alternative zu location_id.',
                ],
                'name' => ['type' => 'string', 'description' => 'Optional: Neuer Name.'],
                'kuerzel' => ['type' => 'string', 'description' => 'Optional: Neues Kürzel (max. 20).'],
                'gruppe' => ['type' => 'string', 'description' => 'Optional: Neue Gruppe.'],
                'pax_min' => ['type' => 'integer', 'description' => 'Optional: Neue Mindestbelegung.'],
                'pax_max' => ['type' => 'integer', 'description' => 'Optional: Neue Kapazität (inkl. Personal).'],
                'mehrfachbelegung' => ['type' => 'boolean', 'description' => 'Optional: Mehrfachbelegung erlaubt ja/nein.'],
                'adresse' => ['type' => 'string', 'description' => 'Optional: Neue Adresse. Bei Aenderung wird per Default via Nominatim geocoded und Lat/Lng neu gesetzt. Mit geocode=false oder expliziten latitude/longitude wird der Lookup uebersprungen.'],
                'latitude' => ['type' => 'number', 'description' => 'Optional: WGS84-Breitengrad. Wenn explizit mitgegeben, wird kein Geocoding ausgefuehrt.'],
                'longitude' => ['type' => 'number', 'description' => 'Optional: WGS84-Laengengrad. Wenn explizit mitgegeben, wird kein Geocoding ausgefuehrt.'],
                'geocode' => ['type' => 'boolean', 'description' => 'Optional (Default true): false unterdrueckt den Nominatim-Lookup beim Setzen einer neuen Adresse.'],
                'sort_order' => ['type' => 'integer', 'description' => 'Optional: Sortierreihenfolge.'],
                'groesse_qm' => ['type' => 'number', 'description' => 'Optional: Größe in qm.'],
                'hallennummer' => ['type' => 'string', 'description' => 'Optional: Hallennummer (max. 30).'],
                'barrierefrei' => ['type' => 'boolean', 'description' => 'Optional: Barrierefrei.'],
                'besonderheit' => ['type' => 'string', 'description' => 'Optional: kurze Besonderheits-Hervorhebung (Freitext, 1-2 Saetze).'],
                'beschreibung' => ['type' => 'string', 'description' => 'Optional: laengerer Beschreibungstext fuer Marketing/Historie/Kundeninfo.'],
                'anlaesse' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Optional: Neue Liste der Anlässe (überschreibt vorherige).'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Aliases anwenden (z. B. anlaesse-String -> Array, address->adresse, ...)
            $aliases = $this->normalizeLocationFields($arguments);

            $query = Location::query();
            if (!empty($arguments['location_id'])) {
                $query->where('id', (int) $arguments['location_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'location_id oder uuid ist erforderlich.');
            }

            $location = $query->first();
            if (!$location) {
                return ToolResult::error('LOCATION_NOT_FOUND', 'Die angegebene Location wurde nicht gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $location->team_id)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Location.');
            }

            $update = [];
            foreach (['name', 'gruppe', 'adresse', 'besonderheit', 'beschreibung'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $update[$field] = $arguments[$field];
                }
            }
            if (array_key_exists('kuerzel', $arguments)) {
                $update['kuerzel'] = mb_substr((string) $arguments['kuerzel'], 0, 20);
            }
            if (array_key_exists('hallennummer', $arguments)) {
                $update['hallennummer'] = $arguments['hallennummer'] !== null
                    ? mb_substr((string) $arguments['hallennummer'], 0, 30)
                    : null;
            }
            foreach (['pax_min', 'pax_max', 'sort_order'] as $field) {
                if (array_key_exists($field, $arguments) && $arguments[$field] !== null) {
                    $update[$field] = (int) $arguments[$field];
                } elseif (array_key_exists($field, $arguments)) {
                    $update[$field] = null;
                }
            }
            if (array_key_exists('mehrfachbelegung', $arguments)) {
                $update['mehrfachbelegung'] = (bool) $arguments['mehrfachbelegung'];
            }
            if (array_key_exists('barrierefrei', $arguments)) {
                $update['barrierefrei'] = (bool) $arguments['barrierefrei'];
            }
            if (array_key_exists('groesse_qm', $arguments)) {
                $update['groesse_qm'] = $arguments['groesse_qm'] !== null && $arguments['groesse_qm'] !== ''
                    ? (float) $arguments['groesse_qm']
                    : null;
            }
            if (array_key_exists('anlaesse', $arguments)) {
                if (is_array($arguments['anlaesse'])) {
                    $cleaned = collect($arguments['anlaesse'])
                        ->map(fn ($v) => is_string($v) ? trim($v) : null)
                        ->filter(fn ($v) => $v !== null && $v !== '')
                        ->values()
                        ->all();
                    $update['anlaesse'] = $cleaned !== [] ? $cleaned : null;
                } elseif ($arguments['anlaesse'] === null) {
                    $update['anlaesse'] = null;
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'anlaesse muss ein Array von Strings (oder null) sein. Tipp: Komma-Liste als String wird automatisch konvertiert (siehe aliases_applied).');
                }
            }

            // Lat/Lng: explizit oder per Geocoding bei Adress-Aenderung
            $latExplicit = array_key_exists('latitude', $arguments);
            $lngExplicit = array_key_exists('longitude', $arguments);
            if ($latExplicit) {
                $update['latitude'] = $arguments['latitude'] !== null && $arguments['latitude'] !== ''
                    ? (float) $arguments['latitude'] : null;
            }
            if ($lngExplicit) {
                $update['longitude'] = $arguments['longitude'] !== null && $arguments['longitude'] !== ''
                    ? (float) $arguments['longitude'] : null;
            }

            $shouldGeocode = (bool) ($arguments['geocode'] ?? true);
            $geocodeResult = null;
            $newAddress = array_key_exists('adresse', $arguments) ? $arguments['adresse'] : null;
            if ($shouldGeocode
                && $newAddress !== null
                && $newAddress !== ''
                && !$latExplicit
                && !$lngExplicit
            ) {
                $geocodeResult = app(GeocodingService::class)->geocodeBest((string) $newAddress);
                if ($geocodeResult !== null) {
                    $update['latitude']  = $geocodeResult['lat'];
                    $update['longitude'] = $geocodeResult['lng'];
                    $aliases[] = 'adresse:geocoded->lat/lng';
                }
            }

            if (empty($update)) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Felder zum Aktualisieren übergeben.');
            }

            $location->update($update);

            $geocodingInfo = null;
            if ($shouldGeocode && $newAddress !== null && $newAddress !== ''
                && !$latExplicit && !$lngExplicit) {
                $geocodingInfo = $geocodeResult !== null
                    ? ['status' => 'matched',  'display' => $geocodeResult['display']]
                    : ['status' => 'no_match', 'message' => 'Nominatim hat keinen Treffer geliefert. Adresse wurde gespeichert, Lat/Lng unveraendert.'];
            }

            $known = array_merge(self::KNOWN_FIELDS, array_keys($this->aliasMapForDiff()));
            $ignored = array_values(array_diff(array_keys($arguments), $known));

            return ToolResult::success([
                'id'               => $location->id,
                'uuid'             => $location->uuid,
                'name'             => $location->name,
                'kuerzel'          => $location->kuerzel,
                'gruppe'           => $location->gruppe,
                'pax_min'          => $location->pax_min,
                'pax_max'          => $location->pax_max,
                'mehrfachbelegung' => (bool) $location->mehrfachbelegung,
                'adresse'          => $location->adresse,
                'latitude'         => $location->latitude !== null ? (float) $location->latitude : null,
                'longitude'        => $location->longitude !== null ? (float) $location->longitude : null,
                'sort_order'       => $location->sort_order,
                'groesse_qm'       => $location->groesse_qm !== null ? (float) $location->groesse_qm : null,
                'hallennummer'     => $location->hallennummer,
                'barrierefrei'     => (bool) $location->barrierefrei,
                'besonderheit'     => $location->besonderheit,
                'beschreibung'     => $location->beschreibung,
                'anlaesse'         => $location->anlaesse,
                'team_id'          => $location->team_id,
                'updated_fields'   => array_keys($update),
                'aliases_applied'  => $aliases,
                'ignored_fields'   => $ignored,
                'geocoding'        => $geocodingInfo,
                'empty_recommended_fields'        => $this->emptyRecommendedLocationFields($location),
                'empty_recommended_field_options' => $this->recommendedLocationFieldOptions($location->team_id),
                'message'          => "Location '{$location->name}' erfolgreich aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren der Location: ' . $e->getMessage());
        }
    }

    /** @return array<string, string> Alias-Eingabefelder (fuer ignored_fields-Diff) */
    protected function aliasMapForDiff(): array
    {
        return [
            'address'     => 'adresse',
            'description' => 'beschreibung',
            'highlight'   => 'besonderheit',
            'occasions'   => 'anlaesse',
            'size_sqm'    => 'groesse_qm',
            'hall_number' => 'hallennummer',
            'accessible'  => 'barrierefrei',
        ];
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'location', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
