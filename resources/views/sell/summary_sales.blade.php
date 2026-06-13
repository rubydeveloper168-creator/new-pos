@extends('layouts.app')
@section('title', __('lang_v1.summary_sales'))

@section('css')
<style>
    .document-filter-checkbox {
        margin-right: 8px;
    }
    .checkbox-inline {
        display: inline-block;
        vertical-align: middle;
    }
    .checkbox-inline label {
        font-weight: normal;
        margin-bottom: 0;
        cursor: pointer;
        padding-left: 20px;
    }
    .checkbox-inline input[type="checkbox"] {
        margin-left: -20px;
        margin-right: 5px;
    }
    

    .tax-label {
        color: #31708f;
        background-color: #d9edf7;
        border-color: #bce8f1;
        display: inline-block;
        padding: 2px 6px;
        font-size: 12px;
        font-weight: bold;
        border-radius: 4px;
        margin-right: 5px;
    }
    /* Modal styling for transaction details */
    .payment_modal .modal-body {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
    }
    .payment_modal .modal-dialog {
        width: 90%;
        max-width: 1000px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 40px);
    }
    .payment_modal .modal-content {
        border-radius: 6px;
        box-shadow: 0 5px 15px rgba(0,0,0,.5);
    }
    .payment_modal .modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #e5e5e5;
        background-color: #f5f5f5;
    }
    .payment_modal .modal-header .modal-title {
        font-size: 18px;
        font-weight: 500;
    }
    .payment_modal .modal-footer {
        padding: 15px 20px;
        text-align: right;
        border-top: 1px solid #e5e5e5;
        background-color: #f5f5f5;
    }
    
    /* Make table rows clickable */
    #summary_sales_table tbody tr {
        cursor: pointer;
    }
    #summary_sales_table tbody tr:hover {
        background-color: #f5f5f5;
    }
    
    /* Fix dropdown positioning for action column */
    .dataTables_wrapper,
    .table-responsive,
    .box-body {
        overflow: visible !important;
    }
    
    /* Action column dropdown positioning */
    #summary_sales_table .btn-group {
        position: relative;
    }
    
    #summary_sales_table .dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        left: auto;
        z-index: 9999;
        margin-top: 2px;
        min-width: 160px;
        background-color: #fff;
        border: 1px solid rgba(0,0,0,.15);
        border-radius: 4px;
        box-shadow: 0 6px 12px rgba(0,0,0,.175);
        background-clip: padding-box;
        display: none;
    }
    
    /* Show dropdown when parent has open class */
    #summary_sales_table .btn-group.open .dropdown-menu {
        display: block;
    }
    
    /* Fix table layout and action buttons */
    #summary_sales_table {
        table-layout: fixed;
        width: 100%;
    }
    
    /* Date column (now first) */
    #summary_sales_table th:nth-child(1),
    #summary_sales_table td:nth-child(1) {
        width: 100px;
        min-width: 100px;
    }
    
    /* Invoice No column */
    #summary_sales_table th:nth-child(2),
    #summary_sales_table td:nth-child(2) {
        width: 130px;
        min-width: 130px;
    }
    
    /* Document Type column */
    #summary_sales_table th:nth-child(3),
    #summary_sales_table td:nth-child(3) {
        width: 100px;
        min-width: 100px;
    }
    
    /* Customer Name column */
    #summary_sales_table th:nth-child(4),
    #summary_sales_table td:nth-child(4) {
        width: 180px;
        min-width: 180px;
    }
    
    /* Contact column */
    #summary_sales_table th:nth-child(5),
    #summary_sales_table td:nth-child(5) {
        width: 120px;
        min-width: 120px;
    }
    
    /* Location column */
    #summary_sales_table th:nth-child(6),
    #summary_sales_table td:nth-child(6) {
        width: 100px;
        min-width: 100px;
    }
    
    /* Payment Status column */
    #summary_sales_table th:nth-child(7),
    #summary_sales_table td:nth-child(7) {
        width: 100px;
        min-width: 100px;
    }
    
    /* Action column styling (last column) */
    #summary_sales_table th:last-child,
    #summary_sales_table td:last-child {
        width: 120px;
        min-width: 120px;
        max-width: 120px;
        overflow: visible !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
    }
    
    /* Amount columns */
    #summary_sales_table th:nth-child(8),
    #summary_sales_table td:nth-child(8),
    #summary_sales_table th:nth-child(9),
    #summary_sales_table td:nth-child(9),
    #summary_sales_table th:nth-child(10),
    #summary_sales_table td:nth-child(10),
    #summary_sales_table th:nth-child(11),
    #summary_sales_table td:nth-child(11) {
        width: 100px;
        min-width: 100px;
        text-align: right;
    }
    
    /* Ensure table allows dropdown overflow */
    #summary_sales_table_wrapper {
        overflow: visible;
    }
    
    .dataTables_wrapper {
        overflow: visible;
    }
    
    /* Ensure text doesn't overflow except for action column */
    #summary_sales_table td:not(:last-child) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: 8px 4px;
    }
    
    #summary_sales_table td {
        padding: 8px 4px;
    }
    
    /* Action column should not truncate */
    #summary_sales_table td:last-child {
        overflow: visible;
        white-space: nowrap;
        text-overflow: initial;
    }
    
    /* Make table responsive but allow dropdown overflow */
    .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
        min-height: 400px;
    }
    
    /* Fix action button display */
    #summary_sales_table .btn-group {
        display: inline-block;
        vertical-align: middle;
    }
    
    #summary_sales_table .btn {
        display: inline-block;
        margin-bottom: 0;
        font-weight: normal;
        text-align: center;
        vertical-align: middle;
        cursor: pointer;
        background-image: none;
        border: 1px solid transparent;
        white-space: nowrap;
        padding: 6px 12px;
        font-size: 14px;
        line-height: 1.42857143;
        border-radius: 4px;
    }
    
    #summary_sales_table .btn-info {
        color: #fff;
        background-color: #5bc0de;
        border-color: #46b8da;
    }
    
    #summary_sales_table .btn-xs {
        padding: 1px 5px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 3px;
    }
    
    /* Highlight newly created billing receipt */
    .highlight-new-record {
        background-color: #ffebee !important;
        border: 2px solid #f44336 !important;
        animation: pulse-highlight 2s ease-in-out;
    }
    
    @keyframes pulse-highlight {
        0% { background-color: #ffcdd2; }
        50% { background-color: #ef5350; }
        100% { background-color: #ffebee; }
    }

    /* Highlight newly created tax invoice */
    .highlight-new-tax-invoice {
        background-color: #ffebee !important;
        border: 2px solid #f44336 !important;
        animation: pulse-highlight-red 3s ease-in-out;
    }
    
    @keyframes pulse-highlight-red {
        0% { background-color: #ffcdd2; }
        25% { background-color: #ef5350; }
        50% { background-color: #f44336; }
        75% { background-color: #ef5350; }
        100% { background-color: #ffebee; }
    }
    
    /* Loading overlay for table refresh */
    .table-loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.8);
        z-index: 1000;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    
    .table-loading-overlay .loading-spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #3498db;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin-bottom: 10px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    #summary_sales_table_wrapper {
        position: relative;
    }
    
    /* Clickable payment status styling */
    .clickable-payment-status {
        transition: opacity 0.2s ease;
    }
    
    .clickable-payment-status:hover {
        opacity: 0.7;
    }
    
    /* Enhanced modal styling */
    .view_modal .modal-dialog,
    .view_modal .modal-dialog.modal-xl {
        width: 98%;
        max-width: 1400px;
        margin: 80px auto;
    }
    
    .loading-spinner .spinner-border {
        animation: spinner-border 0.75s linear infinite;
    }
    
    @keyframes spinner-border {
        to { transform: rotate(360deg); }
    }
    
    .modal-content {
        border: none !important;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
    }
    
    .modal-header {
        border-bottom: none !important;
    }
    
    .modal-footer {
        border-top: 1px solid #e9ecef !important;
    }
    
    /* Smooth modal transitions */
    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: translate(0, -50px);
    }

    .modal.show .modal-dialog {
        transform: none;
    }

    /* Export Section Styles */
    .export-section {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 15px;
    }
    .export-section .section-title {
        font-weight: 600;
        color: #495057;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .export-section .export-date-range {
        display: inline-block;
        width: 280px;
        margin-right: 15px;
    }
    .export-section .export-btn {
        padding: 8px 20px;
        font-weight: 500;
        border-radius: 5px;
        margin-right: 8px;
    }
    .export-section .btn-csv {
        background-color: #28a745;
        border-color: #28a745;
        color: #fff;
    }
    .export-section .btn-csv:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .export-section .btn-xlsx {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: #fff;
    }
    .export-section .btn-xlsx:hover {
        background-color: #138496;
        border-color: #117a8b;
    }
    .export-section .fa-spin {
        margin-right: 5px;
    }
    .export-section .export-filter-group {
        margin-top: 10px;
    }
    .export-section .export-columns {
        margin-top: 10px;
        padding: 10px;
        background: #fff;
        border: 1px solid #e9ecef;
        border-radius: 6px;
    }
    .export-section .export-columns .checkbox-inline {
        margin-right: 12px;
        margin-bottom: 6px;
    }
    .export-toggle-btn {
        padding: 6px 14px;
        font-weight: 600;
        border-radius: 5px;
        margin-bottom: 8px;
    }

    @media print {
        * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
        @page { margin: 10mm; }
        body.modal-printing > *:not(#modal-print-container) { display: none !important; }
        body.modal-printing #modal-print-container { display: block !important; }
        body.modal-printing #modal-print-container .modal-header { display: none !important; }
        body.modal-printing #modal-print-container .modal-footer { display: none !important; }
        body.modal-printing #modal-print-container .no-print { display: none !important; }
        body.modal-printing #modal-print-container .modal-dialog { margin: 0 !important; max-width: 100% !important; width: 100% !important; box-shadow: none !important; transform: none !important; }
        body.modal-printing #modal-print-container .modal-content { box-shadow: none !important; border: none !important; border-radius: 0 !important; }
    }
</style>
@endsection

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header no-print">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
            @lang('lang_v1.summary_sales')
            <small class="text-muted">(@lang('lang_v1.final_bills') & @lang('lang_v1.proforma_invoices'))</small>
        </h1>
    </section>

    <!-- Main content -->
    <section class="content no-print">
        <!-- Sales Summary Cards (Hidden for now) -->
        {{--
        <div class="row" id="sales-summary-cards" style="margin-bottom: 20px;">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3 id="monthly-sales-total">
                            <i class="fa fa-spinner fa-spin"></i>
                        </h3>
                        <p id="monthly-sales-label">ยอดขายเดือนนี้</p>
                        <p class="text-sm" id="monthly-sales-count" style="margin: 0; font-size: 12px;"></p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-calendar"></i>
                    </div>
                    <div class="small-box-footer" id="monthly-sales-change">
                        กำลังโหลด...
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3 id="yearly-sales-total">
                            <i class="fa fa-spinner fa-spin"></i>
                        </h3>
                        <p id="yearly-sales-label">ยอดขายปีนี้</p>
                        <p class="text-sm" id="yearly-sales-count" style="margin: 0; font-size: 12px;"></p>
                    </div>
                    <div class="icon">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <div class="small-box-footer" id="yearly-sales-change">
                        กำลังโหลด...
                    </div>
                </div>
            </div>
        </div>
        --}}

        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('summary_location_id', __('purchase.business_location') . ':') !!}
                    {!! Form::select('summary_location_id', $business_locations, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('summary_customer_id', __('contact.customer') . ':') !!}
                    {!! Form::select('summary_customer_id', $customers, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('summary_payment_status', __('purchase.payment_status') . ':') !!}
                    {!! Form::select(
                        'summary_payment_status',
                        [
                            'paid' => __('lang_v1.paid'),
                            'due' => __('lang_v1.due'),
                            'partial' => __('lang_v1.partial'),
                            'overdue' => __('lang_v1.overdue'),
                        ],
                        null,
                        ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')],
                    ) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('summary_date_range', __('report.date_range') . ':') !!}
                    {!! Form::text('summary_date_range', null, [
                        'placeholder' => __('lang_v1.select_a_date_range'),
                        'class' => 'form-control',
                        'readonly',
                    ]) !!}
                </div>
            </div>
            @if (!empty($sales_representative))
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('summary_created_by', __('report.user') . ':') !!}
                        {!! Form::select('summary_created_by', $sales_representative, null, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'placeholder' => __('lang_v1.all'),
                        ]) !!}
                    </div>
                </div>
            @endif
            @if (!empty($commission_agents))
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('summary_commission_agent', __('lang_v1.commission_agent') . ':') !!}
                        {!! Form::select('summary_commission_agent', $commission_agents, null, [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'placeholder' => __('lang_v1.all'),
                        ]) !!}
                    </div>
                </div>
            @endif
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('summary_shipping_status', __('lang_v1.shipping_status') . ':') !!}
                    {!! Form::select('summary_shipping_status', $shipping_statuses, null, [
                        'class' => 'form-control select2',
                        'style' => 'width:100%',
                        'placeholder' => __('lang_v1.all'),
                    ]) !!}
                </div>
            </div>
            
            <!-- Document Type Filter - Fixed to show only Final Bills and Proforma -->
            <div class="col-md-3">
                <div class="form-group">
                    <label>@lang('lang_v1.document_type'):</label>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="summary_final_bill" value="final_bill" checked disabled>
                            <strong>@lang('lang_v1.final_bills') (IPAY)</strong>
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" id="summary_proforma" value="proforma" checked disabled>
                            <strong>@lang('lang_v1.proforma_invoices') (VT)</strong>
                        </label>
                    </div>
                    <small class="text-muted">@lang('lang_v1.summary_sales_shows_both_types')</small>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary'])
            @can('direct_sell.access')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\SellController::class, 'create']) }}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan

            <!-- Document Type Filter Checkboxes -->
            <div class="row" style="margin-bottom: 15px;">
                <div class="col-md-12">
                    <div class="form-group pull-left">
                        <label style="font-weight: bold; margin-right: 15px;">@lang('lang_v1.document_type') Filter:</label>
                        <div class="checkbox-inline" style="margin-right: 15px;">
                            <label>
                                <input type="checkbox" id="filter_tax_invoice" class="document-filter-checkbox" data-filter="tax_invoice" checked>
                                <span class="label bg-red" style="font-size: 13px; padding: 4px 8px;">Tax-Invoice (VT)</span>
                            </label>
                        </div>
                        <div class="checkbox-inline">
                            <label>
                                <input type="checkbox" id="filter_billing_receive" class="document-filter-checkbox" data-filter="billing_receive" checked>
                                <span class="label bg-green" style="font-size: 13px; padding: 4px 8px;">Payment Received</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Section -->
            <div class="row">
                <div class="col-md-12">
                    <button type="button" id="btn_toggle_export" class="btn btn-default export-toggle-btn">
                        <i class="fa fa-chevron-down"></i> Show Export
                    </button>
                    <div class="export-section" id="export_section" style="display: none;">
                        <div class="section-title">
                            <i class="fa fa-download"></i> Export Data
                        </div>
                        <div class="form-inline">
                            <div class="form-group export-date-range">
                                <label for="export_date_range" style="margin-right: 10px;">Date Range:</label>
                                <input type="text" id="export_date_range" class="form-control" placeholder="Select date range" readonly style="width: 200px;">
                            </div>
                            <button type="button" id="btn_export_csv" class="btn export-btn btn-csv">
                                <i class="fa fa-file-csv"></i> Export CSV
                            </button>
                            <button type="button" id="btn_export_xlsx" class="btn export-btn btn-xlsx">
                                <i class="fa fa-file-excel"></i> Export XLSX
                            </button>
                        </div>
                        <div class="export-filter-group">
                            <label style="margin-right: 10px; font-weight: 600;">Document Type (Export):</label>
                            <div class="checkbox-inline">
                                <label>
                                    <input type="checkbox" id="export_filter_tax_invoice" checked>
                                    <span class="label bg-red" style="font-size: 12px; padding: 3px 6px;">Tax-Invoice (VT)</span>
                                </label>
                            </div>
                            <div class="checkbox-inline">
                                <label>
                                    <input type="checkbox" id="export_filter_billing_receive" checked>
                                    <span class="label bg-green" style="font-size: 12px; padding: 3px 6px;">Payment Received</span>
                                </label>
                            </div>
                        </div>
                        <div class="export-columns">
                            <div style="font-weight: 600; margin-bottom: 6px;">Columns (Export):</div>
                            <div class="checkbox-inline">
                                <label>
                                    <input type="checkbox" id="export_columns_all" checked>
                                    <strong>Select All</strong>
                                </label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="date" checked> Date</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="invoice_no" checked> Invoice No</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="document_type" checked> Document Type</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="customer" checked> Customer</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="location" checked> Location</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="payment_status" checked> Payment Status</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="total_amount" checked> Total Amount</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="total_paid" checked> Total Paid</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="total_remaining" checked> Total Remaining</label>
                            </div>
                            <div class="checkbox-inline">
                                <label><input type="checkbox" class="export-column" value="tax" checked> Tax</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- <div class="row mb-3">
                <div class="col-md-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>@lang('lang_v1.summary_sales'):55555555</strong> 
                        @lang('lang_v1.summary_sales_description')
                    </div>
                </div>
            </div> -->

            @if (auth()->user()->can('direct_sell.view') ||
                    auth()->user()->can('view_own_sell_only') ||
                    auth()->user()->can('view_commission_agent_sell'))
                @php
                    $custom_labels = json_decode(session('business.custom_labels'), true);
                @endphp
                <div id="summary_sales_table_wrapper">
                    <!-- Loading overlay -->
                    <div class="table-loading-overlay" id="table-loading-overlay">
                        <div class="loading-spinner"></div>
                        <div style="color: #666; font-weight: bold;">Loading records...</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped ajax_view" id="summary_sales_table">
                    <thead>
                        <tr>
                            <th>@lang('messages.date')</th>
                            <th>@lang('sale.invoice_no')</th>
                            <th>@lang('lang_v1.document_type')</th>
                            <th>@lang('sale.customer_name')</th>
                            <th>@lang('lang_v1.contact_no')</th>
                            <th>@lang('sale.location')</th>
                            <th>@lang('sale.payment_status')</th>
                            <th>@lang('sale.total_amount')</th>
                            <th>@lang('sale.tax')</th>
                            <th>@lang('sale.total_paid')</th>
                            <th>@lang('lang_v1.sell_due')</th>
                            <th>@lang('lang_v1.total_items')</th>
                            <th>@lang('lang_v1.added_by')</th>
                            <th>@lang('sale.sell_note')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                        <tr class="bg-gray font-17 footer-total text-center">
                            <td colspan="5"><strong>@lang('sale.total'):</strong></td>
                            <td class="footer_payment_status_count"></td>
                            <td class="footer_sale_total display_currency" data-currency_symbol="true"></td>
                            <td class="footer_total_paid display_currency" data-currency_symbol="true"></td>
                            <td class="footer_total_remaining display_currency" data-currency_symbol="true"></td>
                            <td colspan="6"></td>
                        </tr>
                    </tfoot>
                </table>
                    </div>
                </div>
            @endif
        @endcomponent
    </section>
    <!-- /.content -->

    <!-- View/Add/Edit Payment Modal -->
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="paymentModalLabel" style="display: none;">
    </div>

    <!-- This will be printed -->
    <section class="invoice print_section" id="receipt_section">
    </section>

