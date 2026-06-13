@extends(($is_public ?? false) ? 'layouts.public' : 'layouts.app')

@section('title', 'Products V2 - Multi-Level Categories')

@php
    $display_business_id = $business_id ?? (session('user.business_id') ?? 1);
    $total_products_count = $total_products_count ?? \App\Product::where('business_id', $display_business_id)->count();
    $is_public = $is_public ?? false;
@endphp

@section('css')
<style>
/* Full width layout - hide sidebar and expand content */
.content-wrapper {
    margin-left: 0 !important;
    padding-left: 15px;
    padding-right: 15px;
}
.main-header {
    margin-left: 0 !important;
}
.main-sidebar {
    display: none !important;
}
.content {
    padding: 0 !important;
}
body.sidebar-mini.sidebar-collapse .content-wrapper {
    margin-left: 0 !important;
}
/* Override any AdminLTE sidebar styles */
.skin-blue .main-sidebar {
    display: none !important;
}
.wrapper {
    overflow: hidden;
}

/* Hierarchical select styles */
.hierarchical-select option {
    font-family: 'Courier New', monospace;
    padding: 5px 10px;
}
.hierarchical-select option[data-level="0"] {
    font-weight: bold;
    color: #333;
    background-color: #f8f9fa;
}
.hierarchical-select option[data-level="1"] {
    color: #555;
    padding-left: 20px;
}
.hierarchical-select option[data-level="2"] {
    color: #666;
    padding-left: 40px;
}
.hierarchical-select option[data-level="3"] {
    color: #777;
    padding-left: 60px;
}
.hierarchical-select option[data-level="4"] {
    color: #888;
    padding-left: 80px;
}
.hierarchical-select {
    font-family: 'Courier New', monospace;
}

/* Responsive table improvements */
.table-responsive {
    border: none;
}
.table {
    margin-bottom: 0;
}
/* Action buttons spacing */
.action-buttons .btn {
    margin-right: 2px;
}
.action-buttons .btn:last-child {
    margin-right: 0;
}
</style>
@endsection

@section('content')
<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <h1>Products V2 - Multi-Level Categories 
                <span class="label label-primary" style="font-size: 16px; margin-left: 15px;">
                    <i class="fa fa-database"></i> Total in DB: {{ $total_products_count }}
                </span>
            </h1>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Product Filters</h3>
                        <div class="box-tools pull-right">
                            @if(!$is_public)
                                <a href="{{ action([\App\Http\Controllers\ProductController::class, 'create']) }}" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> Add New Product
                                </a>
                            @endif
                        </div>
                    </div>
                    
                    <div class="box-body">
                        <!-- Filter Form -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Search</label>
                                    <input type="text" id="search" class="form-control" placeholder="Search by name or SKU">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Category</label>
                                    <select id="category_filter" class="form-control hierarchical-select">
                                        <option value="__all__" selected>All Categories</option>
                                        <option value="__uncategorized__">Uncategorized</option>
                                        @foreach($hierarchical_categories as $category)
                                            <option value="{{ $category['id'] }}" data-level="{{ $category['level'] }}">
                                                {!! $category['display_name'] !!}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Brand</label>
                                    <select id="brand_filter" class="form-control">
                                        <option value="">All Brands</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Product Type</label>
                                    <select id="product_type_filter" class="form-control">
                                        <option value="">All Types</option>
                                        <option value="single">Single</option>
                                        <option value="variable">Variable</option>
                                        <option value="modifier">Modifier</option>
                                        <option value="combo">Combo</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label><br>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" id="include_subcategories" checked> Include Subcategories
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label><br>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox" id="group_by_category" checked> Group by Category
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>&nbsp;</label><br>
                                    <button type="button" id="apply_filters" class="btn btn-info">
                                        <i class="fa fa-filter"></i> Apply Filters
                                    </button>
                                    <button type="button" id="clear_filters" class="btn btn-default">
                                        <i class="fa fa-refresh"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </div>
                        

                    </div>
                </div>
            </div>
        </div>

        <!-- Products Display -->
        <div class="row">
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header with-border">
                        <h3 class="box-title">Products</h3>
                        <div class="box-tools pull-right">
                            <span id="product_count" class="label label-info">Loading...</span>
                        </div>
                    </div>
                    
                    <div class="box-body">
                        <div id="loading" class="text-center" style="display: none;">
                            <i class="fa fa-spinner fa-spin fa-2x"></i>
                            <p>Loading products...</p>
                        </div>
                        
                        <div id="products_container">
                            <!-- Products will be loaded here via AJAX -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </section>
