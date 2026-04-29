<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Tools\Concerns\ResolvesLocation;
use Platform\Locations\Tools\Concerns\RunsBulkCreate;

class BulkCreateLocationSeatingOptionsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;
    use RunsBulkCreate;

    public function getName(): string
    {
        return 'locations.seating-options.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/seating-options/bulk - Body MUSS {location_id ODER location_uuid, items:[{label, pax_max_ca, sort_order?}, ...]} enthalten. Legt mehrere Bestuhlungs-Hinweise (ca.-Werte) pro Location an. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'atomic' => ['type' => 'boolean', 'description' => 'Default true: DB-Transaktion rundum.'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Liste von Bestuhlungs-Hinweisen. Pflicht je Item: label, pax_max_ca.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label'       => ['type' => 'string'],
                            'pax_max_ca'  => ['type' => 'integer'],
                            'sort_order'  => ['type' => 'integer'],
                        ],
                        'required' => ['label', 'pax_max_ca'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) return $err;

            $items = $arguments['items'] ?? null;
            if (!is_array($items) || empty($items)) {
                return ToolResult::error('INVALID_ARGUMENT', 'items muss ein nicht-leeres Array sein.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $payload = $this->runBulkCreate(
                items: $items,
                injectPerItem: ['location_id' => $location->id],
                singleTool: new CreateLocationSeatingOptionTool(),
                context: $context,
                atomic: $atomic,
                entityLabel: 'SeatingOption',
            );
            if ($payload instanceof ToolResult) {
                return $payload;
            }

            $payload['location_id'] = $location->id;
            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Anlegen der Bestuhlungsoptionen: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'seating-options', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
