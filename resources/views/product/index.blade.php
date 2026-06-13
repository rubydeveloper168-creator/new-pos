@extends('layouts.app')
@section('title', __('sale.products'))

@section('content')
<!-- Enhanced Product Filter Styles -->
<style>
/* Enhanced Filter Panel */
.enhanced-filter-panel {
    background: #3595f6;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.enhanced-filter-panel h4 {
    color: white;
    margin-bottom: 15px;
    font-weight: 600;
}

.filter-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: end;
}

.filter-group {
    flex: 1;
    min-width: 200px;
}

.filter-group label {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
    margin-bottom: 5px;
    display: block;
}

.enhanced-search-box {
    position: relative;
}

.enhanced-search-box input {
    padding-left: 40px;
    border-radius: 25px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.enhanced-search-box input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.enhanced-search-box .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.7);
}

.price-range-inputs {
    display: flex;
    gap: 10px;
    align-items: center;
}

.price-range-inputs input {
    flex: 1;
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    color: white;
    backdrop-filter: blur(10px);
}

.price-range-inputs input::placeholder {
    color: rgba(255, 255, 255, 0.7);
}

.price-range-inputs span {
    color: rgba(255, 255, 255, 0.9);
    font-weight: 500;
}

.filter-actions {
    display: flex;
    gap: 10px;
    align-items: end;
}

.btn-filter {
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    border-radius: 8px;
    padding: 8px 16px;
    font-weight: 500;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.btn-filter:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: rgba(255, 255, 255, 0.5);
    color: white;
    transform: translateY(-2px);
}

.btn-clear {
    background: rgba(220, 53, 69, 0.8);
    border: 2px solid rgba(220, 53, 69, 0.3);
}

.btn-clear:hover {
    background: rgba(220, 53, 69, 1);
    border-color: rgba(220, 53, 69, 0.5);
}

/* Active Filters Display */
.active-filters {
    background: white;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.active-filters h5 {
    margin-bottom: 10px;
    color: #333;
    font-weight: 600;
}

.filter-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-tag {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
}

.filter-tag .remove-filter {
    cursor: pointer;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transition: background 0.2s;
}

.filter-tag .remove-filter:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Enhanced Select2 Styling */
.select2-container--default .select2-selection--single {
    border-radius: 8px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    height: 38px;
    line-height: 34px;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    color: white;
    padding-left: 12px;
}

.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: rgba(255, 255, 255, 0.7);
}

/* Results Summary */
.results-summary {
    background: white;
    border-radius: 8px;
    padding: 15px 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border-left: 4px solid #667eea;
}

.results-summary h5 {
    margin: 0;
    color: #333;
    font-weight: 600;
}