</div>

<!-- Product View Modal -->
<div class="modal fade" id="view_product_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
</div>

@endsection

@section('javascript')
<script>
@php
    $publicListAllUrl = \Illuminate\Support\Facades\Route::has('public.products.all')
        ? route('public.products.all')
        : url('public/products/all');
    $privateListAllUrl = \Illuminate\Support\Facades\Route::has('products-v2.all')
        ? route('products-v2.all')
        : url('products-v2/all');

    $publicListBySubcategoryUrl = \Illuminate\Support\Facades\Route::has('public.products.by-subcategory')
        ? route('public.products.by-subcategory')
        : url('public/products/by-subcategory');
    $privateListBySubcategoryUrl = \Illuminate\Support\Facades\Route::has('products-v2.by-subcategory')
        ? route('products-v2.by-subcategory')
        : url('products-v2/by-subcategory');

    $publicViewUrlTemplate = \Illuminate\Support\Facades\Route::has('public.products.view')
        ? route('public.products.view', ['id' => '__PRODUCT_ID__'])
        : url('public/products/view/__PRODUCT_ID__');
    $privateViewUrlTemplate = \Illuminate\Support\Facades\Route::has('products-v2.view')
        ? route('products-v2.view', ['id' => '__PRODUCT_ID__'])
        : url('products-v2/view/__PRODUCT_ID__');
@endphp

// Base URL for proper routing in subdirectory
var baseUrl = '{{ url("/") }}';
var isPublic = {!! $is_public ? 'true' : 'false' !!};
var publicBusinessId = isPublic ? '{{ $display_business_id }}' : '';
var listAllUrl = isPublic ? @json($publicListAllUrl) : @json($privateListAllUrl);
var listBySubcategoryUrl = isPublic ? @json($publicListBySubcategoryUrl) : @json($privateListBySubcategoryUrl);
var viewUrlTemplate = isPublic ? @json($publicViewUrlTemplate) : @json($privateViewUrlTemplate);
var deleteUrlBase = isPublic ? '{{ url("public/products") }}' : (baseUrl + '/products-v2');
var editUrlBase = baseUrl + '/products/';
var openingStockUrlBase = baseUrl + '/opening-stock/add/';

