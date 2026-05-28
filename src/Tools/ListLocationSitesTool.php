<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Locations\Models\LocationSite;

class ListLocationSitesTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'locations.sites.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/sites - Listet LocationSites (Eltern-Container fuer Locations, z.B. Areal Boehler, WCCB Bonn, Borussia-Park) des aktuellen Teams. REST-Parameter: team_id (optional, integer), search (optional, sucht in name, city, street), filters, sort, limit, offset (optional).';
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

            $teamId = $arguments['team_id'] ?? $context->team?->id;
            if (!$teamId) {
                return ToolResult::error('MISSING_TEAM', 'Kein Team angegeben und kein Team im Kontext gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', "Du hast keinen Zugriff auf Team-ID {$teamId}.");
            }

            $query = LocationSite::query()->where('team_id', $teamId);

            $this->applyStandardFilters($query, $arguments, [
                'name', 'city', 'country', 'country_code', 'is_international',
                'done', 'created_at', 'updated_at',
            ]);
            $this->applyStandardSearch($query, $arguments, ['name', 'city', 'street', 'description']);
            $this->applyStandardSort($query, $arguments, ['name', 'sort_order', 'city', 'created_at', 'updated_at'], 'name', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $sites = $query->get()->map(fn (LocationSite $s) => [
                'id'               => $s->id,
                'uuid'             => $s->uuid,
                'name'             => $s->name,
                'description'      => $s->description,
                'street'           => $s->street,
                'street_number'    => $s->street_number,
                'postal_code'      => $s->postal_code,
                'city'             => $s->city,
                'state'            => $s->state,
                'country'          => $s->country,
                'country_code'     => $s->country_code,
                'latitude'         => $s->latitude !== null ? (float) $s->latitude : null,
                'longitude'        => $s->longitude !== null ? (float) $s->longitude : null,
                'is_international' => (bool) $s->is_international,
                'phone'            => $s->phone,
                'email'            => $s->email,
                'website'          => $s->website,
                'sort_order'       => $s->sort_order,
                'team_id'          => $s->team_id,
                'created_at'       => $s->created_at?->toIso8601String(),
            ])->toArray();

            return ToolResult::success([
                'sites'   => $sites,
                'count'   => count($sites),
                'team_id' => $teamId,
                'message' => count($sites) . ' Site(s) gefunden (Team-ID: ' . $teamId . ').',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Sites: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'sites', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
        ];
    }
}
