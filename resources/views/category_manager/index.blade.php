@extends('layouts.app')

@section('title', __('Category Manager'))

@section('content')
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1>@lang('Category Manager')
            <small>Manage your categories with drag & drop</small>
        </h1>
    </section>

    <!-- New Feature Alert -->
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h4><i class="icon fa fa-info-circle"></i> New Feature!</h4>
                    You can now create products directly from any category level! Look for the <span class="label label-info"><i class="fa fa-plus-circle"></i> Product</span> button next to each category in the tree below.
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <!-- Category Tree Column -->
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-sitemap"></i> Category Tree
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-sm btn-primary" id="refresh-tree">
                                <i class="fa fa-refresh"></i> Refresh
                            </button>
                            <button type="button" class="btn btn-sm btn-success" id="expand-all">
                                <i class="fa fa-plus-square"></i> Expand All
                            </button>
                            <button type="button" class="btn btn-sm btn-warning" id="collapse-all">
                                <i class="fa fa-minus-square"></i> Collapse All
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div id="category-tree" class="bg-white p-4 rounded-lg shadow-sm min-h-96">
                            <div class="flex items-center justify-center h-32">
                                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                <span class="ml-2 text-gray-600">Loading categories...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Category Form -->
            <div class="col-md-4">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-plus"></i> <span id="form-title">Add New Category</span>
                        </h3>
                    </div>
                    <div class="box-body">
                        <form id="category-form" class="space-y-4">
                            <input type="hidden" id="category-id" name="category_id">
                            <input type="hidden" id="parent-id" name="parent_id" value="0">
                            
                            <div class="form-group">
                                <label for="category-name" class="block text-sm font-medium text-gray-700">
                                    Category Name *
                                </label>
                                <input type="text" 
                                       id="category-name" 
                                       name="name" 
                                       class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="Enter category name"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="category-code" class="block text-sm font-medium text-gray-700">
                                    Short Code *
                                </label>
                                <input type="text" 
                                       id="category-code" 
                                       name="short_code" 
                                       class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                       placeholder="e.g., CAT001"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="category-description" class="block text-sm font-medium text-gray-700">
                                    Description
                                </label>
                                <textarea id="category-description" 
                                          name="description" 
                                          rows="3"
                                          class="form-control mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                          placeholder="Enter category description"></textarea>
                            </div>

                            <div class="form-group">
                                <label class="block text-sm font-medium text-gray-700">
                                    Parent Category
                                </label>
                                <div id="parent-category-display" class="mt-1 p-2 bg-gray-50 border rounded-md text-sm text-gray-600">
                                    Root Level (No Parent)
                                </div>
                                <button type="button" id="clear-parent" class="mt-2 text-xs text-red-600 hover:text-red-800">
                                    Clear Parent Selection
                                </button>
                            </div>

                            <div class="flex space-x-2">
                                <button type="submit" 
                                        id="submit-btn"
                                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed">
                                    <i class="fa fa-save mr-1"></i>
                                    <span id="submit-text">Add Category</span>
                                </button>
                                <button type="button" 
                                        id="cancel-btn"
                                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-150">
                                    <i class="fa fa-times mr-1"></i>
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Card -->
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-bar-chart"></i> Statistics
                        </h3>
                    </div>
                    <div class="box-body">
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Total Categories:</span>
                                <span id="total-categories" class="font-semibold text-blue-600">0</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Root Categories:</span>
                                <span id="root-categories" class="font-semibold text-green-600">0</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-600">Maximum Depth:</span>
                                <span id="max-depth" class="font-semibold text-purple-600">0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Custom Styles -->
<style>
.category-tree-item {
    padding: 8px 12px;
    margin: 2px 0;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.category-tree-item:hover {
    background: #e9ecef;
    border-color: #adb5bd;
    transform: translateX(2px);
}

.category-tree-item.selected {
    background: #cce5ff;
    border-color: #0066cc;
    box-shadow: 0 2px 4px rgba(0,102,204,0.2);
}

.category-tree-item.dragging {
    opacity: 0.5;
    transform: rotate(2deg);
}

.category-tree-item.drag-over {
    background: #d4edda;
    border-color: #28a745;
    border-style: dashed;
}

.category-children {
    margin-left: 20px;
    border-left: 2px solid #dee2e6;
    padding-left: 10px;
}

.category-toggle {
    position: absolute;
    left: -8px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    background: #007bff;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    cursor: pointer;
    border: none;
}

.category-info {
    display: flex;
    justify-content: between;
    align-items: center;
    width: 100%;
}

.category-details {
    flex: 1;
}

.category-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s;
}

