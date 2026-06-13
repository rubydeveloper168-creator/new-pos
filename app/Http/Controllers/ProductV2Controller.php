<?php

namespace App\Http\Controllers;

use App\Product;
use App\Category;
use App\Brands;
use App\Unit;
use App\TaxRate;
use App\BusinessLocation;
use App\PurchaseLine;
use App\VariationLocationDetails;
use App\Utils\ModuleUtil;
use App\Events\ProductsCreatedOrModified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductV2Controller extends Controller
{
    /**
     * All Utils instance.
     */
    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param ModuleUtil $moduleUtil
     * @return void
     */
    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    /**
     * Display a listing of the products with multi-level categories.
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        
        // Get all categories for filter dropdown (hierarchical)
        $all_categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        // Build hierarchical category structure
        $hierarchical_categories = $this->buildHierarchicalCategories($all_categories);

        // Get brands for filter dropdown
        $brands = Brands::where('business_id', $business_id)
            ->orderBy('name')
            ->get();

        $total_products_count = Product::where('business_id', $business_id)->count();

        return view('product_v2.index', compact(
            'hierarchical_categories',
            'brands',
            'total_products_count',
            'business_id'
        ));
    }

    /**
     * Public listing of products with multi-level categories.
     */
    public function publicIndex(Request $request)
    {
        $business_id = $this->resolvePublicBusinessId($request);

        $all_categories = Category::where('business_id', $business_id)
            ->where('category_type', 'product')
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        $hierarchical_categories = $this->buildHierarchicalCategories($all_categories);

        $brands = Brands::where('business_id', $business_id)
            ->orderBy('name')
            ->get();

        $total_products_count = Product::where('business_id', $business_id)->count();

        return view('product_v2.index', [
            'hierarchical_categories' => $hierarchical_categories,
            'brands' => $brands,
            'total_products_count' => $total_products_count,
            'business_id' => $business_id,
            'is_public' => true,
        ]);
    }

    /**
     * Build hierarchical category structure with indentation
     */
    private function buildHierarchicalCategories($categories, $parent_id = 0, $level = 0, $isLast = true, $prefix = '')
    {
        $result = [];
        $children = $categories->where('parent_id', $parent_id)->values();
        
        foreach ($children as $index => $category) {
            $isLastChild = ($index === $children->count() - 1);
            
            // Create tree structure
            if ($level === 0) {
                $display_prefix = '';
                $display_name = $category->name . ' (L1)';
            } else {
                $connector = $isLastChild ? '└── ' : '├── ';
                $display_prefix = $prefix . $connector;
                $display_name = $display_prefix . $category->name . ' (L' . ($level + 1) . ')';
            }
            
            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'display_name' => $display_name,
                'level' => $level,
                'parent_id' => $category->parent_id
            ];
            
            // Prepare prefix for children
            $childPrefix = $prefix;
            if ($level > 0) {
                $childPrefix .= $isLastChild ? '    ' : '│   ';
            }
            
            // Recursively get children
            $childCategories = $this->buildHierarchicalCategories($categories, $category->id, $level + 1, $isLastChild, $childPrefix);
            $result = array_merge($result, $childCategories);
        }
        
        return $result;
    }

    /**
     * Get all products with filtering for AJAX requests
     */
    public function getAllProducts(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $products = $this->buildProductQuery($request, $business_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->buildProductsResponse($request, $products);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform product for JSON response with stock and pricing data
     */
    private function transformProductForJson($product)
    {
        // Get stock information
        $total_stock = 0;
        $selling_price = 0;
        
        if ($product->variations && $product->variations->count() > 0) {
            foreach ($product->variations as $variation) {
                // Get stock from variation_location_details
                $stock_details = DB::table('variation_location_details')
                    ->where('variation_id', $variation->id)
                    ->sum('qty_available');
                $total_stock += $stock_details ?? 0;
                
                // Get selling price (use first variation's default selling price)
                if ($selling_price == 0) {
                    $selling_price = $variation->default_sell_price ?? 0;
                }
            }
        }
        
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'category_path' => $product->getCategoryPath(),
            'brand_name' => $product->brand ? $product->brand->name : null,
            'unit_name' => $product->unit ? $product->unit->actual_name : null,
            'image_url' => $product->image_url,
            'created_at' => $product->created_at->format('Y-m-d H:i:s'),
            'total_stock' => $total_stock,
            'selling_price' => number_format($selling_price, 2),
            'category_l1' => $product->categoryL1 ? $product->categoryL1->name : null,
            'category_l2' => $product->categoryL2 ? $product->categoryL2->name : null,
            'category_l3' => $product->categoryL3 ? $product->categoryL3->name : null,
            'category_l4' => $product->categoryL4 ? $product->categoryL4->name : null,
            'category_l5' => $product->categoryL5 ? $product->categoryL5->name : null,
        ];
    }

    /**
     * Get all subcategory IDs recursively
     */
    private function getAllSubcategoryIds($parent_id, $business_id)
    {
        $subcategories = Category::where('business_id', $business_id)
            ->where('parent_id', $parent_id)
            ->where('category_type', 'product')
            ->get();

        $ids = [];
        foreach ($subcategories as $subcategory) {
            $ids[] = $subcategory->id;
            // Recursively get subcategories
            $child_ids = $this->getAllSubcategoryIds($subcategory->id, $business_id);
            $ids = array_merge($ids, $child_ids);
        }

        return $ids;
    }

    /**
     * Show the form for creating a new product.
     */
    public function create()
    {
        $business_id = request()->session()->get('user.business_id');
        
        // Get L1 categories (parent_id = 0)
        $l1_categories = Category::where('business_id', $business_id)
            ->where('parent_id', 0)
            ->where('category_type', 'product')
            ->orderBy('name')
            ->get();

        $brands = Brands::where('business_id', $business_id)->orderBy('name')->get();
        $units = Unit::where('business_id', $business_id)->orderBy('actual_name')->get();
        $tax_rates = TaxRate::where('business_id', $business_id)->get();
        $locations = BusinessLocation::where('business_id', $business_id)->get();

        return view('product_v2.create', compact(
            'l1_categories', 'brands', 'units', 'tax_rates', 'locations'
        ));
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');
        $user_id = request()->session()->get('user.id');

        $request->validate([
            'name' => 'required|string|max:191',
            'sku' => 'required|string|max:191|unique:products,sku',
            'type' => 'required|in:single,variable,modifier,combo',
            'unit_id' => 'required|exists:units,id',
            'tax_type' => 'required|in:inclusive,exclusive',
        ]);

        try {
            DB::beginTransaction();

            // Debug: Log received category data
            \Log::info('Product V2 Store - Category Data Received:', [
                'category_l1_id' => $request->get('category_l1_id'),
                'category_l2_id' => $request->get('category_l2_id'),
                'category_l3_id' => $request->get('category_l3_id'),
                'category_l4_id' => $request->get('category_l4_id'),
                'category_l5_id' => $request->get('category_l5_id'),
                'all_request_data' => $request->all()
            ]);

            $product_data = $request->only([
                'name', 'sku', 'type', 'unit_id', 'brand_id', 'tax', 'tax_type',
                'enable_stock', 'alert_quantity', 'barcode_type', 'weight',
                'product_description', 'category_l1_id', 'category_l2_id',
                'category_l3_id', 'category_l4_id', 'category_l5_id',
                'enable_sr_no', 'not_for_selling', 'preparation_time_in_minutes'
            ]);

            // Debug: Log what will be saved
            \Log::info('Product V2 Store - Data to be saved:', [
                'product_data_categories' => [
                    'category_l1_id' => $product_data['category_l1_id'] ?? null,
                    'category_l2_id' => $product_data['category_l2_id'] ?? null,
                    'category_l3_id' => $product_data['category_l3_id'] ?? null,
                    'category_l4_id' => $product_data['category_l4_id'] ?? null,
                    'category_l5_id' => $product_data['category_l5_id'] ?? null,
                ]
            ]);

            $product_data['business_id'] = $business_id;
            $product_data['created_by'] = $user_id;

            // Convert checkbox values
            $product_data['enable_stock'] = $request->has('enable_stock') ? 1 : 0;
            $product_data['enable_sr_no'] = $request->has('enable_sr_no') ? 1 : 0;
            $product_data['not_for_selling'] = $request->has('not_for_selling') ? 1 : 0;

            // Ensure category hierarchy is properly maintained
            $product_data = $this->validateAndFixCategoryHierarchy($product_data, $business_id);

            // Handle image upload or gallery selection
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . $image->getClientOriginalName();
                $image->move(public_path('uploads/img'), $imageName);
                $product_data['image'] = $imageName;
            } elseif (!empty($request->gallery_image)) {
                // Use selected image from gallery
                $product_data['image'] = $request->gallery_image;
            }

            // Handle product brochure upload
            if ($request->hasFile('product_brochure')) {
                $brochure = $request->file('product_brochure');
                $brochureName = time() . '_brochure_' . $brochure->getClientOriginalName();
                $brochure->move(public_path('uploads/documents'), $brochureName);
                $product_data['product_brochure'] = $brochureName;
            }

            $product = Product::create($product_data);

            // Handle product locations
            if ($request->has('product_locations')) {
                $product->product_locations()->sync($request->product_locations);
            }

            // Handle product variations based on type
            if ($product->type == 'single') {
                // Create single variation
                $variation_data = [
                    'name' => 'DUMMY',
                    'product_id' => $product->id,
                    'sub_sku' => $product->sku,
                    'default_purchase_price' => $request->get('single_dpp', 0),
                    'dpp_inc_tax' => $request->get('single_dpp', 0),
                    'profit_percent' => $request->get('profit_percent', 25),
                    'default_sell_price' => $request->get('single_dsp', 0),
                    'sell_price_inc_tax' => $request->get('single_dsp', 0),
                ];

                $variation = \App\Variation::create($variation_data);

                // Create product variation
                \App\ProductVariation::create([
                    'variation_template_id' => null,
                    'product_id' => $product->id,
                    'name' => 'DUMMY'
                ]);
            }

            DB::commit();

            // Handle different submit types
            $submit_type = $request->get('submit_type', 'submit');
            
            if ($submit_type == 'submit_n_add_opening_stock') {
                return redirect()->route('opening-stock.add', ['product_id' => $product->id])
                    ->with('success', 'Product created successfully! Now add opening stock.');
            } elseif ($submit_type == 'save_n_add_another') {
                return redirect()->route('products-v2.create')
                    ->with('success', 'Product created successfully! Add another product.');
            } else {
                return redirect()->route('products-v2.index')
                    ->with('success', 'Product created successfully with multi-level categories!');
            }

        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating product: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        $product->load([
            'categoryL1', 'categoryL2', 'categoryL3', 'categoryL4', 'categoryL5',
            'brand', 'unit', 'variations', 'product_locations'
        ]);

        return view('product_v2.show', compact('product'));
    }

    /**
     * Show the form for editing the specified product.
     * Redirects to the original product edit page.
     */
    public function edit(Product $product)
    {
        // Redirect to the original product edit page
        return redirect()->route('products.edit', $product->id);
    }

    /**
     * Update the specified product in storage.
     * This method is not used since we redirect to the original product edit.
     */
    public function update(Request $request, Product $product)
    {
        // This method should not be called since we redirect to the original edit
        // But if it is called, redirect to the original product page
        return redirect()->route('products.show', $product->id);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('product.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $can_be_deleted = true;
                $error_msg = '';

                //Check if any purchase or transfer exists
                $count = PurchaseLine::join(
                    'transactions as T',
                    'purchase_lines.transaction_id',
                    '=',
                    'T.id'
                )
                                    ->whereIn('T.type', ['purchase'])
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->count();
                if ($count > 0) {
                    $can_be_deleted = false;
                    $error_msg = __('lang_v1.purchase_already_exist');
                } else {
                    //Check if any opening stock sold
                    $count = PurchaseLine::join(
                        'transactions as T',
                        'purchase_lines.transaction_id',
                        '=',
                        'T.id'
                     )
                                    ->where('T.type', 'opening_stock')
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_sold', '>', 0)
                                    ->count();
                    if ($count > 0) {
                        $can_be_deleted = false;
                        $error_msg = __('lang_v1.opening_stock_sold');
                    } else {
                        //Check if any stock is adjusted
                        $count = PurchaseLine::join(
                            'transactions as T',
                            'purchase_lines.transaction_id',
                            '=',
                            'T.id'
                        )
                                    ->where('T.business_id', $business_id)
                                    ->where('purchase_lines.product_id', $id)
                                    ->where('purchase_lines.quantity_adjusted', '>', 0)
                                    ->count();
                        if ($count > 0) {
                            $can_be_deleted = false;
                            $error_msg = __('lang_v1.stock_adjusted');
                        }
                    }
                }

                $product = Product::where('id', $id)
                                ->where('business_id', $business_id)
                                ->with('variations')
                                ->first();

                //Check if product is added as an ingredient of any recipe
                if ($this->moduleUtil->isModuleInstalled('Manufacturing')) {
                    $variation_ids = $product->variations->pluck('id');

                    $exists_as_ingredient = \Modules\Manufacturing\Entities\MfgRecipeIngredient::whereIn('variation_id', $variation_ids)
                        ->exists();
                    if ($exists_as_ingredient) {
                        $can_be_deleted = false;
                        $error_msg = __('manufacturing::lang.added_as_ingredient');
                    }
                }

                if ($can_be_deleted) {
                    if (! empty($product)) {
                        DB::beginTransaction();
                        //Delete variation location details
                        VariationLocationDetails::where('product_id', $id)
                                                ->delete();
                        $product->delete();
                        event(new ProductsCreatedOrModified($product, 'deleted'));
                        DB::commit();
                    }

                    $output = ['success' => true,
                        'msg' => __('lang_v1.product_delete_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => $error_msg,
                    ];
                }
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Display product details in modal
     */
    public function view($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $product = Product::where('business_id', $business_id)
                        ->with([
                            'categoryL1', 'categoryL2', 'categoryL3', 'categoryL4', 'categoryL5',
                            'brand', 'unit', 'category', 'sub_category', 'product_tax', 
                            'variations', 'variations.product_variation', 'variations.group_prices', 
                            'variations.media', 'product_locations', 'warranty', 'media'
                        ])
                        ->findOrFail($id);

            // Get rack details (if rack system is enabled)
            $rack_details = collect(); // Simplified for now
            
            // Get combo variations if it's a combo product
            $combo_variations = [];
            if ($product->type == 'combo') {
                // Simplified combo handling
                $combo_variations = [];
            }

            return view('product_v2.view-modal')->with(compact(
                'product',
                'rack_details',
                'combo_variations'
            ));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading product details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public: Display product details in modal.
     */
    public function publicView(Request $request, $id)
    {
        try {
            $business_id = $this->resolvePublicBusinessId($request);

            $product = Product::where('business_id', $business_id)
                        ->with([
                            'categoryL1', 'categoryL2', 'categoryL3', 'categoryL4', 'categoryL5',
                            'brand', 'unit', 'category', 'sub_category', 'product_tax',
                            'variations', 'variations.product_variation', 'variations.group_prices',
                            'variations.media', 'product_locations', 'warranty', 'media'
                        ])
                        ->findOrFail($id);

            $rack_details = collect();
            $combo_variations = [];

            return view('product_v2.view-modal')->with(compact(
                'product',
                'rack_details',
                'combo_variations'
            ));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading product details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products by subcategory for AJAX requests
     */
    public function getProductsBySubcategory(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            
            // If no business_id in session, return error
            if (!$business_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Business ID not found in session. Please log in.'
                ], 401);
            }
            
            $products = $this->buildProductQuery($request, $business_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->buildProductsResponse($request, $products);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products by subcategory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public: Get all products with filtering for AJAX requests.
     */
    public function publicAll(Request $request)
    {
        try {
            $business_id = $this->resolvePublicBusinessId($request);

            $products = $this->buildProductQuery($request, $business_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->buildProductsResponse($request, $products);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Public: Get products by subcategory for AJAX requests.
     */
    public function publicBySubcategory(Request $request)
    {
        try {
            $business_id = $this->resolvePublicBusinessId($request);

            $products = $this->buildProductQuery($request, $business_id)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->buildProductsResponse($request, $products);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching products by subcategory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get subcategories for AJAX requests
     */
    public function getSubcategories(Request $request)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            $parent_id = $request->get('parent_id');
            
            $subcategories = Category::where('business_id', $business_id)
                ->where('parent_id', $parent_id)
                ->where('category_type', 'product')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']);

            return response()->json([
                'success' => true,
                'subcategories' => $subcategories
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching subcategories: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Build products query with shared filters.
     */
    private function buildProductQuery(Request $request, $business_id)
    {
        $query = Product::with([
            'category', 'categoryL1', 'categoryL2', 'categoryL3', 'categoryL4', 'categoryL5',
            'brand', 'unit', 'variations'
        ])
        ->where('business_id', $business_id)
        ->where('is_inactive', 0);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $category_id = $request->get('category_id');

            if ($category_id === '__uncategorized__') {
                // Product is uncategorized when no category field has a valid value.
                $query->where(function ($q) {
                    $q->where(function ($nq) {
                        $nq->whereNull('category_l1_id')->orWhere('category_l1_id', 0);
                    })->where(function ($nq) {
                        $nq->whereNull('category_l2_id')->orWhere('category_l2_id', 0);
                    })->where(function ($nq) {
                        $nq->whereNull('category_l3_id')->orWhere('category_l3_id', 0);
                    })->where(function ($nq) {
                        $nq->whereNull('category_l4_id')->orWhere('category_l4_id', 0);
                    })->where(function ($nq) {
                        $nq->whereNull('category_l5_id')->orWhere('category_l5_id', 0);
                    })->where(function ($nq) {
                        $nq->whereNull('category_id')->orWhere('category_id', 0);
                    });
                });
            } else {
                $include_subcategories = $this->boolValue($request->get('include_subcategories', false));

                if ($include_subcategories) {
                    $category_ids = $this->getAllSubcategoryIds($category_id, $business_id);
                    $category_ids[] = $category_id;

                    $query->where(function($q) use ($category_ids) {
                        $q->whereIn('category_l1_id', $category_ids)
                          ->orWhereIn('category_l2_id', $category_ids)
                          ->orWhereIn('category_l3_id', $category_ids)
                          ->orWhereIn('category_l4_id', $category_ids)
                          ->orWhereIn('category_l5_id', $category_ids)
                          ->orWhereIn('category_id', $category_ids);
                    });
                } else {
                    $query->where(function($q) use ($category_id) {
                        $q->where('category_l1_id', $category_id)
                          ->orWhere('category_l2_id', $category_id)
                          ->orWhere('category_l3_id', $category_id)
                          ->orWhere('category_l4_id', $category_id)
                          ->orWhere('category_l5_id', $category_id)
                          ->orWhere('category_id', $category_id);
                    });
                }
            }
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->get('brand_id'));
        }

        if ($request->filled('product_type')) {
            $query->where('type', $request->get('product_type'));
        }

        return $query;
    }

    /**
     * Build JSON response for product lists.
     */
    private function buildProductsResponse(Request $request, $products)
    {
        $group_by_category = $this->boolValue($request->get('group_by_category', false));

        if ($group_by_category) {
            $grouped = $products->groupBy(function($product) {
                return $product->getCategoryPath() ?: 'Uncategorized';
            });

            $transformed_grouped = [];
            foreach ($grouped as $categoryPath => $categoryProducts) {
                $transformed_grouped[$categoryPath] = $categoryProducts->map(function($product) {
                    return $this->transformProductForJson($product);
                });
            }

            return response()->json([
                'success' => true,
                'grouped_products' => $transformed_grouped,
                'total_count' => $products->count()
            ]);
        }

        $transformed_products = $products->map(function($product) {
            return $this->transformProductForJson($product);
        });

        return response()->json([
            'success' => true,
            'products' => $transformed_products,
            'total_count' => $products->count()
        ]);
    }

    /**
     * Resolve public business id (query param, env, or fallback).
     */
    private function resolvePublicBusinessId(Request $request)
    {
        $business_id = $request->get('business_id');
        if (!empty($business_id)) {
            return (int) $business_id;
        }

        $env_business_id = env('PUBLIC_PRODUCTS_BUSINESS_ID');
        if (!empty($env_business_id)) {
            return (int) $env_business_id;
        }

        return 1;
    }

    /**
     * Normalize boolean values from requests.
     */
    private function boolValue($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate and fix category hierarchy to ensure parent categories are set
     */
    private function validateAndFixCategoryHierarchy($product_data, $business_id)
    {
        // Find the deepest category level that has a value
        $deepest_level = null;
        $deepest_category_id = null;
        
        for ($level = 5; $level >= 1; $level--) {
            $field = 'category_l' . $level . '_id';
            if (!empty($product_data[$field])) {
                $deepest_level = $level;
                $deepest_category_id = $product_data[$field];
                break;
            }
        }
        
        if ($deepest_level && $deepest_category_id) {
            // Get the category and build the complete hierarchy
            $category = Category::where('business_id', $business_id)
                ->where('id', $deepest_category_id)
                ->first();
                
            if ($category) {
                // Build the complete hierarchy path
                $hierarchy = $this->buildCategoryHierarchy($category, $business_id);
                
                // Clear all category fields first
                for ($i = 1; $i <= 5; $i++) {
                    $product_data['category_l' . $i . '_id'] = null;
                }
                
                // Set the hierarchy properly
                foreach ($hierarchy as $level => $cat_id) {
                    $product_data['category_l' . $level . '_id'] = $cat_id;
                }
                
                \Log::info('Category hierarchy fixed:', [
                    'original_deepest' => $deepest_category_id,
                    'fixed_hierarchy' => $hierarchy,
                    'final_product_data' => [
                        'category_l1_id' => $product_data['category_l1_id'],
                        'category_l2_id' => $product_data['category_l2_id'],
                        'category_l3_id' => $product_data['category_l3_id'],
                        'category_l4_id' => $product_data['category_l4_id'],
                        'category_l5_id' => $product_data['category_l5_id'],
                    ]
                ]);
            }
        }
        
        return $product_data;
    }

    /**
     * Build category hierarchy from a given category up to root
     */
    private function buildCategoryHierarchy($category, $business_id)
    {
        $hierarchy = [];
        $current_category = $category;
        $level = 1;
        
        // Build path from root to current category
        $path = [];
        while ($current_category) {
            array_unshift($path, $current_category);
            if ($current_category->parent_id == 0) {
                break;
            }
            $current_category = Category::where('business_id', $business_id)
                ->where('id', $current_category->parent_id)
                ->first();
        }
        
        // Assign levels
        foreach ($path as $index => $cat) {
            $hierarchy[$index + 1] = $cat->id;
        }
        
        return $hierarchy;
    }

    /**
     * Generate next SKU number
     */
    public function generateNextSku()
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            
            // Get the last product SKU for this business
            $lastProduct = Product::where('business_id', $business_id)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->orderBy('id', 'desc')
                ->first();

            if ($lastProduct && $lastProduct->sku) {
                // Extract numeric part from SKU
                preg_match('/(\d+)/', $lastProduct->sku, $matches);
                if (!empty($matches)) {
                    $lastNumber = intval($matches[0]);
                    $nextNumber = $lastNumber + 1;
                    $nextSku = str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                } else {
                    $nextSku = '0001';
                }
            } else {
                $nextSku = '0001';
            }

            return response()->json([
                'success' => true,
                'next_sku' => $nextSku
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating SKU: ' . $e->getMessage()
            ], 500);
        }
    }
}
