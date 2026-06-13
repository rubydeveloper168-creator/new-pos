<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\CustomerSource;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class CustomerSourceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $sources = CustomerSource::where('business_id', $business_id)
                ->select(['id', 'name', 'logo_path', 'is_active', 'sort_order']);

            return DataTables::of($sources)
                ->addColumn('logo', function ($source) {
                    if ($source->logo_path) {
                        $url = asset('uploads/customer_sources/' . $source->logo_path);
                        return '<img src="' . $url . '" alt="' . $source->name . '" style="max-width: 40px; max-height: 40px; object-fit: contain;">';
                    }
                    return '<span class="text-muted">No logo</span>';
                })
                ->addColumn('status', function ($source) {
                    if ($source->is_active) {
                        return '<span class="label label-success">Active</span>';
                    }
                    return '<span class="label label-danger">Inactive</span>';
                })
                ->addColumn('action', function ($source) {
                    $html = '<div class="btn-group">
                        <button type="button" class="btn btn-info btn-xs btn-modal"
                            data-href="' . action([\App\Http\Controllers\CustomerSourceController::class, 'edit'], [$source->id]) . '"
                            data-container=".customer_source_modal">
                            <i class="glyphicon glyphicon-edit"></i> ' . __('messages.edit') . '
                        </button>
                        <button type="button" class="btn btn-danger btn-xs delete_customer_source_button"
                            data-href="' . action([\App\Http\Controllers\CustomerSourceController::class, 'destroy'], [$source->id]) . '">
                            <i class="glyphicon glyphicon-trash"></i> ' . __('messages.delete') . '
                        </button>
                    </div>';
                    return $html;
                })
                ->rawColumns(['logo', 'status', 'action'])
                ->make(true);
        }

        return view('customer-sources.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('customer-sources.create');
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
            $business_id = $request->session()->get('user.business_id');

            $request->validate([
                'name' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean'
            ]);

            $data = [
                'business_id' => $business_id,
                'name' => $request->name,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->has('is_active') ? 1 : 0
            ];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                $logo = $request->file('logo');
                $filename = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();

                // Create directory if it doesn't exist
                $upload_path = public_path('uploads/customer_sources');
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $logo->move($upload_path, $filename);
                $data['logo_path'] = $filename;
            }

            CustomerSource::create($data);

            $output = [
                'success' => true,
                'msg' => __('Customer source added successfully')
            ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return redirect('customer-sources')->with('status', $output);
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
        $business_id = request()->session()->get('user.business_id');

        $source = CustomerSource::where('business_id', $business_id)
            ->findOrFail($id);

        return view('customer-sources.edit', compact('source'));
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
        try {
            $business_id = $request->session()->get('user.business_id');

            $source = CustomerSource::where('business_id', $business_id)
                ->findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean'
            ]);

            $data = [
                'name' => $request->name,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->has('is_active') ? 1 : 0
            ];

            // Handle logo upload
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($source->logo_path) {
                    $old_logo_path = public_path('uploads/customer_sources/' . $source->logo_path);
                    if (file_exists($old_logo_path)) {
                        unlink($old_logo_path);
                    }
                }

                $logo = $request->file('logo');
                $filename = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();

                $upload_path = public_path('uploads/customer_sources');
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }

                $logo->move($upload_path, $filename);
                $data['logo_path'] = $filename;
            }

            $source->update($data);

            $output = [
                'success' => true,
                'msg' => __('Customer source updated successfully')
            ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return redirect('customer-sources')->with('status', $output);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            $source = CustomerSource::where('business_id', $business_id)
                ->findOrFail($id);

            // Delete logo if exists
            if ($source->logo_path) {
                $logo_path = public_path('uploads/customer_sources/' . $source->logo_path);
                if (file_exists($logo_path)) {
                    unlink($logo_path);
                }
            }

            $source->delete();

            $output = [
                'success' => true,
                'msg' => __('Customer source deleted successfully')
            ];
        } catch (\Exception $e) {
            \Log::emergency("File:" . $e->getFile(). "Line:" . $e->getLine(). "Message:" . $e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ];
        }

        return $output;
    }
}
