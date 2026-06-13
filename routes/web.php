<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountReportsController;
use App\Http\Controllers\AccountTypeController;
// use App\Http\Controllers\Auth;
use App\Http\Controllers\BackUpController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedPurchaseReturnController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\CustomerSourceController;
use App\Http\Controllers\DashboardConfiguratorController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\DocumentAndNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImageGalleryController;
use App\Http\Controllers\ImportOpeningStockController;
use App\Http\Controllers\ImportProductsController;
use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\Install;
use App\Http\Controllers\InvoiceLayoutController;
use App\Http\Controllers\InvoiceSchemeController;
use App\Http\Controllers\LabelsController;
use App\Http\Controllers\LedgerDiscountController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\ManageUserController;
use App\Http\Controllers\MenuSettingsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Restaurant;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCommissionAgentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellingPriceGroupController;
use App\Http\Controllers\SellPosController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\TypesOfServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationTemplateController;
use App\Http\Controllers\WarrantyController;
use App\Http\Controllers\CategoryTreeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once 'install_r.php';
include_once 'web_multilevel_categories.php';
include_once 'test_multilevel.php';

Route::middleware(['setData'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Auth::routes();

    Route::get('/business/register', [BusinessController::class, 'getRegister'])->name('business.getRegister');
    Route::post('/business/register', [BusinessController::class, 'postRegister'])->name('business.postRegister');
    Route::post('/business/register/check-username', [BusinessController::class, 'postCheckUsername'])->name('business.postCheckUsername');
    Route::post('/business/register/check-email', [BusinessController::class, 'postCheckEmail'])->name('business.postCheckEmail');

    Route::get('/invoice/{token}', [SellPosController::class, 'showInvoice'])
        ->name('show_invoice');
    Route::get('/quote/{token}', [SellPosController::class, 'showInvoice'])
        ->name('show_quote');

    Route::get('/pay/{token}', [SellPosController::class, 'invoicePayment'])
        ->name('invoice_payment');
    Route::post('/confirm-payment/{id}', [SellPosController::class, 'confirmPayment'])
        ->name('confirm_payment');
    
    
    // OLD ROUTES DISABLED - Using new simple authentication system
    /*
    Route::get('/public/quotations/{id}/pdf-print-nodejs', function($id) {
        require_once base_path('pdf-generator-helper.php');
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        // Check if service is running
        if (!$pdfGenerator->isServiceRunning()) {
            return response()->json([
                'error' => 'PDF Generator service is not running. Please start the Node.js server with: npm start'
            ], 503);
        }
        
        // Generate PDF
        $result = $pdfGenerator->generateQuotationPDF($id);
        
        if ($result['success']) {
            // Check if request wants JSON response (for custom URLs)
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'success' => true,
                    'pdf_url' => $result['custom_url'],
                    'filename' => $result['filename'],
                    'download_url' => url('download-pdf/' . $result['clean_filename'])
                ]);
            } else {
                // Return PDF data directly (for backward compatibility)
                return response($result['pdf_data'])
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . $result['filename'] . '"')
                    ->header('X-Suggested-Filename', $result['filename']);
            }
        } else {
            return response()->json([
                'error' => $result['error']
            ], 500);
        }
    })->name('quotations.pdfprint.nodejs.public');
    
    // Secure PDF Routes with token-based authentication
    Route::get('/secure-pdf/{document_type}/{id}', function($document_type, $id) {
        require_once base_path('pdf-generator-helper.php');
        require_once base_path('pdf-security-helper.php');
        
        // Get token from request
        $token = request()->get('token');
        
        // Validate token
        $validation = PDFSecurityHelper::validatePDFToken($token);
        if (!$validation['valid']) {
            return response()->json([
                'error' => 'Access denied: ' . $validation['error']
            ], 403);
        }
        
        $tokenData = $validation['data'];
        
        // Verify token matches request
        if ($tokenData['transaction_id'] != $id || $tokenData['document_type'] !== $document_type) {
            return response()->json([
                'error' => 'Token does not match request'
            ], 403);
        }
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        // Check if service is running
        if (!$pdfGenerator->isServiceRunning()) {
            return response()->json([
                'error' => 'PDF Generator service is not running'
            ], 503);
        }
        
        // Generate PDF based on document type
        $result = null;
        switch ($document_type) {
            case 'quotations':
                $result = $pdfGenerator->generateQuotationPDF($id);
                break;
            case 'tax-invoice':
                $result = $pdfGenerator->generateTaxInvoicePDF($id);
                break;
            case 'billing-receipt':
                $result = $pdfGenerator->generateBillingReceiptPDF($id);
                break;
            default:
                return response()->json(['error' => 'Invalid document type'], 400);
        }
        
        if ($result['success']) {
            // Check if request wants JSON response (for custom URLs)
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                // Use simple URL without token
                
                return response()->json([
                    'success' => true,
                    'pdf_url' => $result['custom_url'],
                    'filename' => $result['filename'],
                    'download_url' => url('download-pdf/' . $result['clean_filename']),
                    'secure_access' => false
                ]);
            } else {
                // Return PDF data directly
                return response($result['pdf_data'])
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="' . $result['filename'] . '"')
                    ->header('X-Suggested-Filename', $result['filename'])
                    ->header('X-PDF-Security', 'token-validated');
            }
        } else {
            return response()->json([
                'error' => $result['error']
            ], 500);
        }
    })->name('secure.pdf.generate');
    
    // Public routes (keep for backward compatibility but add deprecation notice)
    Route::get('/public/quotations/{id}/pdf-print-nodejs', function($id) {
        // Add deprecation warning in logs
        \Log::warning("Deprecated public PDF route used for quotations/{$id}");
        
        require_once base_path('pdf-generator-helper.php');
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        if (!$pdfGenerator->isServiceRunning()) {
            return response()->json([
                'error' => 'PDF Generator service is not running'
            ], 503);
        }
        
        $result = $pdfGenerator->generateQuotationPDF($id);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'pdf_url' => $result['custom_url'],
                'filename' => $result['filename'],
                'download_url' => url('download-pdf/' . $result['clean_filename'])
            ]);
        } else {
            return response()->json([
                'error' => $result['error']
            ], 500);
        }
    })->name('quotations.pdfprint.nodejs.public.deprecated');
    */
    // END OF DISABLED OLD ROUTES
});

// Token-protected cron sync endpoints (used by Node PDF server cron, no login session)
Route::middleware(['setData'])->group(function () {
    Route::get('migrate-update-data/cron-sync-run', [App\Http\Controllers\MigrationUpdateController::class, 'runSyncCron'])->name('migrate-update-data.cron-sync-run');
    Route::get('migrate-update-data/cron-sync-products', [App\Http\Controllers\MigrationUpdateController::class, 'runProductSyncCron'])->name('migrate-update-data.cron-sync-products');
    Route::get('migrate-update-data/cron-sync-payment-updates', [App\Http\Controllers\MigrationUpdateController::class, 'runPaymentSyncCron'])->name('migrate-update-data.cron-sync-payment-updates');
});

