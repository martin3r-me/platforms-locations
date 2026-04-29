<?php

namespace Platform\Locations\Tools\Concerns;

use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;

/**
 * Gemeinsamer Bulk-Create-Loop fuer Sub-Entity-Tools (Pricings, Seating,
 * Addons). Delegiert pro Item an das Single-Create-Tool und sammelt die
 * Ergebnisse. atomic=true (Default) wickelt alles in einer DB-Transaktion ab.
 */
trait RunsBulkCreate
{
    /**
     * @param array<int, array<string,mixed>> $items
     * @return array{results: array<int, array{index:int, ok:bool, data?:array, error?:array}>, summary: array{requested:int, ok:int, failed:int}}
     */
    protected function runBulkCreate(array $items, array $injectPerItem, ToolContract $singleTool, ToolContext $context, bool $atomic, string $entityLabel): array|ToolResult
    {
        $run = function () use ($items, $injectPerItem, $singleTool, $context, $atomic, $entityLabel) {
            $results = [];
            $okCount = 0;
            $failCount = 0;

            foreach ($items as $idx => $item) {
                if (!is_array($item)) {
                    $failCount++;
                    $results[] = [
                        'index' => $idx,
                        'ok'    => false,
                        'error' => ['code' => 'INVALID_ITEM', 'message' => "{$entityLabel}-Item muss ein Objekt sein."],
                    ];
                    if ($atomic) {
                        throw new \RuntimeException(json_encode([
                            'code'         => 'BULK_VALIDATION_ERROR',
                            'message'      => "{$entityLabel} an Index {$idx}: Item muss ein Objekt sein.",
                            'failed_index' => $idx,
                            'results'      => $results,
                        ], JSON_UNESCAPED_UNICODE));
                    }
                    continue;
                }

                $payload = $item + $injectPerItem;
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
                            'code'         => 'BULK_VALIDATION_ERROR',
                            'message'      => "{$entityLabel} an Index {$idx}: {$res->error}",
                            'failed_index' => $idx,
                            'error_code'   => $res->errorCode,
                            'results'      => $results,
                        ], JSON_UNESCAPED_UNICODE));
                    }
                }
            }

            return [
                'results' => $results,
                'summary' => [
                    'requested' => count($items),
                    'ok'        => $okCount,
                    'failed'    => $failCount,
                ],
            ];
        };

        if ($atomic) {
            try {
                return DB::transaction(fn () => $run());
            } catch (\RuntimeException $e) {
                $errorData = json_decode($e->getMessage(), true);
                if (is_array($errorData) && isset($errorData['code'])) {
                    return ToolResult::error($errorData['code'], $errorData['message']);
                }
                throw $e;
            }
        }

        return $run();
    }
}
