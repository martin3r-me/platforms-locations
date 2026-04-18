<?php

namespace Platform\Locations\Tools;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Bulk Delete: mehrere Locations in einem Call löschen (Soft Delete).
 *
 * REST-Idee: DELETE /locations/bulk
 */
class BulkDeleteLocationsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'locations.locations.bulk.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /locations/bulk - Löscht mehrere Locations (Soft Delete). Body: {"location_ids":[1,2]} ODER {"uuids":["..."]}. Mind. eines der beiden Felder ist erforderlich. atomic=true (default): alles oder nichts.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'atomic' => [
                    'type' => 'boolean',
                    'description' => 'Optional: Wenn true, wird alles in einer DB-Transaktion ausgeführt. Standard: true.',
                ],
                'location_ids' => [
                    'type' => 'array',
                    'description' => 'IDs der zu löschenden Locations. Alternative/ergänzend zu uuids.',
                    'items' => ['type' => 'integer'],
                ],
                'uuids' => [
                    'type' => 'array',
                    'description' => 'UUIDs der zu löschenden Locations. Alternative/ergänzend zu location_ids.',
                    'items' => ['type' => 'string'],
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

            $targets = [];
            foreach (($arguments['location_ids'] ?? []) as $id) {
                $targets[] = ['location_id' => (int) $id];
            }
            foreach (($arguments['uuids'] ?? []) as $uuid) {
                $targets[] = ['uuid' => (string) $uuid];
            }

            if (empty($targets)) {
                return ToolResult::error('INVALID_ARGUMENT', 'location_ids oder uuids ist erforderlich.');
            }

            $atomic = (bool) ($arguments['atomic'] ?? true);
            $singleTool = new DeleteLocationTool();

            $run = function () use ($targets, $singleTool, $context, $atomic) {
                $results   = [];
                $okCount   = 0;
                $failCount = 0;

                foreach ($targets as $idx => $payload) {
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
                                'message'       => "Löschen an Index {$idx}: {$res->error}",
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
                        'requested' => count($targets),
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
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Bulk-Delete der Locations: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category'      => 'bulk',
            'tags'          => ['locations', 'location', 'bulk', 'batch', 'delete'],
            'read_only'     => false,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level'    => 'high',
            'idempotent'    => true,
            'side_effects'  => ['deletes'],
        ];
    }
}