//Routes for authenticated users only
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])->group(function () {
    Route::get('pos/payment/{id}', [SellPosController::class, 'edit'])->name('edit-pos-payment');
    Route::get('service-staff-availability', [SellPosController::class, 'showServiceStaffAvailibility']);
    Route::get('pause-resume-service-staff-timer/{user_id}', [SellPosController::class, 'pauseResumeServiceStaffTimer']);
    Route::get('mark-as-available/{user_id}', [SellPosController::class, 'markAsAvailable']);

    Route::resource('purchase-requisition', PurchaseRequisitionController::class)->except(['edit', 'update']);
    Route::post('/get-requisition-products', [PurchaseRequisitionController::class, 'getRequisitionProducts'])->name('get-requisition-products');
    Route::get('get-purchase-requisitions/{location_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitions']);
    Route::get('get-purchase-requisition-lines/{purchase_requisition_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitionLines']);

    Route::get('/sign-in-as-user/{id}', [ManageUserController::class, 'signInAsUser'])->name('sign-in-as-user');

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard-v2', [HomeController::class, 'dashboardV2'])->name('dashboard.v2');
    Route::get('/dashboard-v2/export', [HomeController::class, 'dashboardV2Export'])->name('dashboard.v2.export');
    Route::get('/dashboard-v2/ai-suggestions', [HomeController::class, 'dashboardV2AiSuggestions'])->name('dashboard.v2.ai');
    Route::post('/dashboard-v2/ai-chat', [HomeController::class, 'dashboardV2AiChat'])->name('dashboard.v2.ai.chat');
    Route::post('/dashboard-v2/ai-purchase-plan', [HomeController::class, 'dashboardV2AiPurchasePlan'])->name('dashboard.v2.ai.purchase_plan');
    Route::get('/home/get-totals', [HomeController::class, 'getTotals']);
    Route::get('/home/product-stock-alert', [HomeController::class, 'getProductStockAlert']);
    Route::get('/home/purchase-payment-dues', [HomeController::class, 'getPurchasePaymentDues']);
    Route::get('/home/sales-payment-dues', [HomeController::class, 'getSalesPaymentDues']);
    Route::post('/attach-medias-to-model', [HomeController::class, 'attachMediasToGivenModel'])->name('attach.medias.to.model');
    Route::get('/calendar', [HomeController::class, 'getCalendar'])->name('calendar');

    Route::post('/test-email', [BusinessController::class, 'testEmailConfiguration']);
    Route::post('/test-sms', [BusinessController::class, 'testSmsConfiguration']);
    Route::get('/business/settings', [BusinessController::class, 'getBusinessSettings'])->name('business.getBusinessSettings');
    Route::post('/business/update', [BusinessController::class, 'postBusinessSettings'])->name('business.postBusinessSettings');
    Route::get('/user/profile', [UserController::class, 'getProfile'])->name('user.getProfile');
    Route::post('/user/update', [UserController::class, 'updateProfile'])->name('user.updateProfile');
    Route::post('/user/update-password', [UserController::class, 'updatePassword'])->name('user.updatePassword');
    Route::post('/user/change-language', [UserController::class, 'changeLanguage'])->name('user.changeLanguage');

    // Menu Settings Routes
    Route::get('/menu-settings', [MenuSettingsController::class, 'index'])->name('menu-settings.index');
    Route::get('/menu-settings-standalone', function() {
        $menuSettingsController = new App\Http\Controllers\MenuSettingsController();
        $menuItems = $menuSettingsController->getMenuStructure();
        return view('menu-settings.standalone', compact('menuItems'));
    })->name('menu-settings.standalone');
    Route::post('/menu-settings/update-order', [MenuSettingsController::class, 'updateOrder'])->name('menu-settings.update-order');
    Route::post('/menu-settings/toggle-visibility', [MenuSettingsController::class, 'toggleVisibility'])->name('menu-settings.toggle-visibility');
    Route::post('/menu-settings/update-item', [MenuSettingsController::class, 'updateMenuItem'])->name('menu-settings.update-item');
    Route::post('/menu-settings/add-item', [MenuSettingsController::class, 'addMenuItem'])->name('menu-settings.add-item');
    Route::post('/menu-settings/delete-item', [MenuSettingsController::class, 'deleteMenuItem'])->name('menu-settings.delete-item');

    Route::resource('brands', BrandController::class);

    // Route::resource('payment-account', 'PaymentAccountController');

    Route::resource('customer-sources', CustomerSourceController::class);

    Route::resource('tax-rates', TaxRateController::class);

    Route::resource('units', UnitController::class);

    // Group Types Routes - IMPORTANT: Custom routes BEFORE resource routes
    Route::get('group-types/search-products', [App\Http\Controllers\GroupTypeController::class, 'searchProducts'])->name('group-types.search-products');
    Route::post('group-types/update-order', [App\Http\Controllers\GroupTypeController::class, 'updateOrder'])->name('group-types.update-order');
    Route::post('group-types/{id}/add-product', [App\Http\Controllers\GroupTypeController::class, 'addProduct'])->name('group-types.add-product');
    Route::post('group-types/{id}/remove-product', [App\Http\Controllers\GroupTypeController::class, 'removeProduct'])->name('group-types.remove-product');
    Route::post('group-types/{id}/update-product-order', [App\Http\Controllers\GroupTypeController::class, 'updateProductOrder'])->name('group-types.update-product-order');
    Route::resource('group-types', App\Http\Controllers\GroupTypeController::class);

    // Group Sub Types Routes - IMPORTANT: Custom routes BEFORE resource routes
    Route::post('group-sub-types/update-order', [App\Http\Controllers\GroupSubTypeController::class, 'updateOrder'])->name('group-sub-types.update-order');
    Route::post('group-sub-types/{id}/add-product', [App\Http\Controllers\GroupSubTypeController::class, 'addProduct'])->name('group-sub-types.add-product');
    Route::post('group-sub-types/{id}/remove-product', [App\Http\Controllers\GroupSubTypeController::class, 'removeProduct'])->name('group-sub-types.remove-product');
    Route::post('group-sub-types/{id}/update-product-order', [App\Http\Controllers\GroupSubTypeController::class, 'updateProductOrder'])->name('group-sub-types.update-product-order');
    Route::resource('group-sub-types', App\Http\Controllers\GroupSubTypeController::class)->except(['index', 'show']);

    Route::resource('ledger-discount', LedgerDiscountController::class)->only('edit', 'destroy', 'store', 'update');

    Route::post('check-mobile', [ContactController::class, 'checkMobile']);
    Route::get('/get-contact-due/{contact_id}', [ContactController::class, 'getContactDue']);
    Route::get('/contacts/payments/{contact_id}', [ContactController::class, 'getContactPayments']);
    Route::get('/contacts/map', [ContactController::class, 'contactMap']);
    Route::get('/contacts/update-status/{id}', [ContactController::class, 'updateStatus']);
    Route::get('/contacts/stock-report/{supplier_id}', [ContactController::class, 'getSupplierStockReport']);
    Route::get('/contacts/ledger', [ContactController::class, 'getLedger']);
    Route::post('/contacts/send-ledger', [ContactController::class, 'sendLedger']);
    Route::get('/contacts/import', [ContactController::class, 'getImportContacts'])->name('contacts.import');
    Route::post('/contacts/import', [ContactController::class, 'postImportContacts']);
    Route::post('/contacts/check-contacts-id', [ContactController::class, 'checkContactId']);
    Route::get('/contacts/customers', [ContactController::class, 'getCustomers']);
    Route::get('/contacts/details/{id}', [ContactController::class, 'getContactDetails'])->name('contacts.details');
    Route::get('/contacts/search-by-tax', [ContactController::class, 'searchByTaxNumber']);
    Route::get('/contacts/search-general', [ContactController::class, 'searchGeneral']);
    Route::post('/contacts/{id}/inline-update', [ContactController::class, 'inlineUpdateContact'])->name('contacts.inline-update');
    Route::resource('contacts', ContactController::class);

    Route::get('taxonomies-ajax-index-page', [TaxonomyController::class, 'getTaxonomyIndexPage']);
    Route::resource('taxonomies', TaxonomyController::class);

    // Category Manager Routes
    Route::get('createnewcategories', [App\Http\Controllers\CategoryManagerController::class, 'index'])->name('category-manager.index');
    Route::get('category-manager/categories-json', [App\Http\Controllers\CategoryManagerController::class, 'getCategoriesJson'])->name('category-manager.categories-json');
    Route::post('category-manager/store', [App\Http\Controllers\CategoryManagerController::class, 'store'])->name('category-manager.store');
    Route::put('category-manager/{id}', [App\Http\Controllers\CategoryManagerController::class, 'update'])->name('category-manager.update');
    Route::delete('category-manager/{id}', [App\Http\Controllers\CategoryManagerController::class, 'destroy'])->name('category-manager.destroy');
    Route::post('category-manager/update-order', [App\Http\Controllers\CategoryManagerController::class, 'updateOrder'])->name('category-manager.update-order');

    // Category Manager V2 Routes
    Route::get('createnewcategoriesv2', [App\Http\Controllers\CategoryManagerV2Controller::class, 'index'])->name('category-manager-v2.index');
    Route::get('category-manager-v2/categories', [App\Http\Controllers\CategoryManagerV2Controller::class, 'getCategoriesJson'])->name('category-manager-v2.categories');
    Route::post('category-manager-v2/store', [App\Http\Controllers\CategoryManagerV2Controller::class, 'store'])->name('category-manager-v2.store');
    Route::put('category-manager-v2/{id}', [App\Http\Controllers\CategoryManagerV2Controller::class, 'update'])->name('category-manager-v2.update');
    Route::delete('category-manager-v2/{id}', [App\Http\Controllers\CategoryManagerV2Controller::class, 'destroy'])->name('category-manager-v2.destroy');
    Route::post('category-manager-v2/update-order', [App\Http\Controllers\CategoryManagerV2Controller::class, 'updateOrder'])->name('category-manager-v2.update-order');

    Route::resource('variation-templates', VariationTemplateController::class);

    Route::get('/products/download-excel', [ProductController::class, 'downloadExcel']);
    
    Route::get('/products/generate-next-sku', [ProductController::class, 'generateNextSku']);

    Route::get('/products/stock-history/{id}', [ProductController::class, 'productStockHistory']);
    Route::get('/delete-media/{media_id}', [ProductController::class, 'deleteMedia']);
    Route::post('/products/mass-deactivate', [ProductController::class, 'massDeactivate']);
    Route::get('/products/activate/{id}', [ProductController::class, 'activate']);
    Route::get('/products/view-product-group-price/{id}', [ProductController::class, 'viewGroupPrice']);
    Route::get('/products/add-selling-prices/{id}', [ProductController::class, 'addSellingPrices']);
    Route::post('/products/save-selling-prices', [ProductController::class, 'saveSellingPrices']);
    Route::post('/products/mass-delete', [ProductController::class, 'massDestroy']);
    Route::get('/products/view/{id}', [ProductController::class, 'view']);
    Route::get('/products/list', [ProductController::class, 'getProducts']);
    Route::get('/products/list-no-variation', [ProductController::class, 'getProductsWithoutVariations']);
    Route::post('/products/bulk-edit', [ProductController::class, 'bulkEdit']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/bulk-update-location', [ProductController::class, 'updateProductLocation']);
    Route::get('/products/get-product-to-edit/{product_id}', [ProductController::class, 'getProductToEdit']);

    Route::post('/products/get_sub_categories', [ProductController::class, 'getSubCategories']);
    Route::post('/products/get_category_hierarchy', [ProductController::class, 'getCategoryHierarchy']);
    Route::get('/products/get_sub_units', [ProductController::class, 'getSubUnits']);
    Route::post('/products/product_form_part', [ProductController::class, 'getProductVariationFormPart']);
    Route::post('/products/get_product_variation_row', [ProductController::class, 'getProductVariationRow']);
    Route::post('/products/get_variation_template', [ProductController::class, 'getVariationTemplate']);
    Route::get('/products/get_variation_value_row', [ProductController::class, 'getVariationValueRow']);
    Route::post('/products/check_product_sku', [ProductController::class, 'checkProductSku']);
    Route::post('/products/validate_variation_skus', [ProductController::class, 'validateVaritionSkus']); //validates multiple skus at once
    Route::get('/products/quick_add', [ProductController::class, 'quickAdd']);
    Route::post('/products/save_quick_product', [ProductController::class, 'saveQuickProduct']);
    Route::get('/products/get-combo-product-entry-row', [ProductController::class, 'getComboProductEntryRow']);
    Route::post('/logs/text', [ProductController::class, 'clientLog']);
    Route::post('/products/toggle-woocommerce-sync', [ProductController::class, 'toggleWooCommerceSync']);

    Route::get('/products', function () {
        return redirect('products-v2');
    });
    Route::resource('products', ProductController::class);

    // Image Gallery Routes
    Route::get('/image-gallery/images', [ImageGalleryController::class, 'getImages'])->name('image-gallery.images');
    Route::post('/image-gallery/upload', [ImageGalleryController::class, 'uploadImages'])->name('image-gallery.upload');
    Route::delete('/image-gallery/delete/{id}', [ImageGalleryController::class, 'deleteImage'])->name('image-gallery.delete');

    // ProductsV2 Routes moved to web_multilevel_categories.php


    // Category Tree Routes
    Route::get('/category-tree', [CategoryTreeController::class, 'index'])->name('category-tree.index');
    Route::get('/category-tree/products', [CategoryTreeController::class, 'getCategoryProducts'])->name('category-tree.products')->middleware('clean.json');
    Route::get('/category-tree/search', [CategoryTreeController::class, 'searchCategories'])->name('category-tree.search')->middleware('clean.json');
    Route::post('/category-tree/update-order', [CategoryTreeController::class, 'updateCategoryOrder'])->name('category-tree.update-order')->middleware('clean.json');
    Route::get('/category-tree/test', [CategoryTreeController::class, 'testConnection'])->name('category-tree.test')->middleware('clean.json'); // Temporary debug route
    
    // Simple test route for subcategory products
    Route::get('/test-subcategory-fix/{categoryId}', function($categoryId) {
        try {
            $business_id = 1; // Default business ID for testing
            
            // Get category info
            $category = \App\Category::where('business_id', $business_id)
                ->where('id', $categoryId)
                ->first();
                
            if (!$category) {
                return response()->json(['error' => 'Category not found']);
            }
            
            // Try to get products directly from this category
            $directProducts = \App\Product::where('business_id', $business_id)
                ->where('category_id', $categoryId)
                ->where('is_inactive', 0)
                ->get(['id', 'name', 'sku']);
                
            $result = [
                'category_id' => $categoryId,
                'category_name' => $category->name,
                'parent_id' => $category->parent_id,
                'direct_products' => $directProducts->count(),
                'products' => $directProducts->toArray()
            ];
            
            // If no direct products and this is a subcategory, try parent
            if ($directProducts->count() == 0 && $category->parent_id > 0) {
                $parentProducts = \App\Product::where('business_id', $business_id)
                    ->where('category_id', $category->parent_id)
                    ->where('is_inactive', 0)
                    ->get(['id', 'name', 'sku']);
                    
                $result['parent_products'] = $parentProducts->count();
                $result['showing_parent_products'] = true;
                $result['products'] = $parentProducts->toArray();
                $result['message'] = "Showing products from parent category";
            }
            
            return response()->json($result);
            
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()]);
        }
    })->name('test-subcategory-fix');
    
    // Working subcategory products route
    Route::get('/category-tree/products-simple', [\App\Http\Controllers\CategoryTreeSimpleController::class, 'getCategoryProductsSimple'])->name('category-tree.products-simple');
    Route::get('/category-tree/debug', function() {
        $business_id = request()->session()->get('user.business_id');
        $categories = \App\Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->whereNull('deleted_at')
            ->where('parent_id', 0) // Only root categories
            ->with(['children' => function($query) use ($business_id) {
                $query->where('business_id', $business_id)
                      ->with(['children' => function($subQuery) use ($business_id) {
                          $subQuery->where('business_id', $business_id)
                                   ->with(['children', 'products'])
                                   ->withCount('products')
                                   ->orderBy('name');
                      }])
                      ->withCount('products')
                      ->orderBy('name');
            }])
            ->withCount('products')
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'business_id' => $business_id,
            'total_root_categories' => $categories->count(),
            'categories' => $categories->map(function($cat) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'parent_id' => $cat->parent_id,
                    'children_count' => $cat->children ? $cat->children->count() : 0,
                    'products_count' => $cat->products_count,
                    'children' => $cat->children ? $cat->children->map(function($child) {
                        return [
                            'id' => $child->id,
                            'name' => $child->name,
                            'children_count' => $child->children ? $child->children->count() : 0,
                            'products_count' => $child->products_count
                        ];
                    }) : []
                ];
            })
        ]);
    })->name('category-tree.debug');
    
    Route::get('/category-tree/debug-products', function() {
        $business_id = request()->session()->get('user.business_id');
        
        // Get sample products with their category info
        $products = \App\Product::where('business_id', $business_id)
            ->where('is_inactive', 0)
            ->with(['category'])
            ->take(10)
            ->get();
            
        return response()->json([
            'business_id' => $business_id,
            'sample_products' => $products->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category ? $product->category->name : 'No Category',
                    'category_parent_id' => $product->category ? $product->category->parent_id : null
                ];
            })
        ]);
    })->name('category-tree.debug-products');

    Route::get('/toggle-subscription/{id}', [SellPosController::class, 'toggleRecurringInvoices']);
    Route::post('/sells/pos/get-types-of-service-details', [SellPosController::class, 'getTypesOfServiceDetails']);
    Route::get('/sells/subscriptions', [SellPosController::class, 'listSubscriptions']);
    Route::get('/sells/duplicate/{id}', [SellController::class, 'duplicateSell']);
    Route::get('/sells/drafts', [SellController::class, 'getDrafts']);
    Route::get('/sells/convert-to-draft/{id}', [SellPosController::class, 'convertToInvoice']);
    Route::get('/sells/convert-to-proforma/{id}', [SellPosController::class, 'convertToProforma']);
    Route::post('/sells/create-tax-invoice/{id}', [SellPosController::class, 'createTaxInvoiceFromQuotation']);
    Route::post('/sells/create-billing-receive/{id}', [SellPosController::class, 'createBillingReceiveFromTaxInvoice'])->name('sells.create-billing-receive');
    Route::get('/sells/quotations', [SellController::class, 'getQuotations']);
    Route::get('/sells/draft-dt', [SellController::class, 'getDraftDatables']);
    Route::get('/sells/summary-sales', [SellController::class, 'summarySales'])->name('sells.summary-sales');
    Route::get('/sells/summary-sales-data', [SellController::class, 'getSummarySalesData'])->name('sells.summary-sales-data');
    Route::get('/sells/sales-summary-stats', [SellController::class, 'getSalesSummaryStats'])->name('sells.sales-summary-stats');
    Route::get('/sells/export-summary-sales', [SellController::class, 'exportSummarySales'])->name('sells.export-summary-sales');
    Route::get('/api/get-related-ipay/{id}', [SellController::class, 'getRelatedIpay'])->name('api.get-related-ipay');
    Route::get('/api/get-related-vt/{id}', [SellController::class, 'getRelatedVt'])->name('api.get-related-vt');
    Route::resource('sells', SellController::class)->except(['show']);
    Route::get('/sells/copy-quotation/{id}', [SellPosController::class, 'copyQuotation']);

    Route::post('/import-purchase-products', [PurchaseController::class, 'importPurchaseProducts']);
    Route::post('/purchases/update-status', [PurchaseController::class, 'updateStatus']);
    Route::get('/purchases/get_products', [PurchaseController::class, 'getProducts']);
    Route::get('/purchases/get_suppliers', [PurchaseController::class, 'getSuppliers']);
    Route::post('/purchases/get_purchase_entry_row', [PurchaseController::class, 'getPurchaseEntryRow']);
    Route::post('/purchases/check_ref_number', [PurchaseController::class, 'checkRefNumber']);
    Route::resource('purchases', PurchaseController::class)->except(['show']);

    Route::get('/import-sales', [ImportSalesController::class, 'index']);
    Route::post('/import-sales/preview', [ImportSalesController::class, 'preview']);
    Route::post('/import-sales', [ImportSalesController::class, 'import']);
    Route::get('/revert-sale-import/{batch}', [ImportSalesController::class, 'revertSaleImport']);

    Route::get('/sells/pos/get_product_row/{variation_id}/{location_id}', [SellPosController::class, 'getProductRow']);
    Route::post('/sells/pos/get_payment_row', [SellPosController::class, 'getPaymentRow']);
    Route::post('/sells/pos/get-reward-details', [SellPosController::class, 'getRewardDetails']);
    Route::get('/sells/pos/get-recent-transactions', [SellPosController::class, 'getRecentTransactions']);
    Route::get('/sells/pos/get-product-suggestion', [SellPosController::class, 'getProductSuggestion']);
    Route::get('/sells/pos/get-featured-products/{location_id}', [SellPosController::class, 'getFeaturedProducts']);
    Route::get('/reset-mapping', [SellController::class, 'resetMapping']);

    Route::resource('pos', SellPosController::class);

    Route::resource('roles', RoleController::class);

    Route::resource('users', ManageUserController::class);

    Route::resource('group-taxes', GroupTaxController::class);

    Route::get('/barcodes/set_default/{id}', [BarcodeController::class, 'setDefault']);
    Route::resource('barcodes', BarcodeController::class);

    //Invoice schemes..
    Route::get('/invoice-schemes/set_default/{id}', [InvoiceSchemeController::class, 'setDefault']);
    Route::resource('invoice-schemes', InvoiceSchemeController::class);

    //Print Labels
    Route::get('/labels/show', [LabelsController::class, 'show']);
    Route::get('/labels/add-product-row', [LabelsController::class, 'addProductRow']);
    Route::get('/labels/preview', [LabelsController::class, 'preview']);

    //Reports...
    Route::get('/reports/gst-purchase-report', [ReportController::class, 'gstPurchaseReport']);
    Route::get('/reports/gst-sales-report', [ReportController::class, 'gstSalesReport']);
    Route::get('/reports/get-stock-by-sell-price', [ReportController::class, 'getStockBySellingPrice']);
    Route::get('/reports/purchase-report', [ReportController::class, 'purchaseReport']);
    Route::get('/reports/sale-report', [ReportController::class, 'saleReport']);
    Route::get('/reports/service-staff-report', [ReportController::class, 'getServiceStaffReport']);
    Route::get('/reports/service-staff-line-orders', [ReportController::class, 'serviceStaffLineOrders']);
    Route::get('/reports/table-report', [ReportController::class, 'getTableReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'getProfitLoss']);
    Route::get('/reports/get-opening-stock', [ReportController::class, 'getOpeningStock']);
    Route::get('/reports/purchase-sell', [ReportController::class, 'getPurchaseSell']);
    Route::get('/reports/customer-supplier', [ReportController::class, 'getCustomerSuppliers']);
    Route::get('/reports/stock-report', [ReportController::class, 'getStockReport']);
    Route::get('/reports/stock-details', [ReportController::class, 'getStockDetails']);
    Route::get('/reports/tax-report', [ReportController::class, 'getTaxReport']);
    Route::get('/reports/tax-details', [ReportController::class, 'getTaxDetails']);
    Route::get('/reports/trending-products', [ReportController::class, 'getTrendingProducts']);
    Route::get('/reports/expense-report', [ReportController::class, 'getExpenseReport']);
    Route::get('/reports/stock-adjustment-report', [ReportController::class, 'getStockAdjustmentReport']);
    Route::get('/reports/register-report', [ReportController::class, 'getRegisterReport']);
    Route::get('/reports/sales-representative-report', [ReportController::class, 'getSalesRepresentativeReport']);
    Route::get('/reports/sales-representative-total-expense', [ReportController::class, 'getSalesRepresentativeTotalExpense']);
    Route::get('/reports/sales-representative-total-sell', [ReportController::class, 'getSalesRepresentativeTotalSell']);
    Route::get('/reports/sales-representative-total-commission', [ReportController::class, 'getSalesRepresentativeTotalCommission']);
    Route::get('/reports/stock-expiry', [ReportController::class, 'getStockExpiryReport']);
    Route::get('/reports/stock-expiry-edit-modal/{purchase_line_id}', [ReportController::class, 'getStockExpiryReportEditModal']);
    Route::post('/reports/stock-expiry-update', [ReportController::class, 'updateStockExpiryReport'])->name('updateStockExpiryReport');
    Route::get('/reports/customer-group', [ReportController::class, 'getCustomerGroup']);
    Route::get('/reports/product-purchase-report', [ReportController::class, 'getproductPurchaseReport']);
    Route::get('/reports/product-sell-grouped-by', [ReportController::class, 'productSellReportBy']);
    Route::get('/reports/product-sell-report', [ReportController::class, 'getproductSellReport']);
    Route::get('/reports/product-sell-report-with-purchase', [ReportController::class, 'getproductSellReportWithPurchase']);
    Route::get('/reports/product-sell-grouped-report', [ReportController::class, 'getproductSellGroupedReport']);
    Route::get('/reports/lot-report', [ReportController::class, 'getLotReport']);
    Route::get('/reports/purchase-payment-report', [ReportController::class, 'purchasePaymentReport']);
    Route::get('/reports/sell-payment-report', [ReportController::class, 'sellPaymentReport']);
    Route::get('/reports/sell-payment-report-monthly-yearly', [ReportController::class, 'sellPaymentReportMonthlyYearly']);
    Route::get('/reports/sell-payment-report-monthly-yearly/export-daily', [ReportController::class, 'sellPaymentReportMonthlyYearlyExportDaily']);
    Route::get('/reports/sell-payment-report-monthly-yearly/export-monthly', [ReportController::class, 'sellPaymentReportMonthlyYearlyExportMonthly']);
    Route::get('/reports/product-stock-details', [ReportController::class, 'productStockDetails']);
    Route::get('/reports/adjust-product-stock', [ReportController::class, 'adjustProductStock']);
    Route::get('/reports/get-profit/{by?}', [ReportController::class, 'getProfit']);
    Route::get('/reports/items-report', [ReportController::class, 'itemsReport']);
    Route::get('/reports/get-stock-value', [ReportController::class, 'getStockValue']);

    Route::get('business-location/activate-deactivate/{location_id}', [BusinessLocationController::class, 'activateDeactivateLocation']);

    //Business Location Settings...
    Route::prefix('business-location/{location_id}')->name('location.')->group(function () {
        Route::get('settings', [LocationSettingsController::class, 'index'])->name('settings');
        Route::post('settings', [LocationSettingsController::class, 'updateSettings'])->name('settings_update');
    });

    //Business Locations...
    Route::post('business-location/check-location-id', [BusinessLocationController::class, 'checkLocationId']);
    Route::resource('business-location', BusinessLocationController::class);

    //Invoice layouts..
    Route::resource('invoice-layouts', InvoiceLayoutController::class);

    Route::post('get-expense-sub-categories', [ExpenseCategoryController::class, 'getSubCategories']);

    //Expense Categories...
    Route::resource('expense-categories', ExpenseCategoryController::class);

    //Expenses...
    Route::resource('expenses', ExpenseController::class);

    //Transaction payments...
    // Route::get('/payments/opening-balance/{contact_id}', 'TransactionPaymentController@getOpeningBalancePayments');
    Route::get('/payments/show-child-payments/{payment_id}', [TransactionPaymentController::class, 'showChildPayments']);
    Route::get('/payments/view-payment/{payment_id}', [TransactionPaymentController::class, 'viewPayment']);
    Route::get('/payments/add_payment/{transaction_id}', [TransactionPaymentController::class, 'addPayment']);
    Route::get('/payments/pay-contact-due/{contact_id}', [TransactionPaymentController::class, 'getPayContactDue']);
    Route::post('/payments/pay-contact-due', [TransactionPaymentController::class, 'postPayContactDue']);
    Route::resource('payments', TransactionPaymentController::class);

    //Printers...
    Route::resource('printers', PrinterController::class);

    Route::get('/stock-adjustments/remove-expired-stock/{purchase_line_id}', [StockAdjustmentController::class, 'removeExpiredStock']);
    Route::post('/stock-adjustments/get_product_row', [StockAdjustmentController::class, 'getProductRow']);
    Route::resource('stock-adjustments', StockAdjustmentController::class);

    Route::get('/cash-register/register-details', [CashRegisterController::class, 'getRegisterDetails']);
    Route::get('/cash-register/close-register/{id?}', [CashRegisterController::class, 'getCloseRegister']);
    Route::post('/cash-register/close-register', [CashRegisterController::class, 'postCloseRegister']);
    Route::resource('cash-register', CashRegisterController::class);

    //Import products
    Route::get('/import-products', [ImportProductsController::class, 'index']);
    Route::post('/import-products/store', [ImportProductsController::class, 'store']);

    //Sales Commission Agent
    Route::resource('sales-commission-agents', SalesCommissionAgentController::class);

    //Stock Transfer
    Route::get('stock-transfers/print/{id}', [StockTransferController::class, 'printInvoice']);
    Route::post('stock-transfers/update-status/{id}', [StockTransferController::class, 'updateStatus']);
    Route::resource('stock-transfers', StockTransferController::class);

    Route::get('/opening-stock/add/{product_id}', [OpeningStockController::class, 'add']);
    Route::post('/opening-stock/save', [OpeningStockController::class, 'save']);

    //Customer Groups
    Route::resource('customer-group', CustomerGroupController::class);

    //Import opening stock
    Route::get('/import-opening-stock', [ImportOpeningStockController::class, 'index']);
    Route::post('/import-opening-stock/store', [ImportOpeningStockController::class, 'store']);

    //Sell return
    Route::get('validate-invoice-to-return/{invoice_no}', [SellReturnController::class, 'validateInvoiceToReturn']);
    // service staff replacement
    Route::get('validate-invoice-to-service-staff-replacement/{invoice_no}', [SellPosController::class, 'validateInvoiceToServiceStaffReplacement']);
    Route::put('change-service-staff/{id}', [SellPosController::class, 'change_service_staff'])->name('change_service_staff');

    Route::resource('sell-return', SellReturnController::class);
    Route::get('sell-return/get-product-row', [SellReturnController::class, 'getProductRow']);
    Route::get('/sell-return/print/{id}', [SellReturnController::class, 'printInvoice']);
    Route::get('/sell-return/add/{id}', [SellReturnController::class, 'add']);

    //Backup
    Route::get('backup/download/{file_name}', [BackUpController::class, 'download']);
    Route::get('backup/{id}/delete', [BackUpController::class, 'delete'])->name('delete_backup');
    Route::resource('backup', BackUpController::class)->only('index', 'create', 'store');

    Route::get('selling-price-group/activate-deactivate/{id}', [SellingPriceGroupController::class, 'activateDeactivate']);
    Route::get('update-product-price', [SellingPriceGroupController::class, 'updateProductPrice'])->name('update-product-price');
    Route::get('export-product-price', [SellingPriceGroupController::class, 'export']);
    Route::post('import-product-price', [SellingPriceGroupController::class, 'import']);

    Route::resource('selling-price-group', SellingPriceGroupController::class);

    Route::resource('notification-templates', NotificationTemplateController::class)->only(['index', 'store']);
    Route::get('notification/get-template/{transaction_id}/{template_for}', [NotificationController::class, 'getTemplate']);
    Route::post('notification/send', [NotificationController::class, 'send']);

    Route::post('/purchase-return/update', [CombinedPurchaseReturnController::class, 'update']);
    Route::get('/purchase-return/edit/{id}', [CombinedPurchaseReturnController::class, 'edit']);
    Route::post('/purchase-return/save', [CombinedPurchaseReturnController::class, 'save']);
    Route::post('/purchase-return/get_product_row', [CombinedPurchaseReturnController::class, 'getProductRow']);
    Route::get('/purchase-return/create', [CombinedPurchaseReturnController::class, 'create']);
    Route::get('/purchase-return/add/{id}', [PurchaseReturnController::class, 'add']);
    Route::resource('/purchase-return', PurchaseReturnController::class)->except('create');

    Route::get('/discount/activate/{id}', [DiscountController::class, 'activate']);
    Route::post('/discount/mass-deactivate', [DiscountController::class, 'massDeactivate']);
    Route::resource('discount', DiscountController::class);

    Route::prefix('account')->group(function () {
        Route::resource('/account', AccountController::class);
        Route::get('/fund-transfer/{id}', [AccountController::class, 'getFundTransfer']);
        Route::post('/fund-transfer', [AccountController::class, 'postFundTransfer']);
        Route::get('/deposit/{id}', [AccountController::class, 'getDeposit']);
        Route::post('/deposit', [AccountController::class, 'postDeposit']);
        Route::get('/close/{id}', [AccountController::class, 'close']);
        Route::get('/activate/{id}', [AccountController::class, 'activate']);
        Route::get('/delete-account-transaction/{id}', [AccountController::class, 'destroyAccountTransaction']);
        Route::get('/edit-account-transaction/{id}', [AccountController::class, 'editAccountTransaction']);
        Route::post('/update-account-transaction/{id}', [AccountController::class, 'updateAccountTransaction']);
        Route::get('/get-account-balance/{id}', [AccountController::class, 'getAccountBalance']);
        Route::get('/balance-sheet', [AccountReportsController::class, 'balanceSheet']);
        Route::get('/trial-balance', [AccountReportsController::class, 'trialBalance']);
        Route::get('/payment-account-report', [AccountReportsController::class, 'paymentAccountReport']);
        Route::get('/link-account/{id}', [AccountReportsController::class, 'getLinkAccount']);
        Route::post('/link-account', [AccountReportsController::class, 'postLinkAccount']);
        Route::get('/cash-flow', [AccountController::class, 'cashFlow']);
    });

    Route::resource('account-types', AccountTypeController::class);

    //Restaurant module
    Route::prefix('modules')->group(function () {
        Route::resource('tables', Restaurant\TableController::class);
        Route::resource('modifiers', Restaurant\ModifierSetsController::class);

        //Map modifier to products
        Route::get('/product-modifiers/{id}/edit', [Restaurant\ProductModifierSetController::class, 'edit']);
        Route::post('/product-modifiers/{id}/update', [Restaurant\ProductModifierSetController::class, 'update']);
        Route::get('/product-modifiers/product-row/{product_id}', [Restaurant\ProductModifierSetController::class, 'product_row']);

        Route::get('/add-selected-modifiers', [Restaurant\ProductModifierSetController::class, 'add_selected_modifiers']);

        Route::get('/kitchen', [Restaurant\KitchenController::class, 'index']);
        Route::get('/kitchen/mark-as-cooked/{id}', [Restaurant\KitchenController::class, 'markAsCooked']);
        Route::post('/refresh-orders-list', [Restaurant\KitchenController::class, 'refreshOrdersList']);
        Route::post('/refresh-line-orders-list', [Restaurant\KitchenController::class, 'refreshLineOrdersList']);

        Route::get('/orders', [Restaurant\OrderController::class, 'index']);
        Route::get('/orders/mark-as-served/{id}', [Restaurant\OrderController::class, 'markAsServed']);
        Route::get('/data/get-pos-details', [Restaurant\DataController::class, 'getPosDetails']);
        Route::get('/data/check-staff-pin', [Restaurant\DataController::class, 'checkStaffPin']);
        Route::get('/orders/mark-line-order-as-served/{id}', [Restaurant\OrderController::class, 'markLineOrderAsServed']);
        Route::get('/print-line-order', [Restaurant\OrderController::class, 'printLineOrder']);
    });

    Route::get('bookings/get-todays-bookings', [Restaurant\BookingController::class, 'getTodaysBookings']);
    Route::resource('bookings', Restaurant\BookingController::class);

    Route::resource('types-of-service', TypesOfServiceController::class);
    Route::get('sells/edit-shipping/{id}', [SellController::class, 'editShipping']);
    Route::put('sells/update-shipping/{id}', [SellController::class, 'updateShipping']);
    Route::get('shipments', [SellController::class, 'shipments']);

    Route::post('upload-module', [Install\ModulesController::class, 'uploadModule']);
    Route::delete('manage-modules/destroy/{module_name}', [Install\ModulesController::class, 'destroy']);
    Route::resource('manage-modules', Install\ModulesController::class)
        ->only(['index', 'update']);
    Route::get('regenerate', [Install\ModulesController::class, 'regenerate']);

    Route::resource('warranties', WarrantyController::class);
    Route::get('warranty-check', [App\Http\Controllers\WarrantyCheckController::class, 'index'])->name('warranty-check.index');
    Route::get('warranty-check/calendar', [App\Http\Controllers\WarrantyCheckController::class, 'calendar'])->name('warranty-check.calendar');
    Route::get('warranty-check/products', [App\Http\Controllers\WarrantyCheckController::class, 'productData'])->name('warranty-check.products');
    Route::get('warranty-check/sold-products', [App\Http\Controllers\WarrantyCheckController::class, 'soldProductData'])->name('warranty-check.sold-products');
    Route::get('warranty-check/product/{id}/edit', [App\Http\Controllers\WarrantyCheckController::class, 'editProduct'])->name('warranty-check.product.edit');
    Route::post('warranty-check/product/{id}', [App\Http\Controllers\WarrantyCheckController::class, 'updateProduct'])->name('warranty-check.product.update');
    Route::get('warranty-check/service/{id}/edit', [App\Http\Controllers\WarrantyCheckController::class, 'editService'])->name('warranty-check.service.edit');
    Route::post('warranty-check/service/{id}', [App\Http\Controllers\WarrantyCheckController::class, 'updateService'])->name('warranty-check.service.update');

    Route::resource('dashboard-configurator', DashboardConfiguratorController::class)
    ->only(['edit', 'update']);

    Route::get('view-media/{model_id}', [SellController::class, 'viewMedia']);

    //common controller for document & note
    Route::get('get-document-note-page', [DocumentAndNoteController::class, 'getDocAndNoteIndexPage']);
    Route::post('post-document-upload', [DocumentAndNoteController::class, 'postMedia']);
    Route::resource('note-documents', DocumentAndNoteController::class);
    Route::resource('purchase-order', PurchaseOrderController::class);
    Route::get('get-purchase-orders/{contact_id}', [PurchaseOrderController::class, 'getPurchaseOrders']);
    Route::get('get-purchase-order-lines/{purchase_order_id}', [PurchaseController::class, 'getPurchaseOrderLines']);
    Route::get('edit-purchase-orders/{id}/status', [PurchaseOrderController::class, 'getEditPurchaseOrderStatus']);
    Route::put('update-purchase-orders/{id}/status', [PurchaseOrderController::class, 'postEditPurchaseOrderStatus']);
    Route::resource('sales-order', SalesOrderController::class)->only(['index']);
    Route::get('get-sales-orders/{customer_id}', [SalesOrderController::class, 'getSalesOrders']);
    Route::get('get-sales-order-lines', [SellPosController::class, 'getSalesOrderLines']);
    Route::get('edit-sales-orders/{id}/status', [SalesOrderController::class, 'getEditSalesOrderStatus']);
    Route::put('update-sales-orders/{id}/status', [SalesOrderController::class, 'postEditSalesOrderStatus']);
    Route::get('reports/activity-log', [ReportController::class, 'activityLog']);
    Route::get('user-location/{latlng}', [HomeController::class, 'getUserLocation']);

    // Migration Update Routes
    Route::get('migrate-update-data', [App\Http\Controllers\MigrationUpdateController::class, 'index'])->name('migrate-update-data');
    Route::get('migrate-update-data/run', [App\Http\Controllers\MigrationUpdateController::class, 'runMigration'])->name('migrate-update-data.run');
    Route::get('migrate-update-data/products-only', [App\Http\Controllers\MigrationUpdateController::class, 'migrateProductsOnlyWithStockCompare'])->name('migrate-update-data.products-only');
    Route::get('migrate-update-data/clean', [App\Http\Controllers\MigrationUpdateController::class, 'cleanMigratedData'])->name('migrate-update-data.clean');
    Route::post('migrate-update-data/delete-all-bills', [App\Http\Controllers\MigrationUpdateController::class, 'deleteAllBills'])->name('migrate-update-data.delete-all-bills');
    Route::post('migrate-update-data/delete-all-bills-all-businesses', [App\Http\Controllers\MigrationUpdateController::class, 'deleteAllBillsAllBusinesses'])->name('migrate-update-data.delete-all-bills-all-businesses');
    Route::get('migrate-update-data/sell-lines', [App\Http\Controllers\MigrationUpdateController::class, 'migrateSellLines'])->name('migrate-update-data.sell-lines');
    Route::get('migrate-update-data/product-images', [App\Http\Controllers\MigrationUpdateController::class, 'migrateProductImages'])->name('migrate-update-data.product-images');
    Route::get('migrate-update-data/stock', [App\Http\Controllers\MigrationUpdateController::class, 'migrateStock'])->name('migrate-update-data.stock');
    Route::get('migrate-update-data/quotations', [App\Http\Controllers\MigrationUpdateController::class, 'migrateQuotations'])->name('migrate-update-data.quotations');
    Route::get('migrate-update-data/fix-tax', [App\Http\Controllers\MigrationUpdateController::class, 'fixTaxData'])->name('migrate-update-data.fix-tax');
    Route::get('migrate-update-data/units', [App\Http\Controllers\MigrationUpdateController::class, 'migrateUnits'])->name('migrate-update-data.units');
    Route::get('migrate-update-data/brands', [App\Http\Controllers\MigrationUpdateController::class, 'migrateBrands'])->name('migrate-update-data.brands');
    Route::get('migrate-update-data/set-all-brand', [App\Http\Controllers\MigrationUpdateController::class, 'setAllProductsBrand'])->name('migrate-update-data.set-all-brand');
    Route::get('migrate-update-data/second-name', [App\Http\Controllers\MigrationUpdateController::class, 'migrateSecondName'])->name('migrate-update-data.second-name');
    Route::get('migrate-update-data/product-units-brands', [App\Http\Controllers\MigrationUpdateController::class, 'migrateProductUnitsBrands'])->name('migrate-update-data.product-units-brands');
    Route::get('migrate-update-data/fix-contact-type', [App\Http\Controllers\MigrationUpdateController::class, 'fixContactType'])->name('migrate-update-data.fix-contact-type');
    Route::get('migrate-update-data/fix-sync-doc-receiver', [App\Http\Controllers\MigrationUpdateController::class, 'fixSyncedDocumentAndReceiver'])->name('migrate-update-data.fix-sync-doc-receiver');
    Route::get('migrate-update-data/fix-np-duplicates', [App\Http\Controllers\MigrationUpdateController::class, 'fixNpDuplicateReferences'])->name('migrate-update-data.fix-np-duplicates');

    // Bidirectional Auto-Sync Routes
    Route::get('migrate-update-data/sync-setup', [App\Http\Controllers\MigrationUpdateController::class, 'setupSync'])->name('migrate-update-data.sync-setup');
    Route::get('migrate-update-data/sync-run', [App\Http\Controllers\MigrationUpdateController::class, 'runSync'])->name('migrate-update-data.sync-run');
    Route::get('migrate-update-data/sync-quotation', [App\Http\Controllers\MigrationUpdateController::class, 'runQuotationSync'])->name('migrate-update-data.sync-quotation');
    Route::get('migrate-update-data/sync-payment-updates', [App\Http\Controllers\MigrationUpdateController::class, 'runPaymentSync'])->name('migrate-update-data.sync-payment-updates');
    Route::get('migrate-update-data/sync-status', [App\Http\Controllers\MigrationUpdateController::class, 'syncStatus'])->name('migrate-update-data.sync-status');
});

