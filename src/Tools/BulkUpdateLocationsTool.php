<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Update: mehrere Locations in einem Call aktualisieren.
 *
 * Zwei Modi:
 *  (1) location_ids + data → gleiche Änderung für viele Locations
 *  (2) updates              → individuelle Änderungen pro Location
 *
 * REST-Idee: PATCH /locations/bulk
 */
class BulkUpdateLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.bulk.PATCH';
    }

    public function getDescription(): string
    {
        return 'PATCH /locations/bulk - Aktualisiert mehrere Locations. Zwei Modi: '
            . '(1) {"location_ids":[1,2,3],"data":{"gruppe":"Neu"}} für gemeinsame Änderung. '
            . '(2) {"updates":[{"location_id":1,"name":"..."},{"uuid":"...","pax_max":50}]} für individuelle Änderungen. '
            . 'Genau einer der beiden Modi muss verwendet werden.';
    }

    public function getSchema(): array
    {
        $fields = [
            'name'             => ['type' => 'string'],
            'kuerzel'          => ['type' => 'string'],
            'gruppe'           => ['type' => 'string'],
            'pax_min'          => ['type' => 'integer'],
            'pax_max'          => ['type' => 'integer'],
            'mehrfachbelegung' => ['type' => 'boolean'],
            'adresse'          => ['type' => 'string'],
            'sort_order'       => ['type' => 'integer'],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, wird alles in einer DB-Transaktion ausgeführt. Standard: true.',
                ],
                // Modus 1: gemeinsame Änderung
                'location_ids' => [
                    'type' => 'array',
                    'description' => 'Modus 1: IDs der zu aktualisierenden Locations.',
                    'items' => ['type' => 'integer'],
                ],
                'uuids' => [
                    'type' => 'array',
                    'description' => 'Modus 1 (alternativ zu location_ids): UUIDs der zu aktualisierenden Locations.',
                    'items' => ['type' => 'string'],
                ],
                'data' => [
                    'type' => 'object',
                    'description' => 'Modus 1: Gemeinsame Felder, die auf alle selektierten Locations angewendet werden.',
                    'properties' => $fields,
                ],
                // Modus 2: individuelle Updates
                'updates' => [
                    'type' => 'array',
                    'description' => 'Modus 2: Individuelle Updates. Jedes Item muss location_id ODER uuid enthalten.',
                    'items' => [
                        'type' => 'object',
                        'properties' => array_merge(
                            [
                                'location_id' => ['type' => 'integer'],
                                'uuid'        => ['type' => 'string'],
                            ],
                            $fields
                        ),
                    ],
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'Kein User im Kontext gefunden.');
            }

            $updates = $this->buildUpdateList($arguments);
            if ($updates instanceof ToolResult) {
                return $updates;
            }
            if (empty($updates)) {
                return ToolResult::error('INVALID_ARGUMENT', 'Keine Updates zum Ausführen gefunden.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new UpdateLocationTool();

            $run = function () use ($updates, $singleTool, $context, $atomic) {
                $results   = [];
                $okCount   = 0;
                $failCount = 0;

                foreach ($updates as $idx => $payload) {
                    $res = $singleTool->execute($payload, $context);
                    if ($res->success) {
                        $okCount++;
                        $results[] = ['index' => $idx, 'ok' => true, 'data' => $res->data];
                    } else {
                        $failCount++;
                        $results[] = [
                            'index' => $idx,
                            'ok'    => false,
                            'error' => ['code' => $res->errorCode, 'message' => $res->error],
                        ];

                        if ($atomic) {
                            throw new \RuntimeException(json_encode([
                                'code'          => 'BULK_VALIDATION_ERROR',
                                'message'       => "Update an Index {$idx}: {$res->error}",
                                'failed_index'  => $idx,
                                'error_code'    => $res->errorCode,
                                'error_message' => $res->error,
                                'results'       => $results,
                            ], JSON_UNESCAPED_UNICODE));
                        }
                    }
                }

                return [
                    'results' => $results,
                    'summary' => [
                        'requested' => count($updates),
                        'ok'        => $okCount,
                        'failed'    => $failCount,
                    ],
                ];
            };

            if ($atomic) {
                try {
                    $payload = DB::transaction(fn() => $run());
                } catch (\RuntimeException $e) {
                    $errorData = json_decode($e->getMessage(), true);
                    if (is_array($errorData) && isset($errorData['code'])) {
                        return ToolResult::error($errorData['code'], $errorData['message']);
                    }
                    throw $e;
                }
            } else {
                $payload = $run();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Update der Locations: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int,array<string,mixed>>|ToolResult
     */
    protected function buildUpdateList(array $arguments): array|ToolResult
    {
        $hasMode1 = (!empty($arguments['location_ids']) || !empty($arguments['uuids'])) && !empty($arguments['data']);
        $hasMode2 = !empty($arguments['updates']);

        if ($hasMode1 && $hasMode2) {
            return ToolResult::error('INVALID_ARGUMENT', 'Bitte entweder location_ids/uuids+data ODER updates verwenden – nicht beides.');
        }
        if (!$hasMode1 && !$hasMode2) {
            return ToolResult::error('INVALID_ARGUMENT', 'Entweder location_ids/uuids+data ODER updates angeben.');
        }

        if ($hasMode1) {
            $data = $arguments['data'] ?? [];
            if (!is_array($data) || empty($data)) {
                return ToolResult::error('INVALID_ARGUMENT', 'data muss ein nicht-leeres Objekt sein.');
            }
            $list = [];
            foreach (($arguments['location_ids'] ?? []) as $id) {
                $list[] = array_merge(['location_id' => (int) $id], $data);
            }
            foreach (($arguments['uuids'] ?? []) as $uuid) {
                $list[] = array_merge(['uuid' => (string) $uuid], $data);
            }
            return $list;
        }

        $list = [];
        foreach ($arguments['updates'] as $u) {
            if (!is_array($u)) {
                continue;
            }
            if (empty($u['location_id']) && empty($u['uuid'])) {
                return ToolResult::error('INVALID_ARGUMENT', 'Jedes Update braucht location_id oder uuid.');
            }
            $list[] = $u;
        }
        return $list;
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'location', 'bulk', 'batch', 'update'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'medium',
            'idempotent'    => true,
            'side_effects'  => ['updates'],
        ];
    }
}
