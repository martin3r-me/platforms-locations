<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\Location;

/**
 * Liefert Details zu einer einzelnen Location.
 */
class GetLocationTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.location.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{id} - Liefert Details zu einer einzelnen Location. REST-Parameter: location_id (integer) ODER uuid (string) - mindestens einer ist erforderlich. Nutze "locations.locations.GET" zum Finden.';
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
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

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
                'sort_order'       => $location->sort_order,
                'team_id'          => $location->team_id,
                'created_at'       => $location->created_at?->toIso8601String(),
                'updated_at'       => $location->updated_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Location: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'location', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
        ];
    }
}
