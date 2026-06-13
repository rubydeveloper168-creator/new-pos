@extends('layouts.app')
@section('title', 'Category Tree')

@section('content')
<style>
/* Category Tree Styles */
.category-tree-container {
    display: flex;
    height: calc(100vh - 200px);
    gap: 20px;
    background: #f8fafc;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Left Panel - Categories */
.categories-panel {
    width: 30%;
    background: white;
    border-right: 2px solid #e5e7eb;
    display: flex;
    flex-direction: column;
}

.categories-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    font-weight: 600;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.categories-search {
    padding: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.categories-search input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.categories-search input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.categories-tree {
    flex: 1;
    overflow-y: auto;
    padding: 10px;
}

.categories-tree::-webkit-scrollbar {
    width: 6px;
}

.categories-tree::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.categories-tree::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

/* Category Tree Items */
.category-item {
    margin-bottom: 2px;
    position: relative;
    transition: all 0.3s ease;
}

.category-item.dragging {
    opacity: 0.6;
    transform: rotate(2deg);
    z-index: 1000;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 6px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
}

.category-item.drag-over {
    background: rgba(102, 126, 234, 0.1);
    border: 2px dashed #667eea;
    border-radius: 6px;
}

/* Drag Handle */
.drag-handle {
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    color: #9ca3af;
    font-size: 12px;
    opacity: 0;
    transition: all 0.2s;
    z-index: 10;
}

.category-item:hover .drag-handle {
    opacity: 1;
}

.drag-handle:hover {
    color: #667eea;
    transform: translateY(-50%) scale(1.1);
}

.drag-handle:active {
    cursor: grabbing;
}

/* Tree Connection Lines */
.tree-lines {
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    pointer-events: none;
    z-index: 1;
}

.vertical-line,
.horizontal-line,
.continue-line {
    position: absolute;
    background: #d1d5db;
}

.vertical-line {
    left: 10px;
    top: -12px;
    bottom: 50%;
    width: 1px;
    background: linear-gradient(to bottom, #d1d5db 0%, #d1d5db 100%);
}

.horizontal-line {
    left: 10px;
    top: 50%;
    width: 18px;
    height: 1px;
    background: linear-gradient(to right, #d1d5db 0%, #d1d5db 80%, transparent 100%);
}

.continue-line {
    left: 10px;
    top: 50%;
    bottom: -12px;
    width: 1px;
    background: linear-gradient(to bottom, #d1d5db 0%, #d1d5db 100%);
}

/* Enhanced tree styling for better visual hierarchy */
.category-item[data-level="0"] {
    border-left: 3px solid transparent;
}

.category-item[data-level="1"] {
    border-left: 3px solid #e5e7eb;
    margin-left: 10px;
}

.category-item[data-level="2"] {
    border-left: 3px solid #d1d5db;
    margin-left: 20px;
}

.category-item[data-level="3"] {
    border-left: 3px solid #9ca3af;
    margin-left: 30px;
}

.category-item[data-level="4"] {
    border-left: 3px solid #6b7280;
    margin-left: 40px;
}

.category-node {
    display: flex;
    align-items: center;
    padding: 10px 40px 10px 25px;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
    user-select: none;
    position: relative;
    background: white;
}

.category-node:hover {
    background: #f8fafc;
    transform: translateX(2px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.category-node.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.category-node.active .category-count {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.category-node.active .category-actions .action-btn {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

/* Category Toggle */
.category-toggle {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
    font-size: 10px;
    color: #6b7280;
    transition: all 0.2s;
    cursor: pointer;
}

.category-toggle.expanded {
    transform: rotate(90deg);
    color: #667eea;
}

.category-toggle:hover {
    color: #667eea;
    transform: scale(1.2);
}

.category-toggle.no-children {
    cursor: default;
}

.category-toggle.no-children:hover {
    transform: none;
    color: #6b7280;
}

.tree-connector {
    font-family: monospace;
    font-size: 12px;
    color: #d1d5db;
}

/* Category Icons */
.category-icon {
    margin-right: 10px;
    font-size: 16px;
    width: 20px;
    text-align: center;
}

.root-folder {
    color: #f59e0b;
}

.parent-folder {
    color: #3b82f6;
}

.leaf-category {
    color: #10b981;
}

/* Category Information */
.category-info {
    flex: 1;
    min-width: 0;
}

.category-name {
    font-size: 14px;
    line-height: 1.4;
    font-weight: 500;
    color: inherit;
    word-break: break-word;
}

.category-code {
    font-size: 11px;
    color: #6b7280;
    font-family: monospace;
    margin-top: 2px;
}

.category-node.active .category-code {
    color: rgba(255, 255, 255, 0.8);
}

/* Category Stats */
.category-stats {
    display: flex;
    gap: 6px;
    margin-left: 8px;
}

.category-count {
    background: #e5e7eb;
    color: #6b7280;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 3px;
    min-width: 0;
}

.products-count {
    background: #dcfce7;
    color: #166534;
}

.children-count {
    background: #dbeafe;
    color: #1e40af;
}

/* Category Actions */
.category-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s;
}

.category-item:hover .category-actions {
    opacity: 1;
}

.action-btn {
    width: 24px;
    height: 24px;
    border: none;
    border-radius: 4px;
    background: #f3f4f6;
    color: #6b7280;
    font-size: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.action-btn:hover {
    background: #667eea;
    color: white;
    transform: scale(1.1);
}

.edit-btn:hover {
    background: #f59e0b;
}

.add-btn:hover {
    background: #10b981;
}

.category-children {
    display: none; /* Start collapsed */
    position: relative;
    border-left: 2px dotted #e5e7eb;
    margin-left: 15px;
    padding-left: 10px;
}

.category-children.expanded {
    display: block;
}

.category-children.collapsed {
    display: none;
}

/* Debug styles */
.debug-info {
    font-size: 10px;
    color: #666;
    background: #f0f0f0;
    padding: 2px 4px;
    border-radius: 3px;
    margin-top: 2px;
}

.sortable-container {
    min-height: 20px;
}

/* Sortable placeholders */
.sortable-placeholder {
    background: rgba(102, 126, 234, 0.1);
    border: 2px dashed #667eea;
    border-radius: 6px;
    margin: 2px 0;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 12px;
    font-weight: 500;
}

.sortable-placeholder:before {
    content: "Drop here to reorder";
}

/* Right Panel - Products */
.products-panel {
    width: 70%;
    background: white;
    display: flex;
    flex-direction: column;
}

.products-header {
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.products-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.products-stats {
    color: #6b7280;
    font-size: 14px;
}

.products-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.product-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.2s;
    cursor: pointer;
}

.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    border-color: #667eea;
}

.product-image {
    height: 160px;
    background: #f9fafb;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-image .no-image {
    color: #9ca3af;
    font-size: 48px;
}

.product-type-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(102, 126, 234, 0.9);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    text-transform: capitalize;
}

.product-info {
    padding: 15px;
}

.product-name {
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 5px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-sku {
    color: #6b7280;
    font-size: 12px;
    margin-bottom: 5px;
}

.product-brand {
    color: #667eea;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 8px;
}

.product-price {
    font-size: 1.1rem;
    font-weight: 700;
    color: #059669;
    margin-bottom: 8px;
}

.product-stock {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 500;
}

.product-stock.in-stock {
    background: #d1fae5;
    color: #065f46;
}

.product-stock.out-of-stock {
    background: #fee2e2;
    color: #991b1b;
}

/* Empty States */
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #6b7280;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.2rem;
    margin-bottom: 8px;
    color: #374151;
}

.empty-state p {
    font-size: 14px;
    max-width: 300px;
}

/* Loading State */
.loading-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 200px;
    flex-direction: column;
    gap: 16px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive Design */
@media (max-width: 768px) {
    .category-tree-container {
        flex-direction: column;
        height: auto;
    }
    
    .categories-panel {
        width: 100%;
        max-height: 300px;
    }
    
    .products-panel {
        width: 100%;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 15px;
    }
}

@media (max-width: 480px) {
    .products-grid {
        grid-template-columns: 1fr;
    }
    
    .category-tree-container {
        margin: 10px;
    }
    
    .categories-header,
    .products-header {
        padding: 15px;
    }
}

/* Success flash animation */
.success-flash {
    animation: successFlash 1s ease-in-out;
}

@keyframes successFlash {
    0%, 100% { background: inherit; }
    50% { background: #10b981 !important; color: white !important; }
}

/* Hover effects for drag handles */
.category-item:hover .drag-handle {
    opacity: 1;
}

/* Better visual feedback during drag operations */
.sortable-drag {
    opacity: 0.6;
}

.sortable-ghost {
    opacity: 0.4;
}

/* Improved tree lines */
.tree-lines .vertical-line {
    background: linear-gradient(to bottom, #d1d5db 0%, #d1d5db 100%);
}

.tree-lines .horizontal-line {
    background: linear-gradient(to right, #d1d5db 0%, transparent 100%);
}
</style>

<!-- Content Header -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
         Category Tree
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">Browse products by category</small>
    </h1>
</section>

<!-- Main Content -->
<section class="content">
    <div class="category-tree-container">
        <!-- Left Panel - Categories -->
        <div class="categories-panel">
            <div class="categories-header">
                <i class="fa fa-sitemap"></i>
                Product Categories
            </div>
            
            <div class="categories-search">
                <input type="text" id="category-search" placeholder=" Search categories...">
                <button type="button" id="test-connection" class="btn btn-sm btn-info" style="margin-top: 5px;">Test Connection</button>
                <button type="button" id="debug-categories" class="btn btn-sm btn-warning" style="margin-top: 5px; margin-left: 5px;">Debug Categories</button>
                <button type="button" id="test-products" class="btn btn-sm btn-success" style="margin-top: 5px; margin-left: 5px;">Test Products (Cat 2)</button>
            </div>
            
            <div class="categories-tree sortable-container" id="categories-tree" data-parent-id="0">
                @if(count($categories) > 0)
                    @foreach($categories as $category)
                        @include('category_tree.partials.category_node', ['category' => $category, 'level' => 0, 'loop' => $loop])
                    @endforeach
                @else
                    <div class="empty-state">
                        <div class="empty-state-icon"></div>
                        <h3>No Categories Found</h3>
                        <p>Create some product categories to get started.</p>
                    </div>
                @endif
            </div>
        </div>
        
        <!-- Right Panel - Products -->
        <div class="products-panel">
            <div class="products-header">
                <div class="products-title">
                    <i class="fa fa-cube"></i>
                    <span id="category-title">Select a category</span>
                </div>
                <div class="products-stats" id="products-stats">
                    Ready to browse
                </div>
            </div>
            
            <div class="products-content" id="products-content">
                <div class="empty-state">
                    <div class="empty-state-icon">👈</div>
                    <h3>Select a Category</h3>
                    <p>Choose a category from the left panel to view its products.</p>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('javascript')
<!-- Include SortableJS for drag and drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
$(document).ready(function() {
    let currentCategoryId = null;
    let searchTimeout = null;
    let sortableInstances = [];
    
    // Setup CSRF token for AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // Debug: Log CSRF token
    console.log('CSRF Token:', $('meta[name="csrf-token"]').attr('content'));
    
    // Initialize drag and drop functionality
    initializeSortable();
    
    // Category node click handler
    $(document).on('click', '.category-node', function(e) {
        e.stopPropagation();
        
        const categoryId = $(this).data('category-id');
        const categoryName = $(this).find('.category-name').text();
        
        // Update active state
        $('.category-node').removeClass('active');
        $(this).addClass('active');
        
        // Load products for this category
        loadCategoryProducts(categoryId, categoryName);
    });
    
    // Category toggle handler
    $(document).on('click', '.category-toggle', function(e) {
        e.stopPropagation();
        
        const $toggle = $(this);
        const $children = $toggle.closest('.category-item').find('> .category-children');
        
        if ($children.length === 0) {
            return; // No children to toggle
        }
        
        if ($children.hasClass('collapsed') || $children.is(':hidden')) {
            // Expand: show children and update toggle
            $children.removeClass('collapsed').addClass('expanded').slideDown(200, function() {
                // Re-initialize sortable for newly visible children
                initializeSortable();
            });
            $toggle.addClass('expanded');
        } else {
            // Collapse: hide children and update toggle
            $children.removeClass('expanded').addClass('collapsed').slideUp(200);
            $toggle.removeClass('expanded');
        }
    });
    
    // Search categories
    $('#category-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            filterCategories(searchTerm);
        }, 300);
    });
    
    // Product card click handler
    $(document).on('click', '.product-card', function() {
        const productId = $(this).data('product-id');
        // You can add product detail modal or redirect here
        console.log('Product clicked:', productId);
    });
    
    // Category action handlers
    $(document).on('click', '.edit-btn', function(e) {
        e.stopPropagation();
        const categoryId = $(this).data('category-id');
        console.log('Edit category:', categoryId);
        // Add edit category functionality here
    });
    
    $(document).on('click', '.add-btn', function(e) {
        e.stopPropagation();
        const parentId = $(this).data('parent-id');
        console.log('Add subcategory to parent:', parentId);
        // Add create subcategory functionality here
    });
    
    // Test connection button (temporary for debugging)
    $('#test-connection').on('click', function() {
        $.ajax({
            url: '{{ route("category-tree.test") }}',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Test Connection Response:', response);
                alert('Test successful! Check console for details.');
            },
            error: function(xhr, status, error) {
                console.error('Test Connection Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                alert('Test failed! Check console for details.');
            }
        });
    });
    
    // Test products button (to test the exact AJAX call)
    $('#test-products').on('click', function() {
        console.log('Testing products for category ID 2 (Airless Sprayer)...');
        
        $.ajax({
            url: '{{ route("category-tree.products") }}',
            method: 'GET',
            data: {
                category_id: 2,
                include_subcategories: true
            },
            dataType: 'json',
            success: function(response) {
                console.log('Test Products Response:', response);
                if (response && response.success) {
                    alert(`Products loaded successfully! Found ${response.total_products} products.`);
                } else if (response && response.products) {
                    alert(`Products loaded! Found ${response.products.length} products.`);
                } else {
                    alert('Test failed: ' + (response.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Test Products Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                alert('Test failed! Check console for details.');
            }
        });
    });
    
    // Debug categories button
    $('#debug-categories').on('click', function() {
        $.ajax({
            url: '{{ route("category-tree.debug") }}',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('Debug Categories Response:', response);
                alert('Categories debug info logged to console!');
            },
            error: function(xhr, status, error) {
                console.error('Debug Categories Error:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                alert('Debug failed! Check console for details.');
            }
        });
    });
    
    // Initialize Sortable for drag and drop
    function initializeSortable() {
        // Destroy existing sortable instances
        sortableInstances.forEach(instance => {
            if (instance && typeof instance.destroy === 'function') {
                instance.destroy();
            }
        });
        sortableInstances = [];
        
        // Initialize sortable for all sortable containers
        $('.sortable-container').each(function() {
            const container = this;
            const parentId = $(container).data('parent-id') || 0;
            
            const sortable = new Sortable(container, {
                group: 'categories',
                animation: 300,
                ghostClass: 'sortable-placeholder',
                dragClass: 'dragging',
                handle: '.drag-handle',
                draggable: '.sortable-item',
                fallbackOnBody: true,
                swapThreshold: 0.65,
                
                onStart: function(evt) {
                    // Add visual feedback during drag
                    $(evt.item).addClass('dragging');
                },
                
                onEnd: function(evt) {
                    // Remove visual feedback
                    $(evt.item).removeClass('dragging');
                    $('.drag-over').removeClass('drag-over');
                    
                    // Check if position actually changed
                    if (evt.oldIndex !== evt.newIndex || evt.from !== evt.to) {
                        updateCategoryOrder(evt);
                    }
                },
                
                onMove: function(evt) {
                    // Prevent dropping on non-compatible containers
                    const fromLevel = parseInt($(evt.dragged).data('level')) || 0;
                    const toContainer = evt.to;
                    const toParentId = $(toContainer).data('parent-id') || 0;
                    
                    // Add visual feedback
                    $(evt.related).addClass('drag-over');
                    
                    return true; // Allow move
                }
            });
            
            sortableInstances.push(sortable);
        });
    }
    
    // Update category order via AJAX
    function updateCategoryOrder(evt) {
        const $item = $(evt.item);
        const categoryId = $item.data('category-id');
        const newParentId = $(evt.to).data('parent-id') || 0;
        const oldParentId = $(evt.from).data('parent-id') || 0;
        
        // Get new order for all items in the new container
        const newOrder = [];
        $(evt.to).find('> .sortable-item').each(function(index) {
            newOrder.push({
                id: $(this).data('category-id'),
                position: index + 1
            });
        });
        
        console.log('Updating category order:', {
            categoryId: categoryId,
            oldParentId: oldParentId,
            newParentId: newParentId,
            newOrder: newOrder
        });
        
        // Show loading feedback
        $item.css('opacity', '0.5');
        
        $.ajax({
            url: '{{ route("category-tree.update-order") }}',
            method: 'POST',
            data: {
                category_id: categoryId,
                parent_id: newParentId,
                old_parent_id: oldParentId,
                order: newOrder,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json',
            success: function(response) {
                console.log('Order update response:', response);
                
                if (response.success) {
                    // Update data attributes
                    $item.attr('data-parent-id', newParentId);
                    
                    // Show success feedback
                    $item.css('opacity', '1');
                    $item.find('.category-node').addClass('success-flash');
                    setTimeout(() => {
                        $item.find('.category-node').removeClass('success-flash');
                    }, 1000);
                    
                    // Update tree lines and indentation if parent changed
                    if (oldParentId !== newParentId) {
                        updateTreeVisualization();
                    }
                } else {
                    throw new Error(response.message || 'Failed to update order');
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to update category order:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                // Revert the change
                if (evt.from !== evt.to) {
                    evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                } else {
                    const items = Array.from(evt.to.children);
                    items.splice(evt.newIndex, 1);
                    items.splice(evt.oldIndex, 0, evt.item);
                    items.forEach(item => evt.to.appendChild(item));
                }
                
                $item.css('opacity', '1');
                alert('Failed to update category order. Changes have been reverted.');
            }
        });
    }
    
    // Update tree visualization after structural changes
    function updateTreeVisualization() {
        // This would recalculate tree lines and indentation
        // For now, we'll just refresh the sortable instances
        initializeSortable();
    }
    
    // Load products for a category
    function loadCategoryProducts(categoryId, categoryName) {
        currentCategoryId = categoryId;
        
        // Update header
        $('#category-title').text(categoryName);
        $('#products-stats').text('Loading...');
        
        // Show loading state
        $('#products-content').html(`
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading products...</p>
            </div>
        `);
        
        console.log('Loading products for category:', categoryId, categoryName);
        
        // AJAX request to get products
        $.ajax({
            url: '{{ route("category-tree.products") }}',
            method: 'GET',
            data: {
                category_id: categoryId,
                include_subcategories: true
            },
            dataType: 'json',
            timeout: 30000, // 30 second timeout
            success: function(response) {
                console.log('Products AJAX Response:', response);
                
                if (response && response.success) {
                    displayProducts(response.products, response.total_products);
                    $('#products-stats').text(`${response.total_products} products found`);
                } else if (response && response.products) {
                    // Handle case where response has products but no success flag
                    displayProducts(response.products, response.products.length);
                    $('#products-stats').text(`${response.products.length} products found`);
                } else {
                    showError(response.message || 'Failed to load products');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error
                });
                
                let errorMessage = 'Failed to load products. Please try again.';
                
                if (xhr.status === 403) {
                    errorMessage = 'You do not have permission to view products.';
                } else if (xhr.status === 404) {
                    errorMessage = 'Category not found.';
                } else if (xhr.status === 500) {
                    errorMessage = 'Server error occurred. Please try again later.';
                } else if (status === 'timeout') {
                    errorMessage = 'Request timed out. Please try again.';
                } else if (status === 'parsererror') {
                    errorMessage = 'Invalid response from server. Please check the logs.';
                }
                
                showError(errorMessage);
            }
        });
    }
    
    // Display products in grid
    function displayProducts(products, totalCount) {
        console.log('Displaying products:', products, 'Total count:', totalCount);
        
        if (!products || products.length === 0) {
            $('#products-content').html(`
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Products Found</h3>
                    <p>This category doesn't have any products yet.</p>
                </div>
            `);
            return;
        }
        
        let productsHtml = '<div class="products-grid">';
        
        products.forEach(function(product) {
            console.log('Processing product:', product);
            
            // Handle current_stock - might be in different formats
            let currentStock = product.current_stock || product.stock || 0;
            const stockClass = currentStock > 0 ? 'in-stock' : 'out-of-stock';
            const stockText = currentStock > 0 ? 
                ` In Stock (${currentStock})` : 
                'Out of Stock';
            
            // Handle image URL
            const imageHtml = product.image ? 
                `<img src="${product.image}" alt="${product.name}" onerror="this.parentElement.innerHTML='<div class=\\"no-image\\"><i class=\\"fa fa-image\\"></i></div>'">` :
                `<div class="no-image"><i class="fa fa-image"></i></div>`;
            
            // Handle price display
            let priceDisplay = product.price_display || product.price || 'Price not available';
            if (product.min_price && product.max_price) {
                if (product.min_price === product.max_price) {
                    priceDisplay = `$${parseFloat(product.min_price).toFixed(2)}`;
                } else {
                    priceDisplay = `$${parseFloat(product.min_price).toFixed(2)} - $${parseFloat(product.max_price).toFixed(2)}`;
                }
            }
            
            // Handle brand name
            const brandHtml = product.brand_name ? 
                `<div class="product-brand">${product.brand_name}</div>` : '';
            
            productsHtml += `
                <div class="product-card" data-product-id="${product.id}">
                    <div class="product-image">
                        ${imageHtml}
                        <div class="product-type-badge">${product.type || 'product'}</div>
                    </div>
                    <div class="product-info">
                        <div class="product-name">${product.name || 'Unnamed Product'}</div>
                        <div class="product-sku">SKU: ${product.sku || 'N/A'}</div>
                        ${brandHtml}
                        <div class="product-price">${priceDisplay}</div>
                        <div class="product-stock ${stockClass}">${stockText}</div>
                    </div>
                </div>
            `;
        });
        
        productsHtml += '</div>';
        $('#products-content').html(productsHtml);
        
        console.log('Products displayed successfully');
    }
    
    // Filter categories based on search
    function filterCategories(searchTerm) {
        if (searchTerm === '') {
            $('.category-item').show();
            return;
        }
        
        $('.category-item').each(function() {
            const categoryName = $(this).find('.category-name').first().text().toLowerCase();
            const matches = categoryName.includes(searchTerm);
            
            if (matches) {
                $(this).show();
                // Show parent categories too
                $(this).parents('.category-item').show();
            } else {
                $(this).hide();
            }
        });
    }
    
    // Show error message
    function showError(message) {
        $('#products-content').html(`
            <div class="empty-state">
                <div class="empty-state-icon">❌</div>
                <h3>Error</h3>
                <p>${message}</p>
            </div>
        `);
    }
    
    // Auto-expand categories with products on page load
    $('.category-item').each(function() {
        const productCount = parseInt($(this).find('.products-count').first().text()) || 0;
        if (productCount > 0) {
            $(this).find('> .category-children').addClass('expanded').show();
            $(this).find('> .category-node .category-toggle').addClass('expanded');
        }
    });
    
    // Initialize sortable on page load
    setTimeout(initializeSortable, 100);
});
</script>
@endsection
