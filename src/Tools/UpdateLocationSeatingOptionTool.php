<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSeatingOption;

class UpdateLocationSeatingOptionTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.seating-options.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/seating-options/{id} - Aktualisiert eine Bestuhlungsoption. Identifikation: seating_option_id ODER uuid. Felder: label, pax_max_ca, sort_order (alle optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'seating_option_id' => ['type' => 'integer'],
                'uuid'              => ['type' => 'string'],
                'label'             => ['type' => 'string'],
                'pax_max_ca'        => ['type' => 'integer'],
                'sort_order'        => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) return ToolResult::error('AUTH_ERROR', 'Kein User.');

            $query = LocationSeatingOption::query();
            if (!empty($arguments['seating_option_id'])) {
                $query->where('id', (int) $arguments['seating_option_id']);
            } elseif (!empty($arguments['uuid'])) {
                $query->where('uuid', (string) $arguments['uuid']);
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'seating_option_id oder uuid noetig.');
            }

            $row = $query->with('location')->first();
            if (!$row) return ToolResult::error('SEATING_OPTION_NOT_FOUND', 'Nicht gefunden.');

            $teamId = $row->location?->team_id;
            $hasAccess = $teamId && $context->user->teams()->where('teams.id', $teamId)->exists();
            if (!$hasAccess) return ToolResult::error('ACCESS_DENIED', 'Kein Zugriff.');

            $update = [];
            if (array_key_exists('label', $arguments)) {
                $update['label'] = trim((string) $arguments['label']);
            }
            if (array_key_exists('pax_max_ca', $arguments)) {
                $update['pax_max_ca'] = max(0, (int) $arguments['pax_max_ca']);
            }
            if (array_key_exists('sort_order', $arguments)) {
                $update['sort_order'] = (int) $arguments['sort_order'];
            }
            if (empty($update)) return ToolResult::error('VALIDATION_ERROR', 'Keine Felder.');

            $row->update($update);

            return ToolResult::success([
                'id' => $row->id, 'uuid' => $row->uuid, 'location_id' => $row->location_id,
                'label' => $row->label, 'pax_max_ca' => (int) $row->pax_max_ca, 'sort_order' => (int) $row->sort_order,
                'message' => 'Bestuhlungsoption aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'action','tags'=>['locations','seating-options','update'],'read_only'=>false,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'write','idempotent'=>true,'side_effects'=>['updates']];
    }
}
