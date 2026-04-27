<?php

namespace Platform\Locations\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Locations\Services\LocationAssetService;

class GetLocationAssetCategoriesTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.asset-categories.GET';
    }

    public function getDescription(): string
    {
        return 'GET /locations/asset-categories - Listet die verfuegbaren Asset-Kategorien (Buffetstationen, Bestuhlungsplaene, Fotos mit/ohne Bestuhlung) inkl. erlaubter Endungen, max. Dateigroesse und Multi-Flag. Hilft AI-Agents, vor einem Delete/List den richtigen category-Slug zu nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext.');
            }

            $cats = LocationAssetService::categories();
            $rows = [];
            foreach ($cats as $key => $cfg) {
                $rows[] = [
                    'key'        => $key,
                    'slug'       => $cfg['slug'],
                    'label'      => $cfg['label'],
                    'multi'      => (bool) $cfg['multi'],
                    'extensions' => $cfg['extensions'],
                    'max_kb'     => (int) $cfg['max_kb'],
                    'max_mb'     => round($cfg['max_kb'] / 1024, 1),
                ];
            }

            return ToolResult::success([
                'categories' => $rows,
                'total'      => count($rows),
                'message'    => "Es wurden " . count($rows) . " Asset-Kategorien gefunden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['locations', 'asset-categories', 'discovery'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
            'side_effects' => [],
        ];
    }
}
