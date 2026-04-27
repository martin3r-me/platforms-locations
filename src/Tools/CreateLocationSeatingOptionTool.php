<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationSeatingOption;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class CreateLocationSeatingOptionTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.seating-options.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/seating-options - Legt eine Bestuhlungsoption (ca.-Wert) an. ERFORDERLICH: location_id ODER location_uuid, label, pax_max_ca. OPTIONAL: sort_order.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'label'         => ['type' => 'string', 'description' => 'z.B. "Reihenbestuhlung", "Runde 10er Tische".'],
                'pax_max_ca'    => ['type' => 'integer', 'description' => 'Schaetzwert "bis zu N PAX".'],
                'sort_order'    => ['type' => 'integer'],
            ],
            'required' => ['label', 'pax_max_ca'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            $label = trim((string) ($arguments['label'] ?? ''));
            if ($label === '') {
                return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            }
            if (!array_key_exists('pax_max_ca', $arguments)) {
                return ToolResult::error('VALIDATION_ERROR', 'pax_max_ca ist erforderlich.');
            }

            $row = LocationSeatingOption::create([
                'location_id' => $location->id,
                'label'       => $label,
                'pax_max_ca'  => max(0, (int) $arguments['pax_max_ca']),
                'sort_order'  => isset($arguments['sort_order']) ? (int) $arguments['sort_order'] : 0,
            ]);

            return ToolResult::success([
                'id' => $row->id, 'uuid' => $row->uuid, 'location_id' => $row->location_id,
                'label' => $row->label, 'pax_max_ca' => (int) $row->pax_max_ca, 'sort_order' => (int) $row->sort_order,
                'message' => "Bestuhlungsoption '{$label}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'action','tags'=>['locations','seating-options','create'],'read_only'=>false,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'write','idempotent'=>false,'side_effects'=>['creates']];
    }
}
