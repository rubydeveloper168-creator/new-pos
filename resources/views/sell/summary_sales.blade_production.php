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
        margin: 30px auto;
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
    .modal-xl {
        max-width: 1200px !important;
        width: 95% !important;
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
                                <i class="fas fa-file-invoice text-primary"></i> <strong>Tax-Invoice (VT)</strong>
                            </label>
                        </div>
                        <div class="checkbox-inline">
                            <label>
                                <input type="checkbox" id="filter_billing_receive" class="document-filter-checkbox" data-filter="billing_receive" checked>
                                <i class="fas fa-receipt text-warning"></i> <strong>Billing-Receive (IPAY)</strong>
                            </label>
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
                        <div style="color: #666; font-weight: bold;">Loading new billing receipt...</div>
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
                            <th>@lang('sale.total_paid')</th>
                            <th>@lang('lang_v1.sell_due')</th>
                            <th>@lang('lang_v1.shipping_status')</th>
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
                            <td class="footer_sale_total"></td>
                            <td class="footer_total_paid"></td>
                            <td class="footer_total_remaining"></td>
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

    <!-- Create Billing-Receipt Options Modal -->
    <div class="modal fade" id="createBillingReceiptModal" tabindex="-1" role="dialog" aria-labelledby="createBillingReceiptModalLabel">
        <div class="modal-dialog" style="width: 950px; margin: 50px auto; display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 100px);">
            <div class="modal-content" style="border-radius: 12px; box-shadow: 0 15px 35px rgba(0,0,0,0.3); border: none; width: 100%;">
                <div class="modal-header" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; border-radius: 12px 12px 0 0; padding: 25px; border-bottom: none;">
                    <h4 class="modal-title" style="font-size: 22px; font-weight: 600; margin: 0; display: flex; align-items: center;">
                        <i class="fa fa-receipt" style="margin-right: 10px; font-size: 24px;"></i>
                        Create Billing-Receipt
                    </h4>
                    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.9; font-size: 28px; background: none; border: none;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 40px; background-color: #f8f9fa;">
                    <div style="background: white; padding: 35px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                        <div style="text-align: center; margin-bottom: 35px;">
                            <i class="fa fa-question-circle" style="font-size: 48px; color: #4CAF50; margin-bottom: 15px;"></i>
                            <h5 style="color: #333; font-weight: 600; margin-bottom: 10px;">Choose Creation Option</h5>
                            <p style="color: #666; margin: 0;">How would you like to create the Billing-Receipt?</p>
                        </div>
                        
                        <!-- Horizontal Layout for Options -->
                        <div class="row" style="margin: 0 -15px;">
                           
                             
                        <div class="col-md-6" style="padding: 0 15px;">
                                <div class="option-card" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 30px 20px; cursor: pointer; transition: all 0.3s ease; height: 160px; display: flex; align-items: center; justify-content: center; text-align: center; overflow: hidden;" data-option="with-payment">
                                    <div style="width: 100%;">
                                        <div style="background: #2196F3; color: white; border-radius: 50%; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;">
                                            <i class="fa fa-credit-card" style="font-size: 22px;"></i>
                                        </div>
                                        <h6 style="margin: 0 0 10px 0; font-weight: 600; color: #333; font-size: 17px; line-height: 1.3; word-wrap: break-word;">Create + Add Payment</h6>
                                        <small style="color: #666; font-size: 14px; line-height: 1.4; display: block; word-wrap: break-word;">Create and add payment info</small>
                                    </div>
                                </div>
                            </div>
                       
                        
                        <div class="col-md-6" style="padding: 0 15px;">
                                <div class="option-card" style="border: 2px solid #e9ecef; border-radius: 8px; padding: 30px 20px; cursor: pointer; transition: all 0.3s ease; height: 160px; display: flex; align-items: center; justify-content: center; text-align: center; overflow: hidden;" data-option="only">
                                    <div style="width: 100%;">
                                        <div style="background: #4CAF50; color: white; border-radius: 50%; width: 55px; height: 55px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;">
                                            <i class="fa fa-file-alt" style="font-size: 22px;"></i>
                                        </div>
                                        <h6 style="margin: 0 0 10px 0; font-weight: 600; color: #333; font-size: 17px; line-height: 1.3; word-wrap: break-word;">Create Billing-Receipt Only</h6>
                                        <small style="color: #666; font-size: 14px; line-height: 1.4; display: block; word-wrap: break-word;">Create without adding payment</small>
                                    </div>
                                </div>
                            </div>
                            
                         
                    
                       
                       
                       
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e9ecef; border-radius: 0 0 12px 12px; padding: 25px; display: flex; justify-content: center;">
                    <button type="button" class="btn btn-danger" data-dismiss="modal" style="padding: 12px 35px; border-radius: 6px; font-size: 16px; background-color: #000; border-color: #dc3545; font-weight: 500;">
                    Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- This will be printed -->
    <section class="invoice print_section" id="receipt_section">
    </section>

@stop

@section('javascript')
    <!-- Load functions.js explicitly if not already loaded -->
    <script src="{{ asset('js/functions.js?v=' . $asset_v) }}"></script>
    
    <script type="text/javascript">
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
                        $(this).text(__currency_trans_from_en(value, show_symbol, use_page_currency, 2, is_quantity));
                    } else {
                        // Fallback formatting
                        var num = parseFloat(value);
                        if (!isNaN(num)) {
                            $(this).text(num.toFixed(2));
                        }
                    }
                });
            }
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

        $(document).ready(function() {
            // Initialize clean state: ensure all modals are properly closed
            $('.modal').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('padding-right', '');
            $('.payment_modal, .edit_payment_modal').html('').hide();
            
            //Date range as a button
            $('#summary_date_range').daterangepicker(
                dateRangeSettings,
                function(start, end) {
                    $('#summary_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(
                        moment_date_format));
                    summary_sales_table.ajax.reload();
                }
            );
            $('#summary_date_range').on('cancel.daterangepicker', function(ev, picker) {
                $('#summary_date_range').val('');
                summary_sales_table.ajax.reload();
            });

            summary_sales_table = $('#summary_sales_table').DataTable({
                processing: false,
                serverSide: true,
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
                    { data: 'total_paid', name: 'total_paid', searchable: false },
                    { data: 'total_remaining', name: 'total_remaining', searchable: false },
                    { data: 'shipping_status', name: 'transactions.shipping_status' },
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
                    
                    // Highlight newly created billing receipt if exists in localStorage
                    const billingReceiptId = getNewBillingReceiptId();
                    if (billingReceiptId) {
                        var targetRow = $('#summary_sales_table tbody tr').filter(function() {
                            return $(this).data('href') && $(this).data('href').includes('/' + billingReceiptId);
                        });
                        
                        if (targetRow.length > 0) {
                            targetRow.addClass('highlight-new-record');
                            
                            // Scroll to the highlighted row
                            $('html, body').animate({
                                scrollTop: targetRow.offset().top - 100
                            }, 1000);
                            
                            // Remove highlight after 10 seconds (but keep in localStorage for 1 minute)
                            setTimeout(function() {
                                targetRow.removeClass('highlight-new-record');
                            }, 10000);
                        }
                    }
                    
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

            // Row click to view transaction in modal - use original modal system with improved styling
            $(document).off('click', 'table#summary_sales_table tbody tr').on('click', 'table#summary_sales_table tbody tr', function(e) {
                // Don't trigger if clicking on action buttons, dropdowns, links, or payment status
                if ($(e.target).closest('a, button, .dropdown, .btn-group, .clickable-payment-status, .add_payment_modal, .view_payment_modal, .edit_payment').length) {
                    return;
                }
                
                var href = $(this).data('href');
                var invoiceNo = $(this).find('td:eq(1)').text().trim(); // Get invoice number from 2nd column
                var transactionId = href ? href.split('/').pop() : null; // Extract transaction ID from href
                
                console.log('Row clicked, href:', href, 'invoiceNo:', invoiceNo, 'transactionId:', transactionId); // Debug log
                
                if (href) {
                    // Ensure clean modal state
                    $('.modal').modal('hide');
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');
                    
                    // Clear modal content and show loading
                    $('.view_modal').html(`
                        <div class="modal-dialog modal-xl" style="width: 95%; max-width: 1200px;">
                            <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0; padding: 20px;">
                                    <h4 class="modal-title" style="font-size: 20px; font-weight: 600; margin: 0;">
                                        <i class="fa fa-file-invoice"></i> Transaction Details
                                    </h4>
                                    <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8; font-size: 28px;">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body text-center" style="padding: 60px 40px;">
                                    <div class="loading-spinner">
                                        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                            <span class="sr-only">Loading...</span>
                                        </div>
                                        <br><br>
                                        <h5 style="color: #666; font-weight: 500;">Loading transaction details...</h5>
                                        <p class="text-muted">Please wait while we fetch the information</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `).modal('show');
                    
                    console.log('Loading transaction from:', href); // Debug log
                    
                    // Load transaction details using the original system
                    $.ajax({
                        method: 'GET',
                        url: href,
                        dataType: 'html',
                        timeout: 15000,
                        success: function(result) {
                            console.log('Transaction data loaded successfully'); // Debug log
                            
                            // Extract the main content from the response
                            var $result = $(result);
                            var content = $result.find('.content-wrapper, .content, .box-body').first().html();
                            if (!content) {
                                content = result; // fallback to full response
                            }
                            
                            // Determine print button text based on invoice number format
                            var printButtonText = '';
                            var printButtonClass = '';
                            var docTypeForAPI = '';
                            
                            if (invoiceNo.startsWith('VT')) {
                                // VT prefix = Tax-Invoice (เลขที่ใบกำกับภาษี)
                                printButtonText = '<i class="fa fa-print"></i> Print Tax-Invoice';
                                printButtonClass = 'btn-primary';
                                docTypeForAPI = 'proforma';
                            } else if (invoiceNo.startsWith('IPAY')) {
                                // IPAY prefix = Billing-Receive (เลขที่ใบเสร็จรับเงิน)
                                printButtonText = '<i class="fa fa-print"></i> Print Billing-Receive';
                                printButtonClass = 'btn-success';
                                docTypeForAPI = 'final';
                            } else {
                                // Default fallback
                                printButtonText = '<i class="fa fa-print"></i> Print Document';
                                printButtonClass = 'btn-info';
                                docTypeForAPI = 'final';
                            }
                            
                            // Remove any existing buttons from content to avoid duplicates
                            var cleanContent = content;
                            
                            // Remove close buttons
                            cleanContent = cleanContent.replace(/<button[^>]*class="[^"]*close[^"]*"[^>]*>.*?<\/button>/gi, '');
                            cleanContent = cleanContent.replace(/<a[^>]*class="[^"]*close[^"]*"[^>]*>.*?<\/a>/gi, '');
                            
                            // Remove print buttons (all variations)
                            cleanContent = cleanContent.replace(/<button[^>]*print[^>]*>.*?<\/button>/gi, '');
                            cleanContent = cleanContent.replace(/<a[^>]*print[^>]*>.*?<\/a>/gi, '');
                            cleanContent = cleanContent.replace(/<button[^>]*Print[^>]*>.*?<\/button>/gi, '');
                            cleanContent = cleanContent.replace(/<a[^>]*Print[^>]*>.*?<\/a>/gi, '');
                            
                            // Remove any button containing "Tax" or "Invoice" or "Billing" or "Receipt"
                            cleanContent = cleanContent.replace(/<button[^>]*>(.*?)(Tax|Invoice|Billing|Receipt)(.*?)<\/button>/gi, '');
                            cleanContent = cleanContent.replace(/<a[^>]*>(.*?)(Tax|Invoice|Billing|Receipt)(.*?)<\/a>/gi, '');
                            
                            // Remove entire footer sections that might contain buttons
                            cleanContent = cleanContent.replace(/<div[^>]*class="[^"]*modal-footer[^"]*"[^>]*>.*?<\/div>/gi, '');
                            cleanContent = cleanContent.replace(/<footer[^>]*>.*?<\/footer>/gi, '');
                            
                            // Create Create Billing-Receive button HTML (only for VT documents)
                            var createBillingReceiveButton = '';
                            if (invoiceNo.startsWith('VT')) {
                                createBillingReceiveButton = `
                                    <button type="button" class="btn btn-warning modal-create-billing-btn" 
                                            data-transaction-id="${transactionId}"
                                            style="padding: 10px 20px; border-radius: 5px; font-weight: 500;">
                                        <i class="fa fa-receipt"></i> Create Billing-Receive
                                    </button>
                                `;
                            }
                            
                            // Create improved modal with print button and Create Billing-Receive button in BOTTOM RIGHT footer
                            var modalHtml = `
                                <div class="modal-dialog modal-xl" style="width: 95%; max-width: 1200px;">
                                    <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0; padding: 20px; border-bottom: none;">
                                            <h4 class="modal-title" style="font-size: 20px; font-weight: 600; margin: 0;">
                                                <i class="fa fa-file-invoice"></i> Transaction Details
                                            </h4>
                                            <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8; font-size: 28px; background: none; border: none;">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body" style="max-height: 75vh; overflow-y: auto; padding: 30px; background-color: #f8f9fa;">
                                            <div style="background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                ${cleanContent}
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e9ecef; border-radius: 0 0 8px 8px; padding: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
                                            ${createBillingReceiveButton}
                                            <button type="button" class="btn ${printButtonClass} modal-print-btn" 
                                                    data-transaction-id="${transactionId}" 
                                                    data-document-type="${docTypeForAPI}"
                                                    style="padding: 10px 20px; border-radius: 5px; font-weight: 500;">
                                                ${printButtonText}
                                            </button>
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal" style="padding: 10px 25px; border-radius: 5px;">
                                                <i class="fa fa-times"></i> Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Replace modal content with improved styling
                            $('.view_modal').html(modalHtml);
                            
                            // Apply currency conversion to the modal content
                            __currency_convert_recursively($('.view_modal'));
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to load transaction:', error); // Debug log
                            
                            var errorHtml = `
                                <div class="modal-dialog modal-lg" style="width: 90%; max-width: 800px;">
                                    <div class="modal-content" style="border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                                        <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; border-radius: 8px 8px 0 0; padding: 20px;">
                                            <h4 class="modal-title" style="font-size: 20px; font-weight: 600; margin: 0;">
                                                <i class="fa fa-exclamation-triangle"></i> Error Loading Transaction
                                            </h4>
                                            <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8; font-size: 28px;">
                                                <span>&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body text-center" style="padding: 50px 40px; background-color: #f8f9fa;">
                                            <div style="background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                                <i class="fas fa-exclamation-triangle fa-4x text-warning" style="margin-bottom: 20px;"></i>
                                                <h5 style="color: #666; font-weight: 600; margin-bottom: 15px;">Failed to load transaction details</h5>
                                                <p class="text-muted">Error: ${error}</p>
                                                <p class="text-muted">Status: ${xhr.status}</p>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e9ecef; border-radius: 0 0 8px 8px; padding: 20px;">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal" style="padding: 10px 25px; border-radius: 5px;">
                                                <i class="fa fa-times"></i> Close
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            $('.view_modal').html(errorHtml);
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
                    // Clear any existing modals
                    $('.payment_modal').modal('hide').html('');
                    
                    console.log('Loading view payment from:', href); // Debug log
                    
                    // Load view payments modal
                    $.ajax({
                        method: 'GET',
                        url: href,
                        dataType: 'html',
                        success: function(result) {
                            console.log('View payment loaded successfully'); // Debug log
                            $('.payment_modal').html(result).modal('show');
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

            // Handle regular add payment modal - ensure it works properly with JSON response
            $(document).on('click', '.add_payment_modal', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var paymentUrl = $(this).attr('href');
                console.log('Add payment clicked:', paymentUrl); // Debug log
                
                if (paymentUrl) {
                    // Clear any existing modals
                    $('.payment_modal').modal('hide').html('');
                    
                    // Load payment modal using JSON response (same as payment.js)
                    $.ajax({
                        url: paymentUrl,
                        dataType: 'json',
                        success: function(result) {
                            console.log('Add payment response:', result);
                            if (result.status == 'due') {
                                $('.payment_modal').html(result.view).modal('show');
                                
                                // Ensure this is NOT in billing receipt mode
                                $('.payment_modal').removeAttr('data-billing-receipt-mode');
                                
                                __currency_convert_recursively($('.payment_modal'));
                                $('#paid_on').datetimepicker({
                                    format: moment_date_format + ' ' + moment_time_format,
                                    ignoreReadonly: true,
                                });
                                $('.payment_modal').find('form#transaction_payment_add_form').validate();
                                set_default_payment_account();

                                $('.payment_modal')
                                    .find('input[type="checkbox"].input-icheck')
                                    .each(function() {
                                        $(this).iCheck({
                                            checkboxClass: 'icheckbox_square-blue',
                                            radioClass: 'iradio_square-blue',
                                        });
                                    });
                            } else {
                                toastr.error(result.msg);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Add payment failed:', xhr, status, error);
                            toastr.error('Failed to load payment modal');
                        }
                    });
                } else {
                    console.log('No href found for add_payment_modal'); // Debug log
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

            // Print invoice functionality - original method
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
                    apiEndpoint = 'https://api-shop.rubyshop.co.th/tax-invoice/' + invoiceId + '/pdf-print-nodejs';
                } else if (documentType === 'final') {
                    apiEndpoint = 'https://api-shop.rubyshop.co.th/billing-receipt/' + invoiceId + '/pdf-print-nodejs';
                } else {
                    toastr.error('Unknown document type: ' + documentType);
                    return;
                }
                
                console.log('Calling PDF API:', apiEndpoint, 'for document type:', documentType);
                
                // First check if PDF service is running
                $.ajax({
                    method: 'GET',
                    url: 'https://api-shop.rubyshop.co.th/health',
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
                    apiEndpoint = 'https://api-shop.rubyshop.co.th/tax-invoice/' + transactionId + '/pdf-print-nodejs';
                } else if (documentType === 'final') {
                    apiEndpoint = 'https://api-shop.rubyshop.co.th/billing-receipt/' + transactionId + '/pdf-print-nodejs';
                } else {
                    toastr.error('Unknown document type: ' + documentType);
                    return;
                }
                
                console.log('Calling PDF API:', apiEndpoint, 'for document type:', documentType);
                
                // First check if PDF service is running
                $.ajax({
                    method: 'GET',
                    url: 'https://api-shop.rubyshop.co.th/health',
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

            // Handle modal Create Billing-Receive button click
            $(document).on('click', '.modal-create-billing-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                var transactionId = $(this).data('transaction-id');
                
                console.log('Modal Create Billing-Receive button clicked:', transactionId);
                
                if (!transactionId) {
                    toastr.error('Transaction ID not found');
                    return;
                }
                
                // Close the transaction details modal first
                $('.view_modal').modal('hide');
                
                // Store proforma ID for later use
                window.pendingBillingReceiptProformaId = transactionId;
                
                // Show the Create Billing-Receipt options modal
                setTimeout(function() {
                    $('#createBillingReceiptModal').modal('show');
                }, 300); // Small delay for smooth modal transition
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
            
            // Functions to manage billing receipt highlighting with localStorage
            function storeNewBillingReceiptId(id) {
                const data = {
                    id: id,
                    timestamp: Date.now()
                };
                localStorage.setItem('newBillingReceiptHighlight', JSON.stringify(data));
            }
            
            function getNewBillingReceiptId() {
                const stored = localStorage.getItem('newBillingReceiptHighlight');
                if (!stored) return null;
                
                const data = JSON.parse(stored);
                const oneMinute = 60 * 1000; // 1 minute in milliseconds
                
                // Check if more than 1 minute has passed
                if (Date.now() - data.timestamp > oneMinute) {
                    localStorage.removeItem('newBillingReceiptHighlight');
                    return null;
                }
                
                return data.id;
            }
            
            function clearNewBillingReceiptId() {
                localStorage.removeItem('newBillingReceiptHighlight');
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

            // Create Billing-Receipt functionality
            $(document).on('click', '.create-billing-receipt', function(e) {
                e.preventDefault();
                
                var proformaId = $(this).data('id');
                
                if (!proformaId) {
                    toastr.error('Proforma ID not found');
                    return;
                }
                
                console.log('Create billing receipt clicked for proforma ID:', proformaId);
                
                // Store proforma ID for later use
                window.pendingBillingReceiptProformaId = proformaId;
                
                // Show styled modal instead of confirm dialog
                $('#createBillingReceiptModal').modal('show');
            });

            // Handle option card clicks in the modal
            $(document).on('click', '.option-card', function() {
                // Remove active state from all cards
                $('.option-card').css({
                    'border-color': '#e9ecef',
                    'background-color': 'white',
                    'transform': 'none'
                });
                
                // Add active state to clicked card
                $(this).css({
                    'border-color': '#4CAF50',
                    'background-color': '#f8fff8',
                    'transform': 'translateY(-2px)'
                });
                
                var option = $(this).data('option');
                var proformaId = window.pendingBillingReceiptProformaId;
                
                // Close modal
                $('#createBillingReceiptModal').modal('hide');
                
                // Execute the chosen option
                setTimeout(function() {
                    if (option === 'only') {
                        // User chose to create billing receipt only
                        createBillingReceiptOnly(proformaId);
                    } else if (option === 'with-payment') {
                        // User chose to create billing receipt + add payment
                        console.log('User chose to create billing receipt with payment - opening payment modal directly');
                        openPaymentModalForBillingReceipt(proformaId);
                    }
                }, 300); // Small delay for smooth modal transition
            });

            // Add hover effects for option cards
            $(document).on('mouseenter', '.option-card', function() {
                if ($(this).css('border-color') !== 'rgb(76, 175, 80)') { // Not selected
                    $(this).css({
                        'border-color': '#ddd',
                        'transform': 'translateY(-1px)'
                    });
                }
            });

            $(document).on('mouseleave', '.option-card', function() {
                if ($(this).css('border-color') !== 'rgb(76, 175, 80)') { // Not selected
                    $(this).css({
                        'border-color': '#e9ecef',
                        'transform': 'none'
                    });
                }
            });
            
            // Function to create billing receipt only (without payment modal)
            function createBillingReceiptOnly(proformaId) {
                console.log('Creating billing receipt only for proforma ID:', proformaId);
                
                $.ajax({
                    method: 'POST',
                    url: '{{ route("sells.create-billing-receive", ":id") }}'.replace(':id', proformaId),
                    data: {
                        _token: '{{ csrf_token() }}',
                        with_payment: false
                    },
                    success: function(response) {
                        console.log('Billing receipt only creation response:', response);
                        
                        if (response.success) {
                            toastr.success(response.msg);
                            
                            // Store the new billing receipt ID for highlighting
                            if (response.billing_receipt_id) {
                                storeNewBillingReceiptId(response.billing_receipt_id);
                            }
                            
                            // Show loading overlay before reloading table
                            showTableLoading();
                            summary_sales_table.ajax.reload();
                            
                            // Show success info
                            setTimeout(function() {
                                toastr.info('Billing-Receipt created successfully. You can add payments later if needed.', 'Success');
                            }, 1000);
                        } else {
                            console.error('Failed to create billing receipt:', response);
                            toastr.error(response.msg || 'Failed to create Billing-Receipt');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Billing receipt only creation failed:', xhr, status, error);
                        console.error('Response text:', xhr.responseText);
                        console.error('Status:', xhr.status);
                        toastr.error('Failed to create Billing-Receipt: ' + xhr.status + ' - ' + error);
                    }
                });
            }
            
            // Function to open payment modal for billing receipt creation
            function openPaymentModalForBillingReceipt(proformaId) {
                console.log('Opening payment modal for billing receipt creation, proforma ID:', proformaId);
                
                // Show loading in payment modal
                var loadingHtml = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h4 class="modal-title">Add Payment for Billing-Receipt</h4>
                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                            </div>
                            <div class="modal-body">
                                <div class="text-center">
                                    <i class="fa fa-spinner fa-spin fa-3x"></i>
                                    <p>Loading payment form...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                $('.payment_modal').html(loadingHtml).modal({
                    backdrop: 'static',
                    keyboard: false
                });
                
                // Load the payment form using the correct endpoint that returns JSON
                $.ajax({
                    method: 'GET',
                    url: '/payments/add_payment/' + proformaId,
                    dataType: 'json', // This is the key fix - expect JSON response
                    cache: false,
                    beforeSend: function() {
                        console.log('Loading payment form for proforma ID:', proformaId);
                    },
                    success: function(result) {
                        console.log('Payment form loaded successfully:', result);
                        
                        if (result.status == 'due' && result.view) {
                            // Payment form loaded successfully
                            console.log('Rendering payment form in modal...');
                            
                            // Clear the modal and add the view content
                            $('.payment_modal').html(result.view);
                            
                            // Initialize the modal properly
                            $('.payment_modal').modal('show');
                            
                            // Initialize currency conversion and form validation
                            __currency_convert_recursively($('.payment_modal'));
                            
                            // Initialize datetime picker
                            $('#paid_on').datetimepicker({
                                format: moment_date_format + ' ' + moment_time_format,
                                ignoreReadonly: true,
                            });
                            
                            // Initialize form validation
                            $('.payment_modal').find('form#transaction_payment_add_form').validate();
                            
                            // Set default payment account
                            set_default_payment_account();
                            
                            // Initialize iCheck for checkboxes
                            $('.payment_modal')
                                .find('input[type="checkbox"].input-icheck')
                                .each(function() {
                                    $(this).iCheck({
                                        checkboxClass: 'icheckbox_square-blue',
                                        radioClass: 'iradio_square-blue',
                                    });
                                });
                            
                            // Modify the modal title to indicate this is for billing receipt
                            $('.payment_modal .modal-title').text('Add Payment for Billing-Receipt Creation');
                            
                            // Add a note about what will happen
                            var noteHtml = `
                                <div class="alert alert-info" style="margin-bottom: 15px;">
                                    <i class="fa fa-info-circle"></i> 
                                    <strong>Note:</strong> After saving this payment, the Billing-Receipt will be created with this payment information.
                                </div>
                            `;
                            $('.payment_modal .modal-body').prepend(noteHtml);
                            
                            // Set up custom form submission handler
                            setupBillingReceiptPaymentModal();
                            
                        } else if (result.status == 'paid') {
                            // Transaction is already paid
                            toastr.error(result.msg || 'This transaction is already fully paid');
                            $('.payment_modal').modal('hide');
                        } else {
                            // Unexpected response
                            console.error('Unexpected payment form response:', result);
                            var errorHtml = `
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">Error Loading Payment Form</h4>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="alert alert-danger">
                                                <i class="fa fa-exclamation-triangle"></i>
                                                <strong>Error:</strong> Payment form could not be loaded properly. 
                                                The server returned an unexpected response.
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            $('.payment_modal').html(errorHtml);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to load payment form:', xhr, status, error);
                        console.error('Status:', xhr.status);
                        console.error('Response text:', xhr.responseText);
                        
                        var errorHtml = `
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h4 class="modal-title">Payment Form Load Error</h4>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-warning">
                                            <i class="fa fa-exclamation-triangle"></i>
                                            <strong>Unable to load payment form.</strong>
                                            <br><br>
                                            <strong>What you can do:</strong>
                                            <ul>
                                                <li>Close this modal and try creating the billing receipt without payment first</li>
                                                <li>Then add payments later using the "Add Payment" button in the table</li>
                                                <li>Or refresh the page and try again</li>
                                            </ul>
                                        </div>
                                        <div class="alert alert-info">
                                            <strong>Error Details:</strong> ${xhr.status} - ${error}
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        `;
                        $('.payment_modal').html(errorHtml);
                    }
                });
            }
            
            // Setup custom handling for billing receipt payment modal
            function setupBillingReceiptPaymentModal() {
                console.log('Setting up billing receipt payment modal handlers');
                
                // Mark this modal as being for billing receipt creation
                $('.payment_modal').attr('data-billing-receipt-mode', 'true');
                
                // Override the form submission for billing receipt creation
                $('.payment_modal form').off('submit.billing-receipt').on('submit.billing-receipt', function(e) {
                    e.preventDefault();
                    console.log('Payment form submitted for billing receipt creation');
                    
                    var form = $(this);
                    var formData = new FormData(form[0]);
                    
                    // Debug: log form data
                    for (var pair of formData.entries()) {
                        console.log('Form data:', pair[0] + ' = ' + pair[1]);
                    }
                    
                    // Show loading state
                    var submitButton = form.find('button[type="submit"]');
                    var originalText = submitButton.text();
                    submitButton.prop('disabled', true).text('Processing...');
                    
                    // First save the payment - use the correct payments store endpoint
                    var formAction = form.attr('action');
                    console.log('Original form action:', formAction);
                    
                    // Always use the correct payments store URL
                    formAction = '/payments';
                    console.log('Using payments store URL:', formAction);
                    
                    $.ajax({
                        method: 'POST',
                        url: formAction,
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(paymentResponse) {
                            console.log('Payment saved successfully:', paymentResponse);
                            
                            if (paymentResponse.success) {
                                toastr.success(paymentResponse.msg || 'Payment saved successfully');
                                
                                // Close payment modal
                                $('.payment_modal').modal('hide');
                                
                                // Now create the billing receipt with the payment information
                                console.log('Creating billing receipt with payment ID:', paymentResponse.payment_id);
                                createBillingReceiptWithPayment(window.pendingBillingReceiptProformaId, paymentResponse);
                            } else {
                                // Reset submit button
                                submitButton.prop('disabled', false).text(originalText);
                                console.error('Payment save failed:', paymentResponse);
                                toastr.error(paymentResponse.msg || 'Failed to save payment');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Payment save failed:', xhr, status, error);
                            console.error('Response text preview:', xhr.responseText ? xhr.responseText.substring(0, 200) : 'No response text');
                            
                            // Reset submit button
                            submitButton.prop('disabled', false).text(originalText);
                            
                            var errorMessage = 'Failed to save payment';
                            
                            // Check if response is HTML (redirect page) instead of JSON
                            if (xhr.responseText && xhr.responseText.includes('<!DOCTYPE html>')) {
                                errorMessage = 'Payment form submission returned a page redirect instead of JSON response. This might be a server configuration issue.';
                                console.error('Received HTML response instead of JSON - this indicates the AJAX request was not properly handled');
                            } else if (xhr.responseJSON && xhr.responseJSON.msg) {
                                errorMessage = xhr.responseJSON.msg;
                            } else if (xhr.responseText) {
                                try {
                                    var errorResponse = JSON.parse(xhr.responseText);
                                    errorMessage = errorResponse.msg || errorResponse.message || errorMessage;
                                } catch (e) {
                                    errorMessage = 'Error: ' + xhr.status + ' - ' + error;
                                }
                            }
                            toastr.error(errorMessage);
                        }
                    });
                });
                
                // Handle modal close/cancel - clear pending proforma ID
                $('.payment_modal').off('hidden.bs.modal.billing-receipt').on('hidden.bs.modal.billing-receipt', function() {
                    console.log('Payment modal closed');
                    if (window.pendingBillingReceiptProformaId) {
                        console.log('Clearing pending billing receipt proforma ID');
                        window.pendingBillingReceiptProformaId = null;
                    }
                    // Remove billing receipt mode marker
                    $('.payment_modal').removeAttr('data-billing-receipt-mode');
                });
            }
            
            // Function to create billing receipt after payment is saved
            function createBillingReceiptWithPayment(proformaId, paymentResponse) {
                console.log('Creating billing receipt with payment info:', paymentResponse);
                console.log('Proforma ID:', proformaId);
                
                if (!proformaId) {
                    toastr.error('Proforma ID is missing');
                    return;
                }
                
                // Show loading message
                toastr.info('Creating Billing-Receipt with payment information...', 'Please wait');
                
                var requestData = {
                    _token: '{{ csrf_token() }}',
                    payment_id: paymentResponse.payment_id || null,
                    with_payment: true
                };
                
                console.log('Request data for billing receipt creation:', requestData);
                console.log('Request URL:', '{{ route("sells.create-billing-receive", ":id") }}'.replace(':id', proformaId));
                
                // AJAX call to create billing receipt with payment info
                $.ajax({
                    method: 'POST',
                    url: '{{ route("sells.create-billing-receive", ":id") }}'.replace(':id', proformaId),
                    data: requestData,
                    timeout: 30000,
                    success: function(response) {
                        console.log('Billing receipt creation response:', response);
                        
                        if (response.success) {
                            // Show different success messages based on whether payment was applied
                            if (response.payment_applied) {
                                toastr.success(response.msg);
                                
                                // Show additional success info
                                if (response.tax_invoice_status === 'paid') {
                                    toastr.info('Tax-Invoice payment status updated to: PAID', 'Payment Applied');
                                } else if (response.tax_invoice_status === 'partial') {
                                    toastr.info('Tax-Invoice payment status updated to: PARTIAL', 'Payment Applied');
                                }
                            } else {
                                toastr.success(response.msg);
                            }
                            
                            // Store the new billing receipt ID in localStorage for 1 minute
                            if (response.billing_receipt_id) {
                                storeNewBillingReceiptId(response.billing_receipt_id);
                            }
                            
                            // Show loading overlay before reloading table
                            showTableLoading();
                            
                            // Reload the table to show the new billing receipt and updated tax invoice
                            summary_sales_table.ajax.reload();
                            
                            // Clear pending proforma ID
                            window.pendingBillingReceiptProformaId = null;
                            
                            // Optionally redirect to the new billing receipt or show it
                            if (response.redirect_url) {
                                setTimeout(function() {
                                    window.location.href = response.redirect_url;
                                }, 2000);
                            }
                        } else {
                            console.error('Billing receipt creation failed:', response);
                            toastr.error(response.msg || 'Failed to create Billing-Receipt');
                            window.pendingBillingReceiptProformaId = null;
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Billing-Receipt creation failed:', xhr, status, error);
                        console.error('Response text:', xhr.responseText);
                        console.error('Status:', xhr.status);
                        
                        var errorMessage = 'Failed to create Billing-Receipt';
                        
                        if (status === 'timeout') {
                            errorMessage = 'Request timed out. Please try again.';
                        } else if (xhr.status === 0) {
                            errorMessage = 'Cannot connect to server. Please check your connection.';
                        } else if (xhr.status === 404) {
                            errorMessage = 'Route not found. Please check the URL configuration.';
                        } else if (xhr.status === 500) {
                            errorMessage = 'Server error occurred. Please check the server logs.';
                        } else if (xhr.responseJSON && xhr.responseJSON.msg) {
                            errorMessage = xhr.responseJSON.msg;
                        } else if (xhr.responseText) {
                            try {
                                var errorResponse = JSON.parse(xhr.responseText);
                                errorMessage = errorResponse.msg || errorResponse.message || errorResponse.error || errorMessage;
                            } catch (e) {
                                errorMessage = 'Error: ' + xhr.status + ' - ' + error;
                            }
                        }
                        
                        toastr.error(errorMessage);
                        window.pendingBillingReceiptProformaId = null;
                    }
                });
            }

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
                
                // Store the current filter
                window.currentDocumentFilter = filter;
                
                // Debug log
                console.log('Filter changed to:', filter, 'Tax Invoice:', taxInvoiceChecked, 'Billing Receive:', billingReceiveChecked);
                
                // Show loading overlay before reloading
                showTableLoading();
                
                // Reload the DataTable with the new filter
                summary_sales_table.ajax.reload();
            });
        });
    </script>
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
