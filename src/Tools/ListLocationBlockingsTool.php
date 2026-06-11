<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationBlocking;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class ListLocationBlockingsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.blockings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/blockings - Listet die Sperrzeiten einer Location (tagesgenau, z. B. Renovierung). Identifikation: location_id, location_uuid, location_kuerzel oder location_ref. Optional: from/to (YYYY-MM-DD) zum Filtern auf ueberlappende Sperren.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => array_merge(self::locationRefSchemaFields(), [
                'from' => ['type' => 'string', 'description' => 'Optional: nur Sperren, die ab diesem Datum (YYYY-MM-DD) ueberlappen.'],
                'to'   => ['type' => 'string', 'description' => 'Optional: nur Sperren, die bis zu diesem Datum (YYYY-MM-DD) ueberlappen.'],
            ]),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            $query = $location->blockings();

            $from = isset($arguments['from']) ? (string) $arguments['from'] : null;
            $to   = isset($arguments['to']) ? (string) $arguments['to'] : null;
            if ($from || $to) {
                $query->overlapping($from ?: '1900-01-01', $to ?: '2999-12-31');
            }

            $rows = $query->get()->map(fn (LocationBlocking $b) => [
                'id'          => $b->id,
                'uuid'        => $b->uuid,
                'location_id' => $b->location_id,
                'start_date'  => $b->start_date?->toDateString(),
                'end_date'    => $b->end_date?->toDateString(),
                'reason'      => $b->reason,
            ])->all();

            return ToolResult::success([
                'blockings'       => $rows,
                'total'           => count($rows),
                'aliases_applied' => $this->resolvedLocationAliases(),
                'message'         => 'Es wurden ' . count($rows) . ' Sperrzeit(en) gefunden.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'query',
            'tags'          => ['locations', 'blockings', 'availability', 'list'],
            'read_only'     => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'safe',
            'idempotent'    => true,
            'side_effects'  => [],
        ];
    }
}
