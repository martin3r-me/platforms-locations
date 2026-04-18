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
        return 'GET /locations?team_id={id}&gruppe=&search=&sort=[...] - Listet Locations (Räume/Standorte) des aktuellen Teams. REST-Parameter: team_id (optional, integer) - Filter nach Team-ID, sonst aktuelles Team. gruppe (optional, string) - Filter nach Gruppe. search (optional, string) - Sucht in name und kuerzel. filters, sort, limit, offset (optional).';
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
                    'gruppe' => [
                        'type' => 'string',
                        'description' => 'Optional: Filter nach Gruppe (z.B. "Hauptgebäude").',
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

            $query = Location::query()->where('team_id', $teamId);

            if (!empty($arguments['gruppe'])) {
                $query->where('gruppe', $arguments['gruppe']);
            }
            if (array_key_exists('mehrfachbelegung', $arguments) && $arguments['mehrfachbelegung'] !== null) {
                $query->where('mehrfachbelegung', (bool) $arguments['mehrfachbelegung']);
            }

            $this->applyStandardFilters($query, $arguments, [
                'name', 'kuerzel', 'gruppe', 'pax_min', 'pax_max', 'mehrfachbelegung', 'adresse', 'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'kuerzel', 'gruppe', 'adresse']);
            $this->applyStandardSort($query, $arguments, ['name', 'kuerzel', 'sort_order', 'created_at', 'updated_at'], 'sort_order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $locations = $query->get()->map(fn(Location $l) => [
                'id'               => $l->id,
                'uuid'             => $l->uuid,
                'name'             => $l->name,
                'kuerzel'          => $l->kuerzel,
                'gruppe'           => $l->gruppe,
                'pax_min'          => $l->pax_min,
                'pax_max'          => $l->pax_max,
                'mehrfachbelegung' => (bool) $l->mehrfachbelegung,
                'adresse'          => $l->adresse,
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
