<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Locations\Models\Location;

/**
 * Listet Locations (Räume/Standorte) des aktuellen Teams.
 */
class ListLocationsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'locations.locations.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations?team_id={id}&site_id=&site_uuid=&search=&sort=[...] - Listet Locations (Räume/Standorte) des aktuellen Teams. REST-Parameter: team_id (optional, integer) - Filter nach Team-ID, sonst aktuelles Team. site_id ODER site_uuid (optional) - Filter nach LocationSite-Zugehoerigkeit. search (optional, string) - Sucht in name, kuerzel, adresse, hallennummer, besonderheit. filters, sort, limit, offset (optional).';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'team_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach Team-ID. Wenn nicht angegeben, wird das aktuelle Team aus dem Kontext verwendet.',
                    ],
                    'site_id' => [
                        'type' => 'integer',
                        'description' => 'Optional: Filter nach LocationSite-ID (Eltern-Container).',
                    ],
                    'site_uuid' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach LocationSite-UUID. Alternative zu site_id.',
                    ],
                    'mehrfachbelegung' => [
                        'type' => 'boolean',
                        'description' => 'Optional: Nur Locations mit/ohne erlaubte Mehrfachbelegung.',
                    ],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $teamId = $arguments['team_id'] ?? null;
            if ($teamId === 0 || $teamId === '0') {
                $teamId = null;
            }
            if ($teamId === null) {
                $teamId = $context->team?->id;
            }
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $userHasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$userHasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $query = Location::query()->with('site:id,uuid,name')->where('team_id', $teamId);

            // Site-Filter: site_id direkt oder site_uuid (Lookup).
            if (!empty($arguments['site_id'])) {
                $query->where('site_id', (int) $arguments['site_id']);
            } elseif (!empty($arguments['site_uuid'])) {
                $site = \Platform\Locations\Models\LocationSite::where('uuid', (string) $arguments['site_uuid'])
                    ->where('team_id', $teamId)
                    ->first();
                if ($site) {
                    $query->where('site_id', $site->id);
                } else {
                    $query->whereRaw('1=0'); // unbekannte UUID -> leeres Result
                }
            }
            if (array_key_exists('mehrfachbelegung', $arguments) && $arguments['mehrfachbelegung'] !== null) {
                $query->where('mehrfachbelegung', (bool) $arguments['mehrfachbelegung']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'kuerzel', 'site_id', 'pax_min', 'pax_max', 'mehrfachbelegung', 'adresse',
                'groesse_qm', 'hallennummer', 'barrierefrei', 'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'kuerzel', 'adresse', 'hallennummer', 'besonderheit']);
            $this->applyStandardSort($query, $arguments, ['name', 'kuerzel', 'sort_order', 'created_at', 'updated_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $locations = $query->get()->map(fn(Location $l) => [
                'id'               => $l->id,
                'uuid'             => $l->uuid,
                'name'             => $l->name,
                'kuerzel'          => $l->kuerzel,
                'site_id'          => $l->site_id,
                'site_uuid'        => $l->site?->uuid,
                'site_name'        => $l->site?->name,
                'pax_min'          => $l->pax_min,
                'pax_max'          => $l->pax_max,
                'mehrfachbelegung' => (bool) $l->mehrfachbelegung,
                'adresse'          => $l->adresse,
                'latitude'         => $l->latitude,
                'longitude'        => $l->longitude,
                'groesse_qm'       => $l->groesse_qm !== null ? (float) $l->groesse_qm : null,
                'hallennummer'     => $l->hallennummer,
                'barrierefrei'     => (bool) $l->barrierefrei,
                'besonderheit'     => $l->besonderheit,
                'beschreibung'     => $l->beschreibung,
                'anlaesse'         => $l->anlaesse,
                'sort_order'       => $l->sort_order,
                'team_id'          => $l->team_id,
                'created_at'       => $l->created_at?->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'locations' => $locations,
                'count'     => count($locations),
                'team_id'   => $teamId,
                'message'   => count($locations) . ' Location(s) gefunden (Team-ID: ' . $teamId . ').',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Locations: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'location', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
