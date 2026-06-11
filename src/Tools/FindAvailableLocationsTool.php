<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSite;
use Platform\Locations\Services\AvailabilityService;

/**
 * Sucht verfuegbare Locations fuer ein Datum/einen Zeitraum — optional
 * gefiltert nach Personenzahl und Site. Beantwortet Fragen wie
 * "Welche Location ist am 12.07. fuer 120 Personen frei?".
 */
class FindAvailableLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.availability.SEARCH';
    }

    public function getDescription(): string
    {
        return 'GET /locations/availability - Sucht Locations, die an einem Datum oder in einem Zeitraum verfuegbar sind. Optional: pax (Personenzahl, gematcht gegen pax_max und Bestuhlungsoptionen), site_id/site_uuid (nur Locations einer Site), only_available (Default true: belegte/gesperrte ausblenden). Status pro Location: frei | optionen | belegt | gesperrt.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => [
                    'type'        => 'string',
                    'description' => 'Datum (YYYY-MM-DD). Pflicht. Bei Zeitraum: Startdatum.',
                ],
                'end_date' => [
                    'type'        => 'string',
                    'description' => 'Optionales Enddatum (YYYY-MM-DD, inklusive).',
                ],
                'pax' => [
                    'type'        => 'integer',
                    'description' => 'Optionale Personenzahl. Locations mit pax_max < pax werden als pax_fits=false markiert; Bestuhlungsoptionen werden auf passende gefiltert.',
                ],
                'site_id' => [
                    'type'        => 'integer',
                    'description' => 'Optional: nur Locations dieser Site (ID).',
                ],
                'site_uuid' => [
                    'type'        => 'string',
                    'description' => 'Optional: nur Locations dieser Site (UUID). Alternative zu site_id.',
                ],
                'only_available' => [
                    'type'        => 'boolean',
                    'description' => 'Default true: nur verfuegbare Locations (frei/optionen) zurueckgeben. false: alle inkl. belegt/gesperrt.',
                ],
            ],
            'required' => ['date'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team im Kontext gefunden.');
            }
            $teamId = (int) $teamId;

            $hasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf dieses Team.');
            }

            $date = (string) ($arguments['date'] ?? '');
            $endDate = isset($arguments['end_date']) ? (string) $arguments['end_date'] : null;

            $rangeError = CheckLocationAvailabilityTool::validateRange($date, $endDate);
            if ($rangeError) {
                return $rangeError;
            }

            $pax = isset($arguments['pax']) ? max(1, (int) $arguments['pax']) : null;

            // Site-Filter aufloesen (id oder uuid, Team-gescoped)
            $siteId = null;
            if (!empty($arguments['site_id']) || !empty($arguments['site_uuid'])) {
                $siteQuery = LocationSite::query()->where('team_id', $teamId);
                if (!empty($arguments['site_id'])) {
                    $siteQuery->where('id', (int) $arguments['site_id']);
                } else {
                    $siteQuery->where('uuid', (string) $arguments['site_uuid']);
                }
                $site = $siteQuery->first();
                if (!$site) {
                    return ToolResult::error('SITE_NOT_FOUND', 'Die angegebene Site wurde nicht gefunden.');
                }
                $siteId = $site->id;
            }

            $results = app(AvailabilityService::class)
                ->findAvailable($teamId, $date, $endDate, $pax, $siteId);

            $onlyAvailable = (bool) ($arguments['only_available'] ?? true);
            $filtered = $onlyAvailable
                ? array_values(array_filter($results, fn ($r) => $r['available']))
                : $results;

            $availableCount = count(array_filter($results, fn ($r) => $r['available']));

            return ToolResult::success([
                'locations'       => $filtered,
                'total'           => count($filtered),
                'available_count' => $availableCount,
                'checked_count'   => count($results),
                'message'         => $availableCount . ' von ' . count($results)
                    . ' Location(s) im angefragten Zeitraum verfuegbar'
                    . ($pax !== null ? " (PAX-Filter: {$pax})" : '') . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler bei der Verfuegbarkeits-Suche: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'availability', 'search', 'booking', 'pax'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level'    => 'safe',
            'idempotent'    => true,
            'side_effects'  => [],
        ];
    }
}
