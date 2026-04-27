<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class ListLocationAddonsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.addons.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/addons - Listet die optionalen Add-ons einer Location (z.B. "Heizung 450 EUR pro Tag"). Optional: only_active (boolean) filtert auf is_active=true.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'only_active'   => ['type' => 'boolean', 'description' => 'Optional: nur aktive Add-ons.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) return $err;

            $q = $location->addons();
            if (!empty($arguments['only_active'])) {
                $q->where('is_active', true);
            }

            $rows = $q->get()->map(fn ($r) => [
                'id'         => $r->id,
                'uuid'       => $r->uuid,
                'location_id'=> $r->location_id,
                'label'      => $r->label,
                'price_net'  => (float) $r->price_net,
                'unit'       => $r->unit,
                'unit_label' => $r->unitLabel(),
                'is_active'  => (bool) $r->is_active,
                'sort_order' => (int) $r->sort_order,
            ])->all();

            return ToolResult::success([
                'addons'  => $rows,
                'total'   => count($rows),
                'message' => "Es wurden " . count($rows) . " Add-on(s) gefunden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'query','tags'=>['locations','addons','list'],'read_only'=>true,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'safe','idempotent'=>true,'side_effects'=>[]];
    }
}
