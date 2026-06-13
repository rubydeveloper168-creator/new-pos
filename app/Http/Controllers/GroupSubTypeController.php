<?php

namespace App\Http\Controllers;

use App\GroupType;
use App\GroupSubType;
use App\Product;
use Illuminate\Http\Request;
use DB;

class GroupSubTypeController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $group_type_id = $request->input('group_type_id');
        return view('group_type.sub_type_create', compact('group_type_id'));
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
            $input = $request->only(['name', 'description', 'group_type_id']);
            $business_id = $request->session()->get('user.business_id');
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            // Verify group type belongs to business
            $groupType = GroupType::where('business_id', $business_id)
                ->findOrFail($input['group_type_id']);

            // Get max sort order
            $maxSortOrder = GroupSubType::where('group_type_id', $input['group_type_id'])->max('sort_order') ?? 0;
            $input['sort_order'] = $maxSortOrder + 1;

            $groupSubType = GroupSubType::create($input);

            $output = [
                'success' => true,
                'data' => $groupSubType,
                'msg' => __('group_type.sub_type_added_success'),
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
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $groupSubType = GroupSubType::where('business_id', $business_id)->find($id);

            return view('group_type.sub_type_edit', compact('groupSubType'));
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

                $groupSubType = GroupSubType::where('business_id', $business_id)->findOrFail($id);
                $groupSubType->name = $input['name'];
                $groupSubType->description = $input['description'];
                $groupSubType->save();

                $output = [
                    'success' => true,
                    'msg' => __('group_type.sub_type_updated_success'),
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

                $groupSubType = GroupSubType::where('business_id', $business_id)->findOrFail($id);
                $groupSubType->delete();

                $output = [
                    'success' => true,
                    'msg' => __('group_type.sub_type_deleted_success'),
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
     * Add product to group sub type
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

                $groupSubType = GroupSubType::where('business_id', $business_id)->findOrFail($id);

                // Check if product already exists
                if ($groupSubType->products()->where('product_id', $product_id)->exists()) {
                    return [
                        'success' => false,
                        'msg' => __('group_type.product_already_exists'),
                    ];
                }

                // Get max sort order
                $maxSortOrder = DB::table('group_sub_type_products')
                    ->where('group_sub_type_id', $id)
                    ->max('sort_order') ?? 0;

                $groupSubType->products()->attach($product_id, ['sort_order' => $maxSortOrder + 1]);

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
     * Remove product from group sub type
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

                $groupSubType = GroupSubType::where('business_id', $business_id)->findOrFail($id);
                $groupSubType->products()->detach($product_id);

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
     * Update product order in group sub type
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

                $groupSubType = GroupSubType::where('business_id', $business_id)->findOrFail($id);

                foreach ($product_ids as $index => $product_id) {
                    DB::table('group_sub_type_products')
                        ->where('group_sub_type_id', $id)
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
     * Update sub type order within a group type
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateOrder(Request $request)
    {
        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');
                $sub_type_ids = $request->input('sub_type_ids', []);

                foreach ($sub_type_ids as $index => $sub_type_id) {
                    GroupSubType::where('business_id', $business_id)
                        ->where('id', $sub_type_id)
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
