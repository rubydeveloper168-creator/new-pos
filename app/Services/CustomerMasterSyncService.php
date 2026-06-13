<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CustomerMasterSyncService
{
    private const SOURCE_TABLE = 'companies';
    private const TARGET_TABLE = 'contacts';

    private string $oldConnection = 'old_pos';
    private string $newConnection = 'mysql';
    private int $businessId = 1;

    private ?array $oldColumns = null;
    private ?array $newColumns = null;
    private ?array $oldMappingIndex = null;
    private ?array $newMappingIndex = null;

    public function __construct()
    {
        $this->bootOldConnection();
    }

    public function sync(string $direction = 'both', int $limit = 500, bool $dryRun = false, ?callable $log = null): array
    {
        $logger = $log ?? static function (string $message, string $level = 'info'): void {
            // Intentionally empty default logger.
        };

        $direction = strtolower(trim($direction));
        if (!in_array($direction, ['both', 'old-to-new', 'new-to-old'], true)) {
            throw new RuntimeException("Unsupported sync direction: {$direction}");
        }

        $summary = [
            'old_to_new' => $this->emptyCounters(),
            'new_to_old' => $this->emptyCounters(),
        ];

        if (in_array($direction, ['both', 'old-to-new'], true)) {
            $summary['old_to_new'] = $this->syncOldToNew($limit, $dryRun, $logger);
        }

        if (in_array($direction, ['both', 'new-to-old'], true)) {
            $summary['new_to_old'] = $this->syncNewToOld($limit, $dryRun, $logger);
        }

        $summary['totals'] = $this->sumCounters($summary);
        $summary['direction'] = $direction;
        $summary['limit'] = $limit;
        $summary['dry_run'] = $dryRun;

        return $summary;
    }

    private function syncOldToNew(int $limit, bool $dryRun, callable $log): array
    {
        $sourceConnection = $this->oldConnection;
        $targetConnection = $this->newConnection;
        $sourceTable = $this->resolveOldTableName();
        $targetTable = self::TARGET_TABLE;
        $sourceColumns = $this->getColumns($sourceConnection, $sourceTable);
        $targetColumns = $this->getColumns($targetConnection, $targetTable);

        $targetRows = $this->loadTargetRows($targetConnection, $targetTable, $targetColumns, $this->businessId);
        $targetById = $this->indexRowsById($targetRows);
        $targetLookup = $this->buildLookupIndex($targetRows, $targetColumns, false);
        $mappedTargets = $this->getTargetMappings($sourceTable, $targetTable);
        $sourceToTarget = $this->getSourceMappings($sourceTable, $targetTable);

        $query = DB::connection($sourceConnection)->table($sourceTable);
        if ($this->columnExists($sourceConnection, $sourceTable, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($this->columnExists($sourceConnection, $sourceTable, 'group_id')) {
            $query->whereIn('group_id', [3, 4]);
        }
        $sourceRows = $query->orderBy('id')->limit($limit)->get();

        $log("[Customer Sync] Old -> New: found {$sourceRows->count()} source record(s)", 'info');

        $stats = $this->emptyCounters();

        foreach ($sourceRows as $sourceRow) {
            try {
                $result = $this->syncRecord(
                    'old_to_new',
                    $sourceRow,
                    $sourceColumns,
                    $sourceTable,
                    $targetConnection,
                    $targetTable,
                    $targetColumns,
                    $targetById,
                    $targetLookup,
                    $mappedTargets,
                    $sourceToTarget,
                    $dryRun,
                    $log
                );

                $this->accumulateCounters($stats, $result);
            } catch (Throwable $e) {
                $stats['failed']++;
                $log("[Customer Sync] Old -> New failed for #{$sourceRow->id}: {$e->getMessage()}", 'error');
            }
        }

        $log(
            "[Customer Sync] Old -> New summary: created {$stats['created']}, updated {$stats['updated']}, matched {$stats['matched']}, skipped {$stats['skipped']}, conflicts {$stats['conflicts']}, failed {$stats['failed']}",
            $stats['failed'] > 0 ? 'warning' : 'success'
        );

        return $stats;
    }

    private function syncNewToOld(int $limit, bool $dryRun, callable $log): array
    {
        $sourceConnection = $this->newConnection;
        $targetConnection = $this->oldConnection;
        $sourceTable = self::TARGET_TABLE;
        $targetTable = $this->resolveOldTableName();
        $sourceColumns = $this->getColumns($sourceConnection, $sourceTable);
        $targetColumns = $this->getColumns($targetConnection, $targetTable);

        $targetRows = $this->loadTargetRows($targetConnection, $targetTable, $targetColumns, null);
        $targetById = $this->indexRowsById($targetRows);
        $targetLookup = $this->buildLookupIndex($targetRows, $targetColumns, true);
        $mappedTargets = $this->getSourceMappings($targetTable, self::TARGET_TABLE);
        $sourceToTarget = $this->getTargetMappings($targetTable, self::TARGET_TABLE);

        $query = DB::connection($sourceConnection)->table($sourceTable);
        if ($this->columnExists($sourceConnection, $sourceTable, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        if ($this->columnExists($sourceConnection, $sourceTable, 'type')) {
            $query->whereIn('type', ['customer', 'supplier', 'both']);
        }
        $sourceRows = $query->orderBy('id')->limit($limit)->get();

        $log("[Customer Sync] New -> Old: found {$sourceRows->count()} source record(s)", 'info');

        $stats = $this->emptyCounters();

        foreach ($sourceRows as $sourceRow) {
            try {
                $result = $this->syncRecord(
                    'new_to_old',
                    $sourceRow,
                    $sourceColumns,
                    $targetTable,
                    $targetConnection,
                    $targetTable,
                    $targetColumns,
                    $targetById,
                    $targetLookup,
                    $mappedTargets,
                    $sourceToTarget,
                    $dryRun,
                    $log
                );

                $this->accumulateCounters($stats, $result);
            } catch (Throwable $e) {
                $stats['failed']++;
                $log("[Customer Sync] New -> Old failed for #{$sourceRow->id}: {$e->getMessage()}", 'error');
            }
        }

        $log(
            "[Customer Sync] New -> Old summary: created {$stats['created']}, updated {$stats['updated']}, matched {$stats['matched']}, skipped {$stats['skipped']}, conflicts {$stats['conflicts']}, failed {$stats['failed']}",
            $stats['failed'] > 0 ? 'warning' : 'success'
        );

        return $stats;
    }

    private function syncRecord(
        string $direction,
        object $sourceRow,
        array $sourceColumns,
        string $oldTableName,
        string $targetConnection,
        string $targetTable,
        array $targetColumns,
        array &$targetById,
        array &$targetLookup,
        array &$mappedTargets,
        array &$sourceToTarget,
        bool $dryRun,
        callable $log
    ): array {
        $sourceId = (int) $sourceRow->id;
        $sourcePayload = $direction === 'old_to_new'
            ? $this->mapOldToNewPayload($sourceRow, $sourceColumns, $targetColumns)
            : $this->mapNewToOldPayload($sourceRow, $sourceColumns, $targetColumns);

        $match = $this->resolveTargetMatch(
            $direction,
            $sourceRow,
            $sourcePayload,
            $targetTable,
            $targetColumns,
            $targetById,
            $targetLookup,
            $mappedTargets,
            $sourceToTarget
        );

        if ($match['status'] === 'skipped') {
            return ['created' => 0, 'updated' => 0, 'matched' => 0, 'skipped' => 1, 'conflicts' => 0, 'failed' => 0];
        }

        if ($match['status'] === 'created') {
            if ($dryRun) {
                return ['created' => 1, 'updated' => 0, 'matched' => 0, 'skipped' => 0, 'conflicts' => 0, 'failed' => 0];
            }

            $newTargetId = DB::connection($targetConnection)->transaction(function () use ($targetConnection, $targetTable, $sourcePayload, $sourceId, $direction, $sourceRow, $oldTableName) {
                $insertId = DB::connection($targetConnection)->table($targetTable)->insertGetId($sourcePayload);
                $this->saveMapping($direction, $sourceId, (int) $insertId, $sourceRow, false, $oldTableName);
                return (int) $insertId;
            });

            $newTargetRow = (object) array_merge(['id' => $newTargetId], $sourcePayload);
            $targetById[$newTargetId] = $newTargetRow;
            $this->appendLookupIndex($targetLookup, $newTargetRow, $targetColumns, $direction === 'new_to_old');
            $mappedTargets[$newTargetId] = $sourceId;
            $sourceToTarget[$sourceId] = $newTargetId;

            $log("[Customer Sync] Created {$targetTable} #{$newTargetId} from source #{$sourceId}", 'success');

            return ['created' => 1, 'updated' => 0, 'matched' => 0, 'skipped' => 0, 'conflicts' => 0, 'failed' => 0];
        }

        $targetRow = $match['target'];
        $targetId = (int) $targetRow->id;
        $updates = $this->buildNonDestructiveUpdates($sourcePayload, $targetRow, $targetColumns);

        if ($match['mapping_status'] === 'missing') {
            $this->saveMapping($direction, $sourceId, $targetId, $sourceRow, $dryRun, $oldTableName);
        } elseif ($match['mapping_status'] === 'relinked') {
            $this->saveMapping($direction, $sourceId, $targetId, $sourceRow, $dryRun, $oldTableName);
        }

        if (empty($updates['updates'])) {
            if (!empty($updates['conflicts'])) {
                return [
                    'created' => 0,
                    'updated' => 0,
                    'matched' => 0,
                    'skipped' => 0,
                    'conflicts' => $updates['conflicts'],
                    'failed' => 0,
                ];
            }

            return ['created' => 0, 'updated' => 0, 'matched' => 1, 'skipped' => 0, 'conflicts' => 0, 'failed' => 0];
        }

        if ($dryRun) {
            return [
                'created' => 0,
                'updated' => 1,
                'matched' => 0,
                'skipped' => 0,
                'conflicts' => $updates['conflicts'],
                'failed' => 0,
            ];
        }

        DB::connection($targetConnection)->transaction(function () use ($targetConnection, $targetTable, $targetId, $updates, $direction, $sourceId, $sourceRow, $oldTableName) {
            DB::connection($targetConnection)->table($targetTable)
                ->where('id', $targetId)
                ->update($updates['updates']);

            $this->saveMapping($direction, $sourceId, $targetId, $sourceRow, false, $oldTableName);
        });

        $targetById[$targetId] = (object) array_merge((array) $targetRow, $updates['updates']);
        $this->appendLookupIndex($targetLookup, $targetById[$targetId], $targetColumns, $direction === 'new_to_old');
        $mappedTargets[$targetId] = $sourceId;
        $sourceToTarget[$sourceId] = $targetId;

        return [
            'created' => 0,
            'updated' => 1,
            'matched' => 0,
            'skipped' => 0,
            'conflicts' => $updates['conflicts'],
            'failed' => 0,
        ];
    }

    private function resolveTargetMatch(
        string $direction,
        object $sourceRow,
        array $sourcePayload,
        string $targetTable,
        array $targetColumns,
        array $targetById,
        array $targetLookup,
        array $mappedTargets,
        array $sourceToTarget
    ): array {
        $sourceId = (int) $sourceRow->id;
        $mappedTargetId = $sourceToTarget[$sourceId] ?? null;
        if ($mappedTargetId !== null && isset($targetById[$mappedTargetId])) {
            return [
                'status' => 'matched',
                'mapping_status' => 'existing',
                'target' => $targetById[$mappedTargetId],
            ];
        }

        $candidate = $this->matchTargetByFallback($sourcePayload, $targetLookup);
        if ($candidate !== null) {
            if (isset($mappedTargets[$candidate]) && $mappedTargets[$candidate] !== $sourceId) {
                return ['status' => 'skipped'];
            }

            return [
                'status' => 'matched',
                'mapping_status' => isset($mappedTargets[$candidate]) ? 'existing' : 'missing',
                'target' => $targetById[$candidate],
            ];
        }

        return [
            'status' => 'created',
            'mapping_status' => 'missing',
        ];
    }

    private function matchTargetByFallback(array $payload, array $lookup): ?int
    {
        $candidates = [];

        foreach (['contact_id', 'email', 'mobile', 'landline', 'alternate_number', 'name', 'supplier_business_name'] as $field) {
            $value = $this->normalizeLookupValue($payload[$field] ?? null, $field);
            if ($value === null || $value === '') {
                continue;
            }

            $matches = $lookup[$field][$value] ?? [];
            if (count($matches) === 1) {
                return (int) $matches[0];
            }

            if (count($matches) > 1) {
                $candidates = array_merge($candidates, $matches);
            }
        }

        if (count(array_unique($candidates)) === 1) {
            return (int) array_values(array_unique($candidates))[0];
        }

        return null;
    }

    private function buildNonDestructiveUpdates(array $sourcePayload, object $targetRow, array $targetColumns): array
    {
        $updates = [];
        $conflicts = 0;

        foreach ($sourcePayload as $column => $value) {
            if (!in_array($column, $targetColumns, true)) {
                continue;
            }

            $sourceValue = $this->normalizeComparisonValue($value, $column);
            $targetValue = $this->normalizeComparisonValue($targetRow->{$column} ?? null, $column);

            if ($sourceValue === null && $targetValue === null) {
                continue;
            }

            if ($targetValue === null && $sourceValue !== null) {
                $updates[$column] = $value;
                continue;
            }

            if ($sourceValue !== null && $targetValue !== null && $sourceValue === $targetValue) {
                continue;
            }

            if ($sourceValue !== null && $targetValue !== null && $sourceValue !== $targetValue) {
                $conflicts++;
            }
        }

        return [
            'updates' => $updates,
            'conflicts' => $conflicts,
        ];
    }

    private function mapOldToNewPayload(object $row, array $sourceColumns, array $targetColumns): array
    {
        $type = $this->mapGroupIdToNewType($this->getValue($row, 'group_id'));
        $supplierBusinessName = $this->firstNonEmpty([
            $this->getValue($row, 'company'),
            $this->getValue($row, 'supplier_business_name'),
        ]);
        $name = $this->firstNonEmpty([
            $this->getValue($row, 'name'),
            $this->getValue($row, 'first_name'),
            $supplierBusinessName,
        ]);
        $mobile = $this->firstNonEmpty([
            $this->getValue($row, 'mobile'),
            $this->getValue($row, 'phone'),
            $this->getValue($row, 'landline'),
            $this->getValue($row, 'alternate_number'),
        ], '');

        $payload = [
            'business_id' => $this->businessId,
            'type' => $type,
            'contact_type' => $this->mapGroupIdToContactType($this->getValue($row, 'group_id')),
            'supplier_business_name' => $supplierBusinessName,
            'name' => $name,
            'first_name' => $name,
            'middle_name' => $this->getValue($row, 'middle_name'),
            'last_name' => $this->getValue($row, 'last_name'),
            'email' => $this->getValue($row, 'email'),
            'contact_id' => $this->getValue($row, 'contact_id'),
            'tax_number' => $this->firstNonEmpty([
                $this->getValue($row, 'tax_number'),
                $this->getValue($row, 'vat_no'),
                $this->getValue($row, 'gst_no'),
            ]),
            'city' => $this->getValue($row, 'city'),
            'state' => $this->getValue($row, 'state'),
            'country' => $this->getValue($row, 'country'),
            'address_line_1' => $this->firstNonEmpty([
                $this->getValue($row, 'address_line_1'),
                $this->getValue($row, 'address'),
                $this->getValue($row, 'landmark'),
            ]),
            'address_line_2' => $this->getValue($row, 'address_line_2'),
            'zip_code' => $this->firstNonEmpty([
                $this->getValue($row, 'zip_code'),
                $this->getValue($row, 'postal_code'),
            ]),
            'mobile' => $mobile,
            'landline' => $this->firstNonEmpty([
                $this->getValue($row, 'landline'),
                $this->getValue($row, 'phone'),
            ]),
            'alternate_number' => $this->getValue($row, 'alternate_number'),
            'pay_term_number' => $this->getValue($row, 'pay_term_number'),
            'pay_term_type' => $this->getValue($row, 'pay_term_type') ?: $this->inferPayTermType($row),
            'customer_group_id' => $this->getValue($row, 'customer_group_id'),
            'contact_status' => 'active',
            'is_default' => (int) ($this->getValue($row, 'is_default') ?? 0),
            'created_by' => 1,
            'created_at' => $this->getValue($row, 'created_at') ?? now(),
            'updated_at' => now(),
        ];

        if (!in_array('created_at', $targetColumns, true)) {
            unset($payload['created_at']);
        }
        if (!in_array('updated_at', $targetColumns, true)) {
            unset($payload['updated_at']);
        }
        if (!in_array('created_by', $targetColumns, true)) {
            unset($payload['created_by']);
        }
        if (!in_array('is_default', $targetColumns, true)) {
            unset($payload['is_default']);
        }

        return $this->filterByColumns($payload, $targetColumns);
    }

    private function mapNewToOldPayload(object $row, array $sourceColumns, array $targetColumns): array
    {
        $groupId = $this->mapNewTypeToGroupId($this->getValue($row, 'type'));
        $companyLabel = $this->firstNonEmpty([
            $this->getValue($row, 'supplier_business_name'),
            $this->getValue($row, 'name'),
        ]);
        $name = $this->firstNonEmpty([
            $this->getValue($row, 'name'),
            $this->getValue($row, 'first_name'),
            $companyLabel,
        ]);
        $phone = $this->firstNonEmpty([
            $this->getValue($row, 'mobile'),
            $this->getValue($row, 'landline'),
            $this->getValue($row, 'alternate_number'),
        ], '');

        $payload = [
            'group_id' => $groupId,
            'company' => $companyLabel,
            'name' => $name,
            'email' => $this->getValue($row, 'email'),
            'phone' => $phone,
            'mobile' => $phone,
            'landline' => $this->getValue($row, 'landline'),
            'alternate_number' => $this->getValue($row, 'alternate_number'),
            'address' => $this->firstNonEmpty([
                $this->getValue($row, 'address_line_1'),
                $this->getValue($row, 'address'),
            ]),
            'city' => $this->getValue($row, 'city'),
            'state' => $this->getValue($row, 'state'),
            'country' => $this->getValue($row, 'country'),
            'postal_code' => $this->firstNonEmpty([
                $this->getValue($row, 'zip_code'),
                $this->getValue($row, 'postal_code'),
            ]),
            'vat_no' => $this->getValue($row, 'tax_number'),
            'gst_no' => $this->getValue($row, 'tax_number'),
            'contact_id' => $this->getValue($row, 'contact_id'),
            'created' => $this->getValue($row, 'created_at') ?? now(),
        ];

        if (!in_array('created', $targetColumns, true)) {
            unset($payload['created']);
        }

        return $this->filterByColumns($payload, $targetColumns);
    }

    private function saveMapping(string $direction, int $sourceId, int $targetId, object $sourceRow, bool $dryRun = false, ?string $oldTableName = null): void
    {
        if ($dryRun) {
            return;
        }

        $oldTable = $oldTableName ?? $this->resolveOldTableName();
        $newTable = self::TARGET_TABLE;
        $oldId = $direction === 'old_to_new' ? $sourceId : $targetId;
        $newId = $direction === 'old_to_new' ? $targetId : $sourceId;

        DB::table('migration_mappings')->updateOrInsert(
            [
                'old_table' => $oldTable,
                'old_id' => $oldId,
            ],
            [
                'new_table' => $newTable,
                'new_id' => $newId,
                'migrated_at' => now(),
                'notes' => 'Customer master sync',
            ]
        );
    }

    private function loadTargetRows(string $connection, string $table, array $columns, ?int $businessId): array
    {
        $query = DB::connection($connection)->table($table);

        if ($businessId !== null && in_array('business_id', $columns, true)) {
            $query->where('business_id', $businessId);
        }

        if (in_array('deleted_at', $columns, true)) {
            $query->whereNull('deleted_at');
        }

        return $query->orderBy('id')->get()->all();
    }

    private function buildLookupIndex(array $rows, array $columns, bool $isOldSide): array
    {
        $lookup = [
            'contact_id' => [],
            'email' => [],
            'mobile' => [],
            'landline' => [],
            'alternate_number' => [],
            'name' => [],
            'supplier_business_name' => [],
        ];

        foreach ($rows as $row) {
            $id = (int) $row->id;

            foreach (['contact_id', 'email', 'mobile', 'landline', 'alternate_number'] as $field) {
                if (!in_array($field, $columns, true)) {
                    continue;
                }

                $value = $this->normalizeLookupValue($this->getValue($row, $field), $field);
                if ($value === null || $value === '') {
                    continue;
                }

                $lookup[$field][$value][] = $id;
            }

            foreach ($this->nameLookupValues($row, $isOldSide) as $field => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $lookup[$field][$value][] = $id;
            }
        }

        return $lookup;
    }

    private function appendLookupIndex(array &$lookup, object $row, array $columns, bool $isOldSide): void
    {
        $id = (int) $row->id;

        foreach (['contact_id', 'email', 'mobile', 'landline', 'alternate_number'] as $field) {
            if (!in_array($field, $columns, true)) {
                continue;
            }

            $value = $this->normalizeLookupValue($this->getValue($row, $field), $field);
            if ($value === null || $value === '') {
                continue;
            }

            $lookup[$field][$value][] = $id;
        }

        foreach ($this->nameLookupValues($row, $isOldSide) as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $lookup[$field][$value][] = $id;
        }
    }

    private function nameLookupValues(object $row, bool $isOldSide): array
    {
        $values = [];

        $name = $this->normalizeLookupValue($this->getValue($row, 'name'), 'name');
        if ($name !== null && $name !== '') {
            $values['name'] = $name;
        }

        $supplierBusinessName = $this->normalizeLookupValue($this->getValue($row, 'supplier_business_name'), 'supplier_business_name');
        if ($supplierBusinessName !== null && $supplierBusinessName !== '') {
            $values['supplier_business_name'] = $supplierBusinessName;
        }

        if ($isOldSide) {
            $company = $this->normalizeLookupValue($this->getValue($row, 'company'), 'supplier_business_name');
            if ($company !== null && $company !== '') {
                $values['supplier_business_name'] = $company;
            }
        }

        return $values;
    }

    private function indexRowsById(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[(int) $row->id] = $row;
        }

        return $indexed;
    }

    private function getTargetMappings(string $oldTable, string $newTable): array
    {
        if ($this->newMappingIndex !== null && $oldTable === self::SOURCE_TABLE && $newTable === self::TARGET_TABLE) {
            return $this->newMappingIndex;
        }

        $rows = DB::table('migration_mappings')
            ->where('old_table', $oldTable)
            ->where('new_table', $newTable)
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[(int) $row->new_id] = (int) $row->old_id;
        }

        if ($oldTable === self::SOURCE_TABLE && $newTable === self::TARGET_TABLE) {
            $this->newMappingIndex = $index;
        }

        return $index;
    }

    private function getSourceMappings(string $oldTable, string $newTable): array
    {
        if ($this->oldMappingIndex !== null && $oldTable === self::SOURCE_TABLE && $newTable === self::TARGET_TABLE) {
            return $this->oldMappingIndex;
        }

        $rows = DB::table('migration_mappings')
            ->where('old_table', $oldTable)
            ->where('new_table', $newTable)
            ->get();

        $index = [];
        foreach ($rows as $row) {
            $index[(int) $row->old_id] = (int) $row->new_id;
        }

        if ($oldTable === self::SOURCE_TABLE && $newTable === self::TARGET_TABLE) {
            $this->oldMappingIndex = $index;
        }

        return $index;
    }

    private function resolveOldTableName(): string
    {
        if (DB::connection($this->oldConnection)->getSchemaBuilder()->hasTable(self::SOURCE_TABLE)) {
            return self::SOURCE_TABLE;
        }

        if (DB::connection($this->oldConnection)->getSchemaBuilder()->hasTable('sma_' . self::SOURCE_TABLE)) {
            return 'sma_' . self::SOURCE_TABLE;
        }

        throw new RuntimeException('Unable to locate old POS contacts table.');
    }

    private function bootOldConnection(): void
    {
        config(['database.connections.old_pos' => [
            'driver' => 'mysql',
            'host' => env('OLD_POS_DB_HOST', '127.0.0.1'),
            'port' => env('OLD_POS_DB_PORT', '8889'),
            'database' => env('OLD_POS_DB_DATABASE', 'rubyshop_co_th_sale_pos'),
            'username' => env('OLD_POS_DB_USERNAME', 'root'),
            'password' => env('OLD_POS_DB_PASSWORD', 'root'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]]);

        DB::purge('old_pos');
    }

    private function getColumns(string $connection, string $table): array
    {
        if ($connection === $this->oldConnection && $this->oldColumns !== null) {
            return $this->oldColumns;
        }

        if ($connection === $this->newConnection && $this->newColumns !== null) {
            return $this->newColumns;
        }

        $columns = DB::connection($connection)->getSchemaBuilder()->getColumnListing($table);

        if ($connection === $this->oldConnection) {
            $this->oldColumns = $columns;
        }

        if ($connection === $this->newConnection) {
            $this->newColumns = $columns;
        }

        return $columns;
    }

    private function columnExists(string $connection, string $table, string $column): bool
    {
        return in_array($column, $this->getColumns($connection, $table), true);
    }

    private function filterByColumns(array $payload, array $columns): array
    {
        return array_filter(
            $payload,
            static fn ($value, string $key): bool => in_array($key, $columns, true) && $value !== null,
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function emptyCounters(): array
    {
        return [
            'created' => 0,
            'updated' => 0,
            'matched' => 0,
            'skipped' => 0,
            'conflicts' => 0,
            'failed' => 0,
        ];
    }

    private function sumCounters(array $summary): array
    {
        $totals = $this->emptyCounters();

        foreach (['old_to_new', 'new_to_old'] as $direction) {
            foreach ($totals as $key => $value) {
                $totals[$key] += (int) ($summary[$direction][$key] ?? 0);
            }
        }

        return $totals;
    }

    private function accumulateCounters(array &$stats, array $delta): void
    {
        foreach ($stats as $key => $value) {
            $stats[$key] += (int) ($delta[$key] ?? 0);
        }
    }

    private function mapGroupIdToNewType($groupId): string
    {
        return match ((int) $groupId) {
            4 => 'supplier',
            3 => 'customer',
            default => 'customer',
        };
    }

    private function mapNewTypeToGroupId($type): int
    {
        return match (strtolower(trim((string) $type))) {
            'supplier' => 4,
            'both' => 3,
            default => 3,
        };
    }

    private function mapGroupIdToContactType($groupId): ?string
    {
        return match ((int) $groupId) {
            4 => 'business',
            3 => 'individual',
            default => null,
        };
    }

    private function inferPayTermType(object $row): ?string
    {
        $value = $this->getValue($row, 'pay_term_number');
        return $value !== null && $value !== '' ? 'days' : null;
    }

    private function firstNonEmpty(array $values, mixed $default = null): mixed
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '') {
                    return $trimmed;
                }
                continue;
            }

            return $value;
        }

        return $default;
    }

    private function normalizeLookupValue(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (in_array($field, ['mobile', 'landline', 'alternate_number'], true)) {
            $digits = preg_replace('/\D+/', '', $value);
            return $digits !== '' ? $digits : null;
        }

        if ($field === 'email') {
            return Str::lower($value);
        }

        return $this->normalizeText($value);
    }

    private function normalizeComparisonValue(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (in_array($field, ['mobile', 'landline', 'alternate_number', 'phone'], true)) {
            $digits = preg_replace('/\D+/', '', $value);
            return $digits !== '' ? $digits : null;
        }

        if ($field === 'email') {
            return Str::lower($value);
        }

        return $this->normalizeText($value);
    }

    private function normalizeText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        return Str::lower($value ?? '');
    }

    private function getValue(object $row, string $field): mixed
    {
        return property_exists($row, $field) ? $row->{$field} : null;
    }
}
