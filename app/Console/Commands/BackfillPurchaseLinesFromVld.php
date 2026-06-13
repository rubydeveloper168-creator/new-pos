<?php

namespace App\Console\Commands;

use App\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillPurchaseLinesFromVld extends Command
{
    protected $signature = 'stock:backfill-purchase-lines
                            {--business-id= : Business ID (required)}
                            {--location-id=* : Limit to one or more location IDs}
                            {--sku= : Limit to one SKU}
                            {--chunk=500 : Chunk size}
                            {--max=0 : Maximum variation_location_details rows to process (0 = no limit)}
                            {--created-by= : User ID for created opening stock transactions}
                            {--apply : Apply changes (default is dry-run)}';

    protected $description = 'Backfill missing purchase-line stock (opening_stock) where variation_location_details shows stock but purchase allocation stock is short.';

    private const EPSILON = 0.0001;

    public function handle(): int
    {
        $businessId = (int) $this->option('business-id');
        $locationIds = collect($this->option('location-id'))
            ->filter(function ($value) {
                return is_numeric($value) && (int) $value > 0;
            })
            ->map(function ($value) {
                return (int) $value;
            })
            ->unique()
            ->values()
            ->all();
        $sku = trim((string) $this->option('sku'));
        $chunkSize = max(1, (int) $this->option('chunk'));
        $maxRows = max(0, (int) $this->option('max'));
        $apply = (bool) $this->option('apply');

        if ($businessId <= 0) {
            $this->error('Missing required option --business-id. Example: php artisan stock:backfill-purchase-lines --business-id=1 --apply');

            return Command::FAILURE;
        }

        $createdBy = $this->resolveCreatedByUserId($businessId, $this->option('created-by'));
        if ($createdBy <= 0) {
            $this->error('Unable to resolve a valid user for created_by. Provide --created-by=<user_id>.');

            return Command::FAILURE;
        }

        $mode = $apply ? 'APPLY' : 'DRY-RUN';
        $this->line('═══════════════════════════════════════');
        $this->info('[Purchase Backfill] Starting');
        $this->line('[Purchase Backfill] Mode: ' . $mode);
        $this->line("[Purchase Backfill] business_id={$businessId}, created_by={$createdBy}, chunk={$chunkSize}, max={$maxRows}");
        if (!empty($locationIds)) {
            $this->line('[Purchase Backfill] location filter: ' . implode(',', $locationIds));
        }
        if ($sku !== '') {
            $this->line('[Purchase Backfill] sku filter: ' . $sku);
        }
        $this->line('═══════════════════════════════════════');

        $stats = [
            'scanned_vld_rows' => 0,
            'rows_needing_backfill' => 0,
            'rows_backfilled' => 0,
            'rows_skipped_no_shortage' => 0,
            'rows_failed' => 0,
            'opening_stock_transactions_created' => 0,
            'opening_stock_lines_created' => 0,
            'total_shortage_quantity' => 0.0,
            'total_backfilled_quantity' => 0.0,
            'total_backfilled_value' => 0.0,
        ];

        $lastVldId = 0;
        $processedRows = 0;
        $transactionByLocation = [];
        $productLocationCache = [];
        $note = 'Auto backfill: ensure purchase allocation stock matches variation_location_details (' . Carbon::now()->toDateTimeString() . ')';

        while (true) {
            if ($maxRows > 0 && $processedRows >= $maxRows) {
                break;
            }

            $remaining = $maxRows > 0 ? ($maxRows - $processedRows) : $chunkSize;
            $limit = min($chunkSize, $remaining > 0 ? $remaining : $chunkSize);

            $rows = DB::table('variation_location_details as vld')
                ->join('products as p', 'p.id', '=', 'vld.product_id')
                ->join('variations as v', 'v.id', '=', 'vld.variation_id')
                ->where('p.business_id', $businessId)
                ->where('p.enable_stock', 1)
                ->where('vld.qty_available', '>', 0)
                ->where('vld.id', '>', $lastVldId)
                ->when(!empty($locationIds), function ($query) use ($locationIds) {
                    $query->whereIn('vld.location_id', $locationIds);
                })
                ->when($sku !== '', function ($query) use ($sku) {
                    $query->where('v.sub_sku', $sku);
                })
                ->select(
                    'vld.id',
                    'vld.product_id',
                    'vld.variation_id',
                    'vld.location_id',
                    'vld.qty_available as vld_qty_available',
                    'v.product_variation_id',
                    'v.sub_sku',
                    'v.default_purchase_price',
                    'v.dpp_inc_tax',
                    'p.name as product_name'
                )
                ->orderBy('vld.id')
                ->limit($limit)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $processedRows += $rows->count();
            $stats['scanned_vld_rows'] += $rows->count();
            $lastVldId = (int) $rows->last()->id;

            $variationIds = $rows->pluck('variation_id')->unique()->values()->all();
            $chunkLocationIds = $rows->pluck('location_id')->unique()->values()->all();

            $purchaseAvailability = $this->getPurchaseAvailabilityByVariationAndLocation(
                $businessId,
                $variationIds,
                $chunkLocationIds
            );

            foreach ($rows as $row) {
                $locationId = (int) $row->location_id;
                $variationId = (int) $row->variation_id;
                $productId = (int) $row->product_id;
                $targetQty = (float) $row->vld_qty_available;
                $availableQty = (float) ($purchaseAvailability[$this->availabilityKey($variationId, $locationId)] ?? 0);
                $shortageQty = round($targetQty - $availableQty, 4);

                if ($shortageQty <= self::EPSILON) {
                    $stats['rows_skipped_no_shortage']++;
                    continue;
                }

                $stats['rows_needing_backfill']++;
                $stats['total_shortage_quantity'] += $shortageQty;

                if (!$apply) {
                    $this->line(sprintf(
                        '[DRY-RUN] SKU:%s product_id:%d variation_id:%d location_id:%d target:%s purch_available:%s shortage:%s',
                        $row->sub_sku,
                        $productId,
                        $variationId,
                        $locationId,
                        $this->formatQty($targetQty),
                        $this->formatQty($availableQty),
                        $this->formatQty($shortageQty)
                    ));
                    continue;
                }

                try {
                    $cacheKey = $productId . '_' . $locationId;
                    if (!isset($productLocationCache[$cacheKey])) {
                        DB::table('product_locations')->updateOrInsert(
                            ['product_id' => $productId, 'location_id' => $locationId],
                            ['product_id' => $productId, 'location_id' => $locationId]
                        );
                        $productLocationCache[$cacheKey] = true;
                    }

                    if (empty($transactionByLocation[$locationId])) {
                        $transaction = Transaction::create([
                            'type' => 'opening_stock',
                            'status' => 'received',
                            'business_id' => $businessId,
                            'transaction_date' => Carbon::now(),
                            'total_before_tax' => 0,
                            'location_id' => $locationId,
                            'final_total' => 0,
                            'payment_status' => 'paid',
                            'created_by' => $createdBy,
                            'additional_notes' => $note,
                        ]);

                        $transactionByLocation[$locationId] = $transaction->id;
                        $stats['opening_stock_transactions_created']++;
                    }

                    $purchasePrice = (float) ($row->default_purchase_price ?? 0);
                    $purchasePriceIncTax = (float) ($row->dpp_inc_tax ?? $purchasePrice);
                    if ($purchasePriceIncTax <= 0 && $purchasePrice > 0) {
                        $purchasePriceIncTax = $purchasePrice;
                    }
                    $itemTax = max(0, $purchasePriceIncTax - $purchasePrice);
                    $lineTotal = round($purchasePriceIncTax * $shortageQty, 4);

                    DB::table('purchase_lines')->insert([
                        'transaction_id' => $transactionByLocation[$locationId],
                        'product_id' => $productId,
                        'variation_id' => $variationId,
                        'quantity' => $shortageQty,
                        'pp_without_discount' => $purchasePrice,
                        'purchase_price' => $purchasePrice,
                        'purchase_price_inc_tax' => $purchasePriceIncTax,
                        'item_tax' => $itemTax,
                        'tax_id' => null,
                        'quantity_sold' => 0,
                        'quantity_adjusted' => 0,
                        'quantity_returned' => 0,
                        'mfg_quantity_used' => 0,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);

                    $lineTotalSql = number_format($lineTotal, 4, '.', '');
                    DB::table('transactions')
                        ->where('id', $transactionByLocation[$locationId])
                        ->update([
                            'total_before_tax' => DB::raw('total_before_tax + ' . $lineTotalSql),
                            'final_total' => DB::raw('final_total + ' . $lineTotalSql),
                            'updated_at' => Carbon::now(),
                        ]);

                    $stats['rows_backfilled']++;
                    $stats['opening_stock_lines_created']++;
                    $stats['total_backfilled_quantity'] += $shortageQty;
                    $stats['total_backfilled_value'] += $lineTotal;
                } catch (Throwable $e) {
                    $stats['rows_failed']++;
                    $this->error(sprintf(
                        '[FAILED] SKU:%s variation_id:%d location_id:%d error:%s',
                        $row->sub_sku,
                        $variationId,
                        $locationId,
                        $e->getMessage()
                    ));
                }
            }
        }

        $this->line('');
        $this->line('═══════════════════════════════════════');
        $this->info('[Purchase Backfill] Completed');
        $this->line('scanned_vld_rows: ' . $stats['scanned_vld_rows']);
        $this->line('rows_needing_backfill: ' . $stats['rows_needing_backfill']);
        $this->line('rows_backfilled: ' . $stats['rows_backfilled']);
        $this->line('rows_skipped_no_shortage: ' . $stats['rows_skipped_no_shortage']);
        $this->line('rows_failed: ' . $stats['rows_failed']);
        $this->line('opening_stock_transactions_created: ' . $stats['opening_stock_transactions_created']);
        $this->line('opening_stock_lines_created: ' . $stats['opening_stock_lines_created']);
        $this->line('total_shortage_quantity: ' . $this->formatQty($stats['total_shortage_quantity']));
        $this->line('total_backfilled_quantity: ' . $this->formatQty($stats['total_backfilled_quantity']));
        $this->line('total_backfilled_value: ' . $this->formatQty($stats['total_backfilled_value']));
        $this->line('═══════════════════════════════════════');

        return $stats['rows_failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getPurchaseAvailabilityByVariationAndLocation(int $businessId, array $variationIds, array $locationIds): array
    {
        if (empty($variationIds) || empty($locationIds)) {
            return [];
        }

        $rows = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $businessId)
            ->where('t.status', 'received')
            ->whereIn('t.type', ['purchase', 'purchase_transfer', 'opening_stock', 'production_purchase'])
            ->whereIn('pl.variation_id', $variationIds)
            ->whereIn('t.location_id', $locationIds)
            ->selectRaw(
                'pl.variation_id, t.location_id, SUM(pl.quantity - (pl.quantity_sold + pl.quantity_adjusted + pl.quantity_returned + pl.mfg_quantity_used)) as qty_available'
            )
            ->groupBy('pl.variation_id', 't.location_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$this->availabilityKey((int) $row->variation_id, (int) $row->location_id)] = (float) $row->qty_available;
        }

        return $map;
    }

    private function availabilityKey(int $variationId, int $locationId): string
    {
        return $variationId . '_' . $locationId;
    }

    private function resolveCreatedByUserId(int $businessId, $createdByOption): int
    {
        $candidate = is_numeric($createdByOption) ? (int) $createdByOption : 0;
        if ($candidate > 0) {
            $exists = DB::table('users')
                ->where('id', $candidate)
                ->where('business_id', $businessId)
                ->exists();
            if ($exists) {
                return $candidate;
            }
        }

        $fallback = DB::table('users')
            ->where('business_id', $businessId)
            ->orderBy('id')
            ->value('id');

        return !empty($fallback) ? (int) $fallback : 0;
    }

    private function formatQty(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}

