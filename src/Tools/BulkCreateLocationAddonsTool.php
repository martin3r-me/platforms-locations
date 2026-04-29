<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Models\LocationAddon;
use Platform\Locations\Tools\Concerns\RecommendsMissingLocationFields;
use Platform\Locations\Tools\Concerns\ResolvesLocation;
use Platform\Locations\Tools\Concerns\RunsBulkCreate;

class BulkCreateLocationAddonsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;
    use RecommendsMissingLocationFields;
    use RunsBulkCreate;

    public function getName(): string
    {
        return 'locations.addons.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/addons/bulk - Body MUSS {location_id ODER location_uuid, items:[{label, price_net, unit?, article_number?, is_active?, sort_order?}, ...]} enthalten. Legt mehrere optionale Add-ons pro Location an. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'atomic' => ['type' => 'boolean', 'description' => 'Default true.'],
                'items' => [
                    'type' => 'array',
                    'description' => 'Liste von Add-ons. Pflicht je Item: label, price_net.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label'          => ['type' => 'string'],
                            'price_net'      => ['type' => 'number'],
                            'unit'           => ['type' => 'string', 'enum' => LocationAddon::UNITS, 'description' => 'Default: pro_tag.'],
                            'article_number' => ['type' => 'string'],
                            'is_active'      => ['type' => 'boolean'],
                            'sort_order'     => ['type' => 'integer'],
                        ],
                        'required' => ['label', 'price_net'],
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
                singleTool: new CreateLocationAddonTool(),
                context: $context,
                atomic: $atomic,
                entityLabel: 'Addon',
            );
            if ($payload instanceof ToolResult) {
                return $payload;
            }

            $payload['location_id']  = $location->id;
            $payload['_field_hints'] = ['unit' => $this->recommendedSubEntityFieldOptions($location->team_id)['unit'] ?? null];

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Anlegen der Add-ons: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'addons', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
