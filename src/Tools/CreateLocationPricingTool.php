<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Locations\Models\LocationPricing;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class CreateLocationPricingTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.pricings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /locations/{location_id}/pricings - Legt einen Mietpreis pro Tag-Typ an. ERFORDERLICH: location_id ODER location_uuid, day_type_label (Volltext, matcht events_settings.day_types z.B. "Veranstaltungstag"), price_net. OPTIONAL: label, article_number (lose Verknuepfung zum Events-Artikelstamm — bei Einbuchung werden Gruppe/Name/MwSt/EK/Procurement vom Artikel uebernommen, Preis bleibt aus price_net), sort_order.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location_id'    => ['type' => 'integer'],
                'location_uuid'  => ['type' => 'string'],
                'day_type_label' => ['type' => 'string', 'description' => 'Volltext-Match gegen Events-Settings (z.B. "Veranstaltungstag", "Aufbautag").'],
                'price_net'      => ['type' => 'number', 'description' => 'Netto-Mietpreis.'],
                'label'          => ['type' => 'string', 'description' => 'Optional: Anzeige-Label, sonst "Miete <day_type_label>" oder Artikel-Name.'],
                'article_number' => ['type' => 'string', 'description' => 'Optional: Artikelnummer aus dem Events-Stamm (max. 30 Zeichen). Lose Kopplung — bei Einbuchung loest LocationPricingApplicator die Nummer auf und uebernimmt Stammdaten.'],
                'sort_order'     => ['type' => 'integer', 'description' => 'Optional: Sortierreihenfolge.'],
            ],
            'required' => ['day_type_label', 'price_net'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) {
                return $err;
            }

            $dayType = trim((string) ($arguments['day_type_label'] ?? ''));
            if ($dayType === '') {
                return ToolResult::error('VALIDATION_ERROR', 'day_type_label ist erforderlich.');
            }
            if (!array_key_exists('price_net', $arguments) || $arguments['price_net'] === '' || $arguments['price_net'] === null) {
                return ToolResult::error('VALIDATION_ERROR', 'price_net ist erforderlich.');
            }

            $pricing = LocationPricing::create([
                'location_id'    => $location->id,
                'day_type_label' => $dayType,
                'price_net'      => (float) $arguments['price_net'],
                'label'          => isset($arguments['label']) && $arguments['label'] !== '' ? (string) $arguments['label'] : null,
                'article_number' => isset($arguments['article_number']) && $arguments['article_number'] !== ''
                    ? mb_substr((string) $arguments['article_number'], 0, 30)
                    : null,
                'sort_order'     => isset($arguments['sort_order']) ? (int) $arguments['sort_order'] : 0,
            ]);

            return ToolResult::success([
                'id'             => $pricing->id,
                'uuid'           => $pricing->uuid,
                'location_id'    => $pricing->location_id,
                'day_type_label' => $pricing->day_type_label,
                'price_net'      => (float) $pricing->price_net,
                'label'          => $pricing->label,
                'article_number' => $pricing->article_number,
                'sort_order'     => (int) $pricing->sort_order,
                'message'        => "Mietpreis fuer '{$dayType}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['locations', 'pricings', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'write',
            'idempotent' => false,
            'side_effects' => ['creates'],
        ];
    }
}
