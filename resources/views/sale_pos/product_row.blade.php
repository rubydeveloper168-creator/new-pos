@php
$common_settings = session()->get('business.common_settings');
$multiplier = 1;
$action = !empty($action) ? $action : '';
@endphp

@foreach($sub_units as $key => $value)
	@if(!empty($product->sub_unit_id) && $product->sub_unit_id == $key)
		@php $multiplier = $value['multiplier']; @endphp
	@endif
@endforeach

<tr class="product_row" data-row_index="{{$row_count}}" data-product-id="{{$product->product_id}}" data-variation-id="{{$product->variation_id}}" @if(!empty($so_line)) data-so_id="{{$so_line->transaction_id}}" @endif>

@php
	/* ── Image resolution ── */
	$imageUrl = null;
	$mediaItems = $product->media ?? [];
	$mediaCollection = $mediaItems instanceof \Illuminate\Support\Collection ? $mediaItems : collect(is_array($mediaItems) ? $mediaItems : (!empty($mediaItems) ? [$mediaItems] : []));
	$media = $mediaCollection->first();

	$variationRelation = $product->variation ?? null;
	$variationMediaItems = [];
	if ($variationRelation instanceof \Illuminate\Support\Collection) {
		$firstVariation = $variationRelation->first();
		if ($firstVariation) { $variationMediaItems = is_object($firstVariation) ? ($firstVariation->media ?? []) : ($firstVariation['media'] ?? []); }
	} elseif (is_object($variationRelation) && property_exists($variationRelation, 'media')) {
		$variationMediaItems = $variationRelation->media;
	} elseif (is_array($variationRelation) && array_key_exists('media', $variationRelation)) {
		$variationMediaItems = $variationRelation['media'];
	}
	$variationMediaCollection = $variationMediaItems instanceof \Illuminate\Support\Collection ? $variationMediaItems : collect(is_array($variationMediaItems) ? $variationMediaItems : (!empty($variationMediaItems) ? [$variationMediaItems] : []));
	$variationMedia = $variationMediaCollection->first();

	$candidateSources = [
		$product->image ?? null,
		$product->image_full_url ?? null,
		$product->product_image_url ?? null,
		$product->product_image ?? null,
	];
	if (!empty($product->featured_image)) { $candidateSources[] = $product->featured_image; }
	if ($media) {
		$candidateSources[] = $media->display_url ?? null;
		$candidateSources[] = $media->original_url ?? null;
		if (method_exists($media, 'getUrl')) { $candidateSources[] = $media->getUrl(); }
	}
	if ($variationMedia) {
		$candidateSources[] = $variationMedia->display_url ?? null;
		$candidateSources[] = $variationMedia->original_url ?? null;
		if (method_exists($variationMedia, 'getUrl')) { $candidateSources[] = $variationMedia->getUrl(); }
	}
	foreach ($candidateSources as $candidate) {
		if (empty($candidate) || is_array($candidate) || is_object($candidate)) continue;
		$candidate = trim($candidate);
		if ($candidate === '') continue;
		$lower = strtolower($candidate);
		if (strpos($lower,'default.png')!==false || strpos($lower,'no-image')!==false || strpos($lower,'placeholder')!==false) continue;
		if (preg_match('/^(https?:)?\/\//i', $candidate)) {
			$imageUrl = $candidate;
		} else {
			if (strpos($candidate, '/') === false) { $candidate = 'uploads/img/' . $candidate; }
			$normalized = ltrim($candidate, '/');
			if (rawurldecode($normalized) === $normalized) {
				$segments = array_map('rawurlencode', explode('/', $normalized));
				$normalized = implode('/', $segments);
			}
			$imageUrl = asset($normalized);
		}
		if (!empty($imageUrl)) break;
	}
	$previewTitle = strip_tags($product->product_name . ' ' . $product->sub_sku);

	/* ── Tax / price / discount ── */
	$hide_tax = 'hide';
	if(session()->get('business.enable_inline_tax') == 1){ $hide_tax = ''; }
	$tax_id = $product->tax_id;
	$item_tax = !empty($product->item_tax) ? $product->item_tax : 0;
	$unit_price_inc_tax = $product->sell_price_inc_tax;
	if($hide_tax == 'hide'){ $tax_id = null; $unit_price_inc_tax = $product->default_sell_price; }
	if(!empty($so_line) && $action !== 'edit'){
		$tax_id = $so_line->tax_id;
		$item_tax = $so_line->item_tax;
		$unit_price_inc_tax = $so_line->unit_price_inc_tax;
	}
	$discount_type = !empty($product->line_discount_type) ? $product->line_discount_type : 'fixed';
	$discount_amount = !empty($product->line_discount_amount) ? $product->line_discount_amount : 0;
	if(!empty($discount)){ $discount_type = $discount->discount_type; $discount_amount = $discount->discount_amount; }
	if(!empty($so_line) && $action !== 'edit'){ $discount_type = $so_line->line_discount_type; $discount_amount = $so_line->line_discount_amount; }
	$sell_line_note = !empty($product->sell_line_note) ? $product->sell_line_note : '';
	if(!empty($so_line)){ $sell_line_note = $so_line->sell_line_note; }
	$warranty_id = !empty($action) && $action == 'edit' && !empty($product->warranties->first()) ? $product->warranties->first()->id : $product->warranty_id;
	if($discount_type == 'fixed'){ $discount_amount = $discount_amount * $multiplier; }
