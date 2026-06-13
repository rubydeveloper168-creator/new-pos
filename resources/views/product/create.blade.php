@extends('layouts.app')
@section('title', __('product.add_new_product'))

@section('content')

<style>
/* Fix Brand, Category, Unit and Barcode Type dropdown width */
#brand_id + .select2-container,
#hierarchical_category_id + .select2-container,
#unit_id + .select2-container,
#barcode_type + .select2-container {
    width: 100% !important;
}

.form-group {
    position: relative;
}

/* Remove black background from Select2 */
#brand_id + .select2-container .select2-selection {
    background-color: #fff !important;
    border: 1px solid #ccc !important;
}

#brand_id + .select2-container .select2-selection__rendered {
    background-color: #fff !important;
    color: #333 !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #fff !important;
    color: #333 !important;
}

.select2-dropdown {
    background-color: #fff !important;
}

.select2-results__option {
    background-color: #fff !important;
    color: #333 !important;
}

.select2-results__option--highlighted {
    background-color: #5897fb !important;
    color: #fff !important;
}

.unit-brand-label {
    display: block;
    width: 100%;
}
</style>

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.add_new_product')</h1>
    <div class="text-right">
        <small class="text-muted" id="save_indicator" style="display: none;">
            <i class="fa fa-check text-success"></i> Preferences saved
        </small>
        <button type="button" class="btn btn-sm btn-warning" id="clear_saved_data" title="Clear saved form preferences">
            <i class="fa fa-eraser"></i> Clear Saved Preferences
        </button>
    </div>
</section>

