<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Tools\Concerns\RecommendsMissingLocationFields;
use Platform\Locations\Tools\Concerns\ResolvesLocation;
use Platform\Locations\Tools\Concerns\RunsBulkCreate;

class BulkCreateLocationPricingsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;
    use RecommendsMissingLocationFields;
    use RunsBulkCreate;

    public function getName(): string
    {
        return 'locations.pricings.bulk.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/pricings/bulk - Body MUSS {location_id ODER location_uuid, items:[{day_type_label, price_net, label?, article_number?, sort_order?}, ...]} enthalten. Legt mehrere Mietpreise pro Location an. atomic=true (default): alles oder nichts. Ideal fuer Seed-Imports (z.B. drei Pricings fuer Aufbau/VA/Abbau in einem Call).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true (Default), wird alles in einer DB-Transaktion ausgefuehrt.',
                ],
                'items' => [
                    'type' => 'array',
                    'description' => 'Liste von Pricings. Pflicht je Item: day_type_label, price_net.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'day_type_label' => ['type' => 'string'],
                            'price_net'      => ['type' => 'number'],
                            'label'          => ['type' => 'string'],
                            'article_number' => ['type' => 'string'],
                            'sort_order'     => ['type' => 'integer'],
                        ],
                        'required' => ['day_type_label', 'price_net'],
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
                singleTool: new CreateLocationPricingTool(),
                context: $context,
                atomic: $atomic,
                entityLabel: 'Pricing',
            );
            if ($payload instanceof ToolResult) {
                return $payload; // Atomic-Fehler bereits zurueckgegeben
            }

            $payload['location_id']  = $location->id;
            $payload['_field_hints'] = ['day_type_label' => $this->recommendedSubEntityFieldOptions($location->team_id)['day_type_label'] ?? null];

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Anlegen der Pricings: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'pricings', 'bulk', 'create'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => false,
            'side_effects'  => ['creates'],
        ];
    }
}
