<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Tools\Concerns\RecommendsMissingLocationFields;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class ListLocationPricingsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;
    use RecommendsMissingLocationFields;

    public function getName(): string
    {
        return 'locations.pricings.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/pricings - Listet die Mietpreise (pro Tag-Typ) einer Location. Identifikation: location_id ODER location_uuid.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer', 'description' => 'ID der Location.'],
                'location_uuid' => ['type' => 'string', 'description' => 'UUID der Location.'],
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

            $rows = $location->pricings()->get()->map(fn ($p) => [
                'id'             => $p->id,
                'uuid'           => $p->uuid,
                'location_id'    => $p->location_id,
                'day_type_label' => $p->day_type_label,
                'price_net'      => (float) $p->price_net,
                'label'          => $p->label,
                'article_number' => $p->article_number,
                'sort_order'     => (int) $p->sort_order,
            ])->all();

            $hints = $this->recommendedSubEntityFieldOptions($location->team_id);

            return ToolResult::success([
                'pricings'     => $rows,
                'total'        => count($rows),
                '_field_hints' => ['day_type_label' => $hints['day_type_label'] ?? null],
                'message'      => "Es wurden " . count($rows) . " Mietpreis(e) gefunden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['locations', 'pricings', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
            'side_effects' => [],
        ];
    }
}