<!-- Main content -->
<section class="content">
    @php
    $form_class = empty($duplicate_product) ? 'create' : '';
    $is_image_required = !empty($common_settings['is_product_image_required']);
    @endphp
    {!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'store']), 'method' => 'post',
    'id' => 'product_add_form','class' => 'product_form ' . $form_class, 'files' => true ]) !!}
    
    <!-- First Section: Basic Product Information -->
    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('name', __('product.product_name') . ':*') !!}
                    {!! Form::text('name', !empty($duplicate_product->name) ? $duplicate_product->name : null, [
                        'class' => 'form-control',
                        'required',
                        'placeholder' => __('product.product_name')
                    ]); !!}
                </div>
            </div>

            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('second_name', 'Second Name' . ':') !!}
                    {!! Form::text('second_name', !empty($duplicate_product->second_name) ? $duplicate_product->second_name : null, [
                        'class' => 'form-control',
                        'placeholder' => 'Second Name'
                    ]); !!}
                </div>
            </div>

            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('sku', __('product.sku') . ':') !!}
                    @show_tooltip(__('tooltip.sku'))
                    <div class="input-group">
                        {!! Form::text('sku', !empty($duplicate_product->sku) ? $duplicate_product->sku : null, [
                            'class' => 'form-control',
                            'placeholder' => __('product.sku'),
                            'id' => 'sku_input'
                        ]); !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default bg-red btn-flat" id="auto_generate_sku" title="Auto Generate SKU">
                                ↺
                            </button>
                        </span>
                    </div>
                    <small class="text-muted">Click refresh icon to auto-generate next SKU number</small>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('barcode_type', __('product.barcode_type') . ':*') !!}
                    {!! Form::select('barcode_type', $barcode_types, !empty($duplicate_product->barcode_type) ? $duplicate_product->barcode_type : $barcode_default, [
                        'class' => 'form-control select2-barcode-type',
                        'id' => 'barcode_type',
                        'required'
                    ]); !!}
                </div>
            </div>

            <div class="clearfix"></div>
            
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('unit_id', __('product.unit') . ':*', ['class' => 'unit-brand-label']) !!}
                    <div class="input-group">
                        {!! Form::select('unit_id', $units, !empty($duplicate_product->unit_id) ? $duplicate_product->unit_id : session('business.default_unit'), [
                            'class' => 'form-control select2-unit',
                            'id' => 'unit_id',
                            'required'
                        ]); !!}
                        <span class="input-group-btn">
                            <button type="button" @if(!auth()->user()->can('unit.create')) disabled @endif 
                                class="btn btn-default bg-white btn-flat btn-modal" 
                                data-href="{{action([\App\Http\Controllers\UnitController::class, 'create'], ['quick_add' => true])}}" 
                                title="@lang('unit.add_unit')" 
                                data-container=".view_modal">
                                <i class="fa fa-plus"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_sub_units')) hide @endif">
                <div class="form-group">
                    {!! Form::label('sub_unit_ids', __('lang_v1.related_sub_units') . ':') !!}
                    @show_tooltip(__('lang_v1.sub_units_tooltip'))
                    {!! Form::select('sub_unit_ids[]', [], !empty($duplicate_product->sub_unit_ids) ? $duplicate_product->sub_unit_ids : null, [
                        'class' => 'form-control select2',
                        'multiple',
                        'id' => 'sub_unit_ids'
                    ]); !!}
                </div>
            </div>

            @if(!empty($common_settings['enable_secondary_unit']))
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('secondary_unit_id', __('lang_v1.secondary_unit') . ':') !!}
                        {!! Form::select('secondary_unit_id', $units, !empty($duplicate_product->secondary_unit_id) ? $duplicate_product->secondary_unit_id : null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select')
                        ]); !!}
                    </div>
                </div>
            @endif

            <div class="col-sm-4 @if(!session('business.enable_brand')) hide @endif">
                <div class="form-group">
                    {!! Form::label('brand_id', __('product.brand') . ':', ['class' => 'unit-brand-label']) !!}
                    <div class="input-group">
                        {!! Form::select('brand_id', $brands, !empty($duplicate_product->brand_id) ? $duplicate_product->brand_id : null, [
                            'placeholder' => __('messages.please_select'),
                            'class' => 'form-control select2-brand',
                            'id' => 'brand_id'
                        ]); !!}
                        <span class="input-group-btn">
                            <button type="button" @if(!auth()->user()->can('brand.create')) disabled @endif 
                                class="btn btn-default bg-white btn-flat btn-modal" 
                                data-href="{{action([\App\Http\Controllers\BrandController::class, 'create'], ['quick_add' => true])}}" 
                                title="@lang('brand.add_brand')" 
                                data-container=".view_modal">
                                <i class="fa fa-plus"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="col-sm-4 @if(!session('business.enable_category')) hide @endif">
                <div class="form-group">
                    {!! Form::label('hierarchical_category_id', __('product.category') . ':') !!}
                    <select name="hierarchical_category_selection" id="hierarchical_category_id"
                        class="form-control select2-category">
                        <option value="">{{ __('messages.please_select') }}</option>
                        @foreach($hierarchical_categories as $cat_id => $cat_name)
                            <option value="{{ $cat_id }}" {{ 
                                (!empty($preselected_category_id) && $preselected_category_id == $cat_id) || 
                                (!empty($duplicate_product->category_id) && $duplicate_product->category_id == $cat_id) || 
                                (!empty($duplicate_product->sub_category_id) && $duplicate_product->sub_category_id == $cat_id) ? 'selected' : '' 
                            }}>
                                {{ $cat_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('product_locations', __('business.business_locations') . ':') !!}
                    @show_tooltip(__('lang_v1.product_location_help'))
                    @php
                    $default_location = null;
                    if(count($business_locations) == 1){
                        $default_location = array_key_first($business_locations->toArray());
                    }
                    @endphp
                    {!! Form::select('product_locations[]', $business_locations, $default_location, [
                        'class' => 'form-control select2',
                        'multiple',
                        'id' => 'product_locations'
                    ]); !!}
                </div>
            </div>

            <!-- Hidden fields for compatibility with existing system -->
            {!! Form::hidden('category_id', !empty($duplicate_product->category_id) ? $duplicate_product->category_id : null, ['id' => 'actual_category_id']) !!}
            {!! Form::hidden('sub_category_id', !empty($duplicate_product->sub_category_id) ? $duplicate_product->sub_category_id : null, ['id' => 'sub_category_id']) !!}
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-8">
                <div class="form-group">
                    {!! Form::label('product_description', __('lang_v1.product_description') . ':') !!}
                    {!! Form::textarea('product_description', !empty($duplicate_product->product_description) ? $duplicate_product->product_description : null, [
                        'class' => 'form-control'
                    ]); !!}
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('image', __('lang_v1.product_image') . ':') !!}
                    @if($is_image_required) <span class="required">*</span> @endif
                    
                    <!-- Hidden field for gallery selected image -->
                    <input type="hidden" id="gallery_image" name="gallery_image" value="">
                    
                    <!-- Gallery Browse Section -->
                    <div class="well well-sm" style="padding: 10px; margin-bottom: 10px;">
                        <!-- Image Preview Container -->
                        <div class="image-preview-container text-center" id="selected-image-preview" style="display: none;">
                            <img id="selected-image" src="" alt="Selected Image" class="img-thumbnail" style="max-width: 150px; max-height: 150px; margin-bottom: 10px;">
                            <br>
                            <button type="button" class="btn btn-danger btn-xs remove-selected-image">
                                <i class="fa fa-trash"></i> Remove Image
                            </button>
                        </div>
                        
                        <!-- Gallery Browse Button -->
                        <div class="text-center" id="gallery-browse-section">
                            <div class="mb-2" id="no-image-placeholder">
                                <i class="fa fa-images fa-2x text-muted"></i>
                                <p class="text-muted">Browse from gallery to select product image</p>
                            </div>
                            <button type="button" class="btn btn-primary btn-block" id="browse-gallery-btn">
                                <i class="fa fa-images"></i> Browse Gallery
                            </button>
                        </div>
                    </div>
                    

                    
                    <small><p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]). @lang('lang_v1.aspect_ratio_should_be_1_1')</p></small>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('product_brochure', __('lang_v1.product_brochure') . ':') !!}
                    {!! Form::file('product_brochure', [
                        'id' => 'product_brochure', 
                        'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))
                    ]); !!}
                    <small>
                        <p class="help-block">
                            @lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
                            @includeIf('components.document_help_text')
                        </p>
                    </small>
                </div>
            </div>
        </div>
    @endcomponent
    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
        @if(session('business.enable_product_expiry'))

          @if(session('business.expiry_type') == 'add_expiry')
            @php
              $expiry_period = 12;
              $hide = true;
            @endphp
          @else
            @php
              $expiry_period = null;
              $hide = false;
            @endphp
          @endif
          <div class="col-sm-4 @if($hide) hide @endif">
            <div class="form-group">
              <div class="multi-input">
                {!! Form::label('expiry_period', __('product.expires_in') . ':') !!}
                <br/>
                {!! Form::text('expiry_period', !empty($duplicate_product->expiry_period) ? @num_format($duplicate_product->expiry_period) : $expiry_period, ['class' => 'form-control pull-left input_number', 'style' => 'width:60%;', 'placeholder' => __('product.expiry_period')]); !!}

                {!! Form::select('expiry_period_type', ['months' => __('product.months'), 'days' => __('product.days'), '' => __('product.not_applicable')], !empty($duplicate_product->expiry_period_type) ? $duplicate_product->expiry_period_type : 'months', ['class' => 'form-control select2 pull-left', 'style' => 'width:40%;', 'id' => 'expiry_period_type']); !!}
              </div>
            </div>
          </div>
          @endif
          <div class="col-sm-4">
            <div class="checkbox">
              <br>
              <label>
                {!! Form::checkbox('enable_sr_no', 1, !(empty($duplicate_product)) ? $duplicate_product->enable_sr_no : false, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.enable_imei_or_sr_no')</strong>
              </label>
              @show_tooltip(__('lang_v1.tooltip_sr_no'))
            </div>
          </div>

          <div class="col-sm-4">
            <div class="checkbox">
              <br>
              <label>
                {!! Form::checkbox('enable_stock', 1, !(empty($duplicate_product)) ? $duplicate_product->enable_stock : true, ['class' => 'input-icheck', 'id' => 'enable_stock']); !!} <strong>@lang('product.manage_stock')</strong>
              </label>
              @show_tooltip(__('tooltip.enable_stock'))
              <p class="help-block"><i>@lang('product.enable_stock_help')</i></p>
            </div>
          </div>

          <div class="col-sm-4">
          <div class="form-group">
            <br>
            <label>
              {!! Form::checkbox('not_for_selling', 1, !(empty($duplicate_product)) ? $duplicate_product->not_for_selling : false, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.not_for_selling')</strong>
            </label> @show_tooltip(__('lang_v1.tooltip_not_for_selling'))
          </div>
        </div>

        <div class="clearfix"></div>

        <!-- Rack, Row & position number -->
        @if(session('business.enable_racks') || session('business.enable_row') || session('business.enable_position') || session('business.enable_level'))
          <div class="col-md-12">
            <h4>@lang('lang_v1.rack_details'):
              <small class="text-muted">(@lang('lang_v1.rack_details_help'))</small>
            </h4>
          </div>
          @foreach($business_locations as $id => $location)
            <div class="col-sm-3">
              <div class="form-group">
                <label for="">{{$location}}:</label>
                  @if(session('business.enable_racks'))
                    {!! Form::text('product_racks[' . $id . '][rack]', !empty($rack_details[$id]['rack']) ? $rack_details[$id]['rack'] : null, ['class' => 'form-control', 'id' => 'rack_' . $id, 'placeholder' => __('lang_v1.rack')]); !!}
                  @endif

                  @if(session('business.enable_row'))
                    {!! Form::text('product_racks[' . $id . '][row]', !empty($rack_details[$id]['row']) ? $rack_details[$id]['row'] : null, ['class' => 'form-control', 'id' => 'row_' . $id, 'placeholder' => __('lang_v1.row')]); !!}
                  @endif

                  @if(session('business.enable_position'))
                    {!! Form::text('product_racks[' . $id . '][position]', !empty($rack_details[$id]['position']) ? $rack_details[$id]['position'] : null, ['class' => 'form-control', 'id' => 'position_' . $id, 'placeholder' => __('lang_v1.position')]); !!}
                  @endif

                  @if(session('business.enable_level'))
                    {!! Form::text('product_racks[' . $id . '][level]', !empty($rack_details[$id]['level']) ? $rack_details[$id]['level'] : null, ['class' => 'form-control', 'id' => 'level_' . $id, 'placeholder' => __('lang_v1.level')]); !!}
                  @endif
              </div>
            </div>
          @endforeach
        @endif


        <div class="col-sm-4">
          <div class="form-group">
            {!! Form::label('weight',  __('lang_v1.weight') . ':') !!}
            {!! Form::text('weight', !empty($duplicate_product->weight) ? $duplicate_product->weight : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.weight')]); !!}
          </div>
        </div>
        <div class="clearfix"></div>
        
        @php
            $custom_labels = json_decode(session('business.custom_labels'), true);
            $product_custom_fields = !empty($custom_labels['product']) ? $custom_labels['product'] : [];
            $product_cf_details = !empty($custom_labels['product_cf_details']) ? $custom_labels['product_cf_details'] : [];
        @endphp
        <!--custom fields-->

        @foreach($product_custom_fields as $index => $cf)
            @if(!empty($cf))
                @php
                    $db_field_name = 'product_custom_field' . $loop->iteration;
                    $cf_type = !empty($product_cf_details[$loop->iteration]['type']) ? $product_cf_details[$loop->iteration]['type'] : 'text';
                    $dropdown = !empty($product_cf_details[$loop->iteration]['dropdown_options']) ? explode(PHP_EOL, $product_cf_details[$loop->iteration]['dropdown_options']) : [];
                @endphp

                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label($db_field_name, $cf . ':') !!}
                        @if(in_array($cf_type, ['text', 'date']))
                            {!! Form::input($cf_type, $db_field_name, !empty($duplicate_product->$db_field_name) ? $duplicate_product->$db_field_name : null, ['class' => 'form-control', 'placeholder' => $cf]); !!}
                        @elseif($cf_type == 'dropdown')
                            {!! Form::select($db_field_name, $dropdown, !empty($duplicate_product->$db_field_name) ? $duplicate_product->$db_field_name : null, ['placeholder' => $cf, 'class' => 'form-control select2']); !!}
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <div class="col-sm-3">
          <div class="form-group">
            {!! Form::label('preparation_time_in_minutes',  __('lang_v1.preparation_time_in_minutes') . ':') !!}
            {!! Form::number('preparation_time_in_minutes', !empty($duplicate_product->preparation_time_in_minutes) ? $duplicate_product->preparation_time_in_minutes : null, ['class' => 'form-control', 'placeholder' => __('lang_v1.preparation_time_in_minutes')]); !!}
          </div>
        </div>
        <!--custom fields-->
        @include('layouts.partials.module_form_part')
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
                <div class="form-group">
                    {!! Form::label('tax', __('product.applicable_tax') . ':') !!}
                    {!! Form::select('tax', $taxes, !empty($duplicate_product->tax) ? $duplicate_product->tax : null, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2'], $tax_attributes); !!}
                </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
                <div class="form-group">
                    {!! Form::label('tax_type', __('product.selling_price_tax_type') . ':*') !!}
                    {!! Form::select('tax_type', ['inclusive' => __('product.inclusive'), 'exclusive' => __('product.exclusive')], !empty($duplicate_product->tax_type) ? $duplicate_product->tax_type : 'exclusive', ['class' => 'form-control select2', 'required']); !!}
                </div>
            </div>

            <div class="clearfix"></div>

            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
                <div class="form-group">
                    {!! Form::label('type', __('product.product_type') . ':*') !!}
                    @show_tooltip(__('tooltip.product_type'))
                    {!! Form::select('type', $product_types, !empty($duplicate_product->type) ? $duplicate_product->type : null, ['class' => 'form-control select2', 'required', 'data-action' => !empty($duplicate_product) ? 'duplicate' : 'add', 'data-product_id' => !empty($duplicate_product) ? $duplicate_product->id : '0']); !!}
                </div>
            </div>

            <div class="form-group col-sm-12" id="product_form_part">
                @include('product.partials.single_product_form_part', ['profit_percent' => $default_profit_percent])
            </div>
            <input type="hidden" id="variation_counter" value="1">
            <input type="hidden" id="default_profit_percent" value="{{ $default_profit_percent }}">
            </div>
    @endcomponent
  <div class="row">
    <input type="hidden" name="submit_type" id="submit_type">
        <div class="col-sm-12">
          <div class="text-center">
            @if($selling_price_group_count)
            <button type="submit" value="submit_n_add_selling_prices" class="btn bg-maroon btn-flat btn-lg submit_product_form">@lang('lang_v1.save_n_add_selling_price_group_prices')</button>
            @endif

            @can('product.opening_stock')
            <button id="opening_stock_button" 
                type="submit" value="submit_n_add_opening_stock" 
                onclick="$('#submit_type').val('submit_n_add_opening_stock'); console.log('Opening stock clicked, submit_type set to:', $('#submit_type').val());"
                class="btn bg-purple btn-flat btn-lg submit_product_form">@lang('lang_v1.save_n_add_opening_stock')</button>
            @endcan

            <button type="submit" value="save_n_add_another" class="btn bg-green btn-flat btn-lg submit_product_form">@lang('lang_v1.save_n_add_another')</button>

            <button type="submit" value="submit" class="btn btn-primary btn-flat btn-lg submit_product_form">@lang('messages.save')</button>
          </div>
        </div>
  </div>

    {!! Form::close() !!}
</section>
<!-- /.content -->

@endsection

@section('css')
<style>
.button1 {
    background-color: #38a169; /* Green */
    border: none;
    color: white;
    padding: 10px 24px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    margin: 4px 2px;
    transition-duration: 0.4s;
    cursor: pointer;
    border-radius: 12px;
}

.productnameinput {
    background-color: #000000ff; /* Gray */
    border: none;
    color: black; 
    padding: 10px 24px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    margin: 4px 2px;
    transition-duration: 0.4s;
    cursor: pointer;
    border-radius: 12px;
}

.button3 {
    background-color: #38a169; /* Green */
    border: none;
    color: white;
    padding: 10px 24px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    margin: 4px 2px;
    transition-duration: 0.4s;
    cursor: pointer;
    border-radius: 12px;
}

.button4 {
    background-color: #e53e3e; /* Red */
    border: none;
    color: white;
    padding: 10px 24px;
    text-align: center;
    text-decoration: none;
    display: inline-block;
    font-size: 16px;
    margin: 4px 2px;
    transition-duration: 0.4s;
    cursor: pointer;
    border-radius: 12px;
}
</style>
@endsection

@section('javascript')

<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>

<script type="text/javascript">
    // Debug document mode for TinyMCE
    console.log('Document compatibility mode:', document.compatMode);
    console.log('Document doctype:', document.doctype);
    console.log('Document ready state:', document.readyState);
    
    $(document).ready(function() {
        // Add error handling for standards mode compatibility
        try {
            __page_leave_confirmation('#product_add_form');
        } catch(e) {
            console.warn('Page leave confirmation not available:', e.message);
        }
        
        // Load saved form data from localStorage on page load
        loadSavedFormData();
        
        // Auto-generate SKU on page load
        autoGenerateSKU();

        // Fix Brand dropdown positioning
        var $brandSelect = $('#brand_id');
        if ($brandSelect.length) {
            var $brandFormGroup = $brandSelect.closest('.form-group');
            $brandSelect.select2({
                dropdownParent: $brandFormGroup,
                width: '100%',
                dropdownAutoWidth: false,
                placeholder: '{{ __("messages.please_select") }}'
            });

            // Ensure dropdown width matches select width when opened
            $brandSelect.on('select2:open', function() {
                setTimeout(function() {
                    var $container = $brandSelect.next('.select2-container');
                    var containerWidth = $container.outerWidth();
                    $brandFormGroup.find('.select2-dropdown').css({
                        'width': containerWidth + 'px',
                        'min-width': containerWidth + 'px'
                    });
                }, 0);
            });
        }

        // Fix Category dropdown positioning
        var $categorySelect = $('#hierarchical_category_id');
        if ($categorySelect.length) {
            var $categoryFormGroup = $categorySelect.closest('.form-group');
            $categorySelect.select2({
                dropdownParent: $categoryFormGroup,
                width: '100%',
                dropdownAutoWidth: false,
                placeholder: '{{ __("messages.please_select") }}'
            });

            // Ensure dropdown width matches select width when opened
            $categorySelect.on('select2:open', function() {
                setTimeout(function() {
                    var $container = $categorySelect.next('.select2-container');
                    var containerWidth = $container.outerWidth();
                    $categoryFormGroup.find('.select2-dropdown').css({
                        'width': containerWidth + 'px',
                        'min-width': containerWidth + 'px'
                    });
                }, 0);
            });
        }

        // Fix Unit dropdown positioning
        var $unitSelect = $('#unit_id');
        if ($unitSelect.length) {
            $unitSelect.find('option[value=""]').slice(1).remove();
            var $unitFormGroup = $unitSelect.closest('.form-group');
            $unitSelect.select2({
                dropdownParent: $unitFormGroup,
                width: '100%',
                dropdownAutoWidth: false
            });

            // Ensure dropdown width matches select width when opened
            $unitSelect.on('select2:open', function() {
                setTimeout(function() {
                    var $container = $unitSelect.next('.select2-container');
                    var containerWidth = $container.outerWidth();
                    $unitFormGroup.find('.select2-dropdown').css({
                        'width': containerWidth + 'px',
                        'min-width': containerWidth + 'px'
                    });
                }, 0);
            });
        }

        // Fix Barcode Type dropdown positioning
        var $barcodeTypeSelect = $('#barcode_type');
        if ($barcodeTypeSelect.length) {
            var $barcodeTypeFormGroup = $barcodeTypeSelect.closest('.form-group');
            $barcodeTypeSelect.select2({
                dropdownParent: $barcodeTypeFormGroup,
                width: '100%',
                dropdownAutoWidth: false
            });

            // Ensure dropdown width matches select width when opened
            $barcodeTypeSelect.on('select2:open', function() {
                setTimeout(function() {
                    var $container = $barcodeTypeSelect.next('.select2-container');
                    var containerWidth = $container.outerWidth();
                    $barcodeTypeFormGroup.find('.select2-dropdown').css({
                        'width': containerWidth + 'px',
                        'min-width': containerWidth + 'px'
                    });
                }, 0);
            });
        }

        // Initialize barcode scanner with error handling
        try {
            if (typeof onScan !== 'undefined') {
                onScan.attachTo(document, {
                    suffixKeyCodes: [13], // enter-key expected at the end of a scan
                    reactToPaste: true, // Compatibility to built-in scanners in paste-mode (as opposed to keyboard-mode)
                    onScan: function(sCode, iQty) {
                        $('input#sku_input').val(sCode);
                    },
                    onScanError: function(oDebug) {
                        console.log(oDebug);
                    },
                    minLength: 2,
                    ignoreIfFocusOn: ['input', '.form-control']
                });
            }
        } catch(e) {
            console.warn('Barcode scanner initialization failed:', e.message);
        }

        // Auto-generate SKU button click
        $('#auto_generate_sku').on('click', function() {
            autoGenerateSKU();
        });

        // Clear saved data button click
        $('#clear_saved_data').on('click', function() {
            if (confirm('Are you sure you want to clear all saved form preferences?')) {
                clearSavedData();
            }
        });

        // Save form data to localStorage when values change
        setupLocalStorageSaving();

        // Handle hierarchical category selection
        $(document).on('change', '#hierarchical_category_id', function() {
            var selectedCategoryId = $(this).val();
            
            if (selectedCategoryId) {
                // Get category hierarchy information via AJAX
                $.ajax({
                    url: '/products/get_category_hierarchy',
                    method: 'POST',
                    data: {
                        category_id: selectedCategoryId,
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Category hierarchy response:', response);
                            
                            // Set the legacy category fields for backward compatibility
                            if (response.is_main_category) {
                                // This is a main category (L1)
                                $('#actual_category_id').val(selectedCategoryId);
                                $('#sub_category_id').val('');
                            } else {
                                // This is a subcategory (L2+)
                                $('#actual_category_id').val(response.root_category_id);
                                $('#sub_category_id').val(selectedCategoryId);
                            }
                            
                            // Set the multi-level category fields (L1-L5)
                            // First, clear all existing L1-L5 fields
                            for (var i = 1; i <= 5; i++) {
                                var fieldName = 'category_l' + i + '_id';
                                if ($('#' + fieldName).length) {
                                    $('#' + fieldName).val('');
                                } else {
                                    // Create hidden field if it doesn't exist
                                    $('<input>').attr({
                                        type: 'hidden',
                                        id: fieldName,
                                        name: fieldName,
                                        value: ''
                                    }).appendTo('#product_add_form');
                                }
                            }
                            
                            // Set the appropriate L1-L5 values
                            if (response.l1_id) $('#category_l1_id').val(response.l1_id);
                            if (response.l2_id) $('#category_l2_id').val(response.l2_id);
                            if (response.l3_id) $('#category_l3_id').val(response.l3_id);
                            if (response.l4_id) $('#category_l4_id').val(response.l4_id);
                            if (response.l5_id) $('#category_l5_id').val(response.l5_id);
                            
                            console.log('Set category fields:', {
                                'L1': response.l1_id,
                                'L2': response.l2_id,
                                'L3': response.l3_id,
                                'L4': response.l4_id,
                                'L5': response.l5_id,
                                'Legacy category_id': $('#actual_category_id').val(),
                                'Legacy sub_category_id': $('#sub_category_id').val()
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error getting category hierarchy:', error);
                    }
                });
            } else {
                // Clear all category fields if no selection
                $('#actual_category_id').val('');
                $('#sub_category_id').val('');
                
                // Clear L1-L5 fields
                for (var i = 1; i <= 5; i++) {
                    var fieldName = 'category_l' + i + '_id';
                    if ($('#' + fieldName).length) {
                        $('#' + fieldName).val('');
                    }
                }
            }
        });

        // Auto-populate category fields if there's a preselected category
        @if(!empty($preselected_category_id))
            // Trigger the hierarchy detection for preselected category
            setTimeout(function() {
                $('#hierarchical_category_id').trigger('change');
            }, 500);
        @endif

        // Handle enable_stock checkbox for opening stock button
        $('#enable_stock').on('ifChanged', function() {
            if ($(this).is(':checked')) {
                $('#opening_stock_button').prop('disabled', false).removeClass('disabled');
            } else {
                $('#opening_stock_button').prop('disabled', true).addClass('disabled');
            }
        });

        // Initial state check
        if ($('#enable_stock').is(':checked')) {
            $('#opening_stock_button').prop('disabled', false).removeClass('disabled');
        } else {
            $('#opening_stock_button').prop('disabled', true).addClass('disabled');
        }

        // Auto-generate SKU function
        function autoGenerateSKU() {
            $.ajax({
                url: '/products/generate-next-sku',
                method: 'GET',
                success: function(response) {
                    if (response.success && response.next_sku) {
                        $('#sku_input').val(response.next_sku);
                        console.log('Auto-generated SKU:', response.next_sku);
                    } else {
                        console.error('Failed to generate SKU:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error generating SKU:', error);
                    // Fallback: generate a simple incremental SKU
                    var fallbackSku = String(Date.now()).slice(-4).padStart(4, '0');
                    $('#sku_input').val(fallbackSku);
                }
            });
        }

        // Setup localStorage saving for form fields
        function setupLocalStorageSaving() {
            // Basic fields to save
            var basicFields = ['brand_id', 'unit_id', 'tax', 'type'];
            
            // Save basic fields when they change
            basicFields.forEach(function(fieldName) {
                $(document).on('change', '#' + fieldName, function() {
                    var value = $(this).val();
                    if (value) {
                        localStorage.setItem('product_form_' + fieldName, value);
                        showSaveIndicator();
                        console.log('Saved to localStorage:', fieldName, '=', value);
                    }
                });
            });

            // Save rack fields for each location
            <?php 
            $rack_js_code = '';
            $row_js_code = '';
            $level_js_code = '';
            
            foreach($business_locations as $id => $location) {
                if(session('business.enable_racks')) {
                    $rack_js_code .= "
                        \$(document).on('change input', '#rack_{$id}', function() {
                            var value = \$(this).val();
                            if (value) {
                                localStorage.setItem('product_form_rack_{$id}', value);
                                showSaveIndicator();
                                console.log('Saved rack {$id}:', value);
                            }
                        });
                    ";
                }
                
                if(session('business.enable_row')) {
                    $row_js_code .= "
                        \$(document).on('change input', '#row_{$id}', function() {
                            var value = \$(this).val();
                            if (value) {
                                localStorage.setItem('product_form_row_{$id}', value);
                                showSaveIndicator();
                                console.log('Saved row {$id}:', value);
                            }
                        });
                    ";
                }
                
                if(session('business.enable_level')) {
                    $level_js_code .= "
                        \$(document).on('change input', '#level_{$id}', function() {
                            var value = \$(this).val();
                            if (value) {
                                localStorage.setItem('product_form_level_{$id}', value);
                                showSaveIndicator();
                                console.log('Saved level {$id}:', value);
                            }
                        });
                    ";
                }
            }
            ?>
            
            {!! $rack_js_code !!}
            {!! $row_js_code !!}
            {!! $level_js_code !!}
        }

        // Load saved form data from localStorage
        function loadSavedFormData() {
            // Load brand
            var savedBrand = localStorage.getItem('product_form_brand_id');
            if (savedBrand) {
                $('#brand_id').val(savedBrand).trigger('change');
            }

            // Load unit
            var savedUnit = localStorage.getItem('product_form_unit_id');
            if (savedUnit) {
                $('#unit_id').val(savedUnit).trigger('change');
            }

            // Load tax
            var savedTax = localStorage.getItem('product_form_tax');
            if (savedTax) {
                $('#tax').val(savedTax).trigger('change');
            }

            // Load product type
            var savedType = localStorage.getItem('product_form_type');
            if (savedType) {
                $('#type').val(savedType).trigger('change');
            }

            // Load rack fields for each location
            <?php 
            $load_rack_js = '';
            foreach($business_locations as $id => $location) {
                if(session('business.enable_racks')) {
                    $load_rack_js .= "
                        var savedRack{$id} = localStorage.getItem('product_form_rack_{$id}');
                        if (savedRack{$id}) {
                            \$('#rack_{$id}').val(savedRack{$id});
                        }
                    ";
                }
                
                if(session('business.enable_row')) {
                    $load_rack_js .= "
                        var savedRow{$id} = localStorage.getItem('product_form_row_{$id}');
                        if (savedRow{$id}) {
                            \$('#row_{$id}').val(savedRow{$id});
                        }
                    ";
                }
                
                if(session('business.enable_level')) {
                    $load_rack_js .= "
                        var savedLevel{$id} = localStorage.getItem('product_form_level_{$id}');
                        if (savedLevel{$id}) {
                            \$('#level_{$id}').val(savedLevel{$id});
                        }
                    ";
                }
            }
            ?>
            {!! $load_rack_js !!}

            console.log('Loaded saved form data from localStorage');
        }

        // Clear saved form data from localStorage
        function clearSavedData() {
            // Clear basic fields
            localStorage.removeItem('product_form_brand_id');
            localStorage.removeItem('product_form_unit_id');
            localStorage.removeItem('product_form_tax');
            localStorage.removeItem('product_form_type');

            // Clear rack fields for each location
            <?php 
            $clear_rack_js = '';
            foreach($business_locations as $id => $location) {
                if(session('business.enable_racks')) {
                    $clear_rack_js .= "localStorage.removeItem('product_form_rack_{$id}');\n                ";
                }
                if(session('business.enable_row')) {
                    $clear_rack_js .= "localStorage.removeItem('product_form_row_{$id}');\n                ";
                }
                if(session('business.enable_level')) {
                    $clear_rack_js .= "localStorage.removeItem('product_form_level_{$id}');\n                ";
                }
            }
            ?>
            {!! $clear_rack_js !!}

            // Reset form fields to default values
            $('#brand_id').val('').trigger('change');
            $('#unit_id').val('').trigger('change');
            $('#tax').val('').trigger('change');
            $('#type').val('').trigger('change');

            <?php 
            $reset_rack_js = '';
            foreach($business_locations as $id => $location) {
                if(session('business.enable_racks')) {
                    $reset_rack_js .= "\$('#rack_{$id}').val('');\n            ";
                }
                if(session('business.enable_row')) {
                    $reset_rack_js .= "\$('#row_{$id}').val('');\n            ";
                }
                if(session('business.enable_level')) {
                    $reset_rack_js .= "\$('#level_{$id}').val('');\n            ";
                }
            }
            ?>
            {!! $reset_rack_js !!}

            alert('Saved form preferences cleared successfully!');
            console.log('Cleared all saved form data from localStorage');
        }

        // Show save indicator
        function showSaveIndicator() {
            $('#save_indicator').fadeIn().delay(2000).fadeOut();
        }

        // Debug form submission
        $('#product_add_form').on('submit', function(e) {
            console.log('=== ORIGINAL PRODUCT FORM SUBMISSION DEBUG ===');
            console.log('Legacy category_id:', $('#actual_category_id').val());
            console.log('Legacy sub_category_id:', $('#sub_category_id').val());
            console.log('L1 category_id:', $('#category_l1_id').val());
            console.log('L2 category_id:', $('#category_l2_id').val());
            console.log('L3 category_id:', $('#category_l3_id').val());
            console.log('L4 category_id:', $('#category_l4_id').val());
            console.log('L5 category_id:', $('#category_l5_id').val());
            console.log('Hierarchical selection:', $('#hierarchical_category_id').val());
            console.log('=== END DEBUG ===');
            
            // Continue with form submission
            return true;
        });
    });
</script>

<!-- Image Gallery Modal -->
<div class="modal fade" id="imageGalleryModal" tabindex="-1" role="dialog" aria-labelledby="imageGalleryModalLabel">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageGalleryModalLabel">
                    <i class="fa fa-images"></i> Image Gallery
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Gallery Controls -->
                <div class="gallery-controls mb-3">
                    <div class="row">
                        <div class="col-md-3">
                            <input type="text" id="gallery-search" class="form-control form-control-sm" placeholder="Search images...">
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="gallery-date-from" class="form-control form-control-sm" placeholder="From Date">
                        </div>
                        <div class="col-md-2">
                            <input type="date" id="gallery-date-to" class="form-control form-control-sm" placeholder="To Date">
                        </div>
                        <div class="col-md-2">
                            <select id="gallery-sort" class="form-control form-control-sm">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="name">Name A-Z</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-success btn-sm" id="upload-new-images">
                                <i class="fa fa-upload"></i> Upload New Images
                            </button>
                            <button type="button" class="btn btn-primary btn-sm" id="apply-filters">
                                <i class="fa fa-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="clear-filters">
                                <i class="fa fa-times"></i> Clear
                            </button>
                            <button type="button" class="btn btn-info btn-sm" id="debug-gallery" title="Debug Gallery">
                                <i class="fa fa-bug"></i> Debug
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Upload Area (Hidden by default) -->
                <div id="upload-area" class="upload-area mb-3" style="display: none;">
                    <div class="upload-zone">
                        <input type="file" id="gallery-file-input" multiple accept="image/*" style="display: none;">
                        <div class="upload-content" onclick="document.getElementById('gallery-file-input').click();">
                            <i class="fa fa-cloud-upload-alt fa-2x mb-2"></i>
                            <p>Click to select images or drag and drop</p>
                            <small>Supports: JPEG, PNG, GIF, WebP (Max 5MB each)</small>
                        </div>
                    </div>
                    <div class="upload-progress" id="upload-progress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Content -->
                <div id="gallery-content">
                    <div class="text-center">
                        <i class="fa fa-spinner fa-spin fa-2x"></i>
                        <p>Loading images...</p>
                    </div>
                </div>

                <!-- Pagination -->
                <div id="gallery-pagination" class="text-center mt-3" style="display: none;">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="load-more-images">
                        <i class="fa fa-plus"></i> Load More Images
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="select-image" disabled>
                    <i class="fa fa-check"></i> Select Image
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Image Gallery Modal Styles */
.gallery-controls {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border: 1px solid #dee2e6;
}

.upload-area {
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 5px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
}

.upload-area.dragover {
    border-color: #007bff;
    background: #e7f3ff;
    transform: scale(1.02);
}

.upload-zone {
    cursor: pointer;
}

.upload-content {
    color: #6c757d;
}

.upload-content:hover {
    color: #007bff;
}

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.date-group {
    margin-bottom: 30px;
}

.date-header {
    background: #007bff;
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    margin-bottom: 15px;
    display: inline-block;
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 10px;
}

.gallery-image {
    position: relative;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #f8f9fa;
}

.gallery-image:hover {
    border-color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
}

.gallery-image.selected {
    border-color: #28a745;
    background: #d4edda;
}

.gallery-image img {
    width: 100%;
    height: 120px;
    object-fit: cover;
    display: block;
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    color: white;
    opacity: 0;
    transition: opacity 0.3s ease;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    font-size: 12px;
    padding: 5px;
}

.gallery-image:hover .image-overlay {
    opacity: 1;
}

.image-actions {
    position: absolute;
    top: 5px;
    right: 5px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.gallery-image:hover .image-actions {
    opacity: 1;
}

.delete-image-btn {
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    font-size: 10px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.delete-image-btn:hover {
    background: #c82333;
}

.selected-indicator {
    position: absolute;
    top: 5px;
    left: 5px;
    background: #28a745;
    color: white;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    font-size: 12px;
    display: none;
    align-items: center;
    justify-content: center;
}

.gallery-image.selected .selected-indicator {
    display: flex;
}

.image-upload-container .upload-options {
    margin-top: 10px;
}

.image-upload-container .upload-options .btn {
    margin-right: 5px;
}

.image-preview-container {
    text-align: center;
    margin-top: 10px;
}

.empty-gallery {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

.empty-gallery i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .modal-xl {
        max-width: 95%;
        margin: 10px auto;
    }
    
    .gallery-controls .row > div {
        margin-bottom: 10px;
    }
    
    .image-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 8px;
    }
    
    .gallery-image img {
        height: 100px;
    }
    
    .gallery-controls {
        padding: 10px;
    }
    
    .upload-area {
        padding: 15px;
    }
    
    .modal-body {
        padding: 15px;
    }
}
</style>

<script>
$(document).ready(function() {
    let selectedImageId = null;
    let currentPage = 1;
    let isLoading = false;
    let totalPages = 1;

    // Open gallery modal with proper event handling
    $('#browse-gallery-btn').on('click', function() {
        $('#imageGalleryModal').modal({
            backdrop: 'static',
            keyboard: true,
            show: true
        });
        
        // Load images after modal is fully shown
        $('#imageGalleryModal').on('shown.bs.modal', function() {
            loadGalleryImages(1, true);
        });
    });

    // Handle modal close events
    $('#imageGalleryModal').on('hidden.bs.modal', function() {
        // Reset modal state
        selectedImageId = null;
        currentPage = 1;
        $('#select-image').prop('disabled', true);
        $('#upload-area').hide();
    });

    // Remove selected image
    $('.remove-selected-image').on('click', function() {
        $('#gallery_image').val('');
        $('#selected-image-preview').hide();
        $('#no-image-placeholder').show();
        selectedImageId = null;
    });



    // Toggle upload area
    $('#upload-new-images').on('click', function() {
        $('#upload-area').toggle();
    });

    // Gallery file input
    $('#gallery-file-input').on('change', function(e) {
        if (e.target.files && e.target.files.length > 0) {
            uploadImages(e.target.files);
        }
    });

    // Apply filters
    $('#apply-filters').on('click', function() {
        loadGalleryImages(1, true);
    });

    // Clear filters
    $('#clear-filters').on('click', function() {
        $('#gallery-search').val('');
        $('#gallery-date-from').val('');
        $('#gallery-date-to').val('');
        $('#gallery-sort').val('newest');
        loadGalleryImages(1, true);
    });

    // Debug gallery function
    $('#debug-gallery').on('click', function() {
        console.log('=== GALLERY DEBUG INFO ===');
        console.log('Current page:', currentPage);
        console.log('Total pages:', totalPages);
        console.log('Is loading:', isLoading);
        console.log('Selected image ID:', selectedImageId);
        
        // Test direct API call
        $.get('/image-gallery/images?debug=1', function(response) {
            console.log('Debug API response:', response);
            alert('Debug info logged to console. Check developer tools.');
        }).fail(function(xhr) {
            console.error('Debug API failed:', xhr);
            alert('Debug API failed. Status: ' + xhr.status + '. Check console for details.');
        });
    });

    // Apply filters on Enter key
    $('#gallery-search, #gallery-date-from, #gallery-date-to').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            loadGalleryImages(1, true);
        }
    });

    // Load more images
    $('#load-more-images').on('click', function() {
        if (currentPage < totalPages && !isLoading) {
            loadGalleryImages(currentPage + 1, false);
        }
    });

    // Select image
    $('#select-image').on('click', function() {
        if (selectedImageId) {
            selectGalleryImage(selectedImageId);
        }
    });

    // Load gallery images with improved error handling
    function loadGalleryImages(page = 1, reset = false) {
        isLoading = true;
        $('#apply-filters').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');
        
        const params = {
            page: page,
            per_page: 30,
            search: $('#gallery-search').val(),
            date_from: $('#gallery-date-from').val(),
            date_to: $('#gallery-date-to').val(),
            sort: $('#gallery-sort').val()
        };

        console.log('Loading gallery images with params:', params);

        // Try the image gallery API first, fallback to a simple file list
        $.ajax({
            url: '/image-gallery/images',
            method: 'GET',
            data: params,
            timeout: 10000, // 10 second timeout
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                console.log('Gallery API response:', response);
                handleGalleryResponse(response, reset);
            },
            error: function(xhr, status, error) {
                console.error('Primary gallery endpoint failed:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    error: error,
                    responseText: xhr.responseText
                });
                
                // Try to parse error response for more details
                try {
                    const errorData = JSON.parse(xhr.responseText);
                    console.error('Parsed error response:', errorData);
                } catch(e) {
                    console.error('Could not parse error response');
                }
                
                // Fallback: try alternative endpoint or show manual upload option
                tryFallbackGallery(params, reset);
            },
            complete: function() {
                isLoading = false;
                $('#apply-filters').prop('disabled', false).html('<i class="fa fa-search"></i> Apply Filters');
            }
        });
    }

    // Handle successful gallery response
    function handleGalleryResponse(response, reset) {
        console.log('Processing gallery response:', response);
        
        if (response && response.success) {
            const groupedImages = response.grouped_images || {};
            let galleryHtml = '';
            
            if (reset) {
                $('#gallery-content').empty();
                currentPage = 1;
            }
            
            console.log('Grouped images data:', groupedImages);
            console.log('Number of date groups:', Object.keys(groupedImages).length);
            
            // Check if we have any images
            if (Object.keys(groupedImages).length === 0) {
                galleryHtml = '<div class="empty-gallery text-center py-4">';
                galleryHtml += '<i class="fa fa-images fa-3x text-muted mb-3"></i>';
                galleryHtml += '<h5>No Images Found</h5>';
                galleryHtml += '<p>No images found for your search criteria.</p>';
                galleryHtml += '<div class="alert alert-info mt-3">';
                galleryHtml += '<strong>Debugging Info:</strong><br>';
                galleryHtml += 'Business ID in session: Check browser console<br>';
                galleryHtml += 'Total date groups returned: ' + Object.keys(groupedImages).length + '<br>';
                galleryHtml += 'Try uploading some images first or contact support if this persists.';
                galleryHtml += '</div>';
                galleryHtml += '</div>';
            } else {
                // Loop through each date group
                Object.keys(groupedImages).forEach(function(date) {
                    const images = groupedImages[date] || [];
                    
                    console.log('Processing date group:', date, 'with', images.length, 'images');
                    
                    if (images.length > 0) {
                        galleryHtml += '<div class="date-group mb-4">';
                        galleryHtml += '<div class="date-header">' + date + ' (' + images.length + ' images)</div>';
                        galleryHtml += '<div class="image-grid">';
                        
                        images.forEach(function(image) {
                            if (image && (image.display_url || image.url || image.file_path)) {
                                // Try multiple URL sources for better compatibility
                                let imageUrl = image.display_url || image.url || ('/storage/' + image.file_path) || ('/uploads/' + image.file_name);
                                
                                galleryHtml += '<div class="gallery-image" data-image-id="' + image.id + '" data-file-name="' + (image.file_name || '') + '">';
                                galleryHtml += '<img src="' + imageUrl + '" alt="' + (image.display_name || image.file_name || 'Image') + '" loading="lazy" onerror="this.style.display=\'none\'; this.nextElementSibling.style.display=\'flex\';">';
                                galleryHtml += '<div class="image-error" style="display: none; height: 120px; background: #f8f9fa; border: 1px dashed #dee2e6; justify-content: center; align-items: center; flex-direction: column;"><i class="fa fa-exclamation-triangle text-warning"></i><small>Image not found</small></div>';
                                galleryHtml += '<div class="selected-indicator"><i class="fa fa-check"></i></div>';
                                galleryHtml += '<div class="image-actions">';
                                galleryHtml += '<button type="button" class="delete-image-btn" data-image-id="' + image.id + '" title="Delete Image">';
                                galleryHtml += '<i class="fa fa-trash"></i></button></div>';
                                galleryHtml += '<div class="image-overlay">';
                                galleryHtml += '<div><strong>' + (image.display_name || image.file_name || 'Unknown') + '</strong></div>';
                                galleryHtml += '<div>' + (image.created_at || '') + '</div>';
                                if (image.size || image.file_size_human) {
                                    galleryHtml += '<div>' + (image.size || image.file_size_human) + '</div>';
                                }
                                galleryHtml += '</div></div>';
                            }
                        });
                        
                        galleryHtml += '</div></div>';
                    }
                });
            }
            
            $('#gallery-content').html(galleryHtml);
            
            // Update pagination if present
            if (response.pagination) {
                updatePagination(response.pagination);
                currentPage = response.pagination.current_page;
                totalPages = response.pagination.last_page;
            }
            
            // Bind click events
            bindGalleryImageEvents();
        } else {
            console.error('Gallery API returned error:', response);
            const errorMsg = response && response.message ? response.message : 'Unknown error';
            $('#gallery-content').html('<div class="alert alert-danger">Failed to load images: ' + errorMsg + '<br><small>Check browser console for more details.</small></div>');
        }
    }

    // Fallback gallery function
    function tryFallbackGallery(params, reset) {
        // Show fallback message and upload option
        let fallbackHtml = '<div class="alert alert-warning">';
        fallbackHtml += '<h5><i class="fa fa-exclamation-triangle"></i> Gallery Service Unavailable</h5>';
        fallbackHtml += '<p>The image gallery service is currently unavailable. This might be due to:</p>';
        fallbackHtml += '<ul>';
        fallbackHtml += '<li>CloudFlare tunnel configuration issues</li>';
        fallbackHtml += '<li>Missing image gallery routes</li>';
        fallbackHtml += '<li>Server connectivity problems</li>';
        fallbackHtml += '</ul>';
        fallbackHtml += '<p><strong>You can still upload images directly:</strong></p>';
        fallbackHtml += '<button type="button" class="btn btn-primary" onclick="$(\'#upload-area\').show(); $(this).hide();">';
        fallbackHtml += '<i class="fa fa-upload"></i> Upload New Images';
        fallbackHtml += '</button>';
        fallbackHtml += '</div>';
        
        $('#gallery-content').html(fallbackHtml);
    }

    // Bind gallery image events
    function bindGalleryImageEvents() {
        // Image selection
        $('.gallery-image').off('click').on('click', function(e) {
            if ($(e.target).hasClass('delete-image-btn') || $(e.target).parent().hasClass('delete-image-btn')) {
                return;
            }

            $('.gallery-image').removeClass('selected');
            $(this).addClass('selected');
            
            selectedImageId = $(this).data('image-id');
            $('#select-image').prop('disabled', false);
        });

        // Delete image
        $('.delete-image-btn').off('click').on('click', function(e) {
            e.stopPropagation();
            const imageId = $(this).data('image-id');
            
            if (confirm('Are you sure you want to delete this image?')) {
                deleteImage(imageId);
            }
        });
    }

    // Upload images
    function uploadImages(files) {
        const formData = new FormData();
        
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }
        
        // Add CSRF token
        formData.append('_token', $('meta[name="csrf-token"]').attr('content'));

        $('#upload-progress').show();
        $('.progress-bar').css('width', '0%');

        $.ajax({
            url: '/image-gallery/upload',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = evt.loaded / evt.total * 100;
                        $('.progress-bar').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                console.log('Upload response:', response);
                if (response && response.success) {
                    showSuccess(response.message || 'Images uploaded successfully');
                    $('#gallery-file-input').val('');
                    $('#upload-area').hide();
                    loadGalleryImages(1, true);
                } else {
                    showError(response.message || 'Upload failed');
                }
            },
            error: function(xhr, status, error) {
                console.log('Upload error:', error, 'Status:', xhr.status);
                if (xhr.status === 404) {
                    showError('Upload feature not configured. Please set up media upload routes.');
                } else if (xhr.status === 413) {
                    showError('File size too large. Please upload smaller images.');
                } else if (xhr.status === 422) {
                    showError('Invalid file type. Please upload only image files.');
                } else {
                    showError('Upload failed: ' + error);
                }
            },
            complete: function() {
                $('#upload-progress').hide();
                $('.progress-bar').css('width', '0%');
            }
        });
    }

    // Delete image
    function deleteImage(imageId) {
        $.ajax({
            url: '/image-gallery/delete/' + imageId,
            method: 'DELETE',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                console.log('Delete response:', response);
                if (response && response.success) {
                    $(`.gallery-image[data-image-id="${imageId}"]`).fadeOut(300, function() {
                        $(this).remove();
                    });
                    showSuccess('Image deleted successfully');
                } else {
                    showError(response.message || 'Delete failed');
                }
            },
            error: function(xhr, status, error) {
                console.log('Delete error:', error, 'Status:', xhr.status);
                if (xhr.status === 404) {
                    showError('Delete feature not configured.');
                } else {
                    showError('Error deleting image: ' + error);
                }
            }
        });
    }

    // Select gallery image
    function selectGalleryImage(imageId) {
        const selectedImage = $(`.gallery-image[data-image-id="${imageId}"]`);
        const imageUrl = selectedImage.find('img').attr('src');
        const imageName = selectedImage.data('file-name');
        
        // Set the gallery image hidden field
        $('#gallery_image').val(imageName);
        
        // Show preview
        $('#selected-image').attr('src', imageUrl);
        $('#selected-image-preview').show();
        $('#no-image-placeholder').hide();
        
        $('#imageGalleryModal').modal('hide');
        showSuccess('Image selected successfully');
    }

    // Update pagination
    function updatePagination(pagination) {
        if (!pagination) {
            $('#gallery-pagination').hide();
            return;
        }
        
        if (pagination.has_more) {
            $('#gallery-pagination').show();
            $('#load-more-images').text(`Load More (${pagination.current_page || currentPage}/${pagination.last_page || totalPages})`);
        } else {
            $('#gallery-pagination').hide();
        }
    }

    // Utility functions
    function showSuccess(message) {
        if (typeof toastr !== 'undefined') {
            toastr.success(message);
        } else {
            alert(message);
        }
    }

    function showError(message) {
        if (typeof toastr !== 'undefined') {
            toastr.error(message);
        } else {
            alert('Error: ' + message);
        }
    }

    // Drag and drop functionality
    $('#upload-area').on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    $('#upload-area').on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    $('#upload-area').on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            uploadImages(files);
        }
    });
});
</script>
@endsection