.results-summary .summary-stats {
    display: flex;
    gap: 20px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.results-summary .stat-item {
    color: #666;
    font-size: 14px;
}

.results-summary .stat-value {
    font-weight: 600;
    color: #667eea;
}

/* Responsive Design */
@media (max-width: 768px) {
    .filter-row {
        flex-direction: column;
    }
    
    .filter-group {
        min-width: 100%;
    }
    
    .price-range-inputs {
        flex-direction: column;
        gap: 8px;
    }
    
    .filter-actions {
        width: 100%;
        justify-content: center;
    }
    
    .results-summary .summary-stats {
        flex-direction: column;
        gap: 8px;
    }
}
</style>

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('sale.products')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.manage_products')</small>
        </h1>
        <!-- <ol class="breadcrumb">
                    <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
                    <li class="active">Here</li>
                </ol> -->
    </section>

    <!-- Main content -->
    <section class="content">
        <!-- Enhanced Product Filter Panel -->
        <div class="enhanced-filter-panel">
            <h4><i class="fa fa-filter"></i> @lang('report.filters')</h4>
            <div class="filter-row">
                <!-- Search -->
                <div class="filter-group">
                    <label> @lang('lang_v1.search')</label>
                    <div class="enhanced-search-box">
                        <i class="fa fa-search search-icon"></i>
                        <input type="text" id="enhanced_search" class="form-control" placeholder="Search products, SKU, description...">
                    </div>
                </div>
                
                <!-- Price Range -->
                <div class="filter-group">
                    <label> Price Range</label>
                    <div class="price-range-inputs">
                        <input type="number" id="min_price_filter" class="form-control" placeholder="Min ฿">
                        <span>-</span>
                        <input type="number" id="max_price_filter" class="form-control" placeholder="Max ฿">
                    </div>
                </div>
                
                <!-- Category Filter -->
                <div class="filter-group">
                    <label> Category</label>
                    <select id="enhanced_category_filter" class="form-control select2" style="width: 100%;">
                        <option value="">All Categories</option>
                        @foreach($categories as $category_id => $category_name)
                            <option value="{{ $category_id }}">{{ $category_name }}</option>
                        @endforeach
                    </select>
                </div>
                
                <!-- Stock Status -->
                <div class="filter-group">
                    <label>Stock Status</label>
                    <select id="stock_status_filter" class="form-control">
                        <option value="">All Products</option>
                        <option value="in_stock">In Stock Only</option>
                        <option value="low_stock">Low Stock</option>
                        <option value="out_of_stock">Out of Stock</option>
                    </select>
                </div>
                
                <!-- Filter Actions -->
                <div class="filter-actions">
                    <button type="button" id="apply_enhanced_filters" class="btn btn-filter">
                        <i class="fa fa-search"></i> Apply Filters
                    </button>
                    <button type="button" id="clear_enhanced_filters" class="btn btn-filter btn-clear">
                        <i class="fa fa-times"></i> Clear All
                    </button>
                </div>
            </div>
        </div>

        <!-- Active Filters Display -->
        <div id="active_filters_display" class="active-filters" style="display: none;">
            <h5><i class="fa fa-tags"></i> Active Filters:</h5>
            <div class="filter-tags" id="active_filter_tags"></div>
        </div>

        <!-- Results Summary -->
        <div id="results_summary" class="results-summary" style="display: none;">
            <h5>Search Results</h5>
            <div class="summary-stats">
                <div class="stat-item">
                    <span class="stat-value" id="total_products_count">0</span> products found
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                @component('components.filters', ['title' => __('report.filters')])
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('type', __('product.product_type') . ':') !!}
                            {!! Form::select(
                                'type',
                                ['single' => __('lang_v1.single'), 'variable' => __('lang_v1.variable'), 'combo' => __('lang_v1.combo')],
                                null,
                                [
                                    'class' => 'form-control select2',
                                    'style' => 'width:100%',
                                    'id' => 'product_list_filter_type',
                                    'placeholder' => __('lang_v1.all'),
                                ],
                            ) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('category_id', __('product.category') . ':') !!}
                            {!! Form::select('category_id', $categories, null, [
                                'class' => 'form-control select2',
                                'style' => 'width:100%',
                                'id' => 'product_list_filter_category_id',
                                'placeholder' => __('lang_v1.all'),
                            ]) !!}
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('unit_id', __('product.unit') . ':') !!}
                            {!! Form::select('unit_id', $units, null, [
                                'class' => 'form-control select2',
                                'style' => 'width:100%',
                                'id' => 'product_list_filter_unit_id',
                                'placeholder' => __('lang_v1.all'),
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('tax_id', __('product.tax') . ':') !!}
                            {!! Form::select('tax_id', $taxes, null, [
                                'class' => 'form-control select2',
                                'style' => 'width:100%',
                                'id' => 'product_list_filter_tax_id',
                                'placeholder' => __('lang_v1.all'),
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('brand_id', __('product.brand') . ':') !!}
                            {!! Form::select('brand_id', $brands, null, [
                                'class' => 'form-control select2',
                                'style' => 'width:100%',
                                'id' => 'product_list_filter_brand_id',
                                'placeholder' => __('lang_v1.all'),
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-3" id="location_filter">
                        <div class="form-group">
                            {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                            {!! Form::select('location_id', $business_locations, null, [
                                'class' => 'form-control select2',
                                'style' => 'width:100%',
                                'placeholder' => __('lang_v1.all'),
                            ]) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <br>
                        <div class="form-group">
                            {!! Form::select(
                                'active_state',
                                ['active' => __('business.is_active'), 'inactive' => __('lang_v1.inactive')],
                                null,
                                [
                                    'class' => 'form-control select2',
                                    'style' => 'width:100%',
                                    'id' => 'active_state',
                                    'placeholder' => __('lang_v1.all'),
                                ],
                            ) !!}
                        </div>
                    </div>

                    <!-- include module filter -->
                    @if (!empty($pos_module_data))
                        @foreach ($pos_module_data as $key => $value)
                            @if (!empty($value['view_path']))
                                @includeIf($value['view_path'], ['view_data' => $value['view_data']])
                            @endif
                        @endforeach
                    @endif

                    <div class="col-md-3">
                        <div class="form-group">
                            <br>
                            <label>
                                {!! Form::checkbox('not_for_selling', 1, false, ['class' => 'input-icheck', 'id' => 'not_for_selling']) !!} <strong>@lang('lang_v1.not_for_selling')</strong>
                            </label>
                        </div>
                    </div>
                    @if ($is_woocommerce)
                        <div class="col-md-3">
                            <div class="form-group">
                                <br>
                                <label>
                                    {!! Form::checkbox('woocommerce_enabled', 1, false, ['class' => 'input-icheck', 'id' => 'woocommerce_enabled']) !!} {{ __('lang_v1.woocommerce_enabled') }}
                                </label>
                            </div>
                        </div>
                    @endif
                @endcomponent
            </div>
        </div>
        @can('product.view')
            <div class="row">
                <div class="col-md-12">
                    <!-- Custom Tabs -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active">
                                <a href="#product_list_tab" data-toggle="tab" aria-expanded="true"><i class="fa fa-cubes"
                                        aria-hidden="true"></i> @lang('lang_v1.all_products')</a>
                            </li>
                            @can('stock_report.view')
                                <li>
                                    <a href="#product_stock_report" class="product_stock_report" data-toggle="tab"
                                        aria-expanded="true"><i class="fa fa-hourglass-half" aria-hidden="true"></i>
                                     @lang('report.stock_report')</a>
                                </li>
                            @endcan
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active " id="product_list_tab">
                                @if ($is_admin)

                                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right tw-m-2"
                                        href="{{ action([\App\Http\Controllers\ProductController::class, 'downloadExcel']) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="icon icon-tabler icons-tabler-outline icon-tabler-download">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2 -2v-2" />
                                            <path d="M7 11l5 5l5 -5" />
                                            <path d="M12 4l0 12" />
                                        </svg> @lang('lang_v1.download_excel')
                                    </a>
                                @endif
                                @can('product.create')

                                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right tw-m-2"
                                        href="{{ action([\App\Http\Controllers\ProductController::class, 'create']) }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                            <path d="M12 5l0 14" />
                                            <path d="M5 12l14 0" />
                                        </svg> @lang('messages.add')
                                    </a>
                                    <br><br>
                                @endcan
                                @include('product.partials.product_list')
                            </div>
                            @can('stock_report.view')
                                <div class="tab-pane" id="product_stock_report">
                                    @include('report.partials.stock_report_table')
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        @endcan
        <input type="hidden" id="is_rack_enabled" value="{{ $rack_enabled }}">

        <div class="modal fade product_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

        <div class="modal fade" id="view_product_modal" tabindex="-1" role="dialog"
            aria-labelledby="gridSystemModalLabel">
        </div>

        <div class="modal fade" id="opening_stock_modal" tabindex="-1" role="dialog"
            aria-labelledby="gridSystemModalLabel">
        </div>

        @if ($is_woocommerce)
            @include('product.partials.toggle_woocommerce_sync_modal')
        @endif
        @include('product.partials.edit_product_location_modal')

    </section>
    <!-- /.content -->

@endsection

@section('javascript')
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            product_table = $('#product_table').DataTable({
                processing: true,
                serverSide: true,
                aaSorting: [
                    [3, 'asc']
                ],
                scrollY: "75vh",
                scrollX: true,
                scrollCollapse: true,
                "ajax": {
                    "url": "/products",
                    "data": function(d) {
                        // Original filters
                        d.type = $('#product_list_filter_type').val();
                        d.category_id = $('#product_list_filter_category_id').val();
                        d.brand_id = $('#product_list_filter_brand_id').val();
                        d.unit_id = $('#product_list_filter_unit_id').val();
                        d.tax_id = $('#product_list_filter_tax_id').val();
                        d.active_state = $('#active_state').val();
                        d.not_for_selling = $('#not_for_selling').is(':checked');
                        d.location_id = $('#location_id').val();
                        if ($('#repair_model_id').length == 1) {
                            d.repair_model_id = $('#repair_model_id').val();
                        }

                        if ($('#woocommerce_enabled').length == 1 && $('#woocommerce_enabled').is(
                                ':checked')) {
                            d.woocommerce_enabled = 1;
                        }

                        // Enhanced filters
                        d.enhanced_search = $('#enhanced_search').val();
                        d.enhanced_category = $('#enhanced_category_filter').val();
                        d.min_price = $('#min_price_filter').val();
                        d.max_price = $('#max_price_filter').val();
                        d.stock_status = $('#stock_status_filter').val();

                        d = __datatable_ajax_callback(d);
                    }
                },
                columnDefs: [{
                    "targets": [0, 1, 2],
                    "orderable": false,
                    "searchable": false
                }],
                columns: [{
                        data: 'mass_delete'
                    },
                    {
                        data: 'image',
                        name: 'products.image'
                    },
                    {
                        data: 'action',
                        name: 'action'
                    },
                    {
                        data: 'product',
                        name: 'products.name'
                    },
                    {
                        data: 'product_locations',
                        name: 'product_locations'
                    },
                    @can('view_purchase_price')
                        {
                            data: 'purchase_price',
                            name: 'max_purchase_price',
                            searchable: false
                        },
                    @endcan
                    @can('access_default_selling_price')
                        {
                            data: 'selling_price',
                            name: 'max_price',
                            searchable: false
                        },
                    @endcan {
                        data: 'current_stock',
                        searchable: false
                    },
                    {
                        data: 'type',
                        name: 'products.type'
                    },
                    {
                        data: 'category',
                        name: 'c1.name'
                    },
                    {
                        data: 'brand',
                        name: 'brands.name'
                    },
                    {
                        data: 'tax',
                        name: 'tax_rates.name',
                        searchable: false
                    },
                    {
                        data: 'sku',
                        name: 'products.sku'
                    },
                    {
                        data: 'product_custom_field1',
                        name: 'products.product_custom_field1',
                        visible: $('#cf_1').text().length > 0
                    },
                    {
                        data: 'product_custom_field2',
                        name: 'products.product_custom_field2',
                        visible: $('#cf_2').text().length > 0
                    },
                    {
                        data: 'product_custom_field3',
                        name: 'products.product_custom_field3',
                        visible: $('#cf_3').text().length > 0
                    },
                    {
                        data: 'product_custom_field4',
                        name: 'products.product_custom_field4',
                        visible: $('#cf_4').text().length > 0
                    },
                    {
                        data: 'product_custom_field5',
                        name: 'products.product_custom_field5',
                        visible: $('#cf_5').text().length > 0
                    },
                    {
                        data: 'product_custom_field6',
                        name: 'products.product_custom_field6',
                        visible: $('#cf_6').text().length > 0
                    },
                    {
                        data: 'product_custom_field7',
                        name: 'products.product_custom_field7',
                        visible: $('#cf_7').text().length > 0
                    },
                ],
                createdRow: function(row, data, dataIndex) {
                    if ($('input#is_rack_enabled').val() == 1) {
                        var target_col = 0;
                        @can('product.delete')
                            target_col = 1;
                        @endcan
                        $(row).find('td:eq(' + target_col + ') div').prepend(
                            '<i style="margin:auto;" class="fa fa-plus-circle text-success cursor-pointer no-print rack-details" title="' +
                            LANG.details + '"></i>&nbsp;&nbsp;');
                    }
                    $(row).find('td:eq(0)').attr('class', 'selectable_td');
                },
                fnDrawCallback: function(oSettings) {
                    __currency_convert_recursively($('#product_table'));
                },
            });
            // Array to track the ids of the details displayed rows
            var detailRows = [];

            $('#product_table tbody').on('click', 'tr i.rack-details', function() {
                var i = $(this);
                var tr = $(this).closest('tr');
                var row = product_table.row(tr);
                var idx = $.inArray(tr.attr('id'), detailRows);

                if (row.child.isShown()) {
                    i.addClass('fa-plus-circle text-success');
                    i.removeClass('fa-minus-circle text-danger');

                    row.child.hide();

                    // Remove from the 'open' array
                    detailRows.splice(idx, 1);
                } else {
                    i.removeClass('fa-plus-circle text-success');
                    i.addClass('fa-minus-circle text-danger');

                    row.child(get_product_details(row.data())).show();

                    // Add to the 'open' array
                    if (idx === -1) {
                        detailRows.push(tr.attr('id'));
                    }
                }
            });

            $('#opening_stock_modal').on('hidden.bs.modal', function(e) {
                product_table.ajax.reload();
            });

            $('table#product_table tbody').on('click', 'a.delete-product', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        var href = $(this).attr('href');
                        $.ajax({
                            method: "DELETE",
                            url: href,
                            dataType: "json",
                            success: function(result) {
                                if (result.success == true) {
                                    toastr.success(result.msg);
                                    product_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });

            $(document).on('click', '#delete-selected', function(e) {
                e.preventDefault();
                var selected_rows = getSelectedRows();

                if (selected_rows.length > 0) {
                    $('input#selected_rows').val(selected_rows);
                    swal({
                        title: LANG.sure,
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((willDelete) => {
                        if (willDelete) {
                            $('form#mass_delete_form').submit();
                        }
                    });
                } else {
                    $('input#selected_rows').val('');
                    swal('@lang('lang_v1.no_row_selected')');
                }
            });

            $(document).on('click', '#deactivate-selected', function(e) {
                e.preventDefault();
                var selected_rows = getSelectedRows();

                if (selected_rows.length > 0) {
                    $('input#selected_products').val(selected_rows);
                    swal({
                        title: LANG.sure,
                        icon: "warning",
                        buttons: true,
                        dangerMode: true,
                    }).then((willDelete) => {
                        if (willDelete) {
                            var form = $('form#mass_deactivate_form')

                            var data = form.serialize();
                            $.ajax({
                                method: form.attr('method'),
                                url: form.attr('action'),
                                dataType: 'json',
                                data: data,
                                success: function(result) {
                                    if (result.success == true) {
                                        toastr.success(result.msg);
                                        product_table.ajax.reload();
                                        form
                                            .find('#selected_products')
                                            .val('');
                                    } else {
                                        toastr.error(result.msg);
                                    }
                                },
                            });
                        }
                    });
                } else {
                    $('input#selected_products').val('');
                    swal('@lang('lang_v1.no_row_selected')');
                }
            })

            $(document).on('click', '#edit-selected', function(e) {
                e.preventDefault();
                var selected_rows = getSelectedRows();

                if (selected_rows.length > 0) {
                    $('input#selected_products_for_edit').val(selected_rows);
                    $('form#bulk_edit_form').submit();
                } else {
                    $('input#selected_products').val('');
                    swal('@lang('lang_v1.no_row_selected')');
                }
            })

            $('table#product_table tbody').on('click', 'a.activate-product', function(e) {
                e.preventDefault();
                var href = $(this).attr('href');
                $.ajax({
                    method: "get",
                    url: href,
                    dataType: "json",
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            product_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

            $(document).on('change',
                '#product_list_filter_type, #product_list_filter_category_id, #product_list_filter_brand_id, #product_list_filter_unit_id, #product_list_filter_tax_id, #location_id, #active_state, #repair_model_id',
                function() {
                    if ($("#product_list_tab").hasClass('active')) {
                        product_table.ajax.reload();
                    }

                    if ($("#product_stock_report").hasClass('active')) {
                        stock_report_table.ajax.reload();
                    }
                });

            $(document).on('ifChanged', '#not_for_selling, #woocommerce_enabled', function() {
                if ($("#product_list_tab").hasClass('active')) {
                    product_table.ajax.reload();
                }

                if ($("#product_stock_report").hasClass('active')) {
                    stock_report_table.ajax.reload();
                }
            });

            $('#product_location').select2({
                dropdownParent: $('#product_location').closest('.modal')
            });

            @if ($is_woocommerce)
                $(document).on('click', '.toggle_woocomerce_sync', function(e) {
                    e.preventDefault();
                    var selected_rows = getSelectedRows();
                    if (selected_rows.length > 0) {
                        $('#woocommerce_sync_modal').modal('show');
                        $("input#woocommerce_products_sync").val(selected_rows);
                    } else {
                        $('input#selected_products').val('');
                        swal('@lang('lang_v1.no_row_selected')');
                    }
                });

                $(document).on('submit', 'form#toggle_woocommerce_sync_form', function(e) {
                    e.preventDefault();
                    var url = $('form#toggle_woocommerce_sync_form').attr('action');
                    var method = $('form#toggle_woocommerce_sync_form').attr('method');
                    var data = $('form#toggle_woocommerce_sync_form').serialize();
                    var ladda = Ladda.create(document.querySelector('.ladda-button'));
                    ladda.start();
                    $.ajax({
                        method: method,
                        dataType: "json",
                        url: url,
                        data: data,
                        success: function(result) {
                            ladda.stop();
                            if (result.success) {
                                $("input#woocommerce_products_sync").val('');
                                $('#woocommerce_sync_modal').modal('hide');
                                toastr.success(result.msg);
                                product_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                });
            @endif
        });

        $(document).on('shown.bs.modal', 'div.view_product_modal, div.view_modal, #view_product_modal',
            function() {
                var div = $(this).find('#view_product_stock_details');
                if (div.length) {
                    $.ajax({
                        url: "{{ action([\App\Http\Controllers\ReportController::class, 'getStockReport']) }}" +
                            '?for=view_product&product_id=' + div.data('product_id'),
                        dataType: 'html',
                        success: function(result) {
                            div.html(result);
                            __currency_convert_recursively(div);
                        },
                    });
                }
                __currency_convert_recursively($(this));
            });
        var data_table_initailized = false;
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if ($(e.target).attr('href') == '#product_stock_report') {
                if (!data_table_initailized) {
                    //Stock report table
                    var stock_report_cols = [{
                            data: 'action',
                            name: 'action',
                            searchable: false,
                            orderable: false
                        },
                        {
                            data: 'sku',
                            name: 'variations.sub_sku'
                        },
                        {
                            data: 'product',
                            name: 'p.name'
                        },
                        {
                            data: 'variation',
                            name: 'variation'
                        },
                        {
                            data: 'category_name',
                            name: 'c.name'
                        },
                        {
                            data: 'location_name',
                            name: 'l.name'
                        },
                        {
                            data: 'unit_price',
                            name: 'variations.sell_price_inc_tax'
                        },
                        {
                            data: 'stock',
                            name: 'stock',
                            searchable: false
                        },
                    ];
                    if ($('th.stock_price').length) {
                        stock_report_cols.push({
                            data: 'stock_price',
                            name: 'stock_price',
                            searchable: false
                        });
                        stock_report_cols.push({
                            data: 'stock_value_by_sale_price',
                            name: 'stock_value_by_sale_price',
                            searchable: false,
                            orderable: false
                        });
                        stock_report_cols.push({
                            data: 'potential_profit',
                            name: 'potential_profit',
                            searchable: false,
                            orderable: false
                        });
                    }

                    stock_report_cols.push({
                        data: 'total_sold',
                        name: 'total_sold',
                        searchable: false
                    });
                    stock_report_cols.push({
                        data: 'total_transfered',
                        name: 'total_transfered',
                        searchable: false
                    });
                    stock_report_cols.push({
                        data: 'total_adjusted',
                        name: 'total_adjusted',
                        searchable: false
                    });
                    stock_report_cols.push({
                        data: 'product_custom_field1',
                        name: 'p.product_custom_field1'
                    });
                    stock_report_cols.push({
                        data: 'product_custom_field2',
                        name: 'p.product_custom_field2'
                    });
                    stock_report_cols.push({
                        data: 'product_custom_field3',
                        name: 'p.product_custom_field3'
                    });
                    stock_report_cols.push({
                        data: 'product_custom_field4',
                        name: 'p.product_custom_field4'
                    });

                    if ($('th.current_stock_mfg').length) {
                        stock_report_cols.push({
                            data: 'total_mfg_stock',
                            name: 'total_mfg_stock',
                            searchable: false
                        });
                    }
                    stock_report_table = $('#stock_report_table').DataTable({
                        order: [
                            [1, 'asc']
                        ],
                        processing: true,
                        serverSide: true,
                        scrollY: "75vh",
                        scrollX: true,
                        scrollCollapse: true,
                        ajax: {
                            url: '/reports/stock-report',
                            data: function(d) {
                                d.location_id = $('#location_id').val();
                                d.category_id = $('#product_list_filter_category_id').val();
                                d.brand_id = $('#product_list_filter_brand_id').val();
                                d.unit_id = $('#product_list_filter_unit_id').val();
                                d.type = $('#product_list_filter_type').val();
                                d.active_state = $('#active_state').val();
                                d.not_for_selling = $('#not_for_selling').is(':checked');
                                if ($('#repair_model_id').length == 1) {
                                    d.repair_model_id = $('#repair_model_id').val();
                                }
                            }
                        },
                        columns: stock_report_cols,
                        fnDrawCallback: function(oSettings) {
                            __currency_convert_recursively($('#stock_report_table'));
                        },
                        "footerCallback": function(row, data, start, end, display) {
                            var footer_total_stock = 0;
                            var footer_total_sold = 0;
                            var footer_total_transfered = 0;
                            var total_adjusted = 0;
                            var total_stock_price = 0;
                            var footer_stock_value_by_sale_price = 0;
                            var total_potential_profit = 0;
                            var footer_total_mfg_stock = 0;
                            for (var r in data) {
                                footer_total_stock += $(data[r].stock).data('orig-value') ?
                                    parseFloat($(data[r].stock).data('orig-value')) : 0;

                                footer_total_sold += $(data[r].total_sold).data('orig-value') ?
                                    parseFloat($(data[r].total_sold).data('orig-value')) : 0;

                                footer_total_transfered += $(data[r].total_transfered).data(
                                        'orig-value') ?
                                    parseFloat($(data[r].total_transfered).data('orig-value')) : 0;

                                total_adjusted += $(data[r].total_adjusted).data('orig-value') ?
                                    parseFloat($(data[r].total_adjusted).data('orig-value')) : 0;

                                total_stock_price += $(data[r].stock_price).data('orig-value') ?
                                    parseFloat($(data[r].stock_price).data('orig-value')) : 0;

                                footer_stock_value_by_sale_price += $(data[r].stock_value_by_sale_price)
                                    .data('orig-value') ?
                                    parseFloat($(data[r].stock_value_by_sale_price).data(
                                        'orig-value')) : 0;

                                total_potential_profit += $(data[r].potential_profit).data(
                                        'orig-value') ?
                                    parseFloat($(data[r].potential_profit).data('orig-value')) : 0;

                                footer_total_mfg_stock += $(data[r].total_mfg_stock).data(
                                        'orig-value') ?
                                    parseFloat($(data[r].total_mfg_stock).data('orig-value')) : 0;
                            }

                            $('.footer_total_stock').html(__currency_trans_from_en(footer_total_stock,
                                false));
                            $('.footer_total_stock_price').html(__currency_trans_from_en(
                                total_stock_price));
                            $('.footer_total_sold').html(__currency_trans_from_en(footer_total_sold,
                                false));
                            $('.footer_total_transfered').html(__currency_trans_from_en(
                                footer_total_transfered, false));
                            $('.footer_total_adjusted').html(__currency_trans_from_en(total_adjusted,
                                false));
                            $('.footer_stock_value_by_sale_price').html(__currency_trans_from_en(
                                footer_stock_value_by_sale_price));
                            $('.footer_potential_profit').html(__currency_trans_from_en(
                                total_potential_profit));
                            if ($('th.current_stock_mfg').length) {
                                $('.footer_total_mfg_stock').html(__currency_trans_from_en(
                                    footer_total_mfg_stock, false));
                            }
                        },
                    });
                    data_table_initailized = true;
                } else {
                    stock_report_table.ajax.reload();
                }
            } else {
                product_table.ajax.reload();
            }

            // remove class from data table button
            $('.btn-default').removeClass('btn-default');
            $('.tw-dw-btn-outline').removeClass('btn');
        });

        $(document).on('click', '.update_product_location', function(e) {
            e.preventDefault();
            var selected_rows = getSelectedRows();

            if (selected_rows.length > 0) {
                $('input#selected_products').val(selected_rows);
                var type = $(this).data('type');
                var modal = $('#edit_product_location_modal');
                if (type == 'add') {
                    modal.find('.remove_from_location_title').addClass('hide');
                    modal.find('.add_to_location_title').removeClass('hide');
                } else if (type == 'remove') {
                    modal.find('.add_to_location_title').addClass('hide');
                    modal.find('.remove_from_location_title').removeClass('hide');
                }

                modal.modal('show');
                modal.find('#product_location').select2({
                    dropdownParent: modal
                });
                modal.find('#product_location').val('').change();
                modal.find('#update_type').val(type);
                modal.find('#products_to_update_location').val(selected_rows);
            } else {
                $('input#selected_products').val('');
                swal('@lang('lang_v1.no_row_selected')');
            }
        });

        $(document).on('submit', 'form#edit_product_location_form', function(e) {
            e.preventDefault();
            var form = $(this);
            var data = form.serialize();

            $.ajax({
                method: $(this).attr('method'),
                url: $(this).attr('action'),
                dataType: 'json',
                data: data,
                beforeSend: function(xhr) {
                    __disable_submit_button(form.find('button[type="submit"]'));
                },
                success: function(result) {
                    if (result.success == true) {
                        $('div#edit_product_location_modal').modal('hide');
                        toastr.success(result.msg);
                        product_table.ajax.reload();
                        $('form#edit_product_location_form')
                            .find('button[type="submit"]')
                            .attr('disabled', false);
                    } else {
                        toastr.error(result.msg);
                    }
                },
            });
        });

        // ===== ENHANCED FILTERING FUNCTIONALITY =====
        
        // Enhanced search with debounce
        let searchTimeout;
        $('#enhanced_search').on('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                applyEnhancedFilters();
            }, 500);
        });

        // Price range filters
        $('#min_price_filter, #max_price_filter').on('change', function() {
            applyEnhancedFilters();
        });

        // Category filter
        $('#enhanced_category_filter').on('change', function() {
            applyEnhancedFilters();
        });

        // Stock status filter
        $('#stock_status_filter').on('change', function() {
            applyEnhancedFilters();
        });

        // Apply enhanced filters button
        $('#apply_enhanced_filters').on('click', function() {
            applyEnhancedFilters();
        });

        // Clear enhanced filters button
        $('#clear_enhanced_filters').on('click', function() {
            clearEnhancedFilters();
        });

        // Function to apply enhanced filters
        function applyEnhancedFilters() {
            if ($("#product_list_tab").hasClass('active')) {
                product_table.ajax.reload(function(json) {
                    updateResultsSummary(json);
                    updateActiveFilters();
                });
            }

            if ($("#product_stock_report").hasClass('active')) {
                stock_report_table.ajax.reload();
            }
        }

        // Function to clear enhanced filters
        function clearEnhancedFilters() {
            $('#enhanced_search').val('');
            $('#enhanced_category_filter').val('').trigger('change');
            $('#min_price_filter').val('');
            $('#max_price_filter').val('');
            $('#stock_status_filter').val('').trigger('change');
            
            // Hide active filters and results summary
            $('#active_filters_display').hide();
            $('#results_summary').hide();
            
            applyEnhancedFilters();
        }

        // Function to update results summary
        function updateResultsSummary(json) {
            if (hasActiveEnhancedFilters() && json) {
                $('#results_summary').show();
                $('#total_products_count').text(json.recordsFiltered || 0);
            } else {
                $('#results_summary').hide();
            }
        }

        // Function to update active filters display
        function updateActiveFilters() {
            const activeFilters = [];
            
            // Search filter
            const searchValue = $('#enhanced_search').val();
            if (searchValue) {
                activeFilters.push({
                    type: 'search',
                    label: ' Search: "' + searchValue + '"',
                    value: searchValue
                });
            }
            
            // Category filter
            const categoryValue = $('#enhanced_category_filter').val();
            const categoryText = $('#enhanced_category_filter option:selected').text();
            if (categoryValue) {
                activeFilters.push({
                    type: 'category',
                    label: ' Category: ' + categoryText,
                    value: categoryValue
                });
            }
            
            // Price range filter
            const minPrice = $('#min_price_filter').val();
            const maxPrice = $('#max_price_filter').val();
            if (minPrice || maxPrice) {
                const priceLabel = ' Price: ฿' + (minPrice || '0') + ' - ฿' + (maxPrice || '∞');
                activeFilters.push({
                    type: 'price',
                    label: priceLabel,
                    value: 'price_range'
                });
            }
            
            // Stock status filter
            const stockStatus = $('#stock_status_filter').val();
            if (stockStatus) {
                const stockLabels = {
                    'in_stock': 'In Stock Only',
                    'low_stock': 'Low Stock',
                    'out_of_stock': 'Out of Stock'
                };
                activeFilters.push({
                    type: 'stock',
                    label: stockLabels[stockStatus] || '' + stockStatus,
                    value: stockStatus
                });
            }
            
            // Display active filters
            if (activeFilters.length > 0) {
                $('#active_filters_display').show();
                const tagsHtml = activeFilters.map(filter => 
                    `<div class="filter-tag">
                        ${filter.label}
                        <span class="remove-filter" data-filter-type="${filter.type}" data-filter-value="${filter.value}">×</span>
                    </div>`
                ).join('');
                $('#active_filter_tags').html(tagsHtml);
            } else {
                $('#active_filters_display').hide();
            }
        }

        // Function to check if any enhanced filters are active
        function hasActiveEnhancedFilters() {
            return $('#enhanced_search').val() || 
                   $('#enhanced_category_filter').val() ||
                   $('#min_price_filter').val() || 
                   $('#max_price_filter').val() || 
                   $('#stock_status_filter').val();
        }

        // Remove individual filters
        $(document).on('click', '.remove-filter', function() {
            const filterType = $(this).data('filter-type');
            const filterValue = $(this).data('filter-value');
            
            switch(filterType) {
                case 'search':
                    $('#enhanced_search').val('');
                    break;
                case 'category':
                    $('#enhanced_category_filter').val('').trigger('change');
                    break;
                case 'price':
                    $('#min_price_filter').val('');
                    $('#max_price_filter').val('');
                    break;
                case 'stock':
                    $('#stock_status_filter').val('').trigger('change');
                    break;
            }
            
            applyEnhancedFilters();
        });

        // Also trigger enhanced filters when original filters change
        $(document).on('change',
            '#product_list_filter_type, #product_list_filter_category_id, #product_list_filter_brand_id, #product_list_filter_unit_id, #product_list_filter_tax_id, #location_id, #active_state, #repair_model_id',
            function() {
                // Small delay to ensure the original filter change is processed first
                setTimeout(function() {
                    updateActiveFilters();
                }, 100);
            });

        // Initialize enhanced filters on page load
        $(document).ready(function() {
            // Update active filters on initial load
            updateActiveFilters();
        });
    </script>
@endsection
