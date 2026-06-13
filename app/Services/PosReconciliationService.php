<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PosReconciliationService
{
    private int $sampleLimit = 10;

    public function generateReport(): array
    {
        $this->ensureConnections();

        $domains = [
            'bills' => $this->reconcileBills(),
            'products' => $this->reconcileProducts(),
            'stock' => $this->reconcileStock(),
            'customers' => $this->reconcileCustomers(),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'connections' => [
                'old' => 'old_pos',
                'new' => DB::getDefaultConnection(),
            ],
            'summary' => $this->buildSummary($domains),
            'domains' => $domains,
        ];
    }

    public function writeReport(array $report, ?string $filename = null): string
    {
        $filename = $this->normalizeFilename($filename);
        $relativePath = 'reconciliation/' . $filename;

        $json = json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            throw new RuntimeException('Unable to encode reconciliation report as JSON.');
        }

        if (!Storage::disk('local')->put($relativePath, $json)) {
            throw new RuntimeException('Unable to write reconciliation report to storage/app/reconciliation/.');
        }

        return storage_path('app/' . $relativePath);
    }

    private function ensureConnections(): void
    {
        DB::connection('old_pos')->getPdo();
        DB::connection()->getPdo();
    }

    private function reconcileBills(): array
    {
        $oldSales = DB::connection('old_pos')
            ->table('sales')
            ->select('id', 'reference_no')
            ->get();

        $oldPayments = DB::connection('old_pos')
            ->table('payments')
            ->select('id', 'reference_no')
            ->get();

        $newTransactions = DB::table('transactions')
            ->where('type', 'sell')
            ->select('id', 'invoice_no', 'ref_no', 'document_type', 'sub_status')
            ->get();

        $categories = [
            'vt' => [
                'label' => 'VT',
                'report' => $this->buildCountComparisonReport(
                    $this->countReferencesForCategory($oldSales, 'sales', 'vt'),
                    $this->countReferencesForCategory($newTransactions, 'transactions', 'vt'),
                    'reference_no'
                ),
            ],
            'ipay' => [
                'label' => 'IPAY',
                'report' => $this->buildCountComparisonReport(
                    $this->countReferencesForCategory($oldPayments, 'payments', 'ipay'),
                    $this->countReferencesForCategory($newTransactions, 'transactions', 'ipay'),
                    'reference_no'
                ),
            ],
            'invoice' => [
                'label' => 'Invoice',
                'report' => $this->buildCountComparisonReport(
                    $this->countReferencesForCategory($oldSales, 'sales', 'invoice'),
                    $this->countReferencesForCategory($newTransactions, 'transactions', 'invoice'),
                    'reference_no'
                ),
            ],
        ];

        $totals = $this->summarizeCategoryTotals($categories);

        return [
            'status' => $totals['mismatch_total'] > 0 ? 'mismatch' : 'match',
            'old_count' => $totals['old_count'],
            'new_count' => $totals['new_count'],
            'mismatch_total' => $totals['mismatch_total'],
            'categories' => $categories,
        ];
    }

    private function reconcileProducts(): array
    {
        $oldProducts = DB::connection('old_pos')
            ->table('products')
            ->select('id', 'code', 'name')
            ->get();

        $newProducts = DB::table('products')
            ->select('id', 'sku', 'name', 'old_pos_product_id', 'sync_source')
            ->get();

        $oldCounts = $this->countRowsByKey($oldProducts, function ($row) {
            return $this->normalizeProductKey($row->code ?? null, $row->name ?? null, $row->id ?? null);
        });

        $newCounts = $this->countRowsByKey($newProducts, function ($row) {
            return $this->normalizeProductKey($row->sku ?? null, $row->name ?? null, $row->id ?? null);
        });

        return $this->buildCountComparisonReport($oldCounts, $newCounts, 'code/sku');
    }

    private function reconcileStock(): array
    {
        $oldMappings = DB::table('migration_mappings')
            ->where('old_table', 'products')
            ->pluck('new_id', 'old_id')
            ->all();

        $oldLocationMappings = DB::table('migration_mappings')
            ->where('old_table', 'warehouses')
            ->pluck('new_id', 'old_id')
            ->all();

        $oldStockRows = DB::connection('old_pos')
            ->table('warehouses_products')
            ->select('id', 'product_id', 'warehouse_id', 'quantity')
            ->get();

        $newStockRows = DB::table('variation_location_details')
            ->select('id', 'product_id', 'location_id', 'qty_available')
            ->get();

        $oldAggregated = [
            'keys' => [],
            'missing_mapping' => [],
            'row_count' => 0,
        ];

        foreach ($oldStockRows as $row) {
            $oldAggregated['row_count']++;

            $productId = (int) ($row->product_id ?? 0);
            $warehouseId = (int) ($row->warehouse_id ?? 0);
            $newProductId = $oldMappings[$productId] ?? null;
            $newLocationId = $oldLocationMappings[$warehouseId] ?? null;

            if ($newProductId === null || $newLocationId === null) {
                $oldAggregated['missing_mapping'][] = [
                    'old_stock_id' => (int) ($row->id ?? 0),
                    'old_product_id' => $productId,
                    'old_warehouse_id' => $warehouseId,
                    'quantity' => (float) ($row->quantity ?? 0),
                    'mapped_product_id' => $newProductId,
                    'mapped_location_id' => $newLocationId,
                ];
                continue;
            }

            $key = $newProductId . ':' . $newLocationId;
            if (!isset($oldAggregated['keys'][$key])) {
                $oldAggregated['keys'][$key] = [
                    'row_count' => 0,
                    'qty' => 0.0,
                    'product_id' => (int) $newProductId,
                    'location_id' => (int) $newLocationId,
                ];
            }

            $oldAggregated['keys'][$key]['row_count']++;
            $oldAggregated['keys'][$key]['qty'] += (float) ($row->quantity ?? 0);
        }

        $newAggregated = [
            'keys' => [],
            'row_count' => 0,
        ];

        foreach ($newStockRows as $row) {
            $newAggregated['row_count']++;

            $key = (int) ($row->product_id ?? 0) . ':' . (int) ($row->location_id ?? 0);
            if (!isset($newAggregated['keys'][$key])) {
                $newAggregated['keys'][$key] = [
                    'row_count' => 0,
                    'qty' => 0.0,
                    'product_id' => (int) ($row->product_id ?? 0),
                    'location_id' => (int) ($row->location_id ?? 0),
                ];
            }

            $newAggregated['keys'][$key]['row_count']++;
            $newAggregated['keys'][$key]['qty'] += (float) ($row->qty_available ?? 0);
        }

        $missingInNew = [];
        $missingInOld = [];
        $duplicateInOld = [];
        $duplicateInNew = [];
        $quantityMismatch = [];
        $matchedKeys = 0;

        $allKeys = array_values(array_unique(array_merge(
            array_keys($oldAggregated['keys']),
            array_keys($newAggregated['keys'])
        )));

        foreach ($allKeys as $key) {
            $old = $oldAggregated['keys'][$key] ?? null;
            $new = $newAggregated['keys'][$key] ?? null;

            if ($old !== null && $new !== null) {
                $matchedKeys++;
            }

            if ($old !== null && $new === null) {
                $missingInNew[$key] = [
                    'product_id' => $old['product_id'],
                    'location_id' => $old['location_id'],
                    'row_count' => $old['row_count'],
                    'qty' => $old['qty'],
                ];
            }

            if ($new !== null && $old === null) {
                $missingInOld[$key] = [
                    'product_id' => $new['product_id'],
                    'location_id' => $new['location_id'],
                    'row_count' => $new['row_count'],
                    'qty' => $new['qty'],
                ];
            }

            if ($old !== null && $old['row_count'] > 1) {
                $duplicateInOld[$key] = [
                    'row_count' => $old['row_count'],
                    'extra_rows' => $old['row_count'] - 1,
                    'qty' => $old['qty'],
                ];
            }

            if ($new !== null && $new['row_count'] > 1) {
                $duplicateInNew[$key] = [
                    'row_count' => $new['row_count'],
                    'extra_rows' => $new['row_count'] - 1,
                    'qty' => $new['qty'],
                ];
            }

            if ($old !== null && $new !== null && abs($old['qty'] - $new['qty']) > 0.0001) {
                $quantityMismatch[$key] = [
                    'product_id' => $old['product_id'],
                    'location_id' => $old['location_id'],
                    'old_qty' => $old['qty'],
                    'new_qty' => $new['qty'],
                    'delta' => $new['qty'] - $old['qty'],
                ];
            }
        }

        $mismatchTotal = count($oldAggregated['missing_mapping'])
            + count($missingInNew)
            + count($missingInOld)
            + array_sum(array_column($duplicateInOld, 'extra_rows'))
            + array_sum(array_column($duplicateInNew, 'extra_rows'))
            + count($quantityMismatch);

        return [
            'status' => $mismatchTotal > 0 ? 'mismatch' : 'match',
            'old_count' => $oldAggregated['row_count'],
            'new_count' => $newAggregated['row_count'],
            'mismatch_total' => $mismatchTotal,
            'matched_keys' => $matchedKeys,
            'buckets' => [
                'missing_mapping_old' => [
                    'count' => count($oldAggregated['missing_mapping']),
                    'examples' => array_slice($oldAggregated['missing_mapping'], 0, $this->sampleLimit),
                ],
                'missing_in_new' => [
                    'count' => count($missingInNew),
                    'examples' => array_slice(array_values($missingInNew), 0, $this->sampleLimit),
                ],
                'missing_in_old' => [
                    'count' => count($missingInOld),
                    'examples' => array_slice(array_values($missingInOld), 0, $this->sampleLimit),
                ],
                'duplicate_in_old' => [
                    'count' => array_sum(array_column($duplicateInOld, 'extra_rows')),
                    'examples' => array_slice(array_values($duplicateInOld), 0, $this->sampleLimit),
                ],
                'duplicate_in_new' => [
                    'count' => array_sum(array_column($duplicateInNew, 'extra_rows')),
                    'examples' => array_slice(array_values($duplicateInNew), 0, $this->sampleLimit),
                ],
                'quantity_mismatch' => [
                    'count' => count($quantityMismatch),
                    'examples' => array_slice(array_values($quantityMismatch), 0, $this->sampleLimit),
                ],
            ],
        ];
    }

    private function reconcileCustomers(): array
    {
        $oldCustomers = DB::connection('old_pos')
            ->table('companies')
            ->where('group_id', 3)
            ->select('id', 'name', 'company')
            ->get();

        $newCustomers = DB::table('contacts')
            ->where('type', 'customer')
            ->select('id', 'contact_id', 'name', 'supplier_business_name')
            ->get();

        $oldCounts = $this->countRowsByKey($oldCustomers, function ($row) {
            return $this->normalizeContactKey($row->id ?? null);
        });

        $newCounts = $this->countRowsByKey($newCustomers, function ($row) {
            return $this->normalizeContactKey($row->contact_id ?? null);
        });

        return $this->buildCountComparisonReport($oldCounts, $newCounts, 'contact_id');
    }

    private function countReferencesForCategory(iterable $rows, string $sourceType, string $category): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $reference = $this->pickReference($row, $sourceType);
            if ($reference === '') {
                continue;
            }

            $normalized = $this->normalizeReference($reference);

            if (!$this->referenceMatchesCategory($normalized, $row, $sourceType, $category)) {
                continue;
            }

            $counts[$normalized] = ($counts[$normalized] ?? 0) + 1;
        }

        return $counts;
    }

    private function pickReference(object $row, string $sourceType): string
    {
        if ($sourceType === 'payments') {
            return trim((string) ($row->reference_no ?? ''));
        }

        $invoiceNo = trim((string) ($row->invoice_no ?? ''));
        $refNo = trim((string) ($row->ref_no ?? ''));

        if ($invoiceNo !== '') {
            return $invoiceNo;
        }

        return $refNo;
    }

    private function referenceMatchesCategory(string $reference, object $row, string $sourceType, string $category): bool
    {
        $documentType = strtolower(trim((string) ($row->document_type ?? '')));
        $subStatus = strtolower(trim((string) ($row->sub_status ?? '')));

        return match ($category) {
            'vt' => $reference !== '' && (
                str_starts_with($reference, 'VT')
                || $documentType === 'proforma'
                || $subStatus === 'proforma'
            ),
            'ipay' => $reference !== '' && str_starts_with($reference, 'IPAY'),
            'invoice' => $reference !== ''
                && ! str_starts_with($reference, 'VT')
                && ! str_starts_with($reference, 'IPAY')
                && $documentType !== 'quotation'
                && $subStatus !== 'quotation'
                && $documentType !== 'proforma'
                && $subStatus !== 'proforma',
            default => false,
        };
    }

    private function buildCountComparisonReport(array $oldCounts, array $newCounts, string $matchedBy): array
    {
        $missingInNew = [];
        $missingInOld = [];
        $duplicateInOld = [];
        $duplicateInNew = [];
        $matchedCount = 0;

        $allKeys = array_values(array_unique(array_merge(array_keys($oldCounts), array_keys($newCounts))));

        foreach ($allKeys as $key) {
            $oldCount = (int) ($oldCounts[$key] ?? 0);
            $newCount = (int) ($newCounts[$key] ?? 0);

            $matchedCount += min($oldCount, $newCount);

            if ($oldCount > $newCount) {
                $missingInNew[$key] = $oldCount - $newCount;
            }

            if ($newCount > $oldCount) {
                $missingInOld[$key] = $newCount - $oldCount;
            }

            if ($oldCount > 1) {
                $duplicateInOld[$key] = $oldCount - 1;
            }

            if ($newCount > 1) {
                $duplicateInNew[$key] = $newCount - 1;
            }
        }

        $oldTotal = array_sum($oldCounts);
        $newTotal = array_sum($newCounts);
        $mismatchTotal = array_sum($missingInNew)
            + array_sum($missingInOld)
            + array_sum($duplicateInOld)
            + array_sum($duplicateInNew);

        return [
            'status' => $mismatchTotal > 0 ? 'mismatch' : 'match',
            'matched_by' => $matchedBy,
            'old_count' => $oldTotal,
            'new_count' => $newTotal,
            'mismatch_total' => $mismatchTotal,
            'matched_count' => $matchedCount,
            'buckets' => [
                'missing_in_new' => [
                    'count' => array_sum($missingInNew),
                    'examples' => $this->countBucketExamples($missingInNew, $oldCounts, $newCounts),
                ],
                'missing_in_old' => [
                    'count' => array_sum($missingInOld),
                    'examples' => $this->countBucketExamples($missingInOld, $oldCounts, $newCounts),
                ],
                'duplicate_in_old' => [
                    'count' => array_sum($duplicateInOld),
                    'examples' => $this->countBucketExamples($duplicateInOld, $oldCounts, $newCounts),
                ],
                'duplicate_in_new' => [
                    'count' => array_sum($duplicateInNew),
                    'examples' => $this->countBucketExamples($duplicateInNew, $oldCounts, $newCounts),
                ],
            ],
        ];
    }

    private function countBucketExamples(array $bucket, array $oldCounts, array $newCounts): array
    {
        $examples = [];

        foreach ($bucket as $key => $count) {
            $examples[] = [
                'key' => $key,
                'count' => (int) $count,
                'old_count' => (int) ($oldCounts[$key] ?? 0),
                'new_count' => (int) ($newCounts[$key] ?? 0),
            ];

            if (count($examples) >= $this->sampleLimit) {
                break;
            }
        }

        return $examples;
    }

    private function countRowsByKey(iterable $rows, callable $resolver): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $key = trim((string) $resolver($row));
            if ($key === '') {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function normalizeProductKey($primary, $fallback, $rowId): string
    {
        $primaryKey = $this->normalizeTextKey($primary);
        if ($primaryKey !== '') {
            return $primaryKey;
        }

        $fallbackKey = $this->normalizeTextKey($fallback);
        if ($fallbackKey !== '') {
            return $fallbackKey;
        }

        return 'ROW-' . (int) $rowId;
    }

    private function normalizeContactKey($contactId): string
    {
        $contactId = trim((string) $contactId);

        if ($contactId === '') {
            return '';
        }

        if (preg_match('/^\d+$/', $contactId) === 1) {
            return 'C' . str_pad($contactId, 6, '0', STR_PAD_LEFT);
        }

        return $this->normalizeTextKey($contactId);
    }

    private function normalizeReference(string $reference): string
    {
        $reference = trim($reference);
        $reference = str_replace('\\', '/', $reference);
        return mb_strtoupper($reference, 'UTF-8');
    }

    private function normalizeTextKey($value): string
    {
        $value = trim((string) $value);
        $value = str_replace('\\', '/', $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_strtoupper($value, 'UTF-8');
    }

    private function summarizeCategoryTotals(array $categories): array
    {
        $oldCount = 0;
        $newCount = 0;
        $mismatchTotal = 0;

        foreach ($categories as $category) {
            $report = $category['report'] ?? [];
            $oldCount += (int) ($report['old_count'] ?? 0);
            $newCount += (int) ($report['new_count'] ?? 0);
            $mismatchTotal += (int) ($report['mismatch_total'] ?? 0);
        }

        return [
            'old_count' => $oldCount,
            'new_count' => $newCount,
            'mismatch_total' => $mismatchTotal,
        ];
    }

    private function buildSummary(array $domains): array
    {
        $domainStatuses = [];
        $oldCount = 0;
        $newCount = 0;
        $mismatchTotal = 0;

        foreach ($domains as $name => $domain) {
            $domainStatuses[$name] = $domain['status'] ?? 'unknown';
            $oldCount += (int) ($domain['old_count'] ?? 0);
            $newCount += (int) ($domain['new_count'] ?? 0);
            $mismatchTotal += (int) ($domain['mismatch_total'] ?? 0);
        }

        return [
            'overall_status' => $mismatchTotal > 0 ? 'mismatch' : 'match',
            'old_count' => $oldCount,
            'new_count' => $newCount,
            'mismatch_total' => $mismatchTotal,
            'domains' => $domainStatuses,
        ];
    }

    private function normalizeFilename(?string $filename): string
    {
        $filename = trim((string) $filename);

        if ($filename === '') {
            $filename = 'pos-reconciliation-' . now()->format('Ymd-His') . '.json';
        }

        if (!str_ends_with($filename, '.json')) {
            $filename .= '.json';
        }

        $filename = basename($filename);

        if ($filename === '.json') {
            $filename = 'pos-reconciliation-' . now()->format('Ymd-His') . '.json';
        }

        return $filename;
    }
}