@stop

@section('javascript')
    <!-- Load functions.js explicitly if not already loaded -->
    <script src="{{ asset('js/functions.js?v=' . $asset_v) }}"></script>
    
    <script type="text/javascript">
        // PDF Server URL from Laravel config (set in .env as API_PDF_SERVER_URL)
        const PDF_SERVER_URL = '{{ config("constants.pdf_server_url") }}';
        
        // Define utility functions globally - outside of document ready
        function sum_table_col(table, class_name) {
            var sum = 0;
            table.find('tbody').find('tr').each(function() {
                if (parseFloat($(this).find('.' + class_name).data('orig-value'))) {
                    sum += parseFloat($(this).find('.' + class_name).data('orig-value'));
                }
            });
            return sum;
        }
        
        function count_table_col(table, class_name) {
            var statuses = {};
            table.find('tbody').find('tr').each(function() {
                var element = $(this).find('.' + class_name);
                if (element.length > 0) {
                    var status_name = element.data('orig-value') || element.text().trim();
                    var status_label = element.data('status-name') || element.text().trim();
                    var status_color = element.data('color') || 'gray';
                    
                    if (status_name) {
                        if (!(status_name in statuses)) {
                            statuses[status_name] = {
                                'count': 1,
                                'label': status_label,
                                'color': status_color
                            };
                        } else {
                            statuses[status_name]['count'] += 1;
                        }
                    }
                }
            });
            return statuses;
        }

        // Add currency conversion function if not available
        function __currency_convert_recursively(element, use_page_currency = false) {
            if (typeof element !== 'undefined' && element.length > 0) {
                element.find('.display_currency').each(function() {
                    var value = $(this).text();
                    var show_symbol = $(this).data('currency_symbol');
                    if (show_symbol == undefined || show_symbol != true) {
                        show_symbol = false;
                    }
                    if (typeof $(this).data('use_page_currency') !== 'undefined') {
                        use_page_currency = $(this).data('use_page_currency');
                    }
                    var is_quantity = $(this).data('is_quantity');
                    if (is_quantity == undefined || is_quantity != true) {
                        is_quantity = false;
                    }
                    if (is_quantity) {
                        show_symbol = false;
                    }
                    // Basic currency formatting - you may need to adjust this based on your currency settings
                    if (typeof __currency_trans_from_en === 'function') {
                        // Strip non-numeric characters (except . and -) before parsing
                        var cleanValue = value.replace(/[^\d.-]/g, '');
                        var valNum = parseFloat(cleanValue);
                        var processValue = cleanValue;
                        if (!isNaN(valNum)) {
                            processValue = Math.abs(valNum).toString();
                        }
                        
                        var formatted = __currency_trans_from_en(processValue, show_symbol, use_page_currency, 2, is_quantity);
                        
                        // Remove any remaining negative signs or parentheses just in case
                        formatted = formatted.replace('-', '').replace('(', '').replace(')', '');
                        
                        if (show_symbol && formatted.indexOf('฿') !== -1) {
                            formatted = formatted.replace('฿', '').trim() + ' ฿';
                        }
                        $(this).text(formatted);
                    } else {
                        // Fallback formatting
                        var cleanValue = value.replace(/[^\d.-]/g, '');
                        var num = Math.abs(parseFloat(cleanValue));
                        if (!isNaN(num)) {
                            $(this).text(num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') + ' ฿');
                        }
                    }
                });
            }
        }

        // Base URL helper to keep AJAX calls within the app subdirectory
        const appBaseUrl = "{{ url('/') }}";
        function buildAppUrl(path) {
            if (!path) return appBaseUrl;
            // If already absolute, return as-is
            if (/^https?:\/\//i.test(path)) return path;
            const normalizedBase = appBaseUrl.replace(/\/$/, '');
            const normalizedPath = path.startsWith('/') ? path : '/' + path;
            return normalizedBase + normalizedPath;
        }

        // Add payment account function if not available
        function set_default_payment_account() {
            try {
                var default_accounts = {};
                if ($('#transaction_payment_add_form #default_payment_accounts').length && $('#transaction_payment_add_form #default_payment_accounts').val()) {
                    default_accounts = JSON.parse($('#transaction_payment_add_form #default_payment_accounts').val());
                }
                var payment_type = $('#transaction_payment_add_form .payment_types_dropdown').val();
                if (payment_type && payment_type != 'advance') {
                    var default_account = (default_accounts && default_accounts[payment_type] && default_accounts[payment_type]['account']) ? 
                        default_accounts[payment_type]['account'] : '';
                    $('#transaction_payment_add_form #account_id').val(default_account);
                    $('#transaction_payment_add_form #account_id').change();
                }
            } catch (e) {
                console.log('set_default_payment_account error:', e);
            }
        }

        // Add other missing utility functions if needed
        if (typeof _ === 'undefined') {
            window._ = {
                isUndefined: function(obj) { return typeof obj === 'undefined'; },
                isEmpty: function(obj) { return obj == null || (typeof obj === 'object' && Object.keys(obj).length === 0); }
            };
        }

        // Restore filter state from localStorage BEFORE document ready
        function restoreFilterState() {
            var savedFilter = localStorage.getItem('summary_sales_document_filter');

            if (savedFilter) {
                if (savedFilter === 'tax_invoice') {
                    $('#filter_tax_invoice').prop('checked', true);
                    $('#filter_billing_receive').prop('checked', false);
                    window.currentDocumentFilter = 'tax_invoice';
                } else if (savedFilter === 'billing_receive') {
                    $('#filter_tax_invoice').prop('checked', false);
                    $('#filter_billing_receive').prop('checked', true);
                    window.currentDocumentFilter = 'billing_receive';
                } else {
                    // both
                    $('#filter_tax_invoice').prop('checked', true);
                    $('#filter_billing_receive').prop('checked', true);
                    window.currentDocumentFilter = 'both';
                }

                console.log('Restored filter from localStorage:', savedFilter);
            } else {
                // Default: both checked
                window.currentDocumentFilter = 'both';
            }
        }

        // Call restore function BEFORE DataTable initialization
        restoreFilterState();

        // Function to load sales summary statistics
        function loadSalesSummaryStats() {
            $.ajax({
                url: '{{ route("sells.sales-summary-stats") }}',
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;

                        // Format currency
                        var monthlyTotal = parseFloat(data.monthly.total).toLocaleString('th-TH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        var yearlyTotal = parseFloat(data.yearly.total).toLocaleString('th-TH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });

                        // Update monthly card
                        $('#monthly-sales-total').html(monthlyTotal + ' ฿');
                        $('#monthly-sales-label').text('ยอดขายเดือน ' + data.monthly.month_name + ' ' + data.monthly.year);
                        $('#monthly-sales-count').text(data.monthly.count + ' รายการ');

                        // Monthly change indicator
                        var monthlyChangeHtml = '';
                        if (data.monthly.change > 0) {
                            monthlyChangeHtml = '<i class="fa fa-arrow-up"></i> +' + data.monthly.change + '% จากเดือนก่อน';
                        } else if (data.monthly.change < 0) {
                            monthlyChangeHtml = '<i class="fa fa-arrow-down"></i> ' + data.monthly.change + '% จากเดือนก่อน';
                        } else {
                            monthlyChangeHtml = '<i class="fa fa-minus"></i> เท่ากับเดือนก่อน';
                        }
                        $('#monthly-sales-change').html(monthlyChangeHtml);

                        // Update yearly card
                        $('#yearly-sales-total').html(yearlyTotal + ' ฿');
                        $('#yearly-sales-label').text('ยอดขายปี ' + data.yearly.year);
                        $('#yearly-sales-count').text(data.yearly.count + ' รายการ');

                        // Yearly change indicator
                        var yearlyChangeHtml = '';
                        if (data.yearly.change > 0) {
                            yearlyChangeHtml = '<i class="fa fa-arrow-up"></i> +' + data.yearly.change + '% จากปีก่อน';
                        } else if (data.yearly.change < 0) {
                            yearlyChangeHtml = '<i class="fa fa-arrow-down"></i> ' + data.yearly.change + '% จากปีก่อน';
                        } else {
                            yearlyChangeHtml = '<i class="fa fa-minus"></i> เท่ากับปีก่อน';
                        }
                        $('#yearly-sales-change').html(yearlyChangeHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load sales summary stats:', error);
                    $('#monthly-sales-total').html('Error');
                    $('#yearly-sales-total').html('Error');
                    $('#monthly-sales-change').html('ไม่สามารถโหลดข้อมูลได้');
                    $('#yearly-sales-change').html('ไม่สามารถโหลดข้อมูลได้');
                }
            });
        }

        $(document).ready(function() {
            // Initialize clean state: ensure all modals are properly closed
            $('.modal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            $('.payment_modal, .edit_payment_modal').html('').hide();

            // Load sales summary stats
            loadSalesSummaryStats();

            //Date range as a button
            $('#summary_date_range').daterangepicker(
                dateRangeSettings,
                function(start, end) {
                    $('#summary_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(
                        moment_date_format));
                    summary_sales_table.ajax.reload();
                }
            );

            // Set default date range: 01/01/2025 to current day
            var defaultStart = moment('2025-01-01');
            var defaultEnd = moment();
            $('#summary_date_range').data('daterangepicker').setStartDate(defaultStart);
            $('#summary_date_range').data('daterangepicker').setEndDate(defaultEnd);
            $('#summary_date_range').val(defaultStart.format(moment_date_format) + ' ~ ' + defaultEnd.format(moment_date_format));

            $('#summary_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#summary_date_range').val('');
                summary_sales_table.ajax.reload();
            });

            // Frontend debug helper: log the latest 5 visible bills and VT<->IPAY linkage.
            // Disable by setting this flag to false.
            const ENABLE_SUMMARY_RELATION_DEBUG = true;

            function extractTransactionIdFromHref(href) {
                if (!href) {
                    return null;
                }
                const match = href.match(/\/sells\/(\d+)(?:\D|$)/);
                return match ? parseInt(match[1], 10) : null;
            }

            function normalizeInvoiceText(text) {
                return (text || '').replace(/\s+/g, ' ').trim();
            }

            function debugLastFiveBillsRelations() {
                if (!ENABLE_SUMMARY_RELATION_DEBUG) {
                    return;
                }

                const $rows = $('#summary_sales_table tbody tr').filter(function() {
                    return $(this).find('td').length > 1;
                }).slice(0, 5);

                if ($rows.length === 0) {
                    console.log('[SummarySales Debug] No rows available for relation check.');
                    return;
                }

                const entries = [];
                $rows.each(function(index) {
                    const $row = $(this);
                    const href = $row.attr('data-href') || '';
                    const transactionId = extractTransactionIdFromHref(href);
                    const invoiceNo = normalizeInvoiceText($row.find('td').eq(1).text());
                    const type = invoiceNo.startsWith('VT') ? 'VT'
                        : (invoiceNo.startsWith('IPAY') ? 'IPAY' : 'OTHER');

                    entries.push({
                        position: index + 1,
                        transaction_id: transactionId,
                        invoice_no: invoiceNo,
                        type: type
                    });
                });

                console.groupCollapsed('[SummarySales Debug] Last 5 bills on current page');
                console.table(entries);
                console.groupEnd();

                entries.forEach(function(entry) {
                    if (!entry.transaction_id) {
                        console.warn('[SummarySales Debug] Missing transaction_id from row href:', entry);
                        return;
                    }

                    if (entry.type === 'VT') {
                        $.ajax({
                            url: '{{ url("/api/get-related-ipay") }}/' + entry.transaction_id,
                            method: 'GET'
                        }).done(function(response) {
                            if (response && response.success && response.ipay) {
                                var source = response.ipay.source || 'unknown';
                                var syntheticLabel = response.ipay.synthetic ? 'synthetic' : 'real';
                                console.log(
                                    '[SummarySales Debug] VT -> IPAY',
                                    entry.invoice_no,
                                    '=>',
                                    response.ipay.invoice_no,
                                    '[' + syntheticLabel + ', source=' + source + ']',
                                    '(VT ID:',
                                    entry.transaction_id + ', IPAY ID:',
                                    response.ipay.id + ')'
                                );
                            } else {
                                console.warn(
                                    '[SummarySales Debug] VT has no related IPAY',
                                    entry.invoice_no,
                                    '(VT ID:',
                                    entry.transaction_id + ')',
                                    response
                                );
                            }
                        }).fail(function(xhr) {
                            console.error(
                                '[SummarySales Debug] Failed VT->IPAY lookup',
                                entry.invoice_no,
                                '(VT ID:',
                                entry.transaction_id + ')',
                                xhr.status,
                                xhr.responseText
                            );
                        });
                    } else if (entry.type === 'IPAY') {
                        $.ajax({
                            url: '{{ url("/api/get-related-vt") }}/' + entry.transaction_id,
                            method: 'GET'
                        }).done(function(response) {
                            if (response && response.success && response.vt) {
                                console.log(
                                    '[SummarySales Debug] IPAY -> VT',
                                    entry.invoice_no,
                                    '=>',
                                    response.vt.invoice_no,
                                    '(IPAY ID:',
                                    entry.transaction_id + ', VT ID:',
                                    response.vt.id + ')'
                                );
                            } else {
                                console.warn(
                                    '[SummarySales Debug] IPAY has no related VT',
                                    entry.invoice_no,
                                    '(IPAY ID:',
                                    entry.transaction_id + ')',
                                    response
                                );
                            }
                        }).fail(function(xhr) {
                            console.error(
                                '[SummarySales Debug] Failed IPAY->VT lookup',
                                entry.invoice_no,
                                '(IPAY ID:',
                                entry.transaction_id + ')',
                                xhr.status,
                                xhr.responseText
                            );
                        });
                    } else {
                        console.log(
                            '[SummarySales Debug] Non VT/IPAY row skipped:',
                            entry.invoice_no,
                            '(ID:',
                            entry.transaction_id + ')'
                        );
                    }
                });
            }

            summary_sales_table = $('#summary_sales_table').DataTable({
                processing: false,
                serverSide: true,
                fixedHeader: false,
                // Default to latest transaction time to avoid mixed invoice-number ordering.
                aaSorting: [[0, 'desc']],
                "ajax": {
                    "url": "{{ route('sells.summary-sales-data') }}",
                    "data": function(d) {
                        if ($('#summary_location_id').length) {
                            d.location_id = $('#summary_location_id').val();
                        }
                        if ($('#summary_customer_id').length) {
                            d.customer_id = $('#summary_customer_id').val();
                        }
                        if ($('#summary_payment_status').length) {
                            d.payment_status = $('#summary_payment_status').val();
                        }
                        if ($('#summary_created_by').length) {
                            d.created_by = $('#summary_created_by').val();
                        }
                        if ($('#summary_commission_agent').length) {
                            d.commission_agent = $('#summary_commission_agent').val();
                        }
                        if ($('#summary_shipping_status').length) {
                            d.shipping_status = $('#summary_shipping_status').val();
                        }

                        var start = '';
                        var end = '';
                        if ($('#summary_date_range').val()) {
                            start = $('input#summary_date_range')
                                .data('daterangepicker')
                                .startDate.format('YYYY-MM-DD');
                            end = $('input#summary_date_range')
                                .data('daterangepicker')
                                .endDate.format('YYYY-MM-DD');
                        }
                        d.start_date = start;
                        d.end_date = end;
                        
                        // Add document type filter
                        d.document_filter = window.currentDocumentFilter || 'both';
                    }
                },
                columnDefs: [{
                    "targets": [2, 14],
                    "orderable": false,
                    "searchable": false
                }],
                columns: [
                    { data: 'transaction_date', name: 'transactions.transaction_date' },
                    { data: 'invoice_no', name: 'transactions.invoice_no' },
                    { data: 'document_type', name: 'transactions.document_type', searchable: false },
                    { data: 'name', name: 'contacts.name' },
                    { data: 'mobile', name: 'contacts.mobile' },
                    { data: 'business_location', name: 'bl.name' },
                    { data: 'payment_status', name: 'transactions.payment_status' },
                    { data: 'final_total', name: 'transactions.final_total' },
                    { data: 'tax_amount', name: 'transactions.tax_amount', searchable: false },
                    { data: 'total_paid', name: 'total_paid', searchable: false },
                    { data: 'total_remaining', name: 'total_remaining', searchable: false },
                    { data: 'total_items', name: 'total_items', searchable: false },
                    { data: 'added_by', name: 'u.first_name' },
                    { data: 'additional_notes', name: 'transactions.additional_notes' },
                    { data: 'action', name: 'action' }
                ],
                "fnDrawCallback": function(oSettings) {
                    // Hide loading overlay when table is drawn
                    hideTableLoading();
                    
                    var total_sale = sum_table_col($('#summary_sales_table'), 'final_total');
                    $('.footer_sale_total').text(total_sale);

                    var total_paid = sum_table_col($('#summary_sales_table'), 'total_paid');
                    $('.footer_total_paid').text(total_paid);

                    var total_remaining = sum_table_col($('#summary_sales_table'), 'total_remaining');
                    $('.footer_total_remaining').text(total_remaining);

                    var payment_status_count = count_table_col($('#summary_sales_table'), 'payment_status');
                    var payment_status_html = '';
                    for (var key in payment_status_count) {
                        payment_status_html += '<small class="label pull-left bg-' + payment_status_count[key]['color'] + 
                            '" style="margin:5px 2px;">' + payment_status_count[key]['label'] + ': ' + 
                            payment_status_count[key]['count'] + '</small>';
                    }
                    $('.footer_payment_status_count').html(payment_status_html);

                    __currency_convert_recursively($('#summary_sales_table'));
                    debugLastFiveBillsRelations();
                    
                    // Highlight newly created tax invoice if exists in localStorage
                    const taxInvoiceId = getNewTaxInvoiceId();
                    if (taxInvoiceId) {
                        var targetRow = $('#summary_sales_table tbody tr').filter(function() {
                            return $(this).data('href') && $(this).data('href').includes('/' + taxInvoiceId);
                        });
                        
                        if (targetRow.length > 0) {
                            targetRow.addClass('highlight-new-tax-invoice');
                            
                            // Scroll to the highlighted row
                            $('html, body').animate({
                                scrollTop: targetRow.offset().top - 100
                            }, 1000);
                            
                            // Remove highlight after 15 seconds (but keep in localStorage for 2 minutes)
                            setTimeout(function() {
                                targetRow.removeClass('highlight-new-tax-invoice');
                            }, 15000);
                        }
                    }
                    
                    // Handle dropdown functionality for action buttons
                    $('#summary_sales_table .dropdown-toggle').off('click').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $btnGroup = $(this).closest('.btn-group');
                        
                        // Close all other dropdowns
                        $('#summary_sales_table .btn-group').not($btnGroup).removeClass('open');
                        
                        // Toggle current dropdown
                        $btnGroup.toggleClass('open');
                        
                        return false;
                    });
                    
                    // Close dropdowns when clicking outside
                    $(document).off('click.summary-dropdown').on('click.summary-dropdown', function(e) {
                        if (!$(e.target).closest('#summary_sales_table .btn-group').length) {
                            $('#summary_sales_table .btn-group').removeClass('open');
                        }
                    });
                },
                createdRow: function(row, data, dataIndex) {
                    $(row).find('td:eq(3)').addClass('clickable-cell');
                }
            });

            // Reload table when filters change
            $(document).on('change', '#summary_location_id, #summary_customer_id, #summary_payment_status, #summary_created_by, #summary_commission_agent, #summary_shipping_status', function() {
                showTableLoading();
                summary_sales_table.ajax.reload();
            });

            // Row click to view transaction in modal - using original modal directly
            $(document).off('click', 'table#summary_sales_table tbody tr').on('click', 'table#summary_sales_table tbody tr', function(e) {
                // Don't trigger if clicking on action buttons, dropdowns, links, or payment status
                if ($(e.target).closest('a, button, .dropdown, .btn-group, .clickable-payment-status, .add_payment_modal, .view_payment_modal, .edit_payment').length) {
                    return;
                }
                
                // Stop all event propagation to prevent other handlers from firing
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                var href = $(this).data('href');
                
                if (href) {
                    // Clean up any existing modals
                    $('.modal').modal('hide');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');
                    
                    // Load and show the original modal directly
                    $.ajax({
                        method: 'GET',
                        url: href,
                        dataType: 'html',
                        timeout: 15000,
                        success: function(result) {
                            // Set modal content and show it
                            $('.view_modal').html(result).modal('show');
                            
                            // Apply currency conversion to the modal content
                            __currency_convert_recursively($('.view_modal'));
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to load transaction:', error);
                            toastr.error('Failed to load transaction details: ' + error);
                        }
                    });
                }
            });

            // Print invoice functionality - original method
            
            $(document).on('click', '.view-payment', function(e) {
                console.log('View payment clicked!', this); // Debug log
                e.preventDefault();
                e.stopPropagation();

                var href = $(this).data('href');
                console.log('View payment href:', href); // Debug log
                if (href) {
                    console.log('Loading view payment from:', href); // Debug log

                    // Load view payments modal
                    $.ajax({
                        method: 'GET',
                        url: href,
                        dataType: 'html',
                        success: function(result) {
                            console.log('View payment loaded successfully'); // Debug log
                            $('.payment_modal').html(result);
                            $('.payment_modal').modal('show');
                        },
                        error: function(xhr, status, error) {
                            console.error('Payment details load error:', error, xhr.responseText); // Enhanced debug
                            toastr.error('Failed to load payment details');
                        }
                    });
                } else {
                    console.log('No href found for view-payment'); // Debug log
                }
            });

            // Handle edit payment modal - use exact same approach as add payment
            $(document).on('click', '.edit_payment', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var editUrl = $(this).data('href');
                console.log('Edit payment clicked:', editUrl); // Debug log
                
                if (editUrl) {
                    // Use exact same approach as add_payment_modal
                    $.get(editUrl)
                        .done(function(result) {
                            $('.payment_modal').html(result);
                            $('.payment_modal').modal('show');
                        })
                        .fail(function() {
                            toastr.error('Failed to load edit payment modal');
                        });
                } else {
                    console.log('No href found for edit_payment'); // Debug log
                }
            });

            // Print invoice functionality - original method https://api-shop.rubyshop.co.th/health
            $(document).on('click', '.print-invoice', function(e) {
                e.preventDefault();
                var href = $(this).data('href');
                if (href) {
                    $.ajax({
                        method: 'GET',
                        url: href,
                        dataType: 'html',
                        success: function(result) {
                            $('#receipt_section').html(result);
                            __currency_convert_recursively($('#receipt_section'));
                            setTimeout(function() {
                                window.print();
                            }, 1000);
                        }
                    });
                }
            });

            // Print invoice functionality - API method for PDF generation
            $(document).on('click', '.print-invoice-api', function(e) {
                e.preventDefault();
                
                var invoiceId = $(this).data('id');
                var documentType = $(this).data('document-type');
                
                if (!invoiceId) {
                    toastr.error('Invoice ID not found');
                    return;
                }
                
                // Show loading message
                toastr.info('Generating PDF...', 'Please wait');
                
                // Determine API endpoint based on document type
                var apiEndpoint;
                if (documentType === 'proforma') {
                    apiEndpoint = PDF_SERVER_URL + '/tax-invoice/' + invoiceId + '/pdf-print-nodejs';
                } else if (documentType === 'final') {
                    apiEndpoint = PDF_SERVER_URL + '/billing-receipt/' + invoiceId + '/pdf-print-nodejs';
                } else {
                    toastr.error('Unknown document type: ' + documentType);
                    return;
                }
                
                console.log('Calling PDF API:', apiEndpoint, 'for document type:', documentType);
                
                // First check if PDF service is running
                $.ajax({
                    method: 'GET',
                    url: PDF_SERVER_URL + '/health',
                    timeout: 3000,
                    success: function() {
                        // PDF service is running, proceed with PDF generation
                        generatePDFFromAPI(apiEndpoint, documentType);
                    },
                    error: function() {
                        toastr.error('PDF service is not running. Please start the Node.js PDF server.');
                        console.error('PDF service health check failed. Make sure the Node.js server is running on port 3000.');
                    }
                });
            });
            
            // Function to generate PDF from API
            function generatePDFFromAPI(apiEndpoint, documentType) {
                $.ajax({
                    method: 'GET',
                    url: apiEndpoint,
                    timeout: 30000, // 30 second timeout for PDF generation
                    xhrFields: {
                        responseType: 'blob' // Important for PDF handling
                    },
                    success: function(data, status, xhr) {
                        // Check if the response is a PDF
                        var contentType = xhr.getResponseHeader('content-type');
                        if (contentType && contentType.includes('application/pdf')) {
                            // Create blob URL and open in new tab for printing
                            var blob = new Blob([data], { type: 'application/pdf' });
                            var url = window.URL.createObjectURL(blob);
                            var printWindow = window.open(url, '_blank');
                            
                            if (printWindow) {
                                printWindow.onload = function() {
                                    setTimeout(function() {
                                        printWindow.print();
                                        // Clean up blob URL after a delay
                                        setTimeout(function() {
                                            window.URL.revokeObjectURL(url);
                                        }, 5000);
                                    }, 1000);
                                };
                                toastr.success('PDF generated successfully');
                            } else {
                                toastr.error('Popup blocked. Please allow popups for this site.');
                                // Clean up blob URL
                                window.URL.revokeObjectURL(url);
                            }
                        } else {
                            toastr.error('Invalid response format - not a PDF');
                            console.error('Expected PDF but got:', contentType);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('PDF generation failed:', xhr, status, error);
                        
                        var errorMessage = 'Failed to generate PDF';
                        
                        if (status === 'timeout') {
                            errorMessage = 'PDF generation timed out. Please try again.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Cannot connect to PDF service. Please check if the Node.js server is running.';
                        } else if (xhr.responseText) {
                            try {
                                var errorResponse = JSON.parse(xhr.responseText);
                                errorMessage = errorResponse.error || errorMessage;
                            } catch (e) {
                                errorMessage = 'Error: ' + xhr.status + ' - ' + error;
                            }
                        }
                        
                        toastr.error(errorMessage);
                    }
                });
            }

            // Handle modal print button click
            $(document).on('click', '.modal-print-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var transactionId = $(this).data('transaction-id');
                var documentType = $(this).data('document-type');
                
                console.log('Modal print button clicked:', transactionId, documentType);
                
                if (!transactionId) {
                    toastr.error('Transaction ID not found');
                    return;
                }
                
                // Show loading message
                toastr.info('Generating PDF...', 'Please wait');
                
                // Determine API endpoint based on document type
                var apiEndpoint;
                if (documentType === 'proforma') {
                    apiEndpoint = PDF_SERVER_URL + '/tax-invoice/' + transactionId + '/pdf-print-nodejs';
                } else if (documentType === 'final') {
                    apiEndpoint = PDF_SERVER_URL + '/billing-receipt/' + transactionId + '/pdf-print-nodejs';
                } else {
                    toastr.error('Unknown document type: ' + documentType);
                    return;
                }
                
                console.log('Calling PDF API:', apiEndpoint, 'for document type:', documentType);
                
                // First check if PDF service is running
                $.ajax({
                    method: 'GET',
                    url: PDF_SERVER_URL + '/health',
                    timeout: 3000,
                    success: function() {
                        // PDF service is running, proceed with PDF generation
                        generatePDFFromAPI(apiEndpoint, documentType);
                    },
                    error: function() {
                        toastr.error('PDF service is not running. Please start the Node.js PDF server.');
                        console.error('PDF service health check failed. Make sure the Node.js server is running on port 3000.');
                    }
                });
            });

            // Initialize default document filter
            window.currentDocumentFilter = 'both';
            
            // Functions to show/hide table loading overlay
            function showTableLoading() {
                $('#table-loading-overlay').css('display', 'flex');
            }
            
            function hideTableLoading() {
                $('#table-loading-overlay').css('display', 'none');
            }
            
            // Functions to manage tax invoice highlighting with localStorage
            function storeNewTaxInvoiceId(id) {
                const data = {
                    id: id,
                    timestamp: Date.now()
                };
                localStorage.setItem('newTaxInvoiceHighlight', JSON.stringify(data));
            }
            
            function getNewTaxInvoiceId() {
                const stored = localStorage.getItem('newTaxInvoiceHighlight');
                if (!stored) return null;
                
                const data = JSON.parse(stored);
                const twoMinutes = 2 * 60 * 1000; // 2 minutes in milliseconds
                
                // Check if more than 2 minutes has passed
                if (Date.now() - data.timestamp > twoMinutes) {
                    localStorage.removeItem('newTaxInvoiceHighlight');
                    return null;
                }
                
                return data.id;
            }
            
            function clearNewTaxInvoiceId() {
                localStorage.removeItem('newTaxInvoiceHighlight');
            }

            // Billing receipt is now printed from the same VT transaction context.

            // Document Type Filter Checkboxes
            $(document).on('change', '.document-filter-checkbox', function() {
                var taxInvoiceChecked = $('#filter_tax_invoice').is(':checked');
                var billingReceiveChecked = $('#filter_billing_receive').is(':checked');

                var filter = 'both'; // default

                if (taxInvoiceChecked && billingReceiveChecked) {
                    filter = 'both';
                } else if (taxInvoiceChecked && !billingReceiveChecked) {
                    filter = 'tax_invoice';
                } else if (!taxInvoiceChecked && billingReceiveChecked) {
                    filter = 'billing_receive';
                } else {
                    // If both unchecked, show both (prevent empty table)
                    filter = 'both';
                    $('#filter_tax_invoice, #filter_billing_receive').prop('checked', true);
                }

                // Store the current filter in memory
                window.currentDocumentFilter = filter;

                // Save filter state to localStorage
                localStorage.setItem('summary_sales_document_filter', filter);

                // Debug log
                console.log('Filter changed to:', filter, 'Tax Invoice:', taxInvoiceChecked, 'Billing Receive:', billingReceiveChecked);
                console.log('Saved to localStorage:', filter);

                // Show loading overlay before reloading
                showTableLoading();

                // Reload the DataTable with the new filter
                summary_sales_table.ajax.reload();
            });

            // Export Date Range Picker
            $('#export_date_range').daterangepicker({
                autoUpdateInput: false,
                showDropdowns: true,
                minYear: 2020,
                maxYear: parseInt(moment().format('YYYY'), 10) + 1,
                locale: {
                    format: moment_date_format,
                    separator: ' ~ ',
                    applyLabel: 'Apply',
                    cancelLabel: 'Clear',
                    fromLabel: 'From',
                    toLabel: 'To',
                    customRangeLabel: 'Custom',
                    weekLabel: 'W',
                    daysOfWeek: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                    monthNames: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    firstDay: 0
                },
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'This Year': [moment().startOf('year'), moment().endOf('year')]
                }
            });

            $('#export_date_range').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format(moment_date_format) + ' ~ ' + picker.endDate.format(moment_date_format));
            });

            $('#export_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });

            function getExportDocumentFilter() {
                var taxChecked = $('#export_filter_tax_invoice').is(':checked');
                var billingChecked = $('#export_filter_billing_receive').is(':checked');

                if (taxChecked && billingChecked) return 'both';
                if (taxChecked && !billingChecked) return 'tax_invoice';
                if (!taxChecked && billingChecked) return 'billing_receive';

                // Prevent empty selection
                $('#export_filter_tax_invoice, #export_filter_billing_receive').prop('checked', true);
                return 'both';
            }

            function getExportColumns() {
                var selected = $('.export-column:checked').map(function() {
                    return $(this).val();
                }).get();
                return selected;
            }

            function syncExportColumnsSelectAll() {
                var total = $('.export-column').length;
                var checked = $('.export-column:checked').length;
                $('#export_columns_all').prop('checked', total > 0 && total === checked);
            }

            // Export document type filter: ensure at least one
            $(document).on('change', '#export_filter_tax_invoice, #export_filter_billing_receive', function() {
                var taxChecked = $('#export_filter_tax_invoice').is(':checked');
                var billingChecked = $('#export_filter_billing_receive').is(':checked');
                if (!taxChecked && !billingChecked) {
                    $('#export_filter_tax_invoice, #export_filter_billing_receive').prop('checked', true);
                    toastr.warning('Please select at least one document type for export');
                }
            });

            // Export columns: select all / sync
            $(document).on('change', '#export_columns_all', function() {
                var isChecked = $(this).is(':checked');
                $('.export-column').prop('checked', isChecked);
            });

            $(document).on('change', '.export-column', function() {
                syncExportColumnsSelectAll();
            });

            // Set default export date range to current month
            var exportDefaultStart = moment().startOf('month');
            var exportDefaultEnd = moment();
            $('#export_date_range').data('daterangepicker').setStartDate(exportDefaultStart);
            $('#export_date_range').data('daterangepicker').setEndDate(exportDefaultEnd);
            $('#export_date_range').val(exportDefaultStart.format(moment_date_format) + ' ~ ' + exportDefaultEnd.format(moment_date_format));

            // Export CSV Button
            $('#btn_export_csv').on('click', function() {
                exportSalesData('csv');
            });

            // Export XLSX Button
            $('#btn_export_xlsx').on('click', function() {
                exportSalesData('xlsx');
            });

            // Toggle Export Section
            $('#btn_toggle_export').on('click', function() {
                var isVisible = $('#export_section').is(':visible');
                if (isVisible) {
                    $('#export_section').slideUp(150);
                    $(this).html('<i class="fa fa-chevron-down"></i> Show Export');
                } else {
                    $('#export_section').slideDown(150);
                    $(this).html('<i class="fa fa-chevron-up"></i> Hide Export');
                }
            });

            // Export Function
            function exportSalesData(format) {
                var btn = format === 'csv' ? $('#btn_export_csv') : $('#btn_export_xlsx');
                var originalHtml = btn.html();

                // Validate date range
                if (!$('#export_date_range').val()) {
                    toastr.warning('Please select a date range for export');
                    return;
                }

                var startDate = $('#export_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                var endDate = $('#export_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');

                // Get current filters
                var locationId = $('#summary_location_id').val() || '';
                var customerId = $('#summary_customer_id').val() || '';
                var paymentStatus = $('#summary_payment_status').val() || '';
                var documentFilter = getExportDocumentFilter();
                var exportColumns = getExportColumns();

                if (exportColumns.length === 0) {
                    toastr.warning('Please select at least one column for export');
                    return;
                }

                // Show loading state
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Exporting...');

                // Build export URL
                var exportUrl = '{{ route("sells.export-summary-sales") }}';
                var params = new URLSearchParams({
                    format: format,
                    start_date: startDate,
                    end_date: endDate,
                    location_id: locationId,
                    customer_id: customerId,
                    payment_status: paymentStatus,
                    document_filter: documentFilter,
                    columns: exportColumns.join(',')
                });

                // Create a temporary link and trigger download
                var downloadUrl = exportUrl + '?' + params.toString();

                // Use fetch to check for errors first
                fetch(downloadUrl, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => { throw err; });
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Create download link
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'summary_sales_' + startDate + '_to_' + endDate + '.' + format;
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);

                    toastr.success('Export completed successfully');
                    btn.prop('disabled', false).html(originalHtml);
                })
                .catch(error => {
                    console.error('Export error:', error);
                    toastr.error(error.message || 'Failed to export data');
                    btn.prop('disabled', false).html(originalHtml);
                });
            }
        });
    </script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
