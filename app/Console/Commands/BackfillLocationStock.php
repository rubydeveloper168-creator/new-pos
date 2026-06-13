<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class BackfillLocationStock extends Command
{
    protected $signature = 'stock:backfill-location
                            {--business-id= : Business ID (required)}
                            {--from-location= : Source location_id (required)}
                            {--to-location= : Target location_id (required)}
                            {--chunk=500 : Chunk size}
                            {--overwrite : Overwrite target qty_available with source qty}
                            {--apply : Apply changes (default is dry-run)}';

    protected $description = 'Backfill variation_location_details for target location by copying stock from source location';

    public function handle(): int
    {
        $businessId = (int) $this->option('business-id');
        $fromLocation = (int) $this->option('from-location');
        $toLocation = (int) $this->option('to-location');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $overwrite = (bool) $this->option('overwrite');
        $apply = (bool) $this->option('apply');

        if ($businessId <= 0 || $fromLocation <= 0 || $toLocation <= 0) {
            $this->error('Missing required options. Example: php artisan stock:backfill-location --business-id=1 --from-location=1 --to-location=4');
            return Command::FAILURE;
        }
        if ($fromLocation === $toLocation) {
            $this->error('--from-location and --to-location must be different.');
            return Command::FAILURE;
        }

        $this->line('═══════════════════════════════════════');
        $this->info('[Stock Backfill] Starting');
        $this->line('[Stock Backfill] Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN'));
        $this->line("[Stock Backfill] business_id={$businessId}, from={$fromLocation}, to={$toLocation}, chunk={$chunkSize}, overwrite=" . ($overwrite ? 'yes' : 'no'));
        $this->line('═══════════════════════════════════════');

        $stats = [
            'scanned' => 0,
            'target_missing' => 0,
            'target_existing' => 0,
            'product_location_added' => 0,
            'inserted' => 0,
            'updated_overwrite' => 0,
            'updated_fill_zero' => 0,
            'skipped_existing' => 0,
            'failed' => 0,
        ];

        $query = DB::table('variation_location_details as src')
            ->join('products as p', 'p.id', '=', 'src.product_id')
            ->where('p.business_id', $businessId)
            ->where('src.location_id', $fromLocation)
            ->selectRaw('src.variation_id, MAX(src.product_id) as product_id, MAX(src.product_variation_id) as product_variation_id, SUM(src.qty_available) as source_qty')
            ->groupBy('src.variation_id')
            ->orderBy('src.variation_id');

        $lastVariationId = 0;
        while (true) {
            $rows = (clone $query)
                ->where('src.variation_id', '>', $lastVariationId)
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $variationIds = $rows->pluck('variation_id')->all();
            $productIds = $rows->pluck('product_id')->filter()->unique()->all();

            $targetRows = DB::table('variation_location_details')
                ->where('location_id', $toLocation)
                ->whereIn('variation_id', $variationIds)
                ->select('id', 'variation_id', 'qty_available')
                ->orderBy('id')
                ->get()
                ->groupBy('variation_id');

            $productLocationMapped = DB::table('product_locations')
                ->where('location_id', $toLocation)
                ->whereIn('product_id', $productIds)
                ->pluck('product_id')
                ->flip();

            foreach ($rows as $row) {
                $stats['scanned']++;
                $lastVariationId = (int) $row->variation_id;

                try {
                    $sourceQty = (float) $row->source_qty;
                    $variationId = (int) $row->variation_id;
                    $productId = (int) $row->product_id;
                    $productVariationId = (int) $row->product_variation_id;

                    if (!$productLocationMapped->has($productId)) {
                        if ($apply) {
                            $exists = DB::table('product_locations')
                                ->where('product_id', $productId)
                                ->where('location_id', $toLocation)
                                ->exists();
                            if (!$exists) {
                                DB::table('product_locations')->insert([
                                    'product_id' => $productId,
                                    'location_id' => $toLocation,
                                ]);
                            }
                        }
                        $stats['product_location_added']++;
                    }

                    $existing = $targetRows->get($variationId, collect());
                    if ($existing->isEmpty()) {
                        $stats['target_missing']++;

                        if ($apply) {
                            DB::table('variation_location_details')->insert([
                                'product_id' => $productId,
                                'product_variation_id' => $productVariationId,
                                'variation_id' => $variationId,
                                'location_id' => $toLocation,
                                'qty_available' => $sourceQty,
                            ]);
                        }
                        $stats['inserted']++;
                        continue;
                    }

                    $stats['target_existing']++;
                    $firstTarget = $existing->first();
                    $targetQty = (float) ($firstTarget->qty_available ?? 0);

                    if ($overwrite) {
                        if ($apply) {
                            DB::table('variation_location_details')
                                ->where('id', $firstTarget->id)
                                ->update(['qty_available' => $sourceQty]);
                        }
                        $stats['updated_overwrite']++;
                        continue;
                    }

                    if ($targetQty <= 0 && $sourceQty > 0) {
                        if ($apply) {
                            DB::table('variation_location_details')
                                ->where('id', $firstTarget->id)
                                ->update(['qty_available' => $sourceQty]);
                        }
                        $stats['updated_fill_zero']++;
                        continue;
                    }

                    $stats['skipped_existing']++;
                } catch (Throwable $e) {
                    $stats['failed']++;
                    $this->error('[Stock Backfill] Failed variation_id=' . $row->variation_id . ': ' . $e->getMessage());
                }
            }
        }

        $this->line('');
        $this->line('═══════════════════════════════════════');
        $this->info('[Stock Backfill] Completed');
        foreach ($stats as $k => $v) {
            $this->line(sprintf('%s: %d', $k, $v));
        }
        $this->line('═══════════════════════════════════════');

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
