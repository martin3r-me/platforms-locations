<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSite;

class GetLocationSiteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.site.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/sites/{id} - Liefert eine einzelne LocationSite inklusive aller Felder + Liste der zugeordneten Locations (id, uuid, name, kuerzel). Identifikation per site_id (integer) ODER uuid (string).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'site_id' => ['type' => 'integer', 'description' => 'ID der Site. Alternative zu uuid.'],
                'uuid'    => ['type' => 'string',  'description' => 'UUID der Site. Alternative zu site_id.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $query = LocationSite::query();
            if (!empty($arguments['site_id'])) {
                $query->where('id', (int) $arguments['site_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('INVALID_ARGUMENT', 'Entweder site_id oder uuid muss angegeben werden.');
            }

            $site = $query->first();
            if (!$site) {
                return ToolResult::error('SITE_NOT_FOUND', 'Die angegebene Site wurde nicht gefunden.');
            }

            $hasAccess = $context->user->teams()->where('teams.id', $site->team_id)->exists();
            if (!$hasAccess) {
                return ToolResult::error('ACCESS_DENIED', 'Du hast keinen Zugriff auf diese Site.');
            }

            $locations = $site->locations()->get(['id', 'uuid', 'name', 'kuerzel'])->map(fn ($l) => [
                'id'      => $l->id,
                'uuid'    => $l->uuid,
                'name'    => $l->name,
                'kuerzel' => $l->kuerzel,
            ])->toArray();

            return ToolResult::success([
                'id'               => $site->id,
                'uuid'             => $site->uuid,
                'name'             => $site->name,
                'description'      => $site->description,
                'street'           => $site->street,
                'street_number'    => $site->street_number,
                'postal_code'      => $site->postal_code,
                'city'             => $site->city,
                'state'            => $site->state,
                'country'          => $site->country,
                'country_code'     => $site->country_code,
                'latitude'         => $site->latitude !== null ? (float) $site->latitude : null,
                'longitude'        => $site->longitude !== null ? (float) $site->longitude : null,
                'timezone'         => $site->timezone,
                'is_international' => (bool) $site->is_international,
                'phone'            => $site->phone,
                'email'            => $site->email,
                'website'          => $site->website,
                'notes'            => $site->notes,
                'done'             => (bool) $site->done,
                'sort_order'       => $site->sort_order,
                'team_id'          => $site->team_id,
                'locations'        => $locations,
                'locations_count'  => count($locations),
                'created_at'       => $site->created_at?->toIso8601String(),
                'updated_at'       => $site->updated_at?->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Laden der Site: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'sites', 'get'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
        ];
    }
}
