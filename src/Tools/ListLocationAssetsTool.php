<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Services\LocationAssetService;
use Platform\Locations\Tools\Concerns\ResolvesLocation;

class ListLocationAssetsTool implements ToolContract, ToolMetadataContract
{
    use ResolvesLocation;

    public function getName(): string
    {
        return 'locations.assets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/{location_id}/assets/{category} - Listet die Dateien einer Asset-Kategorie an einer Location (S3, ohne DB). Identifikation: location_id ODER location_uuid. category: buffet | seating_plans | photos_with_seating | photos_empty. Wenn category fehlt, werden ALLE Kategorien zurueckgegeben.';
    }

    public function getSchema(): array
    {
        $cats = array_keys(LocationAssetService::categories());
        return [
            'type' => 'object',
            'properties' => [
                'location_id'   => ['type' => 'integer'],
                'location_uuid' => ['type' => 'string'],
                'category'      => [
                    'type' => 'string',
                    'enum' => $cats,
                    'description' => 'Optional. Wenn nicht gesetzt, werden alle Kategorien gelistet.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            [$location, $err] = $this->resolveLocation($arguments, $context);
            if ($err) return $err;

            $service = app(LocationAssetService::class);
            $cats = LocationAssetService::categories();

            if (!empty($arguments['category'])) {
                $cat = (string) $arguments['category'];
                if (!LocationAssetService::isValidCategory($cat)) {
                    return ToolResult::error('VALIDATION_ERROR', "Unbekannte Kategorie '{$cat}'. Erlaubt: " . implode(', ', array_keys($cats)));
                }
                $files = $service->listFiles($location, $cat)->all();
                return ToolResult::success([
                    'location_id' => $location->id,
                    'category'    => $cat,
                    'label'       => $cats[$cat]['label'],
                    'multi'       => (bool) $cats[$cat]['multi'],
                    'files'       => $files,
                    'total'       => count($files),
                    'message'     => "Es wurden " . count($files) . " Datei(en) in Kategorie '{$cats[$cat]['label']}' gefunden.",
                ]);
            }

            // Alle Kategorien
            $byCategory = [];
            foreach (array_keys($cats) as $catKey) {
                $files = $service->listFiles($location, $catKey)->all();
                $byCategory[$catKey] = [
                    'label' => $cats[$catKey]['label'],
                    'multi' => (bool) $cats[$catKey]['multi'],
                    'files' => $files,
                    'total' => count($files),
                ];
            }

            $totalAll = array_sum(array_column($byCategory, 'total'));

            return ToolResult::success([
                'location_id' => $location->id,
                'categories'  => $byCategory,
                'total'       => $totalAll,
                'message'     => "Es wurden insgesamt " . $totalAll . " Datei(en) ueber alle Kategorien gefunden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['locations', 'assets', 'list', 's3'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
            'side_effects' => [],
        ];
    }
}
