<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationBlocking;

class DeleteLocationBlockingTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.blockings.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/blockings/{id} - Loescht eine Sperrzeit (Soft Delete). Parameter: blocking_id (integer) ODER uuid (string).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'blocking_id' => ['type' => 'integer', 'description' => 'ID der Sperrzeit. Alternative zu uuid.'],
                'uuid'        => ['type' => 'string', 'description' => 'UUID der Sperrzeit. Alternative zu blocking_id.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            // Query-Level-Scoping auf die Teams des Users — kein Load-then-Check.
            $teamIds = $context->user->teams()->pluck('teams.id');

            $query = LocationBlocking::query()
                ->whereHas('location', fn ($q) => $q->whereIn('team_id', $teamIds));

            if (!empty($arguments['blocking_id'])) {
                $query->where('id', (int) $arguments['blocking_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'blocking_id oder uuid ist erforderlich.');
            }

            $blocking = $query->first();
            if (!$blocking) {
                return ToolResult::error('BLOCKING_NOT_FOUND', 'Die angegebene Sperrzeit wurde nicht gefunden.');
            }

            $info = [
                'id'          => $blocking->id,
                'uuid'        => $blocking->uuid,
                'location_id' => $blocking->location_id,
                'start_date'  => $blocking->start_date?->toDateString(),
                'end_date'    => $blocking->end_date?->toDateString(),
                'reason'      => $blocking->reason,
            ];

            $blocking->delete();

            return ToolResult::success([
                'deleted' => $info,
                'message' => 'Sperrzeit erfolgreich geloescht.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Loeschen der Sperrzeit: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'action',
            'tags'          => ['locations', 'blockings', 'availability', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'write',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
