// Category Tree JavaScript
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
        // Get the test route URL from a data attribute or construct it
        const testUrl = $(this).data('test-url') || '/category-tree/test';
        
        $.ajax({
            url: testUrl,
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
        
        // Get the products route URL from a data attribute or construct it
        const productsUrl = $(this).data('products-url') || '/category-tree/products';
        
        $.ajax({
            url: productsUrl,
            method: 'GET',
            data: {
                category_id: 2,
                include_subcategories: true
            },
            dataType: 'json',
            success: function(response) {
                console.log('Test Products Response:', response);
                if (response && response.success) {
                    alert('Products loaded successfully! Found ' + response.total_products + ' products.');
                } else if (response && response.products) {
                    alert('Products loaded! Found ' + response.products.length + ' products.');
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
        // Get the debug route URL from a data attribute or construct it
        const debugUrl = $(this).data('debug-url') || '/category-tree/debug';
        
        $.ajax({
            url: debugUrl,
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
        
        // Get the update order URL from a data attribute or construct it
        const updateOrderUrl = $item.data('update-order-url') || '/category-tree/update-order';
        
        $.ajax({
            url: updateOrderUrl,
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
        $('#products-content').html(
            '<div class="loading-state">' +
                '<div class="spinner"></div>' +
                '<p>Loading products...</p>' +
            '</div>'
        );
        
        console.log('Loading products for category:', categoryId, categoryName);
        
        // Get the products route URL from a data attribute or construct it
        const productsUrl = $('#products-content').data('products-url') || '/category-tree/products';
        
        // AJAX request to get products
        $.ajax({
            url: productsUrl,
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
                    $('#products-stats').text(response.total_products + ' products found');
                } else if (response && response.products) {
                    // Handle case where response has products but no success flag
                    displayProducts(response.products, response.products.length);
                    $('#products-stats').text(response.products.length + ' products found');
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
            $('#products-content').html(
                '<div class="empty-state">' +
                    '<div class="empty-state-icon">📦</div>' +
                    '<h3>No Products Found</h3>' +
                    '<p>This category doesn\'t have any products yet.</p>' +
                '</div>'
            );
            return;
        }
        
        let productsHtml = '<div class="products-grid">';
        
        products.forEach(function(product) {
            console.log('Processing product:', product);
            
            // Handle current_stock - might be in different formats
            let currentStock = product.current_stock || product.stock || 0;
            const stockClass = currentStock > 0 ? 'in-stock' : 'out-of-stock';
            const stockText = currentStock > 0 ? 
                ' In Stock (' + currentStock + ')' : 
                'Out of Stock';
            
            // Handle image URL
            const imageHtml = product.image ? 
                '<img src="' + product.image + '" alt="' + product.name + '" onerror="this.parentElement.innerHTML=\'<div class=&quot;no-image&quot;><i class=&quot;fa fa-image&quot;></i></div>\'">' :
                '<div class="no-image"><i class="fa fa-image"></i></div>';
            
            // Handle price display
            let priceDisplay = product.price_display || product.price || 'Price not available';
            if (product.min_price && product.max_price) {
                if (product.min_price === product.max_price) {
                    priceDisplay = '$' + parseFloat(product.min_price).toFixed(2);
                } else {
                    priceDisplay = '$' + parseFloat(product.min_price).toFixed(2) + ' - $' + parseFloat(product.max_price).toFixed(2);
                }
            }
            
            // Handle brand name
            const brandHtml = product.brand_name ? 
                '<div class="product-brand">' + product.brand_name + '</div>' : '';
            
            productsHtml += 
                '<div class="product-card" data-product-id="' + product.id + '">' +
                    '<div class="product-image">' +
                        imageHtml +
                        '<div class="product-type-badge">' + (product.type || 'product') + '</div>' +
                    '</div>' +
                    '<div class="product-info">' +
                        '<div class="product-name">' + (product.name || 'Unnamed Product') + '</div>' +
                        '<div class="product-sku">SKU: ' + (product.sku || 'N/A') + '</div>' +
                        brandHtml +
                        '<div class="product-price">' + priceDisplay + '</div>' +
                        '<div class="product-stock ' + stockClass + '">' + stockText + '</div>' +
                    '</div>' +
                '</div>';
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
        $('#products-content').html(
            '<div class="empty-state">' +
                '<div class="empty-state-icon">❌</div>' +
                '<h3>Error</h3>' +
                '<p>' + message + '</p>' +
            '</div>'
        );
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