$(document).ready(function() {
    // Load products on page load
    loadProducts();
    
    // Apply filters
    $('#apply_filters').click(function() {
        applyFilters();
    });
    
    // Clear filters
    $('#clear_filters').click(function() {
        $('#search').val('');
        $('#category_filter').val('__all__');
        $('#brand_filter').val('');
        $('#product_type_filter').val('');
        $('#include_subcategories').prop('checked', true);
        $('#group_by_category').prop('checked', true); // Keep checked as default
        loadProducts();
    });
    
    // Auto-apply filters on input change
    $('#search').on('keyup', function() {
        if ($(this).val().length >= 3 || $(this).val().length === 0) {
            applyFilters();
        }
    });
    
    $('#category_filter, #brand_filter, #product_type_filter').change(function() {
        applyFilters();
    });
    
    $('#include_subcategories, #group_by_category').change(function() {
        applyFilters();
    });
    
    function loadProducts() {
        $('#loading').show();
        $('#products_container').empty();
        
        $.ajax({
            url: listAllUrl,
            type: 'GET',
            data: isPublic ? { business_id: publicBusinessId } : {},
            dataType: 'json',
            success: function(response) {
                $('#loading').hide();
                if (response.success) {
                    displayProducts(response.products);
                    $('#product_count').text(response.total_count + ' products');
                } else {
                    $('#products_container').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#loading').hide();
                console.error('AJAX Load error:', {status: xhr.status, statusText: xhr.statusText, responseText: xhr.responseText, error: error});
                $('#products_container').html('<div class="alert alert-danger">Error loading products. Please try again.</div>');
            }
        });
    }
    
    function applyFilters() {
        var selectedCategory = $('#category_filter').val();
        var filters = {
            brand_id: $('#brand_filter').val(),
            product_type: $('#product_type_filter').val(),
            search: $('#search').val(),
            group_by_category: $('#group_by_category').is(':checked'),
            include_subcategories: $('#include_subcategories').is(':checked')
        };

        // Avoid empty-string ambiguity: send category_id only when an actual filter is selected.
        if (selectedCategory && selectedCategory !== '__all__') {
            filters.category_id = selectedCategory;
        }

        if (isPublic) {
            filters.business_id = publicBusinessId;
        }
        
        console.log('Applying filters:', filters);
        
        $('#loading').show();
        $('#products_container').empty();
        
        // Use the by-subcategory endpoint for filtered results
        var endpoint = listBySubcategoryUrl;
        console.log('Using endpoint:', endpoint);
        
        $.ajax({
            url: endpoint,
            type: 'GET',
            data: filters,
            dataType: 'json',
            success: function(response) {
                $('#loading').hide();
                if (response.success) {
                    if (filters.group_by_category && response.grouped_products) {
                        displayGroupedProducts(response.grouped_products);
                    } else {
                        displayProducts(response.products);
                    }
                    $('#product_count').text(response.total_count + ' products');
                } else {
                    $('#products_container').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#loading').hide();
                console.error('AJAX Filter error:', {status: xhr.status, statusText: xhr.statusText, responseText: xhr.responseText, error: error});
                
                // Check if it's a redirect to login
                if (xhr.status === 302 || xhr.responseText.includes('login')) {
                    $('#products_container').html('<div class="alert alert-warning">Please log in to access this feature. <a href="/login">Click here to login</a></div>');
                } else {
                    $('#products_container').html('<div class="alert alert-danger">Error applying filters. Please try again.</div>');
                }
            }
        });
    }
    
    function displayProducts(products) {
        var html = '<div class="table-responsive"><table class="table table-bordered table-striped">';
        html += '<thead><tr>';
        html += '<th>Image</th>';
        html += '<th>Name</th>';
        html += '<th>SKU</th>';
        html += '<th>Type</th>';
        html += '<th>Category Path</th>';
        html += '<th>Brand</th>';
        html += '<th>Unit</th>';
        html += '<th>Stock</th>';
        html += '<th>Selling Price</th>';
        html += '<th>Created</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';
        
        if (products && products.length > 0) {
            $.each(products, function(index, product) {
                html += '<tr class="product-row" data-product-id="' + product.id + '" style="cursor: pointer;">';
                html += '<td><img src="' + (product.image_url || '/img/default-product.png') + '" alt="' + product.name + '" style="width: 50px; height: 50px; object-fit: cover;"></td>';
                html += '<td>' + product.name + '</td>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td><span class="label label-info">' + product.type + '</span></td>';
                html += '<td>' + (product.category_path || 'Uncategorized') + '</td>';
                html += '<td>' + (product.brand_name || '-') + '</td>';
                html += '<td>' + (product.unit_name || '-') + '</td>';
                html += '<td><span class="label label-success">' + (product.total_stock || '0') + '</span></td>';
                html += '<td><strong>' + (product.selling_price || '0.00') + '฿ </strong></td>';
        html += '<td>' + product.created_at + '</td>';
        html += '<td class="action-buttons">';
        html += '<button class="btn btn-xs btn-info" onclick="viewProduct(' + product.id + ')" title="View"><i class="fa fa-eye"></i></button> ';
        if (!isPublic) {
            html += '<a href="' + editUrlBase + product.id + '/edit" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-edit"></i></a> ';
            html += '<a href="' + openingStockUrlBase + product.id + '" class="btn btn-xs btn-success" title="Add Opening Stock"><i class="fa fa-plus-square"></i></a> ';
            html += '<button class="btn btn-xs btn-danger" onclick="deleteProduct(' + product.id + ')" title="Delete"><i class="fa fa-trash"></i></button>';
        }
        html += '</td>';
        html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="11" class="text-center">No products found</td></tr>';
        }
        
        html += '</tbody></table></div>';
        $('#products_container').html(html);
        
        // Add click event for rows (but not on action buttons)
        $('.product-row').on('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (!$(e.target).closest('.action-buttons').length) {
                var productId = $(this).data('product-id');
                viewProduct(productId);
            }
        });
    }
    
    function displayGroupedProducts(groupedProducts) {
        var html = '';
        
        $.each(groupedProducts, function(categoryPath, products) {
            html += '<div class="panel panel-default">';
            html += '<div class="panel-heading">';
            html += '<h4 class="panel-title">' + categoryPath + ' (' + products.length + ' products)</h4>';
            html += '</div>';
            html += '<div class="panel-body">';
            
            html += '<div class="table-responsive"><table class="table table-bordered table-striped">';
            html += '<thead><tr>';
            html += '<th>Image</th>';
            html += '<th>Name</th>';
            html += '<th>SKU</th>';
            html += '<th>Type</th>';
            html += '<th>Brand</th>';
            html += '<th>Unit</th>';
            html += '<th>Stock</th>';
            html += '<th>Selling Price</th>';
            html += '<th>Created</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';
            
            $.each(products, function(index, product) {
                html += '<tr class="product-row" data-product-id="' + product.id + '" style="cursor: pointer;">';
                html += '<td><img src="' + (product.image_url || '/img/default-product.png') + '" alt="' + product.name + '" style="width: 50px; height: 50px; object-fit: cover;"></td>';
                html += '<td>' + product.name + '</td>';
                html += '<td>' + (product.sku || '-') + '</td>';
                html += '<td><span class="label label-info">' + product.type + '</span></td>';
                html += '<td>' + (product.brand_name || '-') + '</td>';
                html += '<td>' + (product.unit_name || '-') + '</td>';
                html += '<td><span class="label label-success">' + (product.total_stock || '0') + '</span></td>';
                html += '<td><strong>' + (product.selling_price || '0.00') + '฿</strong></td>';
            html += '<td>' + product.created_at + '</td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn btn-xs btn-info" onclick="viewProduct(' + product.id + ')" title="View"><i class="fa fa-eye"></i></button> ';
            if (!isPublic) {
                html += '<a href="' + editUrlBase + product.id + '/edit" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-edit"></i></a> ';
                html += '<a href="' + openingStockUrlBase + product.id + '" class="btn btn-xs btn-success" title="Add Opening Stock"><i class="fa fa-plus-square"></i></a> ';
                html += '<button class="btn btn-xs btn-danger" onclick="deleteProduct(' + product.id + ')" title="Delete"><i class="fa fa-trash"></i></button>';
            }
            html += '</td>';
            html += '</tr>';
            });
            
            html += '</tbody></table></div>';
            html += '</div>';
            html += '</div>';
        });
        
        $('#products_container').html(html);
        
        // Add click event for rows (but not on action buttons)
        $('.product-row').on('click', function(e) {
            // Don't trigger if clicking on action buttons
            if (!$(e.target).closest('.action-buttons').length) {
                var productId = $(this).data('product-id');
                viewProduct(productId);
            }
        });
    }
    
    window.deleteProduct = function(productId) {
        if (isPublic) {
            return;
        }
        swal({
            title: "Are you sure?",
            text: "You want to delete this product?",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                url: deleteUrlBase + '/' + productId,
                    type: 'DELETE',
                    data: {
                        '_token': '{{ csrf_token() }}'
                    },
                    dataType: "json",
                    success: function(result) {
                        if (result.success == true) {
                            toastr.success(result.msg);
                            applyFilters(); // Reload the current view
                        } else {
                            toastr.error(result.msg);
                        }
                    },
                    error: function() {
                        toastr.error('Error deleting product');
                    }
                });
            }
        });
    };

    // Function to view product in modal
    window.viewProduct = function(productId) {
        $.ajax({
            url: viewUrlTemplate.replace('__PRODUCT_ID__', productId),
            type: 'GET',
            success: function(response) {
                $('#view_product_modal').html(response);
                $('#view_product_modal').modal('show');
            },
            error: function(xhr, status, error) {
                console.error('Error loading product details:', error);
                toastr.error('Error loading product details');
            }
        });
    };

    // Handle modal events for stock details
    $(document).on('shown.bs.modal', '#view_product_modal', function() {
        var div = $(this).find('#view_product_stock_details');
        if (div.length) {
            $.ajax({
                url: '/reports/stock-report?for=view_product&product_id=' + div.data('product_id'),
                dataType: 'html',
                success: function(result) {
                    div.html(result);
                },
                error: function() {
                    div.html('<p class="text-muted">Unable to load stock details</p>');
                }
            });
        }
    });
});
</script>
@endsection