@endphp

	{{-- ══ COLUMN 1: Image + Product + Description ══ --}}
	<td style="vertical-align:middle;">
		<div style="display:flex;align-items:flex-start;gap:10px;">

			{{-- Thumbnail --}}
			<div style="flex:0 0 40px;width:40px;height:40px;border-radius:5px;overflow:hidden;background:#f4f6f9;display:flex;align-items:center;justify-content:center;border:1px solid #eee;">
				@if(!empty($imageUrl))
					<img src="{{ e($imageUrl) }}" alt="{{ e($previewTitle) }}"
						style="width:40px;height:40px;object-fit:cover;cursor:pointer;"
						loading="lazy"
						onclick='window.showImagePreview && window.showImagePreview(@json($imageUrl), @json($previewTitle));'
						onerror="this.parentElement.innerHTML='<i class=\'fa fa-image\' style=\'color:#ccc;font-size:16px;\'></i>'">
				@else
					<i class="fa fa-image" style="color:#ccc;font-size:16px;"></i>
				@endif
			</div>

			{{-- Product info + description --}}
			<div style="flex:1;min-width:0;">
				@if(!empty($so_line))
					<input type="hidden" name="products[{{$row_count}}][so_line_id]" value="{{$so_line->id}}">
				@endif
				@if(!empty($discount))
					{!! Form::hidden("products[$row_count][discount_id]", $discount->id) !!}
				@endif

				@php
					$product_name = $product->product_name;
					if(!empty($product->brand)){ $product_name .= ' · ' . $product->brand; }
				@endphp

				{{-- Product name --}}
				@if(($edit_price || $edit_discount) && empty($is_direct_sell))
					<div title="@lang('lang_v1.pos_edit_product_price_help')">
						<span class="text-link text-info cursor-pointer" data-toggle="modal" data-target="#row_edit_product_price_modal_{{$row_count}}" style="font-weight:600;font-size:13px;">
							{{ $product_name }}&nbsp;<i class="fa fa-info-circle fa-xs"></i>
						</span>
					</div>
				@else
					<div style="font-weight:600;font-size:13px;line-height:1.3;">{{ $product_name }}</div>
				@endif

				{{-- SKU --}}
				<div style="font-size:11px;color:#999;margin-top:1px;">{{ $product->sub_sku }}</div>

				{{-- Description (compact, 2 lines max) --}}
				@if(!empty($product->product_description))
					@php
						$desc_text = trim(strip_tags($product->product_description));
					@endphp
					@if(!empty($desc_text))
						<div style="font-size:11px;color:#888;margin-top:3px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;" title="{{ $desc_text }}">
							{{ $desc_text }}
						</div>
					@endif
				@endif

				{{-- Note (direct sell) --}}
				@if(!empty($is_direct_sell))
					<textarea class="form-control" name="products[{{$row_count}}][sell_line_note]" rows="1" style="margin-top:4px;font-size:12px;" placeholder="@lang('lang_v1.sell_line_description_help')">{{$sell_line_note}}</textarea>
				@endif

				{{-- Modifiers --}}
				@if(in_array('modifiers', $enabled_modules))
					<div class="modifiers_html">
						@if(!empty($product->product_ms))
							@include('restaurant.product_modifier_set.modifier_for_product', ['edit_modifiers'=>true,'row_count'=>$row_count,'product_ms'=>$product->product_ms])
						@endif
					</div>
				@endif

				{{-- Lot numbers --}}
				@if(session()->get('business.enable_lot_number')==1 || session()->get('business.enable_product_expiry')==1)
					@if(!empty($product->lot_numbers) && empty($is_sales_order))
						<select class="form-control input-sm lot_number" name="products[{{$row_count}}][lot_no_line_id]" style="margin-top:4px;" @if(!empty($product->transaction_sell_lines_id)) disabled @endif>
							<option value="">@lang('lang_v1.lot_n_expiry')</option>
							@foreach($product->lot_numbers as $lot_number)
								@php
									$selected = '';
									$exp_enabled = session()->get('business.enable_product_expiry');
									$lot_enabled = session()->get('business.enable_lot_number');
									$expiry_text = '';
									$lot_no_line_id = !empty($product->lot_no_line_id) ? $product->lot_no_line_id : '';
									if($lot_number->purchase_line_id == $lot_no_line_id){ $selected = 'selected'; }
									if(!empty($purchase_line_id) && $purchase_line_id == $lot_number->purchase_line_id){ $selected = 'selected'; }
									if($exp_enabled==1 && !empty($lot_number->exp_date)){
										if(\Carbon::now()->gt(\Carbon::createFromFormat('Y-m-d',$lot_number->exp_date))){ $expiry_text = '('.__('report.expired').')'; }
									}
								@endphp
								<option value="{{$lot_number->purchase_line_id}}"
									data-qty_available="{{$lot_number->qty_available}}"
									data-msg-max="@lang('lang_v1.quantity_error_msg_in_lot', ['qty'=>$lot_number->qty_formated,'unit'=>$product->unit])"
									{{$selected}}>
									@if(!empty($lot_number->lot_number) && $lot_enabled==1){{$lot_number->lot_number}} @endif
									@if($lot_enabled==1 && $exp_enabled==1) - @endif
									@if($exp_enabled==1 && !empty($lot_number->exp_date)) @lang('product.exp_date'): {{@format_date($lot_number->exp_date)}} @endif
									{{$expiry_text}}
								</option>
							@endforeach
						</select>
					@endif
				@endif

				{{-- Hidden inputs --}}
				<input type="hidden" class="enable_sr_no" value="{{$product->enable_sr_no}}">
				<input type="hidden" name="products[{{$row_count}}][product_type]" class="product_type" value="{{$product->product_type}}">
				@if(!empty($product->transaction_sell_lines_id))
					<input type="hidden" name="products[{{$row_count}}][transaction_sell_lines_id]" value="{{$product->transaction_sell_lines_id}}">
				@endif
				<input type="hidden" name="products[{{$row_count}}][product_id]" class="form-control product_id" value="{{$product->product_id}}">
				<input type="hidden" name="products[{{$row_count}}][variation_id]" class="row_variation_id" value="{{$product->variation_id}}">
				<input type="hidden" name="products[{{$row_count}}][enable_stock]" value="{{$product->enable_stock}}">
				<input type="hidden" name="products[{{$row_count}}][product_unit_id]" value="{{$product->unit_id}}">
				<input type="hidden" class="base_unit_multiplier" name="products[{{$row_count}}][base_unit_multiplier]" value="{{$multiplier}}">
				<input type="hidden" class="hidden_base_unit_sell_price" value="{{$product->default_sell_price / $multiplier}}">

				{{-- Combo hidden inputs --}}
				@if($product->product_type == 'combo' && !empty($product->combo_products))
					@foreach($product->combo_products as $k => $combo_product)
						@php
							if(isset($action) && $action == 'edit'){
								$combo_product['qty_required'] = $combo_product['quantity'] / $product->quantity_ordered;
								$qty_total = $combo_product['quantity'];
							} else {
								$qty_total = $combo_product['qty_required'];
							}
						@endphp
						<input type="hidden" name="products[{{$row_count}}][combo][{{$k}}][product_id]" value="{{$combo_product['product_id']}}">
						<input type="hidden" name="products[{{$row_count}}][combo][{{$k}}][variation_id]" value="{{$combo_product['variation_id']}}">
						<input type="hidden" class="combo_product_qty" name="products[{{$row_count}}][combo][{{$k}}][quantity]" data-unit_quantity="{{$combo_product['qty_required']}}" value="{{$qty_total}}">
						@if(isset($action) && $action == 'edit')
							<input type="hidden" name="products[{{$row_count}}][combo][{{$k}}][transaction_sell_lines_id]" value="{{$combo_product['id']}}">
						@endif
					@endforeach
				@endif

				{{-- Edit price modal --}}
				@if(empty($is_direct_sell))
					<div class="modal fade row_edit_product_price_model" id="row_edit_product_price_modal_{{$row_count}}" tabindex="-1" role="dialog">
						@include('sale_pos.partials.row_edit_product_price_modal')
					</div>
				@endif
			</div>
		</div>
	</td>

	{{-- ══ COLUMN 2: Qty + Unit ══ --}}
	<td style="vertical-align:middle;white-space:nowrap;">
		@php
			$allow_decimal = true;
			if($product->unit_allow_decimal != 1){ $allow_decimal = false; }
			if(empty($product->quantity_ordered)){ $product->quantity_ordered = 1; }
			$max_quantity = $product->qty_available;
			$formatted_max_quantity = $product->formatted_qty_available;
			if(!empty($action) && $action == 'edit'){
				if(!empty($so_line)){
					$qty_available = $so_line->quantity - $so_line->so_quantity_invoiced + $product->quantity_ordered;
					$max_quantity = $qty_available;
					$formatted_max_quantity = number_format($qty_available, session('business.quantity_precision',2), session('currency')['decimal_separator'], session('currency')['thousand_separator']);
				}
			} else {
				if(!empty($so_line) && $so_line->qty_available <= $max_quantity){
					$max_quantity = $so_line->qty_available;
					$formatted_max_quantity = $so_line->formatted_qty_available;
				}
			}
			$max_qty_rule = $max_quantity;
			$max_qty_msg = __('validation.custom-messages.quantity_not_available', ['qty'=>$formatted_max_quantity,'unit'=>$product->unit]);
		@endphp

		@foreach($sub_units as $key => $value)
			@if(!empty($product->sub_unit_id) && $product->sub_unit_id == $key)
				@php
					$max_qty_rule = $max_qty_rule / $multiplier;
					$unit_name = $value['name'];
					$max_qty_msg = __('validation.custom-messages.quantity_not_available', ['qty'=>$max_qty_rule,'unit'=>$unit_name]);
					if(!empty($product->lot_no_line_id)){ $max_qty_msg = __('lang_v1.quantity_error_msg_in_lot', ['qty'=>$max_qty_rule,'unit'=>$unit_name]); }
					if($value['allow_decimal']){ $allow_decimal = true; }
				@endphp
			@endif
		@endforeach

		<div class="legacy-qty-inline">
			<div class="input-group input-number">
				<span class="input-group-btn">
					<button type="button" class="btn btn-default btn-flat quantity-down" style="padding:4px 8px;"><i class="fa fa-minus text-danger"></i></button>
				</span>
				<input type="text" data-min="1"
					class="form-control pos_quantity input_number mousetrap input_quantity"
					style="text-align:center;padding:4px;"
					value="{{@format_quantity($product->quantity_ordered)}}"
					name="products[{{$row_count}}][quantity]"
					data-allow-overselling="{{ empty($pos_settings['allow_overselling']) ? 'false' : 'true' }}"
					@if($allow_decimal) data-decimal=1 @else data-decimal=0 data-rule-abs_digit="true" data-msg-abs_digit="@lang('lang_v1.decimal_value_not_allowed')" @endif
					data-rule-required="true"
					data-msg-required="@lang('validation.custom-messages.this_field_is_required')"
					@if($product->enable_stock && empty($pos_settings['allow_overselling']) && empty($is_sales_order))
						data-rule-max-value="{{$max_qty_rule}}"
						data-qty_available="{{$product->qty_available}}"
						data-msg-max-value="{{$max_qty_msg}}"
						data-msg_max_default="@lang('validation.custom-messages.quantity_not_available', ['qty'=>$product->formatted_qty_available,'unit'=>$product->unit])"
					@endif>
				<span class="input-group-btn">
					<button type="button" class="btn btn-default btn-flat quantity-up" style="padding:4px 8px;"><i class="fa fa-plus text-success"></i></button>
				</span>
			</div>

			{{-- Unit --}}
			@if(count($sub_units) > 0)
				<select name="products[{{$row_count}}][sub_unit_id]" class="form-control input-sm sub_unit">
					@foreach($sub_units as $key => $value)
						<option value="{{$key}}" data-multiplier="{{$value['multiplier']}}" data-unit_name="{{$value['name']}}" data-allow_decimal="{{$value['allow_decimal']}}" @if(!empty($product->sub_unit_id) && $product->sub_unit_id == $key) selected @endif>
							{{$value['name']}}
						</option>
					@endforeach
				</select>
			@else
				<div class="legacy-unit-label">{{$product->unit}}</div>
			@endif
		</div>

		@if(!empty($product->second_unit))
			<div style="margin-top:4px;font-size:11px;color:#888;">@lang('lang_v1.quantity_in_second_unit', ['unit'=>$product->second_unit])*:</div>
			<input type="text" name="products[{{$row_count}}][secondary_unit_quantity]"
				value="{{@format_quantity($product->secondary_unit_quantity)}}"
				class="form-control input-sm input_number" style="width:120px;" required>
		@endif
	</td>

	{{-- ══ Service Staff (optional) ══ --}}
	@if(!empty($pos_settings['inline_service_staff']))
		<td style="vertical-align:middle;">
			{!! Form::select("products[$row_count][res_service_staff_id]", $waiters, !empty($product->res_service_staff_id) ? $product->res_service_staff_id : null,
				['class'=>'form-control select2 order_line_service_staff',
				 'placeholder'=>__('restaurant.select_service_staff'),
				 'required'=>(!empty($pos_settings['is_service_staff_required']) && $pos_settings['is_service_staff_required']==1)]) !!}
		</td>
	@endif

	{{-- ══ COLUMN 3: Unit Price ══ --}}
	@if(!empty($is_direct_sell))
		@php $pos_unit_price = !empty($product->unit_price_before_discount) ? $product->unit_price_before_discount : $product->default_sell_price;
		if(!empty($so_line) && $action!=='edit'){ $pos_unit_price = $so_line->unit_price_before_discount; } @endphp
		<td class="@if(!auth()->user()->can('edit_product_price_from_sale_screen')) hide @endif" style="vertical-align:middle;">
			<input type="text" name="products[{{$row_count}}][unit_price]"
				class="form-control pos_unit_price input_number mousetrap"
				value="{{@num_format($pos_unit_price)}}"
				@if(!empty($pos_settings['enable_msp'])) data-rule-min-value="{{$pos_unit_price}}" data-msg-min-value="{{__('lang_v1.minimum_selling_price_error_msg', ['price'=>@num_format($pos_unit_price)])}}" @endif>
			@if(!empty($last_sell_line))
				<small class="text-muted" style="font-size:11px;">@lang('lang_v1.prev_unit_price'): @format_currency($last_sell_line->unit_price_before_discount)</small>
			@endif
		</td>

		{{-- ══ COLUMN 4: Discount ══ --}}
		<td @if(!$edit_discount) class="hide" @endif style="vertical-align:middle;">
			<div class="legacy-discount-inline">
				{!! Form::text("products[$row_count][line_discount_amount]", @num_format($discount_amount), ['class'=>'form-control input_number row_discount_amount']) !!}
				{!! Form::select("products[$row_count][line_discount_type]", ['fixed'=>__('lang_v1.fixed'),'percentage'=>__('lang_v1.percentage')], $discount_type, ['class'=>'form-control input-sm row_discount_type']) !!}
			</div>
			@if(!empty($discount))
				<p class="help-block" style="font-size:11px;">{!! __('lang_v1.applied_discount_text', ['discount_name'=>$discount->name,'starts_at'=>$discount->formated_starts_at,'ends_at'=>$discount->formated_ends_at]) !!}</p>
			@endif
			@if(!empty($last_sell_line))
				<small class="text-muted" style="font-size:11px;">@lang('lang_v1.prev_discount'):
					@if($last_sell_line->line_discount_type=='percentage') {{@num_format($last_sell_line->line_discount_amount)}}%
					@else @format_currency($last_sell_line->line_discount_amount) @endif
				</small>
			@endif
		</td>

		{{-- Tax --}}
		<td class="text-center {{$hide_tax}}" style="vertical-align:middle;">
			{!! Form::hidden("products[$row_count][item_tax]", @num_format($item_tax), ['class'=>'item_tax']) !!}
			{!! Form::select("products[$row_count][tax_id]", $tax_dropdown['tax_rates'], $tax_id, ['placeholder'=>'Select','class'=>'form-control tax_id'], $tax_dropdown['attributes']) !!}
		</td>
	@endif

	{{-- Price inc tax --}}
	<td class="{{$hide_tax}}" style="vertical-align:middle;">
		<input type="text" name="products[{{$row_count}}][unit_price_inc_tax]"
			class="form-control pos_unit_price_inc_tax input_number"
			value="{{@num_format($unit_price_inc_tax)}}"
			@if(!$edit_price) readonly @endif
			@if(!empty($pos_settings['enable_msp'])) data-rule-min-value="{{$unit_price_inc_tax}}" data-msg-min-value="{{__('lang_v1.minimum_selling_price_error_msg', ['price'=>@num_format($unit_price_inc_tax)])}}" @endif>
	</td>

	{{-- Warranty --}}
	@if(!empty($common_settings['enable_product_warranty']) && !empty($is_direct_sell))
		<td class="pos-warranty-col" style="vertical-align:middle;">
			{!! Form::select("products[$row_count][warranty_id]", $warranties, $warranty_id, ['placeholder'=>__('messages.please_select'),'class'=>'form-control input-sm']) !!}
		</td>
	@endif

	{{-- ══ COLUMN 5: Subtotal ══ --}}
	<td class="text-right" style="vertical-align:middle;white-space:nowrap;font-weight:600;">
		@php $subtotal_type = !empty($pos_settings['is_pos_subtotal_editable']) ? 'text' : 'hidden'; @endphp
		<input type="{{$subtotal_type}}" class="form-control pos_line_total @if(!empty($pos_settings['is_pos_subtotal_editable'])) input_number @endif" value="{{@num_format($product->quantity_ordered*$unit_price_inc_tax)}}">
		<span class="display_currency pos_line_total_text @if(!empty($pos_settings['is_pos_subtotal_editable'])) hide @endif" data-currency_symbol="true">{{$product->quantity_ordered*$unit_price_inc_tax}}</span>
	</td>

	{{-- ══ Delete ══ --}}
	<td class="text-center" style="vertical-align:middle;">
		<i class="fa fa-times text-danger pos_remove_row cursor-pointer" aria-hidden="true"></i>
	</td>

</tr>
