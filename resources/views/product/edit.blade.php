@extends('layouts.app')
@section('title', __('product.edit_product'))

@section('content')

<style>
.unit-brand-label {
    display: block;
    width: 100%;
}
</style>

@php
  $is_image_required = !empty($common_settings['is_product_image_required']) && empty($product->image);
@endphp

<!-- Content Header (Page header) -->
<section class="content-header">
  
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.edit_product')</h1>
    <!-- <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
        <li class="active">Here</li>
    </ol> -->
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\ProductController::class, 'update'] , [$product->id] ), 'method' => 'PUT', 'id' => 'product_add_form',
        'class' => 'product_form', 'files' => true ]) !!}
    <input type="hidden" id="product_id" value="{{ $product->id }}">

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('name', __('product.product_name') . ':*') !!}
                  {!! Form::text('name', $product->name, ['class' => 'form-control', 'required',
                  'placeholder' => __('product.product_name')]); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('second_name', 'Second Name' . ':') !!}
                {!! Form::text('second_name', $product->second_name, ['class' => 'form-control',
                'placeholder' => 'Second Name']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('factory_name', 'Factory Name' . ':') !!}
                {!! Form::text('factory_name', $product->factory_name ?? null, ['class' => 'form-control',
                'placeholder' => 'Factory Name']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('sku', __('product.sku')  . ':*') !!} @show_tooltip(__('tooltip.sku'))
                {!! Form::text('sku', $product->sku, ['class' => 'form-control',
                'placeholder' => __('product.sku'), 'required']); !!}
              </div>
            </div>

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('barcode_type', __('product.barcode_type') . ':*') !!}
                  {!! Form::select('barcode_type', $barcode_types, $product->barcode_type, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2', 'required']); !!}
              </div>
            </div>

            <div class="clearfix"></div>
            
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('unit_id', __('product.unit') . ':*', ['class' => 'unit-brand-label']) !!}
                <div class="input-group">
                  {!! Form::select('unit_id', $units, $product->unit_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2', 'required']); !!}
                  <span class="input-group-btn">
                    <button type="button" @if(!auth()->user()->can('unit.create')) disabled @endif class="btn btn-default bg-white btn-flat quick_add_unit btn-modal" data-href="{{action([\App\Http\Controllers\UnitController::class, 'create'], ['quick_add' => true])}}" title="@lang('unit.add_unit')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                  </span>
                </div>
              </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_sub_units')) hide @endif">
              <div class="form-group">
                {!! Form::label('sub_unit_ids', __('lang_v1.related_sub_units') . ':') !!} @show_tooltip(__('lang_v1.sub_units_tooltip'))

                <select name="sub_unit_ids[]" class="form-control select2" multiple id="sub_unit_ids">
                  @foreach($sub_units as $sub_unit_id => $sub_unit_value)
                    <option value="{{$sub_unit_id}}" 
                      @if(is_array($product->sub_unit_ids) &&in_array($sub_unit_id, $product->sub_unit_ids))   selected 
                      @endif>{{$sub_unit_value['name']}}</option>
                  @endforeach
                </select>
              </div>
            </div>

            @if(!empty($common_settings['enable_secondary_unit']))
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('secondary_unit_id', __('lang_v1.secondary_unit') . ':') !!} @show_tooltip(__('lang_v1.secondary_unit_help'))
                        {!! Form::select('secondary_unit_id', $units, $product->secondary_unit_id, ['class' => 'form-control select2']); !!}
                    </div>
                </div>
            @endif

            <div class="col-sm-4 @if(!session('business.enable_brand')) hide @endif">
              <div class="form-group">
                {!! Form::label('brand_id', __('product.brand') . ':', ['class' => 'unit-brand-label']) !!}
                <div class="input-group">
                  {!! Form::select('brand_id', $brands, $product->brand_id, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2']); !!}
                  <span class="input-group-btn">
                    <button type="button" @if(!auth()->user()->can('brand.create')) disabled @endif class="btn btn-default bg-white btn-flat btn-modal" data-href="{{action([\App\Http\Controllers\BrandController::class, 'create'], ['quick_add' => true])}}" title="@lang('brand.add_brand')" data-container=".view_modal"><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
                  </span>
                </div>
              </div>
            </div>
            <div class="col-sm-4 @if(!session('business.enable_category')) hide @endif">
              <div class="form-group">
                {!! Form::label('hierarchical_category_selection', __('product.category') . ' (All Levels):') !!}
                <small class="text-muted">Select from any level - supports L1 → L2 → L3 → L4 → L5</small>
                <select name="hierarchical_category_selection" id="hierarchical_category_id" class="form-control select2">
                    <option value="">{{ __('messages.please_select') }}</option>
                    @foreach($hierarchical_categories as $cat_id => $cat_name)
                        <option value="{{ $cat_id }}" {{ $current_hierarchical_category == $cat_id ? 'selected' : '' }}>
                            {{ $cat_name }}
                        </option>
                    @endforeach
                </select>
              </div>
            </div>

            <div class="col-sm-4">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div class="text-info">
                        <small><i class="fa fa-info-circle"></i> <strong>Category Levels:</strong><br>
                        L1 = Main Category<br>
                        L2 = Sub Category<br>
                        L3 = Sub-Sub Category<br>
                        L4 = Level 4 Category<br>
                        L5 = Level 5 Category</small>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for compatibility with existing system -->
            {!! Form::hidden('category_id', $product->category_id, ['id' => 'actual_category_id']) !!}
            {!! Form::hidden('sub_category_id', $product->sub_category_id, ['id' => 'sub_category_id']) !!}
            {!! Form::hidden('category_l1_id', $product->category_l1_id, ['id' => 'category_l1_id']) !!}
            {!! Form::hidden('category_l2_id', $product->category_l2_id, ['id' => 'category_l2_id']) !!}
            {!! Form::hidden('category_l3_id', $product->category_l3_id, ['id' => 'category_l3_id']) !!}
            {!! Form::hidden('category_l4_id', $product->category_l4_id, ['id' => 'category_l4_id']) !!}
            {!! Form::hidden('category_l5_id', $product->category_l5_id, ['id' => 'category_l5_id']) !!}

            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('product_locations', __('business.business_locations') . ':') !!} @show_tooltip(__('lang_v1.product_location_help'))
                  {!! Form::select('product_locations[]', $business_locations, $product->product_locations->pluck('id'), ['class' => 'form-control select2', 'multiple', 'id' => 'product_locations']); !!}
              </div>
            </div>

            <div class="clearfix"></div>
            
            <div class="col-sm-4">
              <div class="form-group">
              <br>
                <label>
                  {!! Form::checkbox('enable_stock', 1, $product->enable_stock, ['class' => 'input-icheck', 'id' => 'enable_stock']); !!} <strong>@lang('product.manage_stock')</strong>
                </label>@show_tooltip(__('tooltip.enable_stock')) <p class="help-block"><i>@lang('product.enable_stock_help')</i></p>
              </div>
            </div>
            <div class="col-sm-4" id="alert_quantity_div" @if(!$product->enable_stock) style="display:none" @endif>
              <div class="form-group">
                {!! Form::label('alert_quantity', __('product.alert_quantity') . ':') !!} @show_tooltip(__('tooltip.alert_quantity'))
                {!! Form::text('alert_quantity', $alert_quantity, ['class' => 'form-control input_number',
                'placeholder' => __('product.alert_quantity') , 'min' => '0']); !!}
              </div>
            </div>
            @if(!empty($common_settings['enable_product_warranty']))
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('warranty_id', __('lang_v1.warranty') . ':') !!}
                {!! Form::select('warranty_id', $warranties, $product->warranty_id, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]); !!}
              </div>
            </div>
            @endif
            <!-- include module fields -->
            @if(!empty($pos_module_data))
                @foreach($pos_module_data as $key => $value)
                    @if(!empty($value['view_path']))
                        @includeIf($value['view_path'], ['view_data' => $value['view_data']])
                    @endif
                @endforeach
            @endif
            <div class="clearfix"></div>
            <div class="col-sm-8">
              <div class="form-group">
                {!! Form::label('product_description', __('lang_v1.product_description') . ':') !!}
                  {!! Form::textarea('product_description', $product->product_description, ['class' => 'form-control']); !!}
              </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('image', __('lang_v1.product_image') . ':') !!}
                
                <!-- Hidden field for gallery selected image -->
                <input type="hidden" id="gallery_image" name="gallery_image" value="">
                
                <!-- Gallery Browse Section -->
                <div class="well well-sm" style="padding: 10px; margin-bottom: 10px;">
                  <!-- Image Preview Container -->
                  <div class="image-preview-container text-center" id="selected-image-preview" @if(empty($product->image)) style="display: none;" @endif>
                    <img id="selected-image" src="@if(!empty($product->image)){{ asset('uploads/img/' . $product->image) }}@endif" alt="Selected Image" class="img-thumbnail" style="max-width: 150px; max-height: 150px; margin-bottom: 10px;">
                    <br>
                    <button type="button" class="btn btn-danger btn-xs remove-selected-image">
                      <i class="fa fa-trash"></i> Remove Image
                    </button>
                  </div>
                  
                  <!-- Gallery Browse Button -->
                  <div class="text-center" id="gallery-browse-section">
                    <div class="mb-2" id="no-image-placeholder" @if(!empty($product->image)) style="display: none;" @endif>
                      <i class="fa fa-images fa-2x text-muted"></i>
                      <p class="text-muted">Browse from gallery to select product image</p>
                    </div>
                    <button type="button" class="btn btn-primary btn-block" id="browse-gallery-btn">
                      <i class="fa fa-images"></i> Browse Gallery
                    </button>
                  </div>
                </div>
                
                <small><p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]). @lang('lang_v1.aspect_ratio_should_be_1_1') @if(!empty($product->image)) <br> @lang('lang_v1.previous_image_will_be_replaced') @endif</p></small>
              </div>
            </div>
            </div>
            <div class="col-sm-4">
              <div class="form-group">
                {!! Form::label('product_brochure', __('lang_v1.product_brochure') . ':') !!}
                {!! Form::file('product_brochure', ['id' => 'product_brochure', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
                <small>
                    <p class="help-block">
                        @lang('lang_v1.previous_file_will_be_replaced')<br>
                        @lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
                        @includeIf('components.document_help_text')
                    </p>
                </small>
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
                @php
                  $disabled = false;
                  $disabled_period = false;
                  if( empty($product->expiry_period_type) || empty($product->enable_stock) ){
                    $disabled = true;
                  }
                  if( empty($product->enable_stock) ){
                    $disabled_period = true;
                  }
                @endphp
                  {!! Form::label('expiry_period', __('product.expires_in') . ':') !!}<br>
                  {!! Form::text('expiry_period', @num_format($product->expiry_period), ['class' => 'form-control pull-left input_number',
                    'placeholder' => __('product.expiry_period'), 'style' => 'width:60%;', 'disabled' => $disabled]); !!}
                  {!! Form::select('expiry_period_type', ['months'=>__('product.months'), 'days'=>__('product.days'), '' =>__('product.not_applicable') ], $product->expiry_period_type, ['class' => 'form-control select2 pull-left', 'style' => 'width:40%;', 'id' => 'expiry_period_type', 'disabled' => $disabled_period]); !!}
              </div>
            </div>
          </div>
          @endif
          <div class="col-sm-4">
            <div class="checkbox">
              <label>
                {!! Form::checkbox('enable_sr_no', 1, $product->enable_sr_no, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.enable_imei_or_sr_no')</strong>
              </label>
              @show_tooltip(__('lang_v1.tooltip_sr_no'))
            </div>
          </div>

          <div class="col-sm-4">
          <div class="form-group">
            <br>
            <label>
              {!! Form::checkbox('not_for_selling', 1, $product->not_for_selling, ['class' => 'input-icheck']); !!} <strong>@lang('lang_v1.not_for_selling')</strong>
            </label> @show_tooltip(__('lang_v1.tooltip_not_for_selling'))
          </div>
        </div>

        <div class="clearfix"></div>

        <!-- Rack, Row & position number -->
        @if(session('business.enable_racks') || session('business.enable_row') || session('business.enable_position') || session('business.enable_level'))
          <div class="col-md-12">
            <h4>@lang('lang_v1.rack_details'):
              @show_tooltip(__('lang_v1.tooltip_rack_details'))
            </h4>
          </div>
          @foreach($business_locations as $id => $location)
            <div class="col-sm-3">
              <div class="form-group">
                {!! Form::label('rack_' . $id,  $location . ':') !!}

                
                  @if(!empty($rack_details[$id]))
                    @if(session('business.enable_racks'))
                      {!! Form::text('product_racks_update[' . $id . '][rack]', $rack_details[$id]['rack'], ['class' => 'form-control', 'id' => 'rack_' . $id]); !!}
                    @endif

                    @if(session('business.enable_row'))
                      {!! Form::text('product_racks_update[' . $id . '][row]', $rack_details[$id]['row'], ['class' => 'form-control']); !!}
                    @endif

                    @if(session('business.enable_position'))
                      {!! Form::text('product_racks_update[' . $id . '][position]', $rack_details[$id]['position'], ['class' => 'form-control']); !!}
                    @endif

                    @if(session('business.enable_level'))
                      {!! Form::text('product_racks_update[' . $id . '][level]', $rack_details[$id]['level'], ['class' => 'form-control']); !!}
                    @endif
                  @else
                    @if(session('business.enable_racks'))
                      {!! Form::text('product_racks[' . $id . '][rack]', null, ['class' => 'form-control', 'id' => 'rack_' . $id, 'placeholder' => __('lang_v1.rack')]); !!}
                    @endif

                    @if(session('business.enable_row'))
                      {!! Form::text('product_racks[' . $id . '][row]', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.row')]); !!}
                    @endif

                    @if(session('business.enable_position'))
                      {!! Form::text('product_racks[' . $id . '][position]', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.position')]); !!}
                    @endif

                    @if(session('business.enable_level'))
                      {!! Form::text('product_racks[' . $id . '][level]', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.level')]); !!}
                    @endif
                  @endif

              </div>
            </div>
          @endforeach
        @endif


        <div class="col-sm-4">
          <div class="form-group">
            {!! Form::label('weight',  __('lang_v1.weight') . ':') !!}
            {!! Form::text('weight', $product->weight, ['class' => 'form-control', 'placeholder' => __('lang_v1.weight')]); !!}
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
                            <input type="{{$cf_type}}" name="{{$db_field_name}}" id="{{$db_field_name}}" 
                            value="{{$product->$db_field_name}}" class="form-control" placeholder="{{$cf}}">
                        @elseif($cf_type == 'dropdown')
                            {!! Form::select($db_field_name, $dropdown, $product->$db_field_name, ['placeholder' => $cf, 'class' => 'form-control select2']); !!}
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        <div class="col-sm-3">
          <div class="form-group">
            {!! Form::label('preparation_time_in_minutes',  __('lang_v1.preparation_time_in_minutes') . ':') !!}
            {!! Form::number('preparation_time_in_minutes', $product->preparation_time_in_minutes, ['class' => 'form-control', 'placeholder' => __('lang_v1.preparation_time_in_minutes')]); !!}
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
                  {!! Form::select('tax', $taxes, $product->tax, ['placeholder' => __('messages.please_select'), 'class' => 'form-control select2'], $tax_attributes); !!}
              </div>
            </div>

            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif">
              <div class="form-group">
                {!! Form::label('tax_type', __('product.selling_price_tax_type') . ':*') !!}
                  {!! Form::select('tax_type',['inclusive' => __('product.inclusive'), 'exclusive' => __('product.exclusive')], $product->tax_type,
                  ['class' => 'form-control select2', 'required']); !!}
              </div>
            </div>

            <div class="clearfix"></div>
      @if(!session('business.enable_price_tax')) hide @endif asdasas
            <div class="col-sm-4 @if(!session('business.enable_price_tax')) hide @endif"">
              <div class="form-group">
                {!! Form::label('type', __('product.product_type') . ':*') !!} @show_tooltip(__('tooltip.product_type'))
                {!! Form::select('type', $product_types, $product->type, ['class' => 'form-control select2',
                  'required', 'data-action' => 'edit', 'data-product_id' => $product->id ]); !!}
              </div>
            </div>

            <div class="form-group col-sm-12" id="product_form_part"></div>
            <input type="hidden" id="variation_counter" value="0">
            <input type="hidden" id="default_profit_percent" value="{{ $default_profit_percent }}">
            </div>
    @endcomponent

  <div class="row">
    <input type="hidden" name="submit_type" id="submit_type">
        <div class="col-sm-12">
          <div class="text-center">
            <div class="btn-group">
              @if($selling_price_group_count)
                <button type="submit" value="submit_n_add_selling_prices" class="tw-dw-btn tw-dw-btn-warning tw-text-white tw-dw-btn-lg submit_product_form">@lang('lang_v1.save_n_add_selling_price_group_prices')</button>
              @endif

              @can('product.opening_stock')
              <button type="submit" @if(empty($product->enable_stock)) disabled="true" @endif id="opening_stock_button" value="update_n_edit_opening_stock" class="tw-dw-btn tw-text-white tw-dw-btn-lg bg-purple submit_product_form">@lang('lang_v1.update_n_edit_opening_stock')</button>
              @endif

              <button type="submit" value="save_n_add_another" class="tw-dw-btn tw-text-white tw-dw-btn-lg bg-maroon submit_product_form">@lang('lang_v1.update_n_add_another')</button>

              <button type="submit" value="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-lg submit_product_form">@lang('messages.update')</button>
            </div>
            @can('product.opening_stock')
            <div id="opening_stock_warning" class="text-danger" style="margin-top: 8px; @if(!empty($product->enable_stock)) display: none; @endif">
              กรุณาเลือก "Manage Stock?" ให้เป็น Yes ก่อน
            </div>
            @endcan
          </div>
        </div>
  </div>
{!! Form::close() !!}
</section>
<!-- /.content -->

@endsection

@section('javascript')
  <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>

  <script>
  $(document).ready(function () {
      var $typeSelect = $('select#type');
      if ($typeSelect.length) {
          $typeSelect.prop('disabled', false);

          if ($typeSelect.data('select2')) {
              $typeSelect.select2('destroy');
          }

          $typeSelect.select2();
      }

      // Opening stock button + warning tied to Manage Stock?
      function updateOpeningStockState() {
          if ($('#enable_stock').is(':checked')) {
              $('#opening_stock_button').prop('disabled', false).removeClass('disabled');
              $('#opening_stock_warning').hide();
          } else {
              $('#opening_stock_button').prop('disabled', true).addClass('disabled');
              $('#opening_stock_warning').show();
          }
      }

      var $unitSelect = $('#unit_id');
      if ($unitSelect.length) {
          $unitSelect.find('option[value=""]').slice(1).remove();
      }

      $('#enable_stock').on('ifChanged', function() {
          updateOpeningStockState();
      });

      updateOpeningStockState();
  });
  </script>

  <!-- Image Gallery Modal -->
  <div class="modal fade" id="imageGalleryModal" tabindex="-1" role="dialog" aria-labelledby="imageGalleryModalLabel" aria-hidden="true">
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

                  <!-- Direct Upload Section (shown when gallery fails) -->
                  <div id="direct-upload-section" style="display: none;" class="text-center p-4 border rounded">
                      <h5><i class="fa fa-upload"></i> Direct Image Upload</h5>
                      <p class="text-muted">Gallery feature is not available. Use direct upload instead.</p>
                      <input type="file" id="direct-image-input" accept="image/*" class="form-control mb-3">
                      <button type="button" class="btn btn-primary" id="direct-upload-btn">
                          <i class="fa fa-upload"></i> Upload & Select Image
                      </button>
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

  <script type="text/javascript">
    $(document).ready( function(){
      __page_leave_confirmation('#product_add_form');
      
      // Handle hierarchical category selection
      $(document).on('change', '#hierarchical_category_id', function() {
          var selectedCategoryId = $(this).val();
          console.log('Selected category ID:', selectedCategoryId);
          
          if (selectedCategoryId) {
              // Get category hierarchy information via AJAX
              var csrfToken = $('meta[name="csrf-token"]').attr('content');
              console.log('CSRF Token:', csrfToken);
              
              if (!csrfToken) {
                  console.error('CSRF token not found!');
                  alert('CSRF token not found. Please refresh the page.');
                  return;
              }
              
              $.ajax({
                  url: '/products/get_category_hierarchy',
                  method: 'POST',
                  data: {
                      category_id: selectedCategoryId,
                      _token: csrfToken
                  },
                  success: function(response) {
                      console.log('Category hierarchy response:', response);
                      if (response.success) {
                          // Clear all category fields first
                          $('#actual_category_id').val('');
                          $('#sub_category_id').val('');
                          $('#category_l1_id').val('');
                          $('#category_l2_id').val('');
                          $('#category_l3_id').val('');
                          $('#category_l4_id').val('');
                          $('#category_l5_id').val('');
                          
                          // Set the appropriate category fields based on hierarchy
                          if (response.category_level) {
                              // Set the multi-level category fields
                              if (response.category_level >= 1 && response.l1_id) {
                                  $('#category_l1_id').val(response.l1_id);
                              }
                              if (response.category_level >= 2 && response.l2_id) {
                                  $('#category_l2_id').val(response.l2_id);
                              } else {
                                  $('#category_l2_id').val('');
                              }
                              if (response.category_level >= 3 && response.l3_id) {
                                  $('#category_l3_id').val(response.l3_id);
                              } else {
                                  $('#category_l3_id').val('');
                              }
                              if (response.category_level >= 4 && response.l4_id) {
                                  $('#category_l4_id').val(response.l4_id);
                              } else {
                                  $('#category_l4_id').val('');
                              }
                              if (response.category_level >= 5 && response.l5_id) {
                                  $('#category_l5_id').val(response.l5_id);
                              } else {
                                  $('#category_l5_id').val('');
                              }
                          }
                          
                          // Set legacy fields for backward compatibility
                          if (response.is_main_category) {
                              // This is a main category (L1)
                              $('#actual_category_id').val(selectedCategoryId);
                              $('#sub_category_id').val('');
                              console.log('Set as main category - category_id:', selectedCategoryId);
                          } else {
                              // This is a subcategory (L2+)
                              $('#actual_category_id').val(response.root_category_id);
                              $('#sub_category_id').val(selectedCategoryId);
                              console.log('Set as subcategory - category_id:', response.root_category_id, 'sub_category_id:', selectedCategoryId);
                          }
                          
                          console.log('Final field values:');
                          console.log('L1:', $('#category_l1_id').val());
                          console.log('L2:', $('#category_l2_id').val());
                          console.log('L3:', $('#category_l3_id').val());
                          console.log('L4:', $('#category_l4_id').val());
                          console.log('L5:', $('#category_l5_id').val());
                      } else {
                          console.error('Category hierarchy request failed:', response.message);
                          alert('Failed to load category hierarchy: ' + (response.message || 'Unknown error'));
                      }
                  },
                  error: function(xhr, status, error) {
                      console.error('Error getting category hierarchy:', error);
                      console.error('Status:', status);
                      console.error('Response:', xhr.responseText);
                      console.error('Status Code:', xhr.status);
                      
                      // Show user-friendly error message
                      if (xhr.status === 419) {
                          alert('Session expired. Please refresh the page and try again.');
                      } else if (xhr.status === 500) {
                          alert('Server error occurred. Please check the console for details.');
                      } else {
                          alert('Error loading category hierarchy. Please try again.');
                      }
                  }
              });
          } else {
              // Clear all category fields if no selection
              $('#actual_category_id').val('');
              $('#sub_category_id').val('');
              $('#category_l1_id').val('');
              $('#category_l2_id').val('');
              $('#category_l3_id').val('');
              $('#category_l4_id').val('');
              $('#category_l5_id').val('');
          }
      });
      
      // Debug form submission
      $('#product_add_form').on('submit', function(e) {
          console.log('Form submission - Category field values:');
          console.log('category_id:', $('#actual_category_id').val());
          console.log('sub_category_id:', $('#sub_category_id').val());
          console.log('category_l1_id:', $('#category_l1_id').val());
          console.log('category_l2_id:', $('#category_l2_id').val());
          console.log('category_l3_id:', $('#category_l3_id').val());
          console.log('category_l4_id:', $('#category_l4_id').val());
          console.log('category_l5_id:', $('#category_l5_id').val());
      });
    });
  </script>

  <!-- Gallery JavaScript functionality -->
  <script>
  $(document).ready(function() {
      let selectedImageId = null;
      let currentPage = 1;
      let isLoading = false;
      let totalPages = 1;

      // Open gallery modal
      $('#browse-gallery-btn').on('click', function() {
          $('#imageGalleryModal').modal('show');
          loadGalleryImages(1, true);
      });

      // Alternative: Simple file input for direct upload when gallery isn't available
      $('#gallery-file-input').on('change', function(e) {
          if (e.target.files && e.target.files.length > 0) {
              // For now, just select the first file and show it
              const file = e.target.files[0];
              if (file && file.type.startsWith('image/')) {
                  const reader = new FileReader();
                  reader.onload = function(e) {
                      $('#selected-image').attr('src', e.target.result);
                      $('#selected-image-preview').show();
                      $('#no-image-placeholder').hide();
                      $('#gallery_image').val(file.name);
                      showSuccess('Image selected: ' + file.name);
                  };
                  reader.readAsDataURL(file);
              } else {
                  showError('Please select a valid image file');
              }
          }
      });

      // Direct upload functionality
      $('#direct-upload-btn').on('click', function() {
          const fileInput = $('#direct-image-input')[0];
          if (fileInput.files && fileInput.files.length > 0) {
              const file = fileInput.files[0];
              if (file && file.type.startsWith('image/')) {
                  const reader = new FileReader();
                  reader.onload = function(e) {
                      $('#selected-image').attr('src', e.target.result);
                      $('#selected-image-preview').show();
                      $('#no-image-placeholder').hide();
                      $('#gallery_image').val(file.name);
                      $('#imageGalleryModal').modal('hide');
                      showSuccess('Image selected: ' + file.name);
                  };
                  reader.readAsDataURL(file);
              } else {
                  showError('Please select a valid image file');
              }
          } else {
              showError('Please select an image file first');
          }
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

      // Load gallery images
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

          $.ajax({
              url: '{{ route("image-gallery.images") }}',
              method: 'GET',
              data: params,
              success: function(response) {
                  console.log('Gallery response:', response);
                  if (response && response.success) {
                      currentPage = page;
                      totalPages = response.pagination ? response.pagination.last_page : 1;
                      
                      // The backend returns 'images' but the frontend expects 'grouped_images'
                      const groupedImages = response.grouped_images || response.images || {};
                      
                      renderGalleryImages(groupedImages, reset);
                      updatePagination(response.pagination || {has_more: false});
                  } else {
                      // If no proper response, show empty gallery
                      renderGalleryImages({}, reset);
                      updatePagination({has_more: false});
                  }
              },
              error: function(xhr, status, error) {
                  console.log('Gallery load error:', error, 'Status:', xhr.status);
                  if (xhr.status === 404) {
                      // Route doesn't exist, show direct upload option
                      $('#gallery-content').hide();
                      $('#direct-upload-section').show();
                      showError('Gallery feature not configured. Use direct upload below.');
                  } else {
                      showError('Error loading gallery images: ' + error);
                      renderGalleryImages({}, reset);
                  }
              },
              complete: function() {
                  isLoading = false;
                  $('#apply-filters').prop('disabled', false).html('<i class="fa fa-search"></i> Apply Filters');
              }
          });
      }

      // Render gallery images
      function renderGalleryImages(groupedImages, reset = false) {
          if (reset) {
              $('#gallery-content').empty();
          }

          // Safety check for groupedImages
          if (!groupedImages || typeof groupedImages !== 'object') {
              console.log('Invalid groupedImages data:', groupedImages);
              groupedImages = {};
          }

          if (Object.keys(groupedImages).length === 0) {
              if (reset) {
                  $('#gallery-content').html(`
                      <div class="empty-gallery">
                          <i class="fa fa-images"></i>
                          <h4>No images found</h4>
                          <p>Upload new images using the "Upload New Images" button above or check if the gallery feature is properly configured.</p>
                      </div>
                  `);
              }
              return;
          }

          Object.keys(groupedImages).forEach(date => {
              const images = groupedImages[date];
              if (!Array.isArray(images)) {
                  console.log('Invalid images array for date:', date, images);
                  return;
              }
              
              const dateGroup = $(`
                  <div class="date-group">
                      <div class="date-header">${date}</div>
                      <div class="image-grid"></div>
                  </div>
              `);

              images.forEach(image => {
                  if (!image || !image.id) {
                      console.log('Invalid image data:', image);
                      return;
                  }
                  
                  const imageElement = $(`
                      <div class="gallery-image" data-image-id="${image.id}" data-file-name="${image.file_name || 'unknown'}">
                          <img src="${image.display_url || image.url || image.file_path || ''}" alt="${image.alt_text || image.file_name || 'Image'}">
                          <div class="image-overlay">
                              <div>${image.file_name || 'Unknown'}</div>
                              <div>${image.size || 'Unknown size'}</div>
                              <div>${image.created_at || 'Unknown date'}</div>
                          </div>
                          <div class="image-actions">
                              <button type="button" class="delete-image-btn" data-image-id="${image.id}" title="Delete Image">
                                  <i class="fa fa-times"></i>
                              </button>
                          </div>
                          <div class="selected-indicator">
                              <i class="fa fa-check"></i>
                          </div>
                      </div>
                  `);

                  dateGroup.find('.image-grid').append(imageElement);
              });

              $('#gallery-content').append(dateGroup);
          });

          // Bind click events
          bindGalleryImageEvents();
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
              url: '{{ route("image-gallery.upload") }}',
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
                      showSuccess(response.message || `${files.length} images uploaded successfully`);
                      loadGalleryImages(1, true);
                      $('#upload-area').hide();
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
              url: '{{ route("image-gallery.delete", ":id") }}'.replace(':id', imageId),
              method: 'DELETE',
              data: {
                  _token: $('meta[name="csrf-token"]').attr('content')
              },
              success: function(response) {
                  console.log('Delete response:', response);
                  if (response && response.success) {
                      showSuccess('Image deleted successfully');
                      $(`.gallery-image[data-image-id="${imageId}"]`).fadeOut(300, function() {
                          $(this).remove();
                      });
                  } else {
                      showError(response.message || 'Delete failed');
                  }
              },
              error: function(xhr, status, error) {
                  console.log('Delete error:', error, 'Status:', xhr.status);
                  if (xhr.status === 404) {
                      showError('Delete feature not configured.');
                  } else {
                      showError('Delete failed: ' + error);
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
              alert(message);
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