// Route::middleware(['EcomApi'])->prefix('api/ecom')->group(function () {
//     Route::get('products/{id?}', [ProductController::class, 'getProductsApi']);
//     Route::get('categories', [CategoryController::class, 'getCategoriesApi']);
//     Route::get('brands', [BrandController::class, 'getBrandsApi']);
//     Route::post('customers', [ContactController::class, 'postCustomersApi']);
//     Route::get('settings', [BusinessController::class, 'getEcomSettings']);
//     Route::get('variations', [ProductController::class, 'getVariationsApi']);
//     Route::post('orders', [SellPosController::class, 'placeOrdersApi']);
// });

//common route
Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('user.logout');
});

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone'])->group(function () {
    Route::get('/load-more-notifications', [HomeController::class, 'loadMoreNotifications']);
    Route::get('/get-total-unread', [HomeController::class, 'getTotalUnreadNotifications']);
    Route::get('/purchases/print/{id}', [PurchaseController::class, 'printInvoice']);
    Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('/download-purchase-order/{id}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseOrder.downloadPdf');
    Route::get('/sells/{id}', [SellController::class, 'show']);
    Route::get('/sells/{id}/export-modal-excel', [SellController::class, 'exportModalExcel'])->name('sells.export-modal-excel');
    Route::get('/sells/{transaction_id}/print', [SellPosController::class, 'printInvoice'])->name('sell.printInvoice');
    Route::get('/download-sells/{transaction_id}/pdf', [SellPosController::class, 'downloadPdf'])->name('sell.downloadPdf');
    Route::get('/download-quotation/{id}/pdf', [SellPosController::class, 'downloadQuotationPdf'])
        ->name('quotation.downloadPdf');
    Route::get('/quotations/{id}/pdf-print', [SellPosController::class, 'quotationsPdfPrint'])
        ->name('quotations.pdfprint');
    
    // Document workflow routes
    Route::post('/quotations/{id}/create-proforma', [SellPosController::class, 'createProforma'])
        ->name('quotations.createProforma');
    Route::post('/proforma/{id}/create-final-bill', [SellPosController::class, 'createFinalBill'])
        ->name('proforma.createFinalBill');
    
    // Node.js PDF Generator Routes
    Route::get('/quotations/{id}/pdf-print-nodejs', function($id) {
        require_once base_path('pdf-generator-helper.php');
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        // Check if service is running
        if (!$pdfGenerator->isServiceRunning()) {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => 'PDF Generator service is not running. Please start the Node.js server with: npm start'
                ], 503);
            } else {
                abort(503, 'PDF Generator service is not running. Please start the Node.js server.');
            }
        }
        
        // Generate PDF
        $result = $pdfGenerator->generateQuotationPDF($id);
        
        if ($result['success']) {
            // Extract invoice number from filename for simple URL
            $filename = $result['clean_filename'];
            $invoice_number = str_replace('.pdf', '', $filename);
            
            // Check if this is an AJAX request or wants JSON
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                // Return JSON response for JavaScript handling
                return response()->json([
                    'success' => true,
                    'pdf_url' => url('/' . $invoice_number),
                    'filename' => $result['filename'],
                    'download_url' => url('download-pdf/' . $result['clean_filename']),
                    'message' => 'PDF generated successfully. Opening in new tab...'
                ]);
            } else {
                // Direct browser access - redirect to PDF URL
                return redirect('/' . $invoice_number);
            }
        } else {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => $result['error']
                ], 500);
            } else {
                abort(500, $result['error']);
            }
        }
    })->name('quotations.pdfprint.nodejs');

    Route::get('/tax-invoice/{id}/pdf-print-nodejs', function($id) {
        require_once base_path('pdf-generator-helper.php');
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        // Check if service is running
        if (!$pdfGenerator->isServiceRunning()) {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => 'PDF Generator service is not running. Please start the Node.js server with: npm start'
                ], 503);
            } else {
                abort(503, 'PDF Generator service is not running. Please start the Node.js server.');
            }
        }
        
        // Generate Tax Invoice PDF
        $result = $pdfGenerator->generateTaxInvoicePDF($id);
        
        if ($result['success']) {
            // Extract invoice number from filename for simple URL
            $filename = $result['clean_filename'];
            $invoice_number = str_replace('.pdf', '', $filename);
            
            // Check if this is an AJAX request or wants JSON
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                // Return JSON response for JavaScript handling
                return response()->json([
                    'success' => true,
                    'pdf_url' => url('/' . $invoice_number),
                    'filename' => $result['filename'],
                    'download_url' => url('download-pdf/' . $result['clean_filename']),
                    'message' => 'PDF generated successfully. Opening in new tab...'
                ]);
            } else {
                // Direct browser access - redirect to PDF URL
                return redirect('/' . $invoice_number);
            }
        } else {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => $result['error']
                ], 500);
            } else {
                abort(500, $result['error']);
            }
        }
    })->name('tax-invoice.pdfprint.nodejs');

    Route::get('/billing-receipt/{id}/pdf-print-nodejs', function($id) {
        require_once base_path('pdf-generator-helper.php');
        
        $pdfGenerator = new PDFGeneratorHelper();
        
        // Check if service is running
        if (!$pdfGenerator->isServiceRunning()) {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => 'PDF Generator service is not running. Please start the Node.js server with: npm start'
                ], 503);
            } else {
                // Direct browser access - show error page
                abort(503, 'PDF Generator service is not running. Please start the Node.js server.');
            }
        }
        
        // Generate Billing Receipt PDF
        $result = $pdfGenerator->generateBillingReceiptPDF($id);
        
        if ($result['success']) {
            // Extract invoice number from filename for simple URL
            $filename = $result['clean_filename'];
            $invoice_number = str_replace('.pdf', '', $filename);
            
            // Check if this is an AJAX request or wants JSON
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                // Return JSON response for JavaScript handling
                return response()->json([
                    'success' => true,
                    'pdf_url' => url('/' . $invoice_number),
                    'filename' => $result['filename'],
                    'download_url' => url('download-pdf/' . $result['clean_filename']),
                    'message' => 'PDF generated successfully. Opening in new tab...'
                ]);
            } else {
                // Direct browser access - redirect to PDF URL
                return redirect('/' . $invoice_number);
            }
        } else {
            if (request()->wantsJson() || request()->header('Accept') === 'application/json') {
                return response()->json([
                    'error' => $result['error']
                ], 500);
            } else {
                abort(500, $result['error']);
            }
        }
    })->name('billing-receipt.pdfprint.nodejs');
    
    Route::get('/download-packing-list/{id}/pdf', [SellPosController::class, 'downloadPackingListPdf'])
        ->name('packing.downloadPdf');
    Route::get('/sells/invoice-url/{id}', [SellPosController::class, 'showInvoiceUrl']);
    Route::get('/show-notification/{id}', [HomeController::class, 'showNotification']);
});

