<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class ListLocationSeatingOptionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.seating-options.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/seating-options - Listet die Bestuhlungsoptionen einer Location (ca.-Werte fuer "bis zu N PAX").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            $rows = $location->seatingOptions()->get()->map(fn ($r) => [
                'id'         => $r->id,
                'uuid'       => $r->uuid,
                'location_id'=> $r->location_id,
                'label'      => $r->label,
                'pax_max_ca' => (int) $r->pax_max_ca,
                'sort_order' => (int) $r->sort_order,
            ])->all();

            return ToolResult::success([
                'seating_options' => $rows,
                'total'           => count($rows),
                'message'         => "Es wurden " . count($rows) . " Bestuhlungsoption(en) gefunden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'query','tags'=>['locations','seating-options','list'],'read_only'=>true,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'safe','idempotent'=>true,'side_effects'=>[]];
    }
}