.category-tree-item:hover .category-actions {
    opacity: 1;
}

.level-indicator {
    display: inline-block;
    padding: 2px 6px;
    background: #6c757d;
    color: white;
    border-radius: 10px;
    font-size: 10px;
    font-weight: bold;
    margin-right: 8px;
}

.level-1 .level-indicator { background: #007bff; }
.level-2 .level-indicator { background: #28a745; }
.level-3 .level-indicator { background: #ffc107; color: #000; }
.level-4 .level-indicator { background: #dc3545; }
.level-5 .level-indicator { background: #6f42c1; }

.product-count {
    background: #e9ecef;
    color: #495057;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    margin-left: 8px;
}

/* Submit button states */
#submit-btn:disabled {
    opacity: 0.6 !important;
    cursor: not-allowed !important;
    background-color: #6c757d !important;
}

#submit-btn:not(:disabled):hover {
    background-color: #0056b3 !important;
}

/* Create Product Button Styling */
.create-product-btn {
    font-weight: bold;
    border: 2px solid #17a2b8;
    transition: all 0.2s ease-in-out;
}

.create-product-btn:hover {
    background-color: #138496 !important;
    border-color: #117a8b !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* Form validation feedback */
.form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.form-control.is-valid {
    border-color: #28a745;
}

.form-control.is-invalid {
    border-color: #dc3545;
}
</style>
@endsection

@section('javascript')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
$(document).ready(function() {
    let categories = [];
    let selectedCategory = null;
    let sortableInstance = null;

    // Load initial data
    loadCategories();

    // Function to check form validity and enable/disable submit button
    function validateForm() {
        const name = $('#category-name').val().trim();
        const shortCode = $('#category-code').val().trim();
        const submitBtn = $('#submit-btn');
        const nameField = $('#category-name');
        const codeField = $('#category-code');
        
        // Validate name field
        if (name) {
            nameField.removeClass('is-invalid').addClass('is-valid');
        } else {
            nameField.removeClass('is-valid').addClass('is-invalid');
        }
        
        // Validate short code field
        if (shortCode) {
            codeField.removeClass('is-invalid').addClass('is-valid');
        } else {
            codeField.removeClass('is-valid').addClass('is-invalid');
        }
        
        // Enable/disable submit button
        if (name && shortCode) {
            submitBtn.prop('disabled', false)
                     .removeClass('opacity-50 cursor-not-allowed')
                     .addClass('bg-blue-600 hover:bg-blue-700');
        } else {
            submitBtn.prop('disabled', true)
                     .addClass('opacity-50 cursor-not-allowed')
                     .removeClass('bg-blue-600 hover:bg-blue-700');
        }
    }

    // Add input event listeners to validate form in real-time
    $('#category-name, #category-code').on('input keyup paste', validateForm);

    // Initial form validation
    validateForm();

    // Load categories from server
    function loadCategories() {
        $.ajax({
            url: '{{ route("category-manager.categories-json") }}',
            method: 'GET',
            success: function(data) {
                categories = data;
                renderCategoryTree();
                updateStatistics();
            },
            error: function(xhr) {
                showAlert('Error loading categories: ' + xhr.responseJSON?.message, 'error');
            }
        });
    }

    // Render category tree
    function renderCategoryTree() {
        const treeContainer = $('#category-tree');
        treeContainer.html('');
        
        if (categories.length === 0) {
            treeContainer.html(`
                <div class="text-center text-gray-500 py-8">
                    <i class="fa fa-sitemap text-4xl mb-4"></i>
                    <p>No categories found. Create your first category!</p>
                </div>
            `);
            return;
        }
        
        const treeHtml = renderCategoryLevel(categories);
        treeContainer.html(treeHtml);
        
        // Initialize drag and drop
        initializeDragAndDrop();
        
        // Bind events
        bindCategoryEvents();
    }

    // Render category level recursively
    function renderCategoryLevel(cats, level = 1) {
        let html = '';
        
        cats.forEach(category => {
            const hasChildren = category.children && category.children.length > 0;
            html += `
                <div class="category-tree-item level-${level}" 
                     data-id="${category.id}" 
                     data-parent-id="${category.parent_id}"
                     data-level="${level}">
                    ${hasChildren ? `<button class="category-toggle" data-action="toggle"><i class="fa fa-minus"></i></button>` : ''}
                    <div class="category-info">
                        <div class="category-details">
                            <span class="level-indicator">L${level}</span>
                            <strong>${category.name}</strong>
                            ${category.short_code ? `<small class="text-muted">(${category.short_code})</small>` : ''}
                            ${category.product_count > 0 ? `<span class="product-count">${category.product_count} products</span>` : ''}
                            ${category.description ? `<br><small class="text-muted">${category.description}</small>` : ''}
                        </div>
                        <div class="category-actions">
                            <button class="btn btn-xs btn-info create-product-btn" data-action="create-product" title="Create Product in this Category">
                                <i class="fa fa-plus-circle"></i> Product
                            </button>
                            <button class="btn btn-xs btn-primary" data-action="edit" title="Edit Category">
                                <i class="fa fa-edit"></i>
                            </button>
                            <button class="btn btn-xs btn-success" data-action="add-child" title="Add Child Category">
                                <i class="fa fa-plus"></i>
                            </button>
                            <button class="btn btn-xs btn-danger" data-action="delete" title="Delete Category">
                                <i class="fa fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    ${hasChildren ? `<div class="category-children">${renderCategoryLevel(category.children, level + 1)}</div>` : ''}
                </div>
            `;
        });
        
        return html;
    }

    // Initialize drag and drop
    function initializeDragAndDrop() {
        const treeContainer = document.getElementById('category-tree');
        
        if (sortableInstance) {
            sortableInstance.destroy();
        }
        
        sortableInstance = Sortable.create(treeContainer, {
            group: 'categories',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            draggable: '.category-tree-item',
            ghostClass: 'dragging',
            chosenClass: 'drag-over',
            
            onStart: function(evt) {
                evt.item.classList.add('dragging');
            },
            
            onEnd: function(evt) {
                evt.item.classList.remove('dragging');
                
                // Get new parent from drop position
                const newParentElement = evt.to.closest('.category-tree-item');
                const newParentId = newParentElement ? newParentElement.dataset.id : 0;
                const categoryId = evt.item.dataset.id;
                
                // Update category order
                updateCategoryOrder(categoryId, newParentId);
            }
        });
    }

    // Update category order via AJAX
    function updateCategoryOrder(categoryId, newParentId) {
        const categoriesData = [{
            id: parseInt(categoryId),
            parent_id: parseInt(newParentId) || 0
        }];
        
        $.ajax({
            url: '{{ route("category-manager.update-order") }}',
            method: 'POST',
            data: {
                categories: categoriesData,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                showAlert(response.message, 'success');
                loadCategories(); // Reload to reflect changes
            },
            error: function(xhr) {
                showAlert('Error updating category order: ' + xhr.responseJSON?.message, 'error');
                loadCategories(); // Reload to restore original state
            }
        });
    }

    // Bind category events
    function bindCategoryEvents() {
        // Toggle category children
        $(document).off('click', '.category-toggle').on('click', '.category-toggle', function(e) {
            e.stopPropagation();
            const children = $(this).closest('.category-tree-item').find('> .category-children');
            const icon = $(this).find('i');
            
            children.slideToggle();
            icon.toggleClass('fa-minus fa-plus');
        });

        // Category item click (select)
        $(document).off('click', '.category-tree-item').on('click', '.category-tree-item', function(e) {
            if ($(e.target).closest('.category-actions, .category-toggle').length) return;
            
            $('.category-tree-item').removeClass('selected');
            $(this).addClass('selected');
            
            const categoryId = $(this).data('id');
            selectedCategory = findCategoryById(categoryId);
            updateParentDisplay();
        });

        // Action buttons
        $(document).off('click', '[data-action="edit"]').on('click', '[data-action="edit"]', function(e) {
            e.stopPropagation();
            const categoryId = $(this).closest('.category-tree-item').data('id');
            editCategory(findCategoryById(categoryId));
        });

        $(document).off('click', '[data-action="add-child"]').on('click', '[data-action="add-child"]', function(e) {
            e.stopPropagation();
            const categoryId = $(this).closest('.category-tree-item').data('id');
            addChildCategory(findCategoryById(categoryId));
        });

        $(document).off('click', '[data-action="delete"]').on('click', '[data-action="delete"]', function(e) {
            e.stopPropagation();
            const categoryId = $(this).closest('.category-tree-item').data('id');
            deleteCategory(findCategoryById(categoryId));
        });

        $(document).off('click', '[data-action="create-product"]').on('click', '[data-action="create-product"]', function(e) {
            e.stopPropagation();
            const categoryId = $(this).closest('.category-tree-item').data('id');
            const category = findCategoryById(categoryId);
            createProductInCategory(category);
        });
    }

    // Find category by ID
    function findCategoryById(id, cats = categories) {
        for (let category of cats) {
            if (category.id == id) return category;
            if (category.children) {
                const found = findCategoryById(id, category.children);
                if (found) return found;
            }
        }
        return null;
    }

    // Edit category
    function editCategory(category) {
        $('#form-title').text('Edit Category');
        $('#submit-text').text('Update Category');
        $('#category-id').val(category.id);
        $('#category-name').val(category.name);
        $('#category-code').val(category.short_code);
        $('#category-description').val(category.description);
        $('#parent-id').val(category.parent_id);
        
        // Set selected parent
        if (category.parent_id > 0) {
            selectedCategory = findCategoryById(category.parent_id);
        } else {
            selectedCategory = null;
        }
        updateParentDisplay();
        validateForm(); // Validate form after loading data
    }

    // Add child category
    function addChildCategory(parentCategory) {
        resetForm();
        $('#parent-id').val(parentCategory.id);
        selectedCategory = parentCategory;
        updateParentDisplay();
        $('#category-name').focus();
    }

    // Delete category
    function deleteCategory(category) {
        if (!confirm(`Are you sure you want to delete "${category.name}"?`)) return;
        
        $.ajax({
            url: '{{ route("category-manager.destroy", ":id") }}'.replace(':id', category.id),
            method: 'DELETE',
            data: {
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                showAlert(response.message, 'success');
                loadCategories();
                resetForm();
            },
            error: function(xhr) {
                showAlert('Error deleting category: ' + xhr.responseJSON?.message, 'error');
            }
        });
    }

    // Create product in category
    function createProductInCategory(category) {
        // Get category level for better messaging
        const categoryLevel = $(`.category-tree-item[data-id="${category.id}"]`).data('level') || 'Unknown';
        const levelText = `L${categoryLevel}`;
        
        // Enhanced confirmation with category info
        const confirmMessage = `Create a new product in:\n\n📁 ${category.name} (${levelText})\n\nThis will open the product creation page with this category pre-selected.`;
        
        if (!confirm(confirmMessage)) {
            return;
        }

        // Build the URL with category pre-selection
        const createProductUrl = '{{ url("/products/create") }}' + '?category_id=' + category.id;
        
        // Show success message with category info
        showAlert(` Opening product creation for "${category.name}" (${levelText})...`, 'success');
        
        // Open in new tab for better workflow
        const newWindow = window.open(createProductUrl, '_blank');
        
        // Handle if popup was blocked
        if (!newWindow || newWindow.closed || typeof newWindow.closed == 'undefined') {
            showAlert('⚠️ Popup blocked! Please allow popups and try again, or manually navigate to the products page.', 'warning');
            // Fallback: navigate in same tab
            setTimeout(() => {
                window.location.href = createProductUrl;
            }, 2000);
        } else {
            // Give user feedback that new tab opened
            setTimeout(() => {
                showAlert(` Product creation page opened in new tab for "${category.name}"`, 'info');
            }, 1000);
        }
    }

    // Update parent display
    function updateParentDisplay() {
        if (selectedCategory) {
            const path = getCategoryPath(selectedCategory);
            $('#parent-category-display').html(`
                <i class="fa fa-folder text-blue-500 mr-1"></i>
                ${path}
            `);
        } else {
            $('#parent-category-display').html(`
                <i class="fa fa-home text-gray-400 mr-1"></i>
                Root Level (No Parent)
            `);
        }
    }

    // Get category path
    function getCategoryPath(category) {
        const path = [category.name];
        let current = category;
        
        while (current.parent_id > 0) {
            const parent = findCategoryById(current.parent_id);
            if (parent) {
                path.unshift(parent.name);
                current = parent;
            } else {
                break;
            }
        }
        
        return path.join(' > ');
    }

    // Form submission
    $('#category-form').on('submit', function(e) {
        e.preventDefault();
        
        // Double check if form is valid before submitting
        const name = $('#category-name').val().trim();
        const shortCode = $('#category-code').val().trim();
        
        if (!name || !shortCode) {
            showAlert('Please fill in all required fields (Name and Short Code)', 'error');
            return;
        }
        
        const categoryId = $('#category-id').val();
        const isEdit = categoryId !== '';
        
        // Disable submit button during processing
        const submitBtn = $('#submit-btn');
        const originalText = submitBtn.find('#submit-text').text();
        submitBtn.prop('disabled', true).find('#submit-text').text('Processing...');
        
        const formData = {
            name: name,
            short_code: shortCode,
            description: $('#category-description').val(),
            parent_id: $('#parent-id').val() || 0,
            _token: '{{ csrf_token() }}'
        };
        
        const url = isEdit 
            ? '{{ route("category-manager.update", ":id") }}'.replace(':id', categoryId)
            : '{{ route("category-manager.store") }}';
        
        const method = isEdit ? 'PUT' : 'POST';
        
        $.ajax({
            url: url,
            method: method,
            data: formData,
            success: function(response) {
                showAlert(response.message, 'success');
                loadCategories();
                resetForm();
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors;
                if (errors) {
                    let errorMsg = 'Validation errors:\n';
                    Object.keys(errors).forEach(key => {
                        errorMsg += `- ${errors[key][0]}\n`;
                    });
                    showAlert(errorMsg, 'error');
                } else {
                    showAlert('Error saving category: ' + xhr.responseJSON?.message, 'error');
                }
            },
            complete: function() {
                // Re-enable submit button and restore text
                submitBtn.prop('disabled', false).find('#submit-text').text(originalText);
                validateForm(); // Re-validate to update button state
            }
        });
    });

    // Reset form
    function resetForm() {
        $('#category-form')[0].reset();
        $('#category-id').val('');
        $('#parent-id').val('0');
        $('#form-title').text('Add New Category');
        $('#submit-text').text('Add Category');
        selectedCategory = null;
        updateParentDisplay();
        $('.category-tree-item').removeClass('selected');
        
        // Clear validation classes
        $('#category-name, #category-code').removeClass('is-valid is-invalid');
        
        validateForm(); // Validate form after reset
    }

    // Clear parent selection
    $('#clear-parent').on('click', function() {
        selectedCategory = null;
        $('#parent-id').val('0');
        updateParentDisplay();
        $('.category-tree-item').removeClass('selected');
    });

    // Cancel button
    $('#cancel-btn').on('click', resetForm);

    // Tree controls
    $('#refresh-tree').on('click', loadCategories);
    
    $('#expand-all').on('click', function() {
        $('.category-children').slideDown();
        $('.category-toggle i').removeClass('fa-plus').addClass('fa-minus');
    });
    
    $('#collapse-all').on('click', function() {
        $('.category-children').slideUp();
        $('.category-toggle i').removeClass('fa-minus').addClass('fa-plus');
    });

    // Update statistics
    function updateStatistics() {
        let totalCount = 0;
        let rootCount = 0;
        let maxDepth = 0;
        
        function countCategories(cats, depth = 1) {
            cats.forEach(category => {
                totalCount++;
                if (category.parent_id === 0) rootCount++;
                maxDepth = Math.max(maxDepth, depth);
                
                if (category.children && category.children.length > 0) {
                    countCategories(category.children, depth + 1);
                }
            });
        }
        
        if (categories.length > 0) {
            countCategories(categories);
        }
        
        $('#total-categories').text(totalCount);
        $('#root-categories').text(rootCount);
        $('#max-depth').text(maxDepth);
    }

    // Show alert
    function showAlert(message, type = 'info') {
        const alertClass = type === 'error' ? 'alert-danger' : 
                          type === 'success' ? 'alert-success' : 'alert-info';
        
        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ${message}
            </div>
        `;
        
        $('body').append(alertHtml);
        
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
    }
});
</script>
@endsection
