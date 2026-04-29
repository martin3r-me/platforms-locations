<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationAddon;
use Platform\Locations\Tools\Concerns\RecommendsMissingLocationFields;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class CreateLocationAddonTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;
    use RecommendsMissingLocationFields;

    protected const KNOWN_FIELDS = [
        'location_id', 'location_uuid',
        'label', 'price_net', 'unit', 'article_number', 'is_active', 'sort_order',
    ];

    public function getName(): string
    {
        return 'locations.addons.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/addons - Legt ein optionales Add-on an. ERFORDERLICH: location_id ODER location_uuid, label, price_net. OPTIONAL: unit (pro_tag|pro_va_tag|einmalig|pro_stueck, Default pro_tag), article_number (lose Verknuepfung zum Events-Artikelstamm — bei Einbuchung werden Gruppe/Name/MwSt/EK/Procurement vom Artikel uebernommen; Preis bleibt aus price_net), is_active (Default true), sort_order.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'    => ['type' => 'integer'],
                'location_uuid'  => ['type' => 'string'],
                'label'          => ['type' => 'string', 'description' => 'z.B. "Heizung", "Buehne".'],
                'price_net'      => ['type' => 'number', 'description' => 'Netto-Preis pro Einheit.'],
                'unit'           => ['type' => 'string', 'enum' => LocationAddon::UNITS, 'description' => 'Default: pro_tag.'],
                'article_number' => ['type' => 'string', 'description' => 'Optional: Artikelnummer aus dem Events-Stamm (max. 30 Zeichen). Lose Kopplung — Stammdaten werden beim Einbuchen vom Artikel uebernommen.'],
                'is_active'      => ['type' => 'boolean', 'description' => 'Default true.'],
                'sort_order'     => ['type' => 'integer'],
            ],
            'required' => ['label', 'price_net'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) return $err;

            $label = trim((string) ($arguments['label'] ?? ''));
            if ($label === '') return ToolResult::error('VALIDATION_ERROR', 'label ist erforderlich.');
            if (!array_key_exists('price_net', $arguments) || $arguments['price_net'] === '' || $arguments['price_net'] === null) {
                return ToolResult::error('VALIDATION_ERROR', 'price_net ist erforderlich.');
            }

            $unit = (string) ($arguments['unit'] ?? LocationAddon::UNIT_PRO_TAG);
            if (!in_array($unit, LocationAddon::UNITS, true)) {
                return ToolResult::error('VALIDATION_ERROR', 'unit muss eine von ' . implode(', ', LocationAddon::UNITS) . ' sein.');
            }

            $row = LocationAddon::create([
                'location_id'    => $location->id,
                'label'          => $label,
                'price_net'      => (float) $arguments['price_net'],
                'unit'           => $unit,
                'article_number' => isset($arguments['article_number']) && $arguments['article_number'] !== ''
                    ? mb_substr((string) $arguments['article_number'], 0, 30)
                    : null,
                'is_active'      => (bool) ($arguments['is_active'] ?? true),
                'sort_order'     => isset($arguments['sort_order']) ? (int) $arguments['sort_order'] : 0,
            ]);

            $ignored = array_values(array_diff(array_keys($arguments), self::KNOWN_FIELDS));
            $hints   = $this->recommendedSubEntityFieldOptions($location->team_id);

            return ToolResult::success([
                'id' => $row->id, 'uuid' => $row->uuid, 'location_id' => $row->location_id,
                'label' => $row->label, 'price_net' => (float) $row->price_net,
                'unit' => $row->unit, 'unit_label' => $row->unitLabel(),
                'article_number' => $row->article_number,
                'is_active' => (bool) $row->is_active, 'sort_order' => (int) $row->sort_order,
                'ignored_fields' => $ignored,
                '_field_hints'   => ['unit' => $hints['unit'] ?? null],
                'message' => "Add-on '{$label}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return ['category'=>'action','tags'=>['locations','addons','create'],'read_only'=>false,'requires_auth'=>true,'requires_team'=>false,'risk_level'=>'write','idempotent'=>false,'side_effects'=>['creates']];
    }
}