// Simple PDF Routes - serve PDFs with invoice number URLs using login authentication

// Simple PDF Routes - serve PDFs with invoice number URLs using login authentication
Route::get('/{invoice_number}', function($invoice_number) {
    // Match invoice number patterns: vt2025-0922, ipay2025-10044, quote2025-3015, etc.
    if (!preg_match('/^(vt|ipay|quote)?[0-9]{4}-[0-9]+$/i', $invoice_number)) {
        abort(404);
    }
    
    require_once base_path('pdf-generator-helper.php');
    
    $pdfGenerator = new PDFGeneratorHelper();
    
    // Check if service is running
    if (!$pdfGenerator->isServiceRunning()) {
        return response()->json([
            'error' => 'PDF Generator service is not running. Please start the Node.js server.'
        ], 503);
    }
    
    // Call the Node.js service directly with the invoice number
    try {
        $nodeJsUrl = 'http://127.0.0.1:3000/' . $invoice_number;
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $nodeJsUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Execute request
        $pdfData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        
        curl_close($ch);
        
        if ($pdfData === false || $httpCode !== 200) {
            abort(404, 'PDF not found for invoice: ' . $invoice_number);
        }
        
        // Return the PDF data directly
        return response($pdfData)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $invoice_number . '.pdf"')
            ->header('X-Invoice-Number', $invoice_number)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
            
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Failed to generate PDF: ' . $e->getMessage()
        ], 500);
    }
    
})->where('invoice_number', '(vt|ipay|quote)?[0-9]{4}-[0-9]+');
