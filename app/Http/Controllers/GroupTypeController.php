<?php

namespace App\Http\Controllers;

use App\GroupType;
use App\GroupSubType;
use App\Product;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use DB;

class GroupTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        // Get all group types with their sub types and products
        $groupTypes = GroupType::where('business_id', $business_id)
            ->with(['subTypes' => function($query) {
                $query->orderBy('sort_order');
            }, 'subTypes.products' => function($query) {
                $query->orderBy('group_sub_type_products.sort_order');
            }, 'products' => function($query) {
                $query->orderBy('group_type_products.sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return view('group_type.index', compact('groupTypes'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('group_type.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $input = $request->only(['name', 'description']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            // Get max sort order
            $maxSortOrder = GroupType::where('business_id', $business_id)->max('sort_order') ?? 0;
            $input['sort_order'] = $maxSortOrder + 1;

            $groupType = GroupType::create($input);

            $output = [
                'success' => true,
                'data' => $groupType,
                'msg' => __('group_type.added_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        return $output;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $groupType = GroupType::where('business_id', $business_id)->find($id);

            return view('group_type.edit', compact('groupType'));
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (request()->ajax()) {
            try {
                $input = $request->only(['name', 'description']);
                $business_id = $request->session()->get('user.business_id');

                $groupType = GroupType::where('business_id', $business_id)->findOrFail($id);
                $groupType->name = $input['name'];
                $groupType->description = $input['description'];
                $groupType->save();

                $output = [
                    'success' => true,
                    'msg' => __('group_type.updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                $groupType = GroupType::where('business_id', $business_id)->findOrFail($id);
                $groupType->delete();

                $output = [
                    'success' => true,
                    'msg' => __('group_type.deleted_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Search products for adding to group type
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function searchProducts(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
            $term = $request->input('term', '');

            \Log::info('=== Group Type Product Search ===');
            \Log::info('Business ID: ' . $business_id);
            \Log::info('Search Term: "' . $term . '"');
            \Log::info('Request URL: ' . $request->fullUrl());

            // Return empty array if no search term
            if (empty($term)) {
                \Log::info('Empty search term, returning empty results');
                return response()->json([]);
            }

            $products = Product::where('business_id', $business_id)
                ->where(function($query) use ($term) {
                    $query->where('name', 'LIKE', '%' . $term . '%')
                          ->orWhere('sku', 'LIKE', '%' . $term . '%');
                })
                ->where('is_inactive', 0)
                ->select('id', 'name', 'sku', 'image')
                ->limit(20)
                ->get();

            \Log::info('Products Found: ' . $products->count());

            $results = [];
            foreach ($products as $product) {
                $results[] = [
                    'id' => $product->id,
                    'text' => $product->name . ' (' . $product->sku . ')',
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'image' => $product->image_url,
                ];
            }

            \Log::info('Returning ' . count($results) . ' results');

            return response()->json($results);
        } catch (\Exception $e) {
            \Log::error('Product Search Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([], 500);
        }
    }

    /**
     * Add product to group type
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addProduct(Request $request, $id)
    {
        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');
                $product_id = $request->input('product_id');

                $groupType = GroupType::where('business_id', $business_id)->findOrFail($id);

                // Check if product already exists
                if ($groupType->products()->where('product_id', $product_id)->exists()) {
                    return [
                        'success' => false,
                        'msg' => __('group_type.product_already_exists'),
                    ];
                }

                // Get max sort order
                $maxSortOrder = DB::table('group_type_products')
                    ->where('group_type_id', $id)
                    ->max('sort_order') ?? 0;

                $groupType->products()->attach($product_id, ['sort_order' => $maxSortOrder + 1]);

                // Get the product info for response
                $product = Product::find($product_id);

                $output = [
                    'success' => true,
                    'msg' => __('group_type.product_added_success'),
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'image' => $product->image_url,
                    ],
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Remove product from group type
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function removeProduct(Request $request, $id)
    {
        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');
                $product_id = $request->input('product_id');

                $groupType = GroupType::where('business_id', $business_id)->findOrFail($id);
                $groupType->products()->detach($product_id);

                $output = [
                    'success' => true,
                    'msg' => __('group_type.product_removed_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Update product order in group type
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateProductOrder(Request $request, $id)
    {
        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');
                $product_ids = $request->input('product_ids', []);

                $groupType = GroupType::where('business_id', $business_id)->findOrFail($id);

                foreach ($product_ids as $index => $product_id) {
                    DB::table('group_type_products')
                        ->where('group_type_id', $id)
                        ->where('product_id', $product_id)
                        ->update(['sort_order' => $index + 1]);
                }

                $output = [
                    'success' => true,
                    'msg' => __('group_type.order_updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Update group type order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateOrder(Request $request)
    {
        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');
                $group_type_ids = $request->input('group_type_ids', []);

                foreach ($group_type_ids as $index => $group_type_id) {
                    GroupType::where('business_id', $business_id)
                        ->where('id', $group_type_id)
                        ->update(['sort_order' => $index + 1]);
                }

                $output = [
                    'success' => true,
                    'msg' => __('group_type.order_updated_success'),
                ];
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }
}
