@extends('layouts.app')

@section('title', 'Data Migration Update')

@section('css')
<style>
    .sync-status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: bold;
    }
    .sync-status-badge.ready    { background: #dff0d8; color: #3c763d; }
    .sync-status-badge.pending  { background: #fcf8e3; color: #8a6d3b; }
    .sync-status-badge.not-setup{ background: #f2dede; color: #a94442; }
    .sync-stat-box {
        text-align: center;
        padding: 8px 12px;
        border-radius: 6px;
        margin: 4px;
        flex: 1;
        min-width: 120px;
    }
    .sync-stat-box .stat-value { font-size: 24px; font-weight: bold; }
    .sync-stat-box .stat-label { font-size: 11px; color: #888; }
    .migration-action-buttons {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px 10px;
    }
    .migration-action-buttons .btn {
        margin: 0;
        padding: 8px 14px;
        line-height: 1.2;
        border-radius: 4px;
        white-space: nowrap;
    }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1>Data Migration Update
        <small>Migrate fresh data from old POS to new POS</small>
    </h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- AUTO SYNC PANEL                                             -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="box box-success" id="autoSyncBox">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-refresh"></i> Auto Sync
                        <span class="sync-status-badge not-setup" id="syncSetupBadge" style="margin-left:10px;">
                            Checking...
                        </span>
                    </h3>
                    <div class="box-tools pull-right">
                        <button type="button" class="btn btn-box-tool" data-widget="collapse">
                            <i class="fa fa-minus"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <div class="row" style="margin-bottom:10px;">
                        <!-- Stats -->
                        <div class="col-md-12">
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <div class="sync-stat-box" style="background:#e8f5e9;">
                                    <div class="stat-value text-success" id="statPendingOldToNew">—</div>
                                    <div class="stat-label"><i class="fa fa-arrow-right"></i> Pending Old → New</div>
                                </div>
                                <div class="sync-stat-box" style="background:#e3f2fd;">
                                    <div class="stat-value" style="color:#1565c0;" id="statPendingNewToOld">—</div>
                                    <div class="stat-label"><i class="fa fa-arrow-left"></i> Pending New → Old (Quotation)</div>
                                </div>
                                <div class="sync-stat-box" style="background:#f3e5f5;">
                                    <div class="stat-value" style="color:#6a1b9a;" id="stat24h">—</div>
                                    <div class="stat-label"><i class="fa fa-clock-o"></i> Synced last 24h</div>
                                </div>
                                    <div class="sync-stat-box" style="background:#fff3e0;">
                                    <div class="stat-value" style="color:#e65100;" id="statLastSync">—</div>
                                    <div class="stat-label"><i class="fa fa-history"></i> Last Sync</div>
                                </div>
                            </div>
                            <small class="text-muted" style="margin-top:4px;">
                                <i class="fa fa-info-circle"></i> Auto-syncs every minute via <code>php artisan schedule:run</code>
                            </small>
                        </div>
                    </div>

                    <!-- Recent errors (hidden by default) -->
                    <div id="syncErrorsBox" style="display:none; margin-top:8px;">
                        <div class="alert alert-danger" style="margin:0; font-size:12px;">
                            <strong><i class="fa fa-exclamation-triangle"></i> Recent Sync Errors:</strong>
                            <ul id="syncErrorsList" style="margin:4px 0 0 0; padding-left:20px;"></ul>
                        </div>
                    </div>

                    <!-- Auto-refresh indicator -->
                    <div style="text-align:right; margin-top:4px;">
                        <small class="text-muted" id="syncLastRefresh"></small>
                    </div>
                </div>
            </div>
            <!-- ═══════════════════════════════════════════════════════════ -->

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-database"></i> Migration Control Panel
                    </h3>
                </div>
                <div class="box-body">
                    <!-- Migration Info -->
                    <div class="alert alert-info">
                        <h4><i class="icon fa fa-info"></i> Migration Information</h4>
                        <p>This tool will migrate fresh data from the old POS system to the new POS system.</p>
                        <strong>Migration + sync includes:</strong>
                        <ul>
                            <li>Contacts (Customers & Suppliers)</li>
                            <li>Categories</li>
                            <li>Products (Old → New)</li>
                            <li>Quotations (Old ↔ New)</li>
                            <li>Sales / Tax Invoice / Invoice / Billing Receive (Old → New)</li>
                            <li>Payment updates & payment status sync (Old → New)</li>
                        </ul>
                    </div>

                    <!-- Warning -->
                    <div class="alert alert-warning">
                        <h4><i class="icon fa fa-warning"></i> Important!</h4>
                        <p><strong>Before running migration:</strong></p>
                        <ul>
                            <li>Backup your database</li>
                            <li>Ensure old POS database is accessible</li>
                            <li>This process may take several minutes</li>
                            <li>Don't close this page during migration</li>
                        </ul>
                    </div>

                    <!-- Database Configuration -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Old POS Database:</label>
                                <input type="text" class="form-control" value="rubyshop_co_th_sale_pos (127.0.0.1:8889)" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>New POS Database:</label>
                                <input type="text" class="form-control" value="{{ config('database.connections.mysql.database') }} (Current)" readonly>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="text-center migration-action-buttons" style="margin-top: 20px;">
                        <button type="button" id="startMigrationBtn" class="btn btn-primary btn-lg">
                            <i class="fa fa-play"></i> Run Full Migration + Configured Sync
                        </button>

                        <button type="button" id="syncOnlyBtn" class="btn btn-info btn-lg">
                            <i class="fa fa-refresh"></i> Run Configured Sync Only
                        </button>

                        <button type="button" id="syncQuotationBtn" class="btn btn-primary btn-lg" style="background:#1565c0;border-color:#0d47a1;">
                            <i class="fa fa-file-text-o"></i> Sync Quotation Only
                        </button>

                        <button type="button" id="syncPaymentUpdatesBtn" class="btn btn-success btn-lg">
                            <i class="fa fa-money"></i> Sync Payment Updates (Old → New)
                        </button>

                        <button type="button" id="migrateProductsOnlyBtn" class="btn btn-default btn-lg">
                            <i class="fa fa-cubes"></i> Migrate Products Only + Stock Compare
                        </button>

                        <button type="button" id="deleteAllBillsBtn" class="btn btn-danger btn-lg">
                            <i class="fa fa-trash"></i> Delete All Bills (DB)
                        </button>

                        <button type="button" id="deleteAllBillsAllBusinessesBtn" class="btn btn-danger btn-lg" style="background:#a94442;border-color:#843534;">
                            <i class="fa fa-bomb"></i> Delete All Bills (ALL Businesses)
                        </button>

                        <button type="button" id="stopMigrationBtn" class="btn btn-warning btn-lg" style="display: none;">
                            <i class="fa fa-stop"></i> Stop
                        </button>
                    </div>
                    <div class="text-center" style="margin-top: 10px;">
                        <small class="text-muted">
                            <i class="fa fa-info-circle"></i>
                            <strong>Run Full Migration + Configured Sync</strong>: migrate old POS base data, then sync Products/Sales/Tax Invoice/Invoice/Billing Receive/Payments from Old → New, and sync Quotations both ways. &nbsp;|&nbsp;
                            <strong>Run Configured Sync Only</strong>: run the same sync rules without re-running base migration. &nbsp;|&nbsp;
                            <strong>Sync Quotation Only</strong>: run quotation sync only in both directions without touching products, sales, or payment updates. &nbsp;|&nbsp;
                            <strong>Sync Payment Updates (Old → New)</strong>: sync payment status/records from old POS into new POS for already-linked bills. &nbsp;|&nbsp;
                            <strong>Migrate Products Only + Stock Compare</strong>: migrate products and print per-SKU stock logs (old POS vs new POS) for verification before merging to full migration. &nbsp;|&nbsp;
                            <strong>Delete All Bills (DB)</strong>: permanently delete all <code>sell</code>/<code>sell_return</code> bills and related lines/payments from current business in new POS. &nbsp;|&nbsp;
                            <strong>Delete All Bills (ALL Businesses)</strong>: permanently delete bill data across every business in this DB.
                        </small>
                    </div>
                </div>
            </div>

            <!-- Migration Progress -->
            <div class="box box-success" id="migrationProgressBox" style="display: none;">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-refresh fa-spin"></i> Migration Progress
                    </h3>
                </div>
                <div class="box-body">
                    <!-- Progress Bar -->
                    <div class="progress">
                        <div id="progressBar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 0%">
                            <span id="progressText">0%</span>
                        </div>
                    </div>

                    <!-- Log Output -->
                    <div style="margin-top: 20px;">
                        <label>Real-time Migration Log:</label>
                        <div id="logOutput" style="
                            background-color: #1e1e1e;
                            color: #d4d4d4;
                            padding: 15px;
                            border-radius: 5px;
                            font-family: 'Courier New', monospace;
                            font-size: 13px;
                            max-height: 500px;
                            overflow-y: auto;
                            white-space: pre-wrap;
                        ">
                            <span style="color: #4EC9B0;">Waiting to start migration...</span>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="row" style="margin-top: 20px;">
                        <div class="col-md-2">
                            <div class="info-box bg-aqua">
                                <span class="info-box-icon"><i class="fa fa-users"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Contacts</span>
                                    <span class="info-box-number" id="contactsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-navy">
                                <span class="info-box-icon"><i class="fa fa-tags"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Categories</span>
                                    <span class="info-box-number" id="categoriesCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-green">
                                <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Products</span>
                                    <span class="info-box-number" id="productsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-yellow">
                                <span class="info-box-icon"><i class="fa fa-file-text"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Sales Docs</span>
                                    <span class="info-box-number" id="vtCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-blue">
                                <span class="info-box-icon"><i class="fa fa-receipt"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Payment Rows</span>
                                    <span class="info-box-number" id="ipayCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-purple">
                                <span class="info-box-icon"><i class="fa fa-link"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Status Recalc</span>
                                    <span class="info-box-number" id="linkedCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-red">
                                <span class="info-box-icon"><i class="fa fa-money"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Payments</span>
                                    <span class="info-box-number" id="paymentsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-teal">
                                <span class="info-box-icon"><i class="fa fa-list"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Sell Lines</span>
                                    <span class="info-box-number" id="sellLinesCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-orange">
                                <span class="info-box-icon"><i class="fa fa-image"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Images</span>
                                    <span class="info-box-number" id="imagesCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-yellow">
                                <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Stock</span>
                                    <span class="info-box-number" id="stockCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box bg-aqua">
                                <span class="info-box-icon"><i class="fa fa-file-text-o"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Quotations</span>
                                    <span class="info-box-number" id="quotationsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #605ca8; color: white;">
                                <span class="info-box-icon"><i class="fa fa-percent"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Fixed Tax</span>
                                    <span class="info-box-number" id="fixedTaxCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #00a65a; color: white;">
                                <span class="info-box-icon"><i class="fa fa-balance-scale"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Units</span>
                                    <span class="info-box-number" id="unitsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #f39c12; color: white;">
                                <span class="info-box-icon"><i class="fa fa-bookmark"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Brands</span>
                                    <span class="info-box-number" id="brandsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #3c8dbc; color: white;">
                                <span class="info-box-icon"><i class="fa fa-language"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Second Name</span>
                                    <span class="info-box-number" id="secondNameCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #d81b60; color: white;">
                                <span class="info-box-icon"><i class="fa fa-link"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Product Units/Brands</span>
                                    <span class="info-box-number" id="productUnitsBrandsCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #2196f3; color: white;">
                                <span class="info-box-icon"><i class="fa fa-user-circle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Contact Type Fix</span>
                                    <span class="info-box-number" id="contactTypeFixCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #455a64; color: white;">
                                <span class="info-box-icon"><i class="fa fa-random"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Synced Doc/Receiver Fix</span>
                                    <span class="info-box-number" id="syncDocReceiverFixCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #5d4037; color: white;">
                                <span class="info-box-icon"><i class="fa fa-compress"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">NP Duplicate Ref Fix</span>
                                    <span class="info-box-number" id="npDuplicateFixCount">0</span>
                                </div>
                            </div>
                        </div>
                        <!-- Sync counters (shown during runSync) -->
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #1b5e20; color: white;">
                                <span class="info-box-icon"><i class="fa fa-arrow-right"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Synced Old→New</span>
                                    <span class="info-box-number" id="syncedOldToNewCount">0</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="info-box" style="background-color: #0d47a1; color: white;">
                                <span class="info-box-icon"><i class="fa fa-arrow-left"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Synced New→Old (Quotation)</span>
                                    <span class="info-box-number" id="syncedNewToOldCount">0</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('javascript')
<script>
$(document).ready(function() {
    let eventSource = null;
    let migrationRunning = false;
    let quoteSyncedOldToNew = 0;
    let quoteSyncedNewToOld = 0;

    function resetQuotationSyncCounters() {
        quoteSyncedOldToNew = 0;
        quoteSyncedNewToOld = 0;
        $('#quotationsCount').text('0');
    }

    function runSyncPhaseAfterMigration() {
        appendLog('[AUTO] Migration completed. Starting sync...', 'info');

        eventSource = new EventSource('{{ route("migrate-update-data.sync-run") }}');
        let syncProgress = 96;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'done') {
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('100%');
                    appendLog('[DONE] Full migration + sync pipeline completed.', 'success');
                    return;
                }

                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    return;
                }

                appendLog(data.message, data.type);
                extractCounts(data.message);

                syncProgress += 1;
                if (syncProgress > 99) syncProgress = 99;
                $('#progressBar').css('width', syncProgress + '%');
                $('#progressText').text(syncProgress + '%');
            } catch (e) {
                console.error('Error parsing sync SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error during sync. Please check server logs.', 'error');
            if (eventSource) {
                eventSource.close();
            }
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncQuotationBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
            $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
        };
    }

    // Start Migration
    $('#startMigrationBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        // Confirm before starting
        if (!confirm('Run full migration + sync now?\n\nThis will migrate old POS data into new POS, then run configured sync rules automatically:\n• Products/Sales/Payments: Old → New\n• Quotations: Old ↔ New')) {
            return;
        }

        // Show progress box
        $('#migrationProgressBox').slideDown();
        $('#startMigrationBtn').hide();
        $('#syncOnlyBtn').hide();
        $('#syncQuotationBtn').hide();
        $('#syncPaymentUpdatesBtn').hide();
        $('#migrateProductsOnlyBtn').hide();
        $('#stopMigrationBtn').show();
        migrationRunning = true;

        // Clear log
        $('#logOutput').html('<span style="color: #4EC9B0;"> Connecting to server...</span>\n');

        // Reset counters
        $('#contactsCount, #categoriesCount, #productsCount, #vtCount, #ipayCount, #linkedCount, #paymentsCount').text('0');
        $('#syncedOldToNewCount, #syncedNewToOldCount').text('0');
        resetQuotationSyncCounters();
        $('#progressBar').css('width', '0%');
        $('#progressText').text('0%');

        // Create EventSource for SSE
        eventSource = new EventSource('{{ route("migrate-update-data.run") }}', {
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        });

        let progress = 0;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                // Check if done
                if (data.type === 'done') {
                    eventSource.close();
                    $('#progressBar').css('width', '96%');
                    $('#progressText').text('96%');
                    runSyncPhaseAfterMigration();
                    return;
                }

                // Check if error
                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active').addClass('progress-bar-danger');
                    return;
                }

                // Append log message
                appendLog(data.message, data.type);

                // Update progress
                progress += 2;
                if (progress > 95) progress = 95;
                $('#progressBar').css('width', progress + '%');
                $('#progressText').text(progress + '%');

                // Extract counts from messages
                extractCounts(data.message);

            } catch (e) {
                console.error('Error parsing SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error. Please check server logs.', 'error');
            eventSource.close();
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
        };
    });

    // Stop Migration
    $('#stopMigrationBtn').click(function() {
        if (eventSource) {
            eventSource.close();
        }
        migrationRunning = false;
        $('#startMigrationBtn').show();
        $('#syncOnlyBtn').show();
        $('#syncPaymentUpdatesBtn').show();
        $('#migrateProductsOnlyBtn').show();
        $('#stopMigrationBtn').hide();
        appendLog('⚠️ Operation stopped by user', 'warning');
    });

    // Sync New Data Only
    $('#syncOnlyBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Sync new data only?\n\nThis will sync pending data using configured rules without running full migration:\n• Products/Sales/Payments: Old → New\n• Quotations: Old ↔ New')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#startMigrationBtn').hide();
        $('#syncOnlyBtn').hide();
        $('#syncPaymentUpdatesBtn').hide();
        $('#migrateProductsOnlyBtn').hide();
        $('#stopMigrationBtn').show();
        migrationRunning = true;

        $('#logOutput').html('<span style="color: #4EC9B0;"> Connecting to server for sync...</span>\n');
        $('#syncedOldToNewCount, #syncedNewToOldCount').text('0');
        resetQuotationSyncCounters();
        $('#progressBar').css('width', '0%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('0%');

        eventSource = new EventSource('{{ route("migrate-update-data.sync-run") }}');
        let syncProgress = 0;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'done') {
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('100%');
                    appendLog('[DONE] Sync completed.', 'success');
                    return;
                }

                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    return;
                }

                appendLog(data.message, data.type);
                extractCounts(data.message);

                syncProgress += 2;
                if (syncProgress > 99) syncProgress = 99;
                $('#progressBar').css('width', syncProgress + '%');
                $('#progressText').text(syncProgress + '%');
            } catch (e) {
                console.error('Error parsing sync SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error during sync. Please check server logs.', 'error');
            if (eventSource) {
                eventSource.close();
            }
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncQuotationBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
            $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
        };
    });

    // Sync quotations only (Old↔New)
    $('#syncQuotationBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Sync quotations only?\n\nThis will sync pending quotations in both directions:\n• Old POS → New POS\n• New POS → Old POS\n\nProducts, sales, and payment updates will not run.')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#startMigrationBtn').hide();
        $('#syncOnlyBtn').hide();
        $('#syncQuotationBtn').hide();
        $('#syncPaymentUpdatesBtn').hide();
        $('#migrateProductsOnlyBtn').hide();
        $('#stopMigrationBtn').show();
        migrationRunning = true;

        $('#logOutput').html('<span style="color: #4EC9B0;"> Connecting to server for quotation sync...</span>\n');
        $('#syncedOldToNewCount, #syncedNewToOldCount').text('0');
        resetQuotationSyncCounters();
        $('#progressBar').css('width', '0%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('0%');

        eventSource = new EventSource('{{ route("migrate-update-data.sync-quotation") }}');
        let quotationProgress = 0;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'done') {
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('100%');
                    appendLog('[DONE] Quotation sync completed.', 'success');
                    return;
                }

                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    return;
                }

                appendLog(data.message, data.type);
                extractCounts(data.message);

                quotationProgress += 3;
                if (quotationProgress > 99) quotationProgress = 99;
                $('#progressBar').css('width', quotationProgress + '%');
                $('#progressText').text(quotationProgress + '%');
            } catch (e) {
                console.error('Error parsing quotation sync SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error during quotation sync. Please check server logs.', 'error');
            if (eventSource) {
                eventSource.close();
            }
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncQuotationBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
            $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
        };
    });

    // Sync Payment Updates (Old→New for already-synced bills)
    $('#syncPaymentUpdatesBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Sync payment updates (Old → New)?\n\nThis will check already-linked bills and sync payment status / payment records from old POS into new POS.\n\nSafe to run anytime.')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#startMigrationBtn').hide();
        $('#syncOnlyBtn').hide();
        $('#syncQuotationBtn').hide();
        $('#syncPaymentUpdatesBtn').hide();
        $('#migrateProductsOnlyBtn').hide();
        $('#stopMigrationBtn').show();
        migrationRunning = true;

        $('#logOutput').html('<span style="color: #4EC9B0;"> Checking payment updates (Old → New)...</span>\n');
        $('#progressBar').css('width', '0%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('0%');

        eventSource = new EventSource('{{ route("migrate-update-data.sync-payment-updates") }}');
        let payProgress = 0;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'done') {
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('100%');
                    appendLog('[DONE] Payment sync completed.', 'success');
                    return;
                }

                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    return;
                }

                appendLog(data.message, data.type);

                if (data.message.includes('Total Payment Updates:')) {
                    const match = data.message.match(/Total Payment Updates: (\d+)/);
                    if (match) $('#paymentsCount').text(match[1]);
                }

                payProgress += 1;
                if (payProgress > 99) payProgress = 99;
                $('#progressBar').css('width', payProgress + '%');
                $('#progressText').text(payProgress + '%');
            } catch (e) {
                console.error('Error parsing payment sync SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error during payment sync. Please check server logs.', 'error');
            if (eventSource) {
                eventSource.close();
            }
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncQuotationBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
            $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
        };
    });

    // Migrate Products Only + compare stock logs (Old POS vs New POS)
    $('#migrateProductsOnlyBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Run products-only migration with stock compare logs?\n\nThis will:\n• Migrate products only\n• Sync stock from old POS to new POS\n• Print per-SKU comparison logs like:\n  SKU: 79038810 older pos stock = 4 , new pos = 4')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#startMigrationBtn').hide();
        $('#syncOnlyBtn').hide();
        $('#syncQuotationBtn').hide();
        $('#syncPaymentUpdatesBtn').hide();
        $('#migrateProductsOnlyBtn').hide();
        $('#stopMigrationBtn').show();
        migrationRunning = true;

        $('#logOutput').html('<span style="color: #4EC9B0;"> Starting products-only migration + stock compare...</span>\n');
        $('#productsCount, #stockCount').text('0');
        $('#progressBar').css('width', '0%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('0%');

        eventSource = new EventSource('{{ route("migrate-update-data.products-only") }}');

        let productOnlyProgress = 0;

        eventSource.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'done') {
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('100%');
                    appendLog('[DONE] Products-only migration + stock compare completed.', 'success');
                    return;
                }

                if (data.type === 'error') {
                    appendLog(data.message, 'error');
                    eventSource.close();
                    migrationRunning = false;
                    $('#startMigrationBtn').show();
                    $('#syncOnlyBtn').show();
                    $('#syncQuotationBtn').show();
                    $('#syncPaymentUpdatesBtn').show();
                    $('#migrateProductsOnlyBtn').show();
                    $('#stopMigrationBtn').hide();
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    return;
                }

                appendLog(data.message, data.type);
                extractCounts(data.message);

                if (typeof data.progress !== 'undefined') {
                    productOnlyProgress = parseFloat(data.progress) || productOnlyProgress;
                } else {
                    productOnlyProgress += 1;
                    if (productOnlyProgress > 99) productOnlyProgress = 99;
                }
                $('#progressBar').css('width', productOnlyProgress + '%');
                $('#progressText').text(Math.round(productOnlyProgress) + '%');
            } catch (e) {
                console.error('Error parsing products-only SSE data:', e);
            }
        };

        eventSource.onerror = function(error) {
            console.error('EventSource error:', error);
            appendLog('Connection error during products-only migration. Please check server logs.', 'error');
            if (eventSource) {
                eventSource.close();
            }
            migrationRunning = false;
            $('#startMigrationBtn').show();
            $('#syncOnlyBtn').show();
            $('#syncQuotationBtn').show();
            $('#syncPaymentUpdatesBtn').show();
            $('#migrateProductsOnlyBtn').show();
            $('#stopMigrationBtn').hide();
            $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
        };
    });

    // Delete all bills in current business (new POS DB)
    $('#deleteAllBillsBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Delete ALL bills from current business in new POS DB?\n\nThis will permanently remove:\n• sell / sell_return transactions\n• transaction_sell_lines\n• transaction_payments\n• related links/log rows\n\nThis action cannot be undone.')) {
            return;
        }

        if (!confirm('Final confirmation: continue deleting all bills now?')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#logOutput').html('<span style="color: #4EC9B0;"> Deleting all bills from new POS DB...</span>\n');
        $('#progressBar').css('width', '30%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('30%');

        const $btn = $('#deleteAllBillsBtn');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        $.ajax({
            url: '{{ route("migrate-update-data.delete-all-bills") }}',
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (!response || !response.success) {
                    appendLog((response && response.message) ? response.message : 'Delete bills failed.', 'error');
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('Failed');
                    return;
                }

                const data = response.data || {};
                appendLog('[DONE] ' + response.message, 'success');
                appendLog('Transactions Deleted: ' + (data.transactions_deleted || 0), 'success');
                appendLog('Sell Lines Deleted: ' + (data.sell_lines_deleted || 0), 'success');
                appendLog('Payments Deleted: ' + (data.payments_deleted || 0), 'success');
                appendLog('Sell Line/Purchase Links Deleted: ' + (data.sell_line_purchase_links_deleted || 0), 'success');
                appendLog('Sync Logs Deleted: ' + (data.sync_logs_deleted || 0), 'success');

                $('#vtCount').text('0');
                $('#sellLinesCount').text('0');
                $('#paymentsCount').text('0');
                $('#ipayCount').text('0');
                $('#linkedCount').text('0');

                $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                $('#progressBar').css('width', '100%');
                $('#progressText').text('100%');
            },
            error: function(xhr) {
                let message = 'Delete bills failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                appendLog(message, 'error');
                $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                $('#progressBar').css('width', '100%');
                $('#progressText').text('Failed');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Delete all bills in all businesses (new POS DB)
    $('#deleteAllBillsAllBusinessesBtn').click(function() {
        if (migrationRunning) {
            return;
        }

        if (!confirm('Delete ALL bills from ALL businesses in this DB?\n\nThis will permanently remove:\n• sell / sell_return transactions\n• transaction_sell_lines\n• transaction_payments\n• related links/log rows\n\nThis affects every business and cannot be undone.')) {
            return;
        }

        if (!confirm('FINAL WARNING: This will clean bill data for ALL businesses. Continue now?')) {
            return;
        }

        $('#migrationProgressBox').slideDown();
        $('#logOutput').html('<span style="color: #4EC9B0;"> Deleting all bills for ALL businesses...</span>\n');
        $('#progressBar').css('width', '30%').removeClass('progress-bar-success progress-bar-danger').addClass('active');
        $('#progressText').text('30%');

        const $btn = $('#deleteAllBillsAllBusinessesBtn');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

        $.ajax({
            url: '{{ route("migrate-update-data.delete-all-bills-all-businesses") }}',
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (!response || !response.success) {
                    appendLog((response && response.message) ? response.message : 'Delete all-business bills failed.', 'error');
                    $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                    $('#progressBar').css('width', '100%');
                    $('#progressText').text('Failed');
                    return;
                }

                const data = response.data || {};
                appendLog('[DONE] ' + response.message, 'success');
                appendLog('Businesses Affected: ' + (data.businesses_affected || 0), 'success');
                appendLog('Transactions Deleted: ' + (data.transactions_deleted || 0), 'success');
                appendLog('Sell Lines Deleted: ' + (data.sell_lines_deleted || 0), 'success');
                appendLog('Payments Deleted: ' + (data.payments_deleted || 0), 'success');
                appendLog('Sell Line/Purchase Links Deleted: ' + (data.sell_line_purchase_links_deleted || 0), 'success');
                appendLog('Sync Logs Deleted: ' + (data.sync_logs_deleted || 0), 'success');

                $('#vtCount').text('0');
                $('#sellLinesCount').text('0');
                $('#paymentsCount').text('0');
                $('#ipayCount').text('0');
                $('#linkedCount').text('0');

                $('#progressBar').removeClass('active progress-bar-danger').addClass('progress-bar-success');
                $('#progressBar').css('width', '100%');
                $('#progressText').text('100%');
            },
            error: function(xhr) {
                let message = 'Delete all-business bills failed.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                appendLog(message, 'error');
                $('#progressBar').removeClass('active progress-bar-success').addClass('progress-bar-danger');
                $('#progressBar').css('width', '100%');
                $('#progressText').text('Failed');
            },
            complete: function() {
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Append log message
    function appendLog(message, type) {
        const colors = {
            'info': '#4EC9B0',
            'success': '#4CAF50',
            'warning': '#FFA726',
            'error': '#F44336'
        };

        const color = colors[type] || '#d4d4d4';
        const timestamp = new Date().toLocaleTimeString();
        const logEntry = `<span style="color: #808080;">[${timestamp}]</span> <span style="color: ${color};">${message}</span>\n`;

        $('#logOutput').append(logEntry);

        // Auto-scroll to bottom
        const logDiv = document.getElementById('logOutput');
        logDiv.scrollTop = logDiv.scrollHeight;
    }

    // Extract counts from messages
    function extractCounts(message) {
        // Extract contacts count
        const contactsMatch = message.match(/Migrated (\d+) contacts/);
        if (contactsMatch) {
            $('#contactsCount').text(contactsMatch[1]);
        }

        // Extract categories count
        const categoriesMatch = message.match(/Migrated (\d+) categories/);
        if (categoriesMatch) {
            $('#categoriesCount').text(categoriesMatch[1]);
        }
        const categoriesFoundMatch = message.match(/Found (\d+) categories/);
        if (categoriesFoundMatch) {
            $('#categoriesCount').text(categoriesFoundMatch[1]);
        }

        // Extract products count
        const productsMatch = message.match(/Migrated (\d+) products/);
        if (productsMatch) {
            $('#productsCount').text(productsMatch[1]);
        }

        // Extract VT count
        const vtMatch = message.match(/Migrated (\d+) sales/);
        if (vtMatch) {
            $('#vtCount').text(vtMatch[1]);
        }

        // Extract payment-row count
        const ipayMatch = message.match(/Migrated (\d+) payment rows/);
        if (ipayMatch) {
            $('#ipayCount').text(ipayMatch[1]);
        }

        // Extract payment-status recalculation count
        const linkedMatch = message.match(/Payment statuses recalculated: (\d+)/);
        if (linkedMatch) {
            $('#linkedCount').text(linkedMatch[1]);
        }

        // Extract payments count
        const paymentsMatch = message.match(/Migrated (\d+) payments/);
        if (paymentsMatch) {
            $('#paymentsCount').text(paymentsMatch[1]);
            $('#ipayCount').text(paymentsMatch[1]);
        }

        // Summary counts
        if (message.includes('Total Contacts:')) {
            const match = message.match(/Total Contacts: (\d+)/);
            if (match) $('#contactsCount').text(match[1]);
        }
        if (message.includes('Total Categories:')) {
            const match = message.match(/Total Categories: (\d+)/);
            if (match) $('#categoriesCount').text(match[1]);
        }
        if (message.includes('Total Products:')) {
            const match = message.match(/Total Products: (\d+)/);
            if (match) $('#productsCount').text(match[1]);
        }
        if (message.includes('Total Sales')) {
            const match = message.match(/Total Sales.*?: (\d+)/);
            if (match) $('#vtCount').text(match[1]);
        }
        if (message.includes('Total Payment Rows:')) {
            const match = message.match(/Total Payment Rows: (\d+)/);
            if (match) $('#ipayCount').text(match[1]);
        }
        if (message.includes('Payment statuses recalculated:')) {
            const match = message.match(/Payment statuses recalculated: (\d+)/);
            if (match) $('#linkedCount').text(match[1]);
        }
        if (message.includes('Total Payments:')) {
            const match = message.match(/Total Payments: (\d+)/);
            if (match) {
                $('#paymentsCount').text(match[1]);
                $('#ipayCount').text(match[1]);
            }
        }
        if (message.includes('Total Synced Old→New:')) {
            const match = message.match(/Total Synced Old→New: (\d+)/);
            if (match) $('#syncedOldToNewCount').text(match[1]);
        }
        if (message.includes('Total Synced New→Old:')) {
            const match = message.match(/Total Synced New→Old: (\d+)/);
            if (match) $('#syncedNewToOldCount').text(match[1]);
        }
        if (message.includes('Total Quotation Sync Old→New:')) {
            const match = message.match(/Total Quotation Sync Old→New: (\d+)/);
            if (match) $('#syncedOldToNewCount').text(match[1]);
        }
        if (message.includes('Total Quotation Sync New→Old:')) {
            const match = message.match(/Total Quotation Sync New→Old: (\d+)/);
            if (match) $('#syncedNewToOldCount').text(match[1]);
        }

        // Extract sell lines count
        const sellLinesMatch = message.match(/Sell lines created: (\d+)/);
        if (sellLinesMatch) {
            $('#sellLinesCount').text(sellLinesMatch[1]);
        }
        if (message.includes('Items:')) {
            const match = message.match(/Items: (\d+)/);
            if (match) $('#sellLinesCount').text(match[1]);
        }
        if (message.includes('Total Sell Lines:')) {
            const match = message.match(/Total Sell Lines: (\d+)/);
            if (match) $('#sellLinesCount').text(match[1]);
        }

        // Extract stock compare sync count
        if (message.includes('Stock Rows Synced:')) {
            const match = message.match(/Stock Rows Synced: (\d+)/);
            if (match) $('#stockCount').text(match[1]);
        }
        if (message.includes('Processed') && message.includes('rows (synced')) {
            const match = message.match(/rows \(synced (\d+)/);
            if (match) $('#stockCount').text(match[1]);
        }

        // Extract quotations count
        if (message.includes('Total Quotations:')) {
            const match = message.match(/Total Quotations: (\d+)/);
            if (match) $('#quotationsCount').text(match[1]);
        }
        if (message.includes('Total Quotations Synced:')) {
            const match = message.match(/Total Quotations Synced: (\d+)/);
            if (match) $('#quotationsCount').text(match[1]);
        }

        // Extract quotation sync totals from runSync domain summaries.
        const quoteOldToNewSummary = message.match(/Quotations Old.?New: synced (\d+), updated (\d+)/);
        if (quoteOldToNewSummary) {
            quoteSyncedOldToNew = (parseInt(quoteOldToNewSummary[1], 10) || 0) + (parseInt(quoteOldToNewSummary[2], 10) || 0);
            $('#quotationsCount').text(quoteSyncedOldToNew + quoteSyncedNewToOld);
        }
        const quoteNewToOldSummary = message.match(/Quotations New.?Old: synced (\d+), updated (\d+)/);
        if (quoteNewToOldSummary) {
            quoteSyncedNewToOld = (parseInt(quoteNewToOldSummary[1], 10) || 0) + (parseInt(quoteNewToOldSummary[2], 10) || 0);
            $('#quotationsCount').text(quoteSyncedOldToNew + quoteSyncedNewToOld);
        }

        // Fallback for direct quotation sync method completion logs.
        const quoteOldToNewDone = message.match(/OLD.?NEW:QUOTE\] Done: (\d+) synced/i);
        if (quoteOldToNewDone) {
            quoteSyncedOldToNew = parseInt(quoteOldToNewDone[1], 10) || 0;
            $('#quotationsCount').text(quoteSyncedOldToNew + quoteSyncedNewToOld);
        }
        const quoteNewToOldDone = message.match(/NEW.?OLD:QUOTE\] Done: (\d+) synced/i);
        if (quoteNewToOldDone) {
            quoteSyncedNewToOld = parseInt(quoteNewToOldDone[1], 10) || 0;
            $('#quotationsCount').text(quoteSyncedOldToNew + quoteSyncedNewToOld);
        }

        // Extract fixed tax count
        if (message.includes('Total Transactions Fixed:')) {
            const match = message.match(/Total Transactions Fixed: (\d+)/);
            if (match) $('#fixedTaxCount').text(match[1]);
        }
        if (message.includes('Fixed') && message.includes('records')) {
            const match = message.match(/Fixed (\d+)\/\d+ records/);
            if (match) $('#fixedTaxCount').text(match[1]);
        }

        // Extract units count
        if (message.includes('Total Units:')) {
            const match = message.match(/Total Units: (\d+)/);
            if (match) $('#unitsCount').text(match[1]);
        }
        if (message.includes('Migrated:') && message.includes('units')) {
            const match = message.match(/Migrated: (\d+)/);
            if (match) $('#unitsCount').text(match[1]);
        }

        // Extract brands count
        if (message.includes('Total Brands:')) {
            const match = message.match(/Total Brands: (\d+)/);
            if (match) $('#brandsCount').text(match[1]);
        }
        if (message.includes('Migrated:') && message.includes('brands')) {
            const match = message.match(/Migrated: (\d+)/);
            if (match) $('#brandsCount').text(match[1]);
        }

        // Extract second_name count
        if (message.includes('Updated:')) {
            const match = message.match(/Updated: (\d+)/);
            if (match) $('#secondNameCount').text(match[1]);
        }
        if (message.includes('updated') && message.includes('skipped')) {
            const match = message.match(/\((\d+) updated/);
            if (match) $('#secondNameCount').text(match[1]);
        }

        // Extract product units/brands updated count
        if (message.includes('Products Updated:')) {
            const match = message.match(/Products Updated: (\d+)/);
            if (match) $('#productUnitsBrandsCount').text(match[1]);
        }
        if (message.includes('updated') && message.includes('skipped')) {
            const match = message.match(/\((\d+) updated/);
            if (match) $('#productUnitsBrandsCount').text(match[1]);
        }

        // Extract synced doc/receiver fix count
        if (message.includes('Total Synced Records Fixed:')) {
            const match = message.match(/Total Synced Records Fixed: (\d+)/);
            if (match) $('#syncDocReceiverFixCount').text(match[1]);
        }
        if (message.includes('Total NP Duplicates Fixed:')) {
            const match = message.match(/Total NP Duplicates Fixed: (\d+)/);
            if (match) $('#npDuplicateFixCount').text(match[1]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // AUTO SYNC
    // ─────────────────────────────────────────────────────────────────

    // Fetch and display sync status
    function refreshSyncStatus() {
        $.ajax({
            url: '{{ route("migrate-update-data.sync-status") }}',
            method: 'GET',
            success: function(data) {
                if (data.setup_done) {
                    $('#syncSetupBadge')
                        .removeClass('not-setup pending')
                        .addClass('ready')
                        .html('<i class="fa fa-check"></i> Ready');
                } else {
                    $('#syncSetupBadge')
                        .removeClass('ready pending')
                        .addClass('not-setup')
                        .html('<i class="fa fa-exclamation-triangle"></i> Setup Required');
                }

                // Update stats
                var pendingON = parseInt(data.pending_old_to_new || 0);
                var pendingNO = parseInt(data.pending_new_to_old || 0);
                $('#statPendingOldToNew').text(pendingON);
                $('#statPendingNewToOld').text(pendingNO);
                $('#stat24h').text(data.last_24h_synced || 0);

                if (data.last_sync_at) {
                    var d = new Date(data.last_sync_at);
                    var diff = Math.floor((Date.now() - d.getTime()) / 60000);
                    $('#statLastSync').text(diff < 1 ? 'just now' : diff + 'm ago');
                } else {
                    $('#statLastSync').text('Never');
                }

                // Show errors if any
                if (data.recent_errors && data.recent_errors.length > 0) {
                    $('#syncErrorsList').empty();
                    data.recent_errors.forEach(function(err) {
                        $('#syncErrorsList').append(
                            '<li><strong>' + (err.direction || '') + ':</strong> ' +
                            (err.error_message || '').substring(0, 100) + '</li>'
                        );
                    });
                    $('#syncErrorsBox').show();
                } else {
                    $('#syncErrorsBox').hide();
                }

                // Badge color when pending > 0
                if (pendingON > 0 || pendingNO > 0) {
                    $('#syncSetupBadge').removeClass('ready').addClass('pending')
                        .html('<i class="fa fa-clock-o"></i> ' + (pendingON + pendingNO) + ' Pending');
                }

                $('#syncLastRefresh').text('Status refreshed at ' + new Date().toLocaleTimeString());
            },
            error: function() {
                $('#syncSetupBadge').text('Error loading status');
            }
        });
    }

    // Initial load + auto-refresh every 30 seconds
    refreshSyncStatus();
    setInterval(refreshSyncStatus, 30000);

});
</script>
@endsection
