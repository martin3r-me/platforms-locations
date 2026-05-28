<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSite;

class DeleteLocationSiteTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.sites.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/sites/{id} - Soft-Delete einer LocationSite. Zugeordnete Locations werden NICHT geloescht; ihre site_id bleibt auf der nun ungueltigen Site stehen — kann via UpdateLocationTool auf null oder eine andere Site gesetzt werden.';
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

            $locationsCount = $site->locations()->count();
            $name = $site->name;
            $site->delete();

            return ToolResult::success([
                'deleted'          => true,
                'id'               => $site->id,
                'uuid'             => $site->uuid,
                'locations_affected' => $locationsCount,
                'message'          => "Site '{$name}' geloescht. {$locationsCount} zugeordnete Location(s) behalten ihre site_id-Referenz.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen der Site: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'sites', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
        ];
    }
}
