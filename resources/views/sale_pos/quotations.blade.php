@extends('layouts.app')
@section('title', __( 'lang_v1.quotation'))

@section('css')
<link rel="stylesheet" href="{{ asset('css/pdf-loader.css') }}">
<style>
    /* Make table rows clickable */
    #sell_table tbody tr {
        cursor: pointer;
    }
    #sell_table tbody tr:hover {
        background-color: #f5f5f5;
    }

    /* Fix dropdown positioning for action column */
    .dataTables_wrapper,
    .table-responsive,
    .box-body {
        overflow: visible !important;
    }

    /* Action column dropdown positioning */
    #sell_table .btn-group {
        position: relative;
        display: inline-block;
        vertical-align: middle;
    }

    #sell_table .dropdown-menu {
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

    #sell_table .btn-group.open .dropdown-menu {
        display: block;
    }

    /* Fixed table layout */
    #sell_table {
        table-layout: fixed;
        width: 100%;
    }

    /* Date column */
    #sell_table th:nth-child(1),
    #sell_table td:nth-child(1) {
        width: 100px;
        min-width: 100px;
    }

    /* Ref No column */
    #sell_table th:nth-child(2),
    #sell_table td:nth-child(2) {
        width: 130px;
        min-width: 130px;
    }

    /* Customer Name column */
    #sell_table th:nth-child(3),
    #sell_table td:nth-child(3) {
        width: 180px;
        min-width: 180px;
    }

    /* Contact No column */
    #sell_table th:nth-child(4),
    #sell_table td:nth-child(4) {
        width: 120px;
        min-width: 120px;
    }

    /* Location column */
    #sell_table th:nth-child(5),
    #sell_table td:nth-child(5) {
        width: 110px;
        min-width: 110px;
    }

    /* Total Items column */
    #sell_table th:nth-child(6),
    #sell_table td:nth-child(6) {
        width: 90px;
        min-width: 90px;
        text-align: right;
    }

    /* Total Amount column */
    #sell_table th:nth-child(7),
    #sell_table td:nth-child(7) {
        width: 130px;
        min-width: 130px;
        text-align: right;
    }

    /* Added By column */
    #sell_table th:nth-child(8),
    #sell_table td:nth-child(8) {
        width: 120px;
        min-width: 120px;
    }

    /* Action column (last) */
    #sell_table th:last-child,
    #sell_table td:last-child {
        width: 120px;
        min-width: 120px;
        max-width: 120px;
        overflow: visible !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
    }

    /* Truncate non-action cells */
    #sell_table td:not(:last-child) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        padding: 8px 4px;
    }

    #sell_table td {
        padding: 8px 4px;
    }

    #sell_table td:last-child {
        overflow: visible;
        white-space: nowrap;
        text-overflow: initial;
    }

    #sell_table .btn {
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

    #sell_table .btn-info {
        color: #fff;
        background-color: #5bc0de;
        border-color: #46b8da;
    }

    #sell_table .btn-xs {
        padding: 1px 5px;
        font-size: 12px;
        line-height: 1.5;
        border-radius: 3px;
    }

    /* Ensure table wrapper allows dropdown overflow */
    #sell_table_wrapper {
        position: relative;
        overflow: visible;
    }

    .dataTables_wrapper {
        overflow: visible;
    }

    .table-responsive {
        overflow-x: auto;
        overflow-y: visible;
        min-height: 400px;
    }

    /* Loading overlay */
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

    /* Highlight newly created record */
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

    /* Modal styling */
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

    .view_modal .modal-dialog,
    .view_modal .modal-dialog.modal-xl {
        width: 98%;
        max-width: 1400px;
        margin: 80px auto;
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

    .modal.fade .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: translate(0, -50px);
    }

    .modal.show .modal-dialog {
        transform: none;
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
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.list_quotations')
        <small></small>
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
        @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_location_id',  __('purchase.business_location') . ':') !!}

                {!! Form::select('sell_list_filter_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all') ]); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_customer_id',  __('contact.customer') . ':') !!}
                {!! Form::select('sell_list_filter_customer_id', $customers, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('sell_list_filter_date_range', __('report.date_range') . ':') !!}
                {!! Form::text('sell_list_filter_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'readonly']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('created_by',  __('report.user') . ':') !!}
                {!! Form::select('created_by', $sales_representative, null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
            </div>
        </div>
    @endcomponent
    @component('components.widget', ['class' => 'box-primary'])
        @slot('tool')
            <div class="box-tools">

                <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                    href="{{action([\App\Http\Controllers\SellController::class, 'create'], ['status' => 'quotation'])}}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('lang_v1.add_quotation')
                </a>
            </div>
        @endslot
        <div id="sell_table_wrapper" style="position: relative;">
          
            <div class="table-responsive">
                <table class="table table-bordered table-striped ajax_view" id="sell_table">
                    <thead>
                        <tr>
                            <th>@lang('messages.date')</th>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('sale.customer_name')</th>
                            <th>@lang('lang_v1.contact_no')</th>
                            <th>@lang('sale.location')</th>
                            <th>@lang('lang_v1.total_items')</th>
                            <th>Total Amount</th>
                            <th>@lang('lang_v1.added_by')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    @endcomponent
</section>
<!-- /.content -->
@stop
@section('javascript')
<script src="{{ asset('js/pdf-loader.js') }}"></script>
<script type="text/javascript">
$(document).ready( function(){
    // Initialize clean state: ensure all modals are properly closed
    $('.modal').modal('hide');
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');

    // Custom date range for quotations: January 1st of last year to today
    var lastYear = moment().subtract(1, 'year').year();
    var quotationDateRangeSettings = {
        ranges: ranges,
        startDate: moment(lastYear + '-01-01'),
        endDate: moment(),
        locale: {
            cancelLabel: LANG.clear,
            applyLabel: LANG.apply,
            customRangeLabel: LANG.custom_range,
            format: moment_date_format,
            toLabel: '~',
        },
    };

    //Date range as a button
    $('#sell_list_filter_date_range').daterangepicker(
        quotationDateRangeSettings,
        function (start, end) {
            $('#sell_list_filter_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            sell_table.ajax.reload();
        }
    );

    // Set initial value
    $('#sell_list_filter_date_range').val(
        moment(lastYear + '-01-01').format(moment_date_format) + ' ~ ' + moment().format(moment_date_format)
    );
    $('#sell_list_filter_date_range').on('cancel.daterangepicker', function(ev, picker) {
        $('#sell_list_filter_date_range').val('');
        sell_table.ajax.reload();
    });

    sell_table = $('#sell_table').DataTable({
        processing: true,
        serverSide: true,
        aaSorting: [[0, 'desc']],
        "ajax": {
            "url": '/sells/draft-dt?is_quotation=1',
            "data": function ( d ) {
                console.log('=== DataTable Request Parameters ===');
                if($('#sell_list_filter_date_range').val()) {
                    var start = $('#sell_list_filter_date_range').data('daterangepicker').startDate.format('YYYY-MM-DD');
                    var end = $('#sell_list_filter_date_range').data('daterangepicker').endDate.format('YYYY-MM-DD');
                    d.start_date = start;
                    d.end_date = end;
                }

                if($('#sell_list_filter_location_id').length) {
                    d.location_id = $('#sell_list_filter_location_id').val();
                }
                d.customer_id = $('#sell_list_filter_customer_id').val();

                if($('#created_by').length) {
                    d.created_by = $('#created_by').val();
                }

                console.log('Request Data:', d);
            },
            "dataSrc": function (json) {
                console.log('=== DataTable Response ===');
                console.log('Full Response:', json);
                console.log('Total Records:', json.recordsTotal);
                console.log('Filtered Records:', json.recordsFiltered);
                console.log('Data Array Length:', json.data ? json.data.length : 0);
                if (json.data && json.data.length > 0) {
                    console.log('First Row Sample:', json.data[0]);
                } else {
                    console.log('NO DATA RETURNED!');
                }
                return json.data;
            },
            "error": function (xhr, error, code) {
                console.error('=== DataTable AJAX Error ===');
                console.error('Status:', xhr.status);
                console.error('Error:', error);
                console.error('Code:', code);
                console.error('Response:', xhr.responseText);
            }
        },
        columnDefs: [ {
            "targets": 8,
            "orderable": false,
            "searchable": false
        } ],
        columns: [
            { data: 'transaction_date', name: 'transaction_date'  },
            { data: 'invoice_no', name: 'invoice_no'},
            { data: 'conatct_name', name: 'conatct_name'},
            { data: 'mobile', name: 'contacts.mobile'},
            { data: 'business_location', name: 'bl.name'},
            { data: 'total_items', name: 'total_items', "searchable": false},
            { data: 'final_total', name: 'transactions.final_total', "searchable": false},
            { data: 'added_by', name: 'added_by'},
            { data: 'action', name: 'action'}
        ],
        "fnDrawCallback": function (oSettings) {
            __currency_convert_recursively($('#sell_table'));
        },
        createdRow: function(row, data, dataIndex) {
            // Add data-href attribute for row clicking
            if (data.DT_RowData && data.DT_RowData.href) {
                $(row).attr('data-href', data.DT_RowData.href);
            }
            // Make rows clickable
            $(row).css('cursor', 'pointer');
        }
    });
    
    $(document).on('change', '#sell_list_filter_location_id, #sell_list_filter_customer_id, #created_by',  function() {
        sell_table.ajax.reload();
    });

    // Row click to view quotation in modal - use standard modal loading
    $(document).off('click', 'table#sell_table tbody tr').on('click', 'table#sell_table tbody tr', function(e) {
        // Don't trigger if clicking on action buttons, dropdowns, links
        if ($(e.target).closest('a, button, .dropdown, .btn-group').length) {
            return;
        }

        var href = $(this).data('href');

        if (href) {
            // Load content directly into view_modal using standard approach
            $.ajax({
                method: 'GET',
                url: href,
                dataType: 'html',
                success: function(result) {
                    $('.view_modal').html(result).modal('show');
                    __currency_convert_recursively($('.view_modal'));
                },
                error: function(xhr, status, error) {
                    toastr.error('Failed to load quotation details');
                }
            });
        }
    });

    $(document).on('click', 'a.convert-to-proforma', function(e){
        e.preventDefault();
        swal({
            title: LANG.sure,
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(confirm => {
            if (confirm) {
                var url = $(this).attr('href');
                $.ajax({
                    method: 'GET',
                    url: url,
                    dataType: 'json',
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            sell_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                });
            }
        });
    });
});

// Function to create proforma from quotation
function createProforma(quotationId) {
    if (confirm('Are you sure you want to create a Tax-Invoice (Proforma) from this quotation?')) {
        $.ajax({
            method: 'POST',
            url: '/quotations/' + quotationId + '/create-proforma',
            dataType: 'json',
            data: {
                '_token': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success == true) {
                    toastr.success(result.msg);
                    sell_table.ajax.reload();
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('An error occurred while creating the proforma.');
            }
        });
    }
}

// Function to create Billing-receive from Tax-Invoice
function createBillingReceive(id) {
    if (confirm('Are you sure you want to create a Billing-receive from this Tax-Invoice?')) {
        $.ajax({
            url: '/sells/create-billing-receive/' + id,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg);
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        // Reload the current page if no redirect URL
                        if (typeof sell_table !== 'undefined') {
                            sell_table.ajax.reload();
                        } else {
                            location.reload();
                        }
                    }
                } else {
                    toastr.error(response.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Error creating Billing-receive: ' + error);
            }
        });
    }
}

// Function to create Tax-Invoice (Proforma) from quotation
function createTaxInvoice(id) {
    console.log('createTaxInvoice called with ID:', id);

    // Close the view modal first
    $('.view_modal').modal('hide');

    // Use swal confirmation
    swal({
        title: 'Are you sure?',
        text: 'Are you sure you want to create a Tax-Invoice (Proforma) from this quotation?',
        icon: 'warning',
        buttons: true,
        dangerMode: false,
    }).then(confirm => {
        if (confirm) {
            executeCreateTaxInvoice(id);
        }
    });
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

// Functions to show/hide table loading overlay
function showTableLoading() {
    $('#table-loading-overlay').css('display', 'flex');
}

function hideTableLoading() {
    $('#table-loading-overlay').css('display', 'none');
}

// Function to actually create the tax invoice
function executeCreateTaxInvoice(quotationId) {
    console.log('Executing createTaxInvoice for ID:', quotationId);
    
    // Check if CSRF token exists
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    console.log('CSRF Token:', csrfToken);
    
    if (!csrfToken) {
        toastr.error('CSRF token not found! Please refresh the page.');
        return;
    }
    
    // Show loading overlay
    showTableLoading();
    
    console.log('User confirmed, sending AJAX request...');
    
    $.ajax({
        url: "{{ url('sells/create-tax-invoice') }}/" + quotationId,
        method: "POST",
        dataType: "json",
        data: {
            _token: csrfToken
        },
        beforeSend: function() {
            console.log('AJAX request starting...');
        },
        success: function(response) {
            console.log('AJAX success response:', response);
            
            if (response.success == 1 || response.success === true) {
                toastr.success(response.msg);
                console.log('Success! Tax Invoice ID:', response.tax_invoice_id);
                
                // Store the new tax invoice ID for highlighting
                if (response.tax_invoice_id) {
                    storeNewTaxInvoiceId(response.tax_invoice_id);
                }
                
                // Hide loading overlay and reload table
                hideTableLoading();
                
                // Redirect to sells page to show the new tax invoice
                setTimeout(function() {
                    window.location.href = response.redirect_url || '/sells/summary-sales';
                }, 1000);
                
            } else {
                console.log('Response indicates failure:', response.msg);
                toastr.error(response.msg || 'Unknown error occurred');
                hideTableLoading();
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error Details:');
            console.error('Status:', status);
            console.error('Error:', error);
            console.error('Response Text:', xhr.responseText);
            console.error('Status Code:', xhr.status);
            
            hideTableLoading();
            
            // Try to parse error response
            try {
                var errorResponse = JSON.parse(xhr.responseText);
                console.error('Parsed Error Response:', errorResponse);
                toastr.error(errorResponse.message || 'Error creating Tax-Invoice');
            } catch(e) {
                console.error('Could not parse error response');
                toastr.error('Error creating Tax-Invoice. Status: ' + xhr.status);
            }
        },
        complete: function() {
            console.log('AJAX request completed');
        }
    });
}

// Function to create Billing-receive from Tax-Invoice (Proforma)
function createBillingReceive(taxInvoiceId) {
    if (confirm('Are you sure you want to create a Billing-receive from this Tax-Invoice?')) {
        $.ajax({
            url: '/sells/create-billing-receive/' + taxInvoiceId,
            type: 'POST',
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg);
                    if (response.redirect_url) {
                        window.location.href = response.redirect_url;
                    } else {
                        // Reload the current page if no redirect URL
                        if (typeof sell_table !== 'undefined') {
                            sell_table.ajax.reload();
                        } else {
                            location.reload();
                        }
                    }
                } else {
                    toastr.error(response.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('Error creating Billing-receive: ' + error);
            }
        });
    }
}

// Function to create final bill from proforma
function createFinalBill(proformaId) {
    if (confirm('Are you sure you want to create a Billing Receipt (Final) from this proforma?')) {
        $.ajax({
            method: 'POST',
            url: '/proforma/' + proformaId + '/create-final-bill',
            dataType: 'json',
            data: {
                '_token': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(result) {
                if (result.success == true) {
                    toastr.success(result.msg);
                    sell_table.ajax.reload();
                } else {
                    toastr.error(result.msg);
                }
            },
            error: function(xhr, status, error) {
                toastr.error('An error occurred while creating the final bill.');
            }
        });
    }
}
</script>
	
@endsection
