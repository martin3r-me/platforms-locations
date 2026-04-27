<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationAddon;

class DeleteLocationAddonTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.addons.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/addons/{id} - Loescht ein Add-on (Soft Delete). Identifikation: addon_id ODER uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'addon_id' => ['type' => 'integer'],
                'uuid'     => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $query = LocationAddon::query();
            if (!empty($arguments['addon_id'])) {
                $query->where('id', (int) $arguments['addon_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'addon_id oder uuid noetig.');
            }

            $row = $query->with('location')->first();
            if (!$row) return ToolResult::error('ADDON_NOT_FOUND', 'Nicht gefunden.');

            $teamId = $row->location?->team_id;
            $hasAccess = $teamId && $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');

            $row->delete();

            return ToolResult::success([
                'id' => $row->id, 'uuid' => $row->uuid,
                'message' => "Add-on '{$row->label}' geloescht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'action','tags'=>['locations','addons','delete'],'read_only'=>false,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'write','idempotent'=>true,'side_effects'=>['deletes']];
    }
}
