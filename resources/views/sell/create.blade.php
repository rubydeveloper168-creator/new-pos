@extends('layouts.app')

@php
	if (!empty($status) && $status == 'quotation') {
		$title = __('lang_v1.add_quotation');
	} else if (!empty($status) && $status == 'draft') {
		$title = __('lang_v1.add_draft');
	} else {
		$title = __('sale.add_sale');
	}

	if($sale_type == 'sales_order') {
		$title = __('lang_v1.sales_order');
	}
@endphp

@section('title', $title)

@section('content')

@php
	$walk_in_customer = $walk_in_customer ?? [];
	$pos_settings = $pos_settings ?? [];
@endphp

<style>
/* Fix Select2 search field styling */
.select2-search__field {
    background-color: white !important;
    color: black !important;
    box-shadow: none !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.select2-search--dropdown .select2-search__field {
    background-color: white !important;
    color: black !important;
    border: 1px solid #ddd !important;
    box-shadow: none !important;
    width: 100% !important;
    box-sizing: border-box !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: white !important;
    color: black !important;
    box-shadow: none !important;
}

/* Ensure proper z-index for dropdowns */
.select2-container--open {
    z-index: 9999 !important;
}

.select2-dropdown {
    z-index: 9999 !important;
}

/* Removed position:relative from .form-group — it caused stacking context that clipped Select2 dropdowns */

/* Ensure Select2 container takes full width */
#status + .select2-container,
#invoice_scheme_id + .select2-container,
#price_group + .select2-container {
    width: 100% !important;
    display: block !important;
}

#status + .select2-container .select2-selection,
#invoice_scheme_id + .select2-container .select2-selection,
#price_group + .select2-container .select2-selection {
    width: 100% !important;
}

/* Fix customer dropdown - container sits between icon and button in input-group */
#customer_id + .select2-container {
    flex: 1 1 auto;
    width: 100% !important;
}

/* Force select2 selection to full width inside input-group */
.input-group .select2-container .select2-selection {
    width: 100% !important;
}

/* Customer dropdown will be sized by JavaScript to match container */

/* Force status/invoice/price group dropdowns to match select width */
#status + .select2-container.select2-container--open .select2-dropdown,
#invoice_scheme_id + .select2-container.select2-container--open .select2-dropdown,
#price_group + .select2-container.select2-container--open .select2-dropdown {
    width: 100% !important;
    box-sizing: border-box !important;
}

/* Customer dropdown width/position handled via JS on select2:open */

/* Hide billing address preview under customer field */
#billing_address_block {
    display: none !important;
}

/* Add more height/spacing to the top form section */
#add_sell_form .box-solid > .box-body {
    padding: 20px 15px 30px 15px !important;
    min-height: 280px;
}

#add_sell_form .box-solid .form-group {
    margin-bottom: 20px;
}

/* Disable customer outstanding balance warning - allow all customers to create bills */
.contact_due_text {
    display: none !important;
}

/* Customer Source radio buttons */
.customer-source-option {
    transition: all 0.15s ease;
}
.customer-source-option:hover {
    border-color: #3c8dbc !important;
    background: #f0f8ff !important;
}
.customer-source-option input[type="radio"]:checked ~ span {
    font-weight: 600;
    color: #fff;
}
.customer-source-option:has(input:checked) {
    border-color: #3c8dbc !important;
    background: #3c8dbc !important;
    color: #fff !important;
}
.customer-source-option:has(input:checked) span {
    color: #fff;
}

/* Fix select2 dropdowns in contact modal */
.contact_modal .form-group {
    position: relative;
}

.contact_modal .input-group .select2-container {
    flex: 1 1 auto;
}

/* Dropdown width will be set by JavaScript */

/* Product search dropdown with images */
.ui-autocomplete {
    box-sizing: border-box;
}
.pos-image-col {
    width: 60px;
}
.ui-autocomplete .pos-search-item {
    display: flex;
    gap: 8px;
    align-items: flex-start;
}
.ui-autocomplete .pos-search-img {
    width: 32px;
    height: 32px;
    object-fit: cover;
    border: 1px solid #ddd;
    border-radius: 3px;
    background: #fff;
    flex: 0 0 auto;
}
.ui-autocomplete .pos-search-text {
    flex: 1 1 auto;
}

/* Screenshot-matching legacy skin */
.content-header h1 {
    display: none;
}
.content {
    background: #e9e9e9;
    padding: 10px 15px;
}
#add_sell_form .box.box-solid {
    border: 1px solid #cfcfcf;
    box-shadow: none;
    background: #e9e9e9;
}
#add_sell_form .box.box-solid > .box-body {
    background: #e9e9e9;
    padding: 14px;
}
#add_sell_form .form-control {
    border: 1px solid #bfc3c7;
    border-radius: 0;
    box-shadow: none;
    height: 31px;
}
#add_sell_form textarea.form-control {
    height: auto;
}
#add_sell_form label {
    font-weight: 700;
    color: #333;
}
.legacy-sale-title {
    border: 1px solid #cfcfcf;
    background: #efefef;
    margin: -14px -14px 0 -14px;
    padding: 9px 14px;
}
.legacy-sale-title h3 {
    margin: 0;
    color: #3c8dbc;
    font-size: 28px;
    font-weight: 700;
}
.legacy-sale-intro {
    margin: 0 -14px 14px -14px;
    padding: 10px 14px;
    border: 1px solid #cfcfcf;
    border-top: 0;
    background: #efefef;
    color: #333;
}
.legacy-warning-strip {
    border: 1px solid #e9ddb6;
    background: #f7f1df;
    color: #7a6326;
    font-weight: 700;
    padding: 8px 12px;
    margin-bottom: 14px;
}
.legacy-search-row {
    width: 100%;
    margin-left: 0 !important;
}
.legacy-search-row,
.pos_product_div {
    padding-left: 0 !important;
    padding-right: 0 !important;
}
.legacy-search-row .form-group {
    margin-bottom: 0 !important;
}
.legacy-search-shell {
    display: flex;
    align-items: center;
    border: 1px solid #d2d2d2;
    background: #ececec;
    padding: 8px;
}
.legacy-search-left {
    width: 36px;
    height: 42px;
    border: 1px solid #c6c6c6;
    border-right: 0;
    border-radius: 0 !important;
    background: #e4e4e4;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0;
    padding: 0;
}
.legacy-search-left .fa {
    font-size: 18px;
}
.legacy-search-input-wrap {
    flex: 1;
}
.legacy-search-input {
    height: 42px !important;
    border: 1px solid #c6c6c6 !important;
    border-radius: 0 !important;
    background: #f5f5f5;
    padding: 8px 14px;
    box-shadow: none !important;
    font-size: 18px;
}
.legacy-search-input:focus {
    border-color: #5aa7e6 !important;
}
.legacy-search-input::placeholder {
    color: #666;
    opacity: 1;
}
.legacy-search-actions {
    display: flex;
    align-items: center;
    gap: 0;
    margin-left: 0;
}
.legacy-search-plus {
    width: 38px;
    height: 42px;
    border-radius: 0 !important;
    border: 1px solid #c6c6c6;
    border-left: 0;
    background: #e9e9e9;
    color: #428bca;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.legacy-search-config {
    width: 48px;
    height: 42px;
    border-radius: 0 !important;
    border: 1px solid #c6c6c6;
    border-left: 0;
    background: #e9e9e9;
    color: #428bca;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}
.legacy-search-plus .fa,
.legacy-search-config .fa {
    font-size: 22px;
}
.customer-source-option {
    border-radius: 3px !important;
    padding: 4px 10px !important;
    border-color: #c6c9cc !important;
    background: #f9f9f9 !important;
}
.customer-source-option input[type="radio"] {
    margin-right: 4px !important;
}
#pos_table thead th {
    background: #428bca;
    color: #fff;
    border-color: #3f83ba;
    font-weight: 700;
}
#pos_table .legacy-qty-inline,
#pos_table .legacy-discount-inline {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: nowrap;
}
#pos_table .legacy-qty-inline .input-group {
    width: 120px;
    min-width: 120px;
}
#pos_table .legacy-qty-inline .sub_unit,
#pos_table .legacy-qty-inline .legacy-unit-label {
    width: 95px;
    min-width: 95px;
}
#pos_table .legacy-qty-inline .legacy-unit-label {
    font-size: 12px;
    color: #666;
    line-height: 1;
}
#pos_table .legacy-discount-inline .row_discount_amount {
    width: 95px;
    min-width: 95px;
}
#pos_table .legacy-discount-inline .row_discount_type {
    width: 88px;
    min-width: 88px;
}
#pos_table th.pos-warranty-col,
#pos_table td.pos-warranty-col {
    display: none !important;
}
.pos_product_div {
    height: auto !important;
    min-height: auto !important;
    max-height: none !important;
    overflow-y: visible !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
    width: 100%;
    clear: both;
    display: block;
}
.legacy-post-table-section {
    clear: both;
    margin-top: 8px;
    margin-left: 0 !important;
    margin-right: 0 !important;
}
#add_sell_form .input-group-addon {
    border-color: #bfc3c7;
    border-radius: 0;
    background: #e8e8e8;
}
#add_sell_form .input-group-btn .btn {
    border-radius: 0;
}
#add_sell_form .table {
    background: #fff;
}
#add_sell_form .table-bordered > thead > tr > th,
#add_sell_form .table-bordered > tbody > tr > td {
    border-color: #cfd3d6;
}
</style>

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>{{$title}}</h1>
</section>
<!-- Main content -->
<section class="content">
<input type="hidden" id="amount_rounding_method" value="{{$pos_settings['amount_rounding_method'] ?? ''}}">
@if(!empty($pos_settings['allow_overselling']))
	<input type="hidden" id="is_overselling_allowed">
@endif
@if(session('business.enable_rp') == 1)
    <input type="hidden" id="reward_point_enabled">
@endif
@if(count($business_locations) > 0)
<div class="row">
	<div class="col-sm-3">
		<div class="form-group">
			<div class="input-group">
				<span class="input-group-addon">
					<i class="fa fa-map-marker"></i>
				</span>
			{!! Form::select('select_location_id', $business_locations, $default_location->id ?? null, ['class' => 'form-control input-sm',
			'id' => 'select_location_id', 
			'required', 'autofocus'], $bl_attributes); !!}
			<span class="input-group-addon">
					@show_tooltip(__('tooltip.sale_location'))
				</span> 
			</div>
		</div>
	</div>
</div>
@endif

@php
	$custom_labels = json_decode(session('business.custom_labels'), true);
	$common_settings = session()->get('business.common_settings');
@endphp
<input type="hidden" id="item_addition_method" value="{{$business_details->item_addition_method}}">
	{!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'store']), 'method' => 'post', 'id' => 'add_sell_form', 'files' => true ]) !!}
	 @if(!empty($sale_type))
	 	<input type="hidden" id="sale_type" name="type" value="{{$sale_type}}">
	 @endif
	<div class="row">
		<div class="col-md-12 col-sm-12">
			@component('components.widget', ['class' => 'box-solid'])
				<div class="legacy-sale-title">
					<h3><i class="fa-fw fa fa-plus"></i> {{ $title }}</h3>
				</div>
				<div class="legacy-sale-intro">
					กรุณากรอกข้อมูลด้านล่าง
				</div>
				{!! Form::hidden('location_id', !empty($default_location) ? $default_location->id : null , ['id' => 'location_id', 'data-receipt_printer_type' => !empty($default_location->receipt_printer_type) ? $default_location->receipt_printer_type : 'browser', 'data-default_payment_accounts' => !empty($default_location) ? $default_location->default_payment_accounts : '']); !!}

				@if(!empty($price_groups))
					@if(count($price_groups) > 1)
						<div class="col-sm-4">
							<div class="form-group">
								<div class="input-group">
									<span class="input-group-addon">
										<i class="fas fa-money-bill-alt"></i>
									</span>
									@php
										reset($price_groups);
										$selected_price_group = !empty($default_price_group_id) && array_key_exists($default_price_group_id, $price_groups) ? $default_price_group_id : null;
									@endphp
									{!! Form::hidden('hidden_price_group', key($price_groups), ['id' => 'hidden_price_group']) !!}
									{!! Form::select('price_group', $price_groups, $selected_price_group, ['class' => 'form-control select2-price-group', 'id' => 'price_group']); !!}
									<span class="input-group-addon">
										@show_tooltip(__('lang_v1.price_group_help_text'))
									</span> 
								</div>
							</div>
						</div>
						
					@else
						@php
							reset($price_groups);
						@endphp
						{!! Form::hidden('price_group', key($price_groups), ['id' => 'price_group']) !!}
					@endif
				@endif

				{!! Form::hidden('default_price_group', null, ['id' => 'default_price_group']) !!}

				@if(in_array('types_of_service', $enabled_modules) && !empty($types_of_service))
					<div class="col-md-4 col-sm-6">
						<div class="form-group">
							<div class="input-group">
								<span class="input-group-addon">
									<i class="fa fa-external-link-square-alt text-primary service_modal_btn"></i>
								</span>
								{!! Form::select('types_of_service_id', $types_of_service, null, ['class' => 'form-control', 'id' => 'types_of_service_id', 'style' => 'width: 100%;', 'placeholder' => __('lang_v1.select_types_of_service')]); !!}

								{!! Form::hidden('types_of_service_price_group', null, ['id' => 'types_of_service_price_group']) !!}

								<span class="input-group-addon">
									@show_tooltip(__('lang_v1.types_of_service_help'))
								</span> 
							</div>
							<small><p class="help-block hide" id="price_group_text">@lang('lang_v1.price_group'): <span></span></p></small>
						</div>
					</div>
					<div class="modal fade types_of_service_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
				@endif
				
				@if(in_array('subscription', $enabled_modules))
					<div class="col-md-4 pull-right col-sm-6">
						<div class="checkbox">
							<label>
				              {!! Form::checkbox('is_recurring', 1, false, ['class' => 'input-icheck', 'id' => 'is_recurring']); !!} @lang('lang_v1.subscribe')?
				            </label><button type="button" data-toggle="modal" data-target="#recurringInvoiceModal" class="btn btn-link"><i class="fa fa-external-link"></i></button>@show_tooltip(__('lang_v1.recurring_invoice_help'))
						</div>
					</div>
				@endif
				<div class="clearfix"></div>
				<div class="col-sm-12">
					<div class="legacy-warning-strip">กรุณาเลือกสิ่งเหล่านี้ก่อนเพิ่มสินค้า</div>
				</div>
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('transaction_date', __('sale.sale_date') . ':*') !!}
						<div class="input-group">
							<span class="input-group-addon">
								<i class="fa fa-calendar"></i>
							</span>
							{!! Form::text('transaction_date', $default_datetime, ['class' => 'form-control', 'readonly', 'required']); !!}
						</div>
					</div>
				</div>
				@can('edit_invoice_number')
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('invoice_no', $sale_type == 'sales_order' ? __('restaurant.order_no') : __('sale.invoice_no') . ':') !!}
						{!! Form::text('invoice_no', null, ['class' => 'form-control', 'placeholder' => $sale_type == 'sales_order' ? __('restaurant.order_no') : __('sale.invoice_no')]); !!}
						<p class="help-block">@lang('lang_v1.keep_blank_to_autogenerate')</p>
					</div>
				</div>
				@endcan
				<div class="col-sm-4">
		          <div class="form-group">
		            <div class="multi-input">
		            @php
						$is_pay_term_required = !empty($pos_settings['is_pay_term_required'] ?? null);
					@endphp
		              {!! Form::label('pay_term_number', __('contact.pay_term') . ':') !!} @show_tooltip(__('tooltip.pay_term'))
		              <br/>
		              {!! Form::number('pay_term_number', $walk_in_customer['pay_term_number'] ?? null, ['class' => 'form-control width-40 pull-left', 'placeholder' => __('contact.pay_term'), 'required' => ($is_pay_term_required ?? false)]); !!}

		              {!! Form::select('pay_term_type',
		              	['months' => __('lang_v1.months'),
		              		'days' => __('lang_v1.days')],
		              		$walk_in_customer['pay_term_type'] ?? null,
		              	['class' => 'form-control width-60 pull-left','placeholder' => __('messages.please_select'), 'required' => ($is_pay_term_required ?? false)]); !!}
		            </div>
		          </div>
		        </div>

				<div class="clearfix"></div>

				@php
					// Always show status dropdown; when status is provided in URL (e.g. quotation)
					// use it as the selected value.
					$default_status = !empty($status) ? $status : 'proforma';
				@endphp
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('status', __('sale.status') . ':*') !!}
						{!! Form::select('status', $statuses, $default_status, ['class' => 'form-control select2-status', 'id' => 'status', 'placeholder' => __('messages.please_select'), 'required']); !!}
					</div>
				</div>
				@if(in_array($default_status, ['draft', 'quotation']))
					<input type="hidden" id="disable_qty_alert">
				@endif
				<div class="col-sm-4">
					<div class="form-group">
						{!! Form::label('contact_id', __('contact.customer') . ':*') !!}
						<div class="input-group">
							<span class="input-group-addon">
								<i class="fa fa-user"></i>
							</span>
							<input type="hidden" id="default_customer_id" 
							value="{{ $walk_in_customer['id'] ?? '' }}" >
							<input type="hidden" id="default_customer_name" 
							value="{{ $walk_in_customer['name'] ?? '' }}" >
							<input type="hidden" id="default_customer_balance" value="{{ $walk_in_customer['balance'] ?? '' }}" >
							<input type="hidden" id="default_customer_address" value="{{ $walk_in_customer['shipping_address'] ?? '' }}" >
							@if(!empty($walk_in_customer['price_calculation_type']) && $walk_in_customer['price_calculation_type'] == 'selling_price_group')
								<input type="hidden" id="default_selling_price_group" 
							value="{{ $walk_in_customer['selling_price_group_id'] ?? '' }}" >
							@endif
							{!! Form::select('contact_id', 
								[], null, ['class' => 'form-control mousetrap', 'id' => 'customer_id', 'placeholder' => 'Enter Customer name / phone / Tax ID', 'required']); !!}
							<span class="input-group-btn">
								<button type="button" class="btn btn-default bg-white btn-flat add_new_customer" data-name=""><i class="fa fa-plus-circle text-primary fa-lg"></i></button>
								<button type="button" class="btn btn-warning bg-white btn-flat edit_customer_btn" title="Edit Customer Details">
									<i class="fa fa-edit text-warning fa-lg"></i>
								</button>
							</span>
						</div>
						<small class="text-danger hide contact_due_text"><strong>@lang('account.customer_due'):</strong> <span></span></small>
						<!-- Tax ID lookup status messages -->
						<div id="tax_lookup_status" style="margin-top: 5px; display: none;">
							<small id="tax_lookup_loading" class="text-info" style="display: none;">
								<i class="fa fa-spinner fa-spin"></i> Looking up Tax ID...
							</small>
							<small id="tax_lookup_success" class="text-success" style="display: none;">
								<i class="fa fa-check"></i> <span id="company_found_name"></span>
							</small>
							<small id="tax_lookup_error" class="text-danger" style="display: none;">
								<i class="fa fa-times"></i> <span id="tax_lookup_error_msg"></span>
							</small>
						</div>
					</div>
					<div class="text-muted small hide" id="billing_address_block">
						<strong>
							@lang('lang_v1.billing_address'):
						</strong>
						<div id="billing_address_div">
							{!! $walk_in_customer['contact_address'] ?? '' !!}
						</div>
					</div>
				</div>
				@if($sale_type != 'sales_order')
					<div class="col-sm-4">
						<div class="form-group">
							{!! Form::label('invoice_scheme_id', __('invoice.invoice_scheme') . ':') !!}
							@php
								// Set default invoice scheme based on status
								// Default (no status or proforma): TAX-INVOICE (id: 4)
								// Quotation: Quotation (id: 1)
								// Final: BILLING-RECEIVE (id: 5)

								if (!empty($status) && $status == 'quotation') {
									// For quotation: use "Quotation (ใบเสนอราคา)" scheme (id: 1)
									$default_invoice_scheme_id = 1;
								} elseif (!empty($status) && $status == 'final') {
									// For final bills: use "BILLING-RECEIVE" scheme (id: 5)
									$default_invoice_scheme_id = 5;
								} else {
									// Default (proforma or no status): use "TAX-INVOICE ( ใบกำกับภาษี / ใบแจ้งหนี้ / )" scheme (id: 4)
									$default_invoice_scheme_id = 4;
								}
							@endphp
							{!! Form::select('invoice_scheme_id', $invoice_schemes, $default_invoice_scheme_id, ['class' => 'form-control select2-invoice-scheme', 'id' => 'invoice_scheme_id', 'placeholder' => __('messages.please_select')]); !!}
						</div>
					</div>
				@endif
				@if(!empty($commission_agent))
				@php
					$is_commission_agent_required = !empty($pos_settings['is_commission_agent_required']);
				@endphp
				<div class="col-sm-4">
					<div class="form-group">
					{!! Form::label('commission_agent', __('lang_v1.commission_agent') . ':') !!}
					{!! Form::select('commission_agent', 
								$commission_agent, null, ['class' => 'form-control select2', 'id' => 'commission_agent', 'required' => $is_commission_agent_required]); !!}
					</div>
				</div>
				@endif
				
				@php
			        $custom_field_1_label = !empty($custom_labels['sell']['custom_field_1']) ? $custom_labels['sell']['custom_field_1'] : '';

			        $is_custom_field_1_required = !empty($custom_labels['sell']['is_custom_field_1_required']) && $custom_labels['sell']['is_custom_field_1_required'] == 1 ? true : false;

			        $custom_field_2_label = !empty($custom_labels['sell']['custom_field_2']) ? $custom_labels['sell']['custom_field_2'] : '';

			        $is_custom_field_2_required = !empty($custom_labels['sell']['is_custom_field_2_required']) && $custom_labels['sell']['is_custom_field_2_required'] == 1 ? true : false;

			        $custom_field_3_label = !empty($custom_labels['sell']['custom_field_3']) ? $custom_labels['sell']['custom_field_3'] : '';

			        $is_custom_field_3_required = !empty($custom_labels['sell']['is_custom_field_3_required']) && $custom_labels['sell']['is_custom_field_3_required'] == 1 ? true : false;

			        $custom_field_4_label = !empty($custom_labels['sell']['custom_field_4']) ? $custom_labels['sell']['custom_field_4'] : '';

			        $is_custom_field_4_required = !empty($custom_labels['sell']['is_custom_field_4_required']) && $custom_labels['sell']['is_custom_field_4_required'] == 1 ? true : false;
		        @endphp
		        @if(!empty($custom_field_1_label))
		        	@php
		        		$label_1 = $custom_field_1_label . ':';
		        		if($is_custom_field_1_required) {
		        			$label_1 .= '*';
		        		}
		        	@endphp

		        	<div class="col-md-4">
				        <div class="form-group">
				            {!! Form::label('custom_field_1', $label_1 ) !!}
				            {!! Form::text('custom_field_1', null, ['class' => 'form-control','placeholder' => $custom_field_1_label, 'required' => $is_custom_field_1_required]); !!}
				        </div>
				    </div>
		        @endif
		        @if(!empty($custom_field_2_label))
		        	@php
		        		$label_2 = $custom_field_2_label . ':';
		        		if($is_custom_field_2_required) {
		        			$label_2 .= '*';
		        		}
		        	@endphp

		        	<div class="col-md-4">
				        <div class="form-group">
				            {!! Form::label('custom_field_2', $label_2 ) !!}
				            {!! Form::text('custom_field_2', null, ['class' => 'form-control','placeholder' => $custom_field_2_label, 'required' => $is_custom_field_2_required]); !!}
				        </div>
				    </div>
		        @endif
		        @if(!empty($custom_field_3_label))
		        	@php
		        		$label_3 = $custom_field_3_label . ':';
		        		if($is_custom_field_3_required) {
		        			$label_3 .= '*';
		        		}
		        	@endphp

		        	<div class="col-md-4">
				        <div class="form-group">
				            {!! Form::label('custom_field_3', $label_3 ) !!}
				            {!! Form::text('custom_field_3', null, ['class' => 'form-control','placeholder' => $custom_field_3_label, 'required' => $is_custom_field_3_required]); !!}
				        </div>
				    </div>
		        @endif
		        @if(!empty($custom_field_4_label))
		        	@php
		        		$label_4 = $custom_field_4_label . ':';
		        		if($is_custom_field_4_required) {
		        			$label_4 .= '*';
		        		}
		        	@endphp

		        	<div class="col-md-4">
				        <div class="form-group">
				            {!! Form::label('custom_field_4', $label_4 ) !!}
				            {!! Form::text('custom_field_4', null, ['class' => 'form-control','placeholder' => $custom_field_4_label, 'required' => $is_custom_field_4_required]); !!}
				        </div>
				    </div>
		        @endif
		        <div class="col-sm-3" style="display:none;">
	                <div class="form-group">
	                    {!! Form::label('upload_document', __('purchase.attach_document') . ':') !!}
	                    {!! Form::file('sell_document', ['id' => 'upload_document', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
	                    <p class="help-block">
	                    	@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
	                    	@includeIf('components.document_help_text')
	                    </p>
	                </div>
	            </div>
		        <div class="clearfix"></div>

		        @if((!empty($pos_settings['enable_sales_order']) && $sale_type != 'sales_order') || $is_order_request_enabled)
					<div class="col-sm-3">
						<div class="form-group">
							{!! Form::label('sales_order_ids', __('lang_v1.sales_order').':') !!}
							{!! Form::select('sales_order_ids[]', [], null, ['class' => 'form-control select2', 'multiple', 'id' => 'sales_order_ids']); !!}
						</div>
					</div>
					<div class="clearfix"></div>
				@endif
				<!-- Call restaurant module if defined -->
		        @if(in_array('tables' ,$enabled_modules) || in_array('service_staff' ,$enabled_modules))
		        	<span id="restaurant_module_span">
		        	</span>
		        @endif
					<div class="col-sm-12 legacy-search-row">
					<div class="form-group">
						<div class="legacy-search-shell">
							<button type="button" class="btn legacy-search-left" data-toggle="modal" data-target="#configure_search_modal" title="{{__('lang_v1.configure_product_search')}}">
								<i class="fa fa-barcode"></i>
							</button>
							<div class="legacy-search-input-wrap">
								{!! Form::text('search_product', null, ['class' => 'form-control mousetrap legacy-search-input', 'id' => 'search_product', 'placeholder' => 'โปรดเพิ่มสินค้าในรายการ',
								'disabled' => is_null($default_location)? true : false,
								'autofocus' => is_null($default_location)? false : true,
								]); !!}
							</div>
							<div class="legacy-search-actions">
								<button type="button" class="btn legacy-search-plus pos_add_quick_product" data-href="{{action([\App\Http\Controllers\ProductController::class, 'quickAdd'])}}" data-container=".quick_add_product_modal" title="{{ __('messages.add') }}">
									<i class="fa fa-plus-circle"></i>
								</button>
								<button type="button" class="btn legacy-search-config" data-toggle="modal" data-target="#configure_search_modal" title="{{__('lang_v1.configure_product_search')}}">
									<i class="fa fa-credit-card"></i>
								</button>
							</div>
						</div>
					</div>
				</div>

				<div class="pos_product_div">

					<input type="hidden" name="sell_price_tax" id="sell_price_tax" value="{{$business_details->sell_price_tax}}">

					<!-- Keeps count of product rows -->
					<input type="hidden" id="product_row_count" 
						value="0">
					@php
						$hide_tax = '';
						if( session()->get('business.enable_inline_tax') == 0){
							$hide_tax = 'hide';
						}
					@endphp
					<div class="table-responsive">
					<table class="table table-condensed table-bordered table-striped table-responsive" id="pos_table">
						<thead>
							<tr>
								<th class="text-center">	
									@lang('sale.product')
								</th>
								<th class="text-center">
									@lang('sale.qty')
								</th>
								@if(!empty($pos_settings['inline_service_staff']))
									<th class="text-center">
										@lang('restaurant.service_staff')
									</th>
								@endif
								<th class="@if(!auth()->user()->can('edit_product_price_from_sale_screen')) hide @endif">
									@lang('sale.unit_price')
								</th>
								<th class="@if(!auth()->user()->can('edit_product_discount_from_sale_screen')) hide @endif">
									@lang('receipt.discount')
								</th>
								<th class="text-center {{$hide_tax}}">
									@lang('sale.tax')
								</th>
								<th class="text-center {{$hide_tax}}">
									@lang('sale.price_inc_tax')
								</th>
								@if(!empty($common_settings['enable_product_warranty']))
									<th class="pos-warranty-col">@lang('lang_v1.warranty')</th>
								@endif
								<th class="text-center">
									@lang('sale.subtotal')
								</th>
								<th class="text-center"><i class="fas fa-times" aria-hidden="true"></i></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
					</div>
					<div class="table-responsive">
					<table class="table table-condensed table-bordered table-striped">
						<tr>
							<td>
								<div class="pull-right">
								<b>@lang('sale.item'):</b> 
								<span class="total_quantity">0</span>
								&nbsp;&nbsp;&nbsp;&nbsp;
								<b>@lang('sale.total'): </b>
									<span class="price_total">0</span>
								</div>
							</td>
						</tr>
					</table>
					</div>
						</div>
						<div class="clearfix"></div>
						<div class="row legacy-post-table-section">
						<div class="col-md-4  @if($sale_type == 'sales_order') hide @endif">
				        <div class="form-group">
				            {!! Form::label('discount_type', __('sale.discount_type') . ':*' ) !!}
			            <div class="input-group">
			                <span class="input-group-addon">
			                    <i class="fa fa-info"></i>
			                </span>
			                {!! Form::select('discount_type', ['fixed' => __('lang_v1.fixed'), 'percentage' => __('lang_v1.percentage')], 'fixed' , ['class' => 'form-control','placeholder' => __('messages.please_select'), 'required', 'data-default' => 'fixed']); !!}
			            </div>
			        </div>
			    </div>
			    @php
			    	$max_discount = !is_null(auth()->user()->max_sales_discount_percent) ? auth()->user()->max_sales_discount_percent : '';

			    	//if sale discount is more than user max discount change it to max discount
			    	$sales_discount = $business_details->default_sales_discount;
			    	if($max_discount != '' && $sales_discount > $max_discount) $sales_discount = $max_discount;

			    	$default_sales_tax = $business_details->default_sales_tax;

			    	if($sale_type == 'sales_order') {
			    		$sales_discount = 0;
			    		$default_sales_tax = null;
			    	}
			    @endphp
			    <div class="col-md-4 @if($sale_type == 'sales_order') hide @endif">
			        <div class="form-group">
			            {!! Form::label('discount_amount', __('sale.discount_amount') . ':*' ) !!}
			            <div class="input-group">
			                <span class="input-group-addon">
			                    <i class="fa fa-info"></i>
			                </span>
			                {!! Form::text('discount_amount', @num_format($sales_discount), ['class' => 'form-control input_number', 'data-default' => $sales_discount, 'data-max-discount' => $max_discount, 'data-max-discount-error_msg' => __('lang_v1.max_discount_error_msg', ['discount' => $max_discount != '' ? @num_format($max_discount) : '']) ]); !!}
			            </div>
			        </div>
			    </div>
			    <div class="col-md-4 @if($sale_type == 'sales_order') hide @endif"><br>
			    	<b>@lang( 'sale.discount_amount' ):</b>(-) 
					<span class="display_currency" id="total_discount">0</span>
			    </div>
			    <div class="clearfix"></div>
			    <div class="col-md-12 well well-sm bg-light-gray @if(session('business.enable_rp') != 1 || $sale_type == 'sales_order') hide @endif">
			    	<input type="hidden" name="rp_redeemed" id="rp_redeemed" value="0">
			    	<input type="hidden" name="rp_redeemed_amount" id="rp_redeemed_amount" value="0">
			    	<div class="col-md-12"><h4>{{session('business.rp_name')}}</h4></div>
			    	<div class="col-md-4">
				        <div class="form-group">
				            {!! Form::label('rp_redeemed_modal', __('lang_v1.redeemed') . ':' ) !!}
				            <div class="input-group">
				                <span class="input-group-addon">
				                    <i class="fa fa-gift"></i>
				                </span>
				                {!! Form::number('rp_redeemed_modal', 0, ['class' => 'form-control direct_sell_rp_input', 'data-amount_per_unit_point' => session('business.redeem_amount_per_unit_rp'), 'min' => 0, 'data-max_points' => 0, 'data-min_order_total' => session('business.min_order_total_for_redeem') ]); !!}
				                <input type="hidden" id="rp_name" value="{{session('business.rp_name')}}">
				            </div>
				        </div>
				    </div>
				    <div class="col-md-4">
				    	<p><strong>@lang('lang_v1.available'):</strong> <span id="available_rp">0</span></p>
				    </div>
				    <div class="col-md-4">
				    	<p><strong>@lang('lang_v1.redeemed_amount'):</strong> (-)<span id="rp_redeemed_amount_text">0</span></p>
				    </div>
			    </div>
			    <div class="clearfix"></div>
			    <div class="col-md-4  @if($sale_type == 'sales_order') hide @endif">
			    	<div class="form-group">
			            {!! Form::label('tax_rate_id', __('sale.order_tax') . ':*' ) !!}
			            <div class="input-group">
			                <span class="input-group-addon">
			                    <i class="fa fa-info"></i>
			                </span>
			                {!! Form::select('tax_rate_id', $taxes['tax_rates'], $default_sales_tax, ['placeholder' => __('messages.please_select'), 'class' => 'form-control', 'data-default'=> $default_sales_tax], $taxes['attributes']); !!}

							<input type="hidden" name="tax_calculation_amount" id="tax_calculation_amount" 
							value="@if(empty($edit)) {{@num_format($business_details->tax_calculation_amount)}} @else {{@num_format($transaction->tax?->amount)}} @endif" data-default="{{$business_details->tax_calculation_amount}}">
			            </div>
			        </div>
			    </div>
			    <div class="col-md-4 col-md-offset-4  @if($sale_type == 'sales_order') hide @endif">
			    	<b>@lang( 'sale.order_tax' ):</b>(+) 
					<span class="display_currency" id="order_tax">0</span>
			    </div>				
				
				    <div class="col-md-12">
				    	<div class="form-group">
						{!! Form::label('sell_note',__('sale.sell_note')) !!}
						{!! Form::textarea('sale_note', null, ['class' => 'form-control', 'rows' => 3]); !!}
					</div>
				    </div>
				<div class="col-md-12">
					<div class="form-group" style="margin-top: 5px;">
						<label>Customer Source:</label>
						<div class="customer-source-radios" style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
							<label class="customer-source-option" style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; padding: 4px 10px; border: 1px solid #ddd; border-radius: 20px; background: #fff; font-weight: normal; font-size: 13px; margin: 0;">
								<input type="radio" name="customer_source_id" value="" checked style="margin: 0;">
								<span>ไม่ระบุ</span>
							</label>
							@foreach($customer_sources as $source)
								<label class="customer-source-option" style="display: inline-flex; align-items: center; gap: 4px; cursor: pointer; padding: 4px 10px; border: 1px solid #ddd; border-radius: 20px; background: #fff; font-weight: normal; font-size: 13px; margin: 0;">
									<input type="radio" name="customer_source_id" value="{{ $source->id }}" style="margin: 0;">
									@if($source->logo_url)
										<img src="{{ $source->logo_url }}" alt="{{ $source->name }}" style="width: 20px; height: 20px; object-fit: contain; border-radius: 50%;">
									@endif
									<span>{{ $source->name }}</span>
								</label>
							@endforeach
						</div>
					</div>
				</div>
				    </div>
					<input type="hidden" name="is_direct_sale" value="1">
			@endcomponent
			@component('components.widget', ['class' => 'box-solid'])
				<div class="col-md-12 text-right" style="margin-bottom: 10px;">
					<button type="button" class="btn btn-default btn-sm toggle-section-btn" data-target="#create_shipping_section_fields" data-show-text="แสดงรายละเอียดการจัดส่ง" data-hide-text="ซ่อนรายละเอียดการจัดส่ง">
						<i class="fa fa-chevron-down"></i> <span class="toggle-text">แสดงรายละเอียดการจัดส่ง</span>
					</button>
				</div>
				<div id="create_shipping_section_fields" style="display: none;">
				<div class="col-md-4">
					<div class="form-group">
			            {!! Form::label('shipping_details', __('sale.shipping_details')) !!}
		            {!! Form::textarea('shipping_details',null, ['class' => 'form-control','placeholder' => __('sale.shipping_details') ,'rows' => '3', 'cols'=>'30']); !!}
		        </div>
			</div>
			{{-- <div class="col-md-4">
				<div class="form-group">
		            {!! Form::label('shipping_address', __('lang_v1.shipping_address')) !!}
		            {!! Form::textarea('shipping_address',null, ['class' => 'form-control','placeholder' => __('lang_v1.shipping_address') ,'rows' => '3', 'cols'=>'30']); !!}
		        </div>
			</div> --}}
			<div class="col-md-4">
				<div class="form-group">
					{!!Form::label('shipping_charges', __('sale.shipping_charges'))!!}
					<div class="input-group">
					<span class="input-group-addon">
					<i class="fa fa-info"></i>
					</span>
					{!!Form::text('shipping_charges',@num_format(0.00),['class'=>'form-control input_number','placeholder'=> __('sale.shipping_charges')]);!!}
					</div>
				</div>
			</div>
			<div class="clearfix"></div>
			<div class="col-md-4">
				<div class="form-group">
		            {!! Form::label('shipping_status', __('lang_v1.shipping_status')) !!}
		            {!! Form::select('shipping_status',$shipping_statuses, null, ['class' => 'form-control','placeholder' => __('messages.please_select')]); !!}
		        </div>
			</div>
			<div class="col-md-4">
		        <div class="form-group">
		            {!! Form::label('delivered_to', __('lang_v1.delivered_to') . ':' ) !!}
		            {!! Form::text('delivered_to', null, ['class' => 'form-control','placeholder' => __('lang_v1.delivered_to')]); !!}
		        </div>
		    </div>
			<div class="col-md-4">
				<div class="form-group">
					{!! Form::label('delivery_person', __('lang_v1.delivery_person') . ':' ) !!}
					{!! Form::select('delivery_person', $users, null, ['class' => 'form-control select2','placeholder' => __('messages.please_select')]); !!}
				</div>
			</div>
		    @php
		        $shipping_custom_label_1 = !empty($custom_labels['shipping']['custom_field_1']) ? $custom_labels['shipping']['custom_field_1'] : '';

		        $is_shipping_custom_field_1_required = !empty($custom_labels['shipping']['is_custom_field_1_required']) && $custom_labels['shipping']['is_custom_field_1_required'] == 1 ? true : false;

		        $shipping_custom_label_2 = !empty($custom_labels['shipping']['custom_field_2']) ? $custom_labels['shipping']['custom_field_2'] : '';

		        $is_shipping_custom_field_2_required = !empty($custom_labels['shipping']['is_custom_field_2_required']) && $custom_labels['shipping']['is_custom_field_2_required'] == 1 ? true : false;

		        $shipping_custom_label_3 = !empty($custom_labels['shipping']['custom_field_3']) ? $custom_labels['shipping']['custom_field_3'] : '';
		        
		        $is_shipping_custom_field_3_required = !empty($custom_labels['shipping']['is_custom_field_3_required']) && $custom_labels['shipping']['is_custom_field_3_required'] == 1 ? true : false;

		        $shipping_custom_label_4 = !empty($custom_labels['shipping']['custom_field_4']) ? $custom_labels['shipping']['custom_field_4'] : '';
		        
		        $is_shipping_custom_field_4_required = !empty($custom_labels['shipping']['is_custom_field_4_required']) && $custom_labels['shipping']['is_custom_field_4_required'] == 1 ? true : false;

		        $shipping_custom_label_5 = !empty($custom_labels['shipping']['custom_field_5']) ? $custom_labels['shipping']['custom_field_5'] : '';
		        
		        $is_shipping_custom_field_5_required = !empty($custom_labels['shipping']['is_custom_field_5_required']) && $custom_labels['shipping']['is_custom_field_5_required'] == 1 ? true : false;
	        @endphp

	        @if(!empty($shipping_custom_label_1))
	        	@php
	        		$label_1 = $shipping_custom_label_1 . ':';
	        		if($is_shipping_custom_field_1_required) {
	        			$label_1 .= '*';
	        		}
	        	@endphp

	        	<div class="col-md-4">
			        <div class="form-group">
			            {!! Form::label('shipping_custom_field_1', $label_1 ) !!}
			            {!! Form::text('shipping_custom_field_1', !empty($walk_in_customer['shipping_custom_field_details']['shipping_custom_field_1']) ? $walk_in_customer['shipping_custom_field_details']['shipping_custom_field_1'] : null, ['class' => 'form-control','placeholder' => $shipping_custom_label_1, 'required' => $is_shipping_custom_field_1_required]); !!}
			        </div>
			    </div>
	        @endif
	        @if(!empty($shipping_custom_label_2))
	        	@php
	        		$label_2 = $shipping_custom_label_2 . ':';
	        		if($is_shipping_custom_field_2_required) {
	        			$label_2 .= '*';
	        		}
	        	@endphp

	        	<div class="col-md-4">
			        <div class="form-group">
			            {!! Form::label('shipping_custom_field_2', $label_2 ) !!}
			            {!! Form::text('shipping_custom_field_2', !empty($walk_in_customer['shipping_custom_field_details']['shipping_custom_field_2']) ? $walk_in_customer['shipping_custom_field_details']['shipping_custom_field_2'] : null, ['class' => 'form-control','placeholder' => $shipping_custom_label_2, 'required' => $is_shipping_custom_field_2_required]); !!}
			        </div>
			    </div>
	        @endif
	        @if(!empty($shipping_custom_label_3))
	        	@php
	        		$label_3 = $shipping_custom_label_3 . ':';
	        		if($is_shipping_custom_field_3_required) {
	        			$label_3 .= '*';
	        		}
	        	@endphp

	        	<div class="col-md-4">
			        <div class="form-group">
			            {!! Form::label('shipping_custom_field_3', $label_3 ) !!}
			            {!! Form::text('shipping_custom_field_3', !empty($walk_in_customer['shipping_custom_field_details']['shipping_custom_field_3']) ? $walk_in_customer['shipping_custom_field_details']['shipping_custom_field_3'] : null, ['class' => 'form-control','placeholder' => $shipping_custom_label_3, 'required' => $is_shipping_custom_field_3_required]); !!}
			        </div>
			    </div>
	        @endif
	        @if(!empty($shipping_custom_label_4))
	        	@php
	        		$label_4 = $shipping_custom_label_4 . ':';
	        		if($is_shipping_custom_field_4_required) {
	        			$label_4 .= '*';
	        		}
	        	@endphp

	        	<div class="col-md-4">
			        <div class="form-group">
			            {!! Form::label('shipping_custom_field_4', $label_4 ) !!}
			            {!! Form::text('shipping_custom_field_4', !empty($walk_in_customer['shipping_custom_field_details']['shipping_custom_field_4']) ? $walk_in_customer['shipping_custom_field_details']['shipping_custom_field_4'] : null, ['class' => 'form-control','placeholder' => $shipping_custom_label_4, 'required' => $is_shipping_custom_field_4_required]); !!}
			        </div>
			    </div>
	        @endif
	        @if(!empty($shipping_custom_label_5))
	        	@php
	        		$label_5 = $shipping_custom_label_5 . ':';
	        		if($is_shipping_custom_field_5_required) {
	        			$label_5 .= '*';
	        		}
	        	@endphp

	        	<div class="col-md-4">
			        <div class="form-group">
			            {!! Form::label('shipping_custom_field_5', $label_5 ) !!}
			            {!! Form::text('shipping_custom_field_5', !empty($walk_in_customer['shipping_custom_field_details']['shipping_custom_field_5']) ? $walk_in_customer['shipping_custom_field_details']['shipping_custom_field_5'] : null, ['class' => 'form-control','placeholder' => $shipping_custom_label_5, 'required' => $is_shipping_custom_field_5_required]); !!}
			        </div>
			    </div>
	        @endif
	        <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('shipping_documents', __('lang_v1.shipping_documents') . ':') !!}
                    {!! Form::file('shipping_documents[]', ['id' => 'shipping_documents', 'multiple', 'accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
                    <p class="help-block">
                    	@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)])
                    	@includeIf('components.document_help_text')
                    </p>
                </div>
            </div>
	        <div class="clearfix"></div>
	        <div class="col-md-12 text-center">
				<button type="button" class="btn btn-primary btn-sm" id="toggle_additional_expense"> <i class="fas fa-plus"></i> @lang('lang_v1.add_additional_expenses') <i class="fas fa-chevron-down"></i></button>
			</div>
			<div class="col-md-8 col-md-offset-4" id="additional_expenses_div" style="display: none;">
				<table class="table table-condensed">
					<thead>
						<tr>
							<th>@lang('lang_v1.additional_expense_name')</th>
							<th>@lang('sale.amount')</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>
								{!! Form::text('additional_expense_key_1', null, ['class' => 'form-control', 'id' => 'additional_expense_key_1']); !!}
							</td>
							<td>
								{!! Form::text('additional_expense_value_1', 0, ['class' => 'form-control input_number', 'id' => 'additional_expense_value_1']); !!}
							</td>
						</tr>
						<tr>
							<td>
								{!! Form::text('additional_expense_key_2', null, ['class' => 'form-control', 'id' => 'additional_expense_key_2']); !!}
							</td>
							<td>
								{!! Form::text('additional_expense_value_2', 0, ['class' => 'form-control input_number', 'id' => 'additional_expense_value_2']); !!}
							</td>
						</tr>
						<tr>
							<td>
								{!! Form::text('additional_expense_key_3', null, ['class' => 'form-control', 'id' => 'additional_expense_key_3']); !!}
							</td>
							<td>
								{!! Form::text('additional_expense_value_3', 0, ['class' => 'form-control input_number', 'id' => 'additional_expense_value_3']); !!}
							</td>
						</tr>
						<tr>
							<td>
								{!! Form::text('additional_expense_key_4', null, ['class' => 'form-control', 'id' => 'additional_expense_key_4']); !!}
							</td>
							<td>
								{!! Form::text('additional_expense_value_4', 0, ['class' => 'form-control input_number', 'id' => 'additional_expense_value_4']); !!}
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		    <div class="col-md-4 col-md-offset-8">
		    	@if(!empty($pos_settings['amount_rounding_method']) && $pos_settings['amount_rounding_method'] > 0)
		    	<small id="round_off"><br>(@lang('lang_v1.round_off'): <span id="round_off_text">0</span>)</small>
				<br/>
				<input type="hidden" name="round_off_amount" 
					id="round_off_amount" value=0>
				@endif
			    	<div><b>@lang('sale.total_payable'): </b>
						<input type="hidden" name="final_total" id="final_total_input">
						<span id="total_payable">0</span>
					</div>
			    </div>
				</div>
				@endcomponent
		</div>
	</div>
	@if(!empty($common_settings['is_enabled_export']) && $sale_type != 'sales_order')
		@component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.export')])
			<div class="col-md-12 mb-12">
                <div class="form-check">
                    <input type="checkbox" name="is_export" class="form-check-input" id="is_export" @if(!empty($walk_in_customer['is_export'])) checked @endif>
                    <label class="form-check-label" for="is_export">@lang('lang_v1.is_export')</label>
                </div>
            </div>
	        @php
	            $i = 1;
	        @endphp
	        @for($i; $i <= 6 ; $i++)
	            <div class="col-md-4 export_div" @if(empty($walk_in_customer['is_export'])) style="display: none;" @endif>
	                <div class="form-group">
	                    {!! Form::label('export_custom_field_'.$i, __('lang_v1.export_custom_field'.$i).':') !!}
	                    {!! Form::text('export_custom_fields_info['.'export_custom_field_'.$i.']', !empty($walk_in_customer['export_custom_field_'.$i]) ? $walk_in_customer['export_custom_field_'.$i] : null, ['class' => 'form-control','placeholder' => __('lang_v1.export_custom_field'.$i), 'id' => 'export_custom_field_'.$i]); !!}
	                </div>
	            </div>
	        @endfor
		@endcomponent
	@endif
	@php
		$is_enabled_download_pdf = config('constants.enable_download_pdf');
		$payment_body_id = 'payment_rows_div';
		if ($is_enabled_download_pdf) {
			$payment_body_id = '';
		}
	@endphp
		@if((empty($status) || (!in_array($status, ['quotation', 'draft'])) || $is_enabled_download_pdf) && $sale_type != 'sales_order')
			@can('sell.payments')
				@component('components.widget', ['class' => 'box-solid', 'id' => $payment_body_id, 'title' => __('purchase.add_payment')])
				<div class="col-md-12 text-right" style="margin-bottom: 10px;">
					<button type="button" class="btn btn-default btn-sm toggle-section-btn" data-target="#create_payment_section_fields" data-show-text="แสดงเพิ่มการชำระเงิน" data-hide-text="ซ่อนเพิ่มการชำระเงิน">
						<i class="fa fa-chevron-down"></i> <span class="toggle-text">แสดงเพิ่มการชำระเงิน</span>
					</button>
				</div>
				<div id="create_payment_section_fields" style="display: none;">
				@if($is_enabled_download_pdf)
					<div class="well row">
					<div class="col-md-6">
						<div class="form-group">
							{!! Form::label("prefer_payment_method" , __('lang_v1.prefer_payment_method') . ':') !!}
							@show_tooltip(__('lang_v1.this_will_be_shown_in_pdf'))
							<div class="input-group">
								<span class="input-group-addon">
									<i class="fas fa-money-bill-alt"></i>
								</span>
								{!! Form::select("prefer_payment_method", $payment_types, 'cash', ['class' => 'form-control','style' => 'width:100%;']); !!}
							</div>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							{!! Form::label("prefer_payment_account" , __('lang_v1.prefer_payment_account') . ':') !!}
							@show_tooltip(__('lang_v1.this_will_be_shown_in_pdf'))
							<div class="input-group">
								<span class="input-group-addon">
									<i class="fas fa-money-bill-alt"></i>
								</span>
								{!! Form::select("prefer_payment_account", $accounts, null, ['class' => 'form-control','style' => 'width:100%;']); !!}
							</div>
						</div>
					</div>
				</div>
			@endif
				@if(empty($status) || !in_array($status, ['quotation', 'draft']))
					<div class="payment_row" @if($is_enabled_download_pdf) id="payment_rows_div" @endif>
					<div class="row">
						<div class="col-md-12 mb-12">
							<strong>@lang('lang_v1.advance_balance'):</strong> <span id="advance_balance_text"></span>
							{!! Form::hidden('advance_balance', null, ['id' => 'advance_balance', 'data-error-msg' => __('lang_v1.required_advance_balance_not_available')]); !!}
						</div>
					</div>
					@include('sale_pos.partials.payment_row_form', ['row_index' => 0, 'show_date' => true, 'show_denomination' => true])
                </div>
                <div class="payment_row">
					<div class="row">
						<div class="col-md-12">
			        		<hr>
			        		<strong>
			        			@lang('lang_v1.change_return'):
			        		</strong>
			        		<br/>
			        		<span class="lead text-bold change_return_span">0</span>
			        		{!! Form::hidden("change_return", $change_return['amount'], ['class' => 'form-control change_return input_number', 'required', 'id' => "change_return"]); !!}
			        		<!-- <span class="lead text-bold total_quantity">0</span> -->
			        		@if(!empty($change_return['id']))
			            		<input type="hidden" name="change_return_id" 
			            		value="{{$change_return['id']}}">
			            	@endif
						</div>
					</div>
					<div class="row hide payment_row" id="change_return_payment_data">
						<div class="col-md-4">
							<div class="form-group">
								{!! Form::label("change_return_method" , __('lang_v1.change_return_payment_method') . ':*') !!}
								<div class="input-group">
									<span class="input-group-addon">
										<i class="fas fa-money-bill-alt"></i>
									</span>
									@php
										$_payment_method = empty($change_return['method']) && array_key_exists('cash', $payment_types) ? 'cash' : $change_return['method'];

										$_payment_types = $payment_types;
										if(isset($_payment_types['advance'])) {
											unset($_payment_types['advance']);
										}
									@endphp
									{!! Form::select("payment[change_return][method]", $_payment_types, $_payment_method, ['class' => 'form-control col-md-12 payment_types_dropdown', 'id' => 'change_return_method', 'style' => 'width:100%;']); !!}
								</div>
							</div>
						</div>
						@if(!empty($accounts))
						<div class="col-md-4">
							<div class="form-group">
								{!! Form::label("change_return_account" , __('lang_v1.change_return_payment_account') . ':') !!}
								<div class="input-group">
									<span class="input-group-addon">
										<i class="fas fa-money-bill-alt"></i>
									</span>
									{!! Form::select("payment[change_return][account_id]", $accounts, !empty($change_return['account_id']) ? $change_return['account_id'] : '' , ['class' => 'form-control select2', 'id' => 'change_return_account', 'style' => 'width:100%;']); !!}
								</div>
							</div>
						</div>
						@endif
						@include('sale_pos.partials.payment_type_details', ['payment_line' => $change_return, 'row_index' => 'change_return'])
					</div>
					<hr>
					<div class="row">
						<div class="col-sm-12">
							<div class="pull-right"><strong>@lang('lang_v1.balance'):</strong> <span class="balance_due">0.00</span></div>
						</div>
					</div>
					</div>
				@endif
				</div>
				@endcomponent
			@endcan
		@endif
	
	<div class="row">
		{!! Form::hidden('is_save_and_print', 0, ['id' => 'is_save_and_print']); !!}
		<div class="col-sm-12 text-center" style="margin-top: 20px;">
			<button type="button" id="submit-sell" class="btn btn-primary btn-lg">@lang('messages.save')</button>
			{{-- Hidden: Save and Print button --}}
			{{-- <button type="button" id="save-and-print" class="btn btn-success btn-lg">@lang('lang_v1.save_and_print')</button> --}}
		</div>
	</div>
	
	@if(empty($pos_settings['disable_recurring_invoice']))
		@include('sale_pos.partials.recurring_invoice_modal')
	@endif
	
	{!! Form::close() !!}
</section>

<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
	@include('contact.create_compact_customer', ['quick_add' => true])
</div>
<!-- /.content -->
<div class="modal fade register_details_modal" tabindex="-1" role="dialog" 
	aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" 
	aria-labelledby="gridSystemModalLabel">
</div>

<!-- quick product modal -->
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>

@include('sale_pos.partials.configure_search_modal')

@stop

@section('javascript')
	<script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>

	<!-- Call restaurant module if defined -->
    @if(in_array('tables' ,$enabled_modules) || in_array('modifiers' ,$enabled_modules) || in_array('service_staff' ,$enabled_modules))
    	<script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif
    <script type="text/javascript">
    	console.log('========== CREATE.BLADE.PHP SCRIPT LOADED ==========');
    	console.log('Timestamp:', new Date().toISOString());

    	// Set current user ID for assignments
    	window.currentUserId = {{ auth()->user()->id ?? 'null' }};

    	// PDF Server URL from Laravel config (set in .env as API_PDF_SERVER_URL)
    	const PDF_SERVER_URL = '{{ config("constants.pdf_server_url") }}';
    	console.log('PDF_SERVER_URL:', PDF_SERVER_URL);

    	$(document).ready( function() {
    		console.log('[DEBUG] document.ready fired');

    		// Fix price_group select2 dropdown positioning
    		var $priceGroupSelect = $('#price_group');
    		if ($priceGroupSelect.length && !$priceGroupSelect.prop('type') === 'hidden') {
    			var $priceGroupFormGroup = $priceGroupSelect.closest('.form-group');
    			var priceGroupOptions = {
    				dropdownParent: $priceGroupFormGroup,
    				width: '100%',
    				dropdownAutoWidth: false
    			};
    			if ($('html').attr('dir') == 'rtl') {
    				priceGroupOptions.dir = 'rtl';
    			}
    			if ($priceGroupSelect.data('select2')) {
    				$priceGroupSelect.select2('destroy');
    			}
    			$priceGroupSelect.select2(priceGroupOptions);

    			// Ensure dropdown width matches select width when opened
    			$priceGroupSelect.on('select2:open', function() {
    				setTimeout(function() {
    					var $container = $priceGroupSelect.next('.select2-container');
    					var selectWidth = $container.outerWidth();
    					$priceGroupFormGroup.find('.select2-dropdown').css({
    						'width': selectWidth + 'px',
    						'min-width': selectWidth + 'px'
    					});
    				}, 0);
    			});
    		}

    		// Fix status select2 dropdown offset in POS layout
    		var $statusSelect = $('#status');
    		if ($statusSelect.length && $statusSelect.is('select')) {
    			var $formGroup = $statusSelect.closest('.form-group');
    			var options = {
    				dropdownParent: $formGroup,
    				width: '100%',
    				dropdownAutoWidth: false
    			};
    			if ($('html').attr('dir') == 'rtl') {
    				options.dir = 'rtl';
    			}
    			if ($statusSelect.data('select2')) {
    				$statusSelect.select2('destroy');
    			}
    			$statusSelect.select2(options);

    			// Ensure dropdown width matches select width when opened
    			$statusSelect.on('select2:open', function() {
    				setTimeout(function() {
    					var $container = $statusSelect.next('.select2-container');
    					var selectWidth = $container.outerWidth();
    					// Find the dropdown within the form-group parent
    					$formGroup.find('.select2-dropdown').css({
    						'width': selectWidth + 'px',
    						'min-width': selectWidth + 'px'
    					});
    				}, 0);
    			});

    			// Auto-change invoice scheme when status changes
    			$statusSelect.on('change', function() {
    				var status = $(this).val();
    				var $invoiceScheme = $('#invoice_scheme_id');

    				if ($invoiceScheme.length) {
    					var schemeId = null;

    					// Map status to invoice scheme:
    					// quotation -> Quotation (id: 1)
    					// proforma -> TAX-INVOICE (id: 4)
    					// final -> BILLING-RECEIVE (id: 5)
    					if (status == 'quotation') {
    						schemeId = 1;  // Quotation (ใบเสนอราคา)
    					} else if (status == 'proforma') {
    						schemeId = 4;  // TAX-INVOICE ( ใบกำกับภาษี / ใบแจ้งหนี้ / )
    					} else if (status == 'final') {
    						schemeId = 5;  // BILLING-RECEIVE
    					}

    					if (schemeId) {
    						$invoiceScheme.val(schemeId).trigger('change');
    					}
    				}
    			});
    		}

    		// Fix invoice scheme select2 dropdown offset
    		var $invoiceSchemeSelect = $('#invoice_scheme_id');
    		if ($invoiceSchemeSelect.length) {
    			var $invoiceFormGroup = $invoiceSchemeSelect.closest('.form-group');
    			var invoiceOptions = {
    				dropdownParent: $invoiceFormGroup,
    				width: '100%',
    				dropdownAutoWidth: false
    			};
    			if ($('html').attr('dir') == 'rtl') {
    				invoiceOptions.dir = 'rtl';
    			}
    			if ($invoiceSchemeSelect.data('select2')) {
    				$invoiceSchemeSelect.select2('destroy');
    			}
    			$invoiceSchemeSelect.select2(invoiceOptions);

    			// Ensure dropdown width matches select width when opened
    			$invoiceSchemeSelect.on('select2:open', function() {
    				setTimeout(function() {
    					var $container = $invoiceSchemeSelect.next('.select2-container');
    					var selectWidth = $container.outerWidth();
    					$invoiceFormGroup.find('.select2-dropdown').css({
    						'width': selectWidth + 'px',
    						'min-width': selectWidth + 'px'
    					});
    				}, 0);
    			});
    		}


	    		// Fix select2 dropdowns in contact modal when it opens
		    		$(document).on('shown.bs.modal', '.contact_modal', function() {
		    			var $modal = $(this);
		    			simplifyCustomerModalAdditionalFields($modal);
		    			ensureQuickContactRawDataPanel();
		    			hideQuickContactRawData();
		    			syncQuickContactType($modal);
		    			$modal.find('select.select2').each(function() {
	    				var $select = $(this);
	    				var $formGroup = $select.closest('.form-group');
	    				var $inputGroup = $select.closest('.input-group');

    				if ($select.data('select2')) {
    					$select.select2('destroy');
    				}

    				$select.select2({
    					dropdownParent: $formGroup.length ? $formGroup : $modal,
    					width: '100%',
    					dropdownAutoWidth: false
    				});

    				// Fix dropdown width when opened
    				$select.on('select2:open', function() {
    					setTimeout(function() {
    						var $container = $select.next('.select2-container');
    						var containerWidth = $container.outerWidth();
    						// Find the dropdown that was just opened
    						var $dropdown = $('.select2-container--open .select2-dropdown');
    						$dropdown.css({
    							'width': containerWidth + 'px',
    							'min-width': containerWidth + 'px',
    							'max-width': containerWidth + 'px'
    						});
    					}, 0);
    				});
    			});
    		});


    		// Tax ID Lookup Variables - declared at top to avoid temporal dead zone
    		var taxLookupTimeout;
    		var lastLookedUpTaxId = '';
    		var companyData = null;
    		var originalNoResults = null;
    		var quickContactTaxLookupTimer = null;
    		var quickContactLastSearchedTaxId = '';
    		
	    		window.UPLOADS_IMG_BASE = "{{ asset('uploads/img') }}";
	    		function hideContactModalFieldByName($modal, fieldName) {
	    			$modal.find('[name="' + fieldName + '"], [name="' + fieldName + '[]"]').each(function() {
	    				var $field = $(this);
	    				var $container = $field.closest('[class*="col-md-"], [class*="col-sm-"], [class*="col-lg-"]');
	    				if ($container.length) {
	    					$container.hide();
	    				} else {
	    					$field.closest('.form-group').hide();
	    				}
	    			});
	    		}

	    		function simplifyCustomerModalAdditionalFields($modal) {
	    			if (! $modal.length) {
	    				return;
	    			}

	    			var $moreDiv = $modal.find('#more_div');
	    			if ($moreDiv.length) {
	    				$moreDiv.removeClass('hide').show();
	    			}

	    			$modal.find('.more_btn').closest('[class*="col-"]').hide();
	    			$modal.find('div.opening_balance, div.pay_term').hide();

	    			hideContactModalFieldByName($modal, 'credit_limit');
	    			hideContactModalFieldByName($modal, 'state');
	    			hideContactModalFieldByName($modal, 'country');
	    			hideContactModalFieldByName($modal, 'address_line_2');
	    		}

	    		function fillCompactCustomerModalFromCompanyData(data) {
	    			if (!data) {
	    				return;
	    			}

	    			var addressText = data.address || '';
	    			var addressParts = extractAddressParts(addressText);
	    			var companyName = data.companyNameTh || data.companyNameEn || '';

	    			// Clean up broken/partial suffix text and always enforce "(สำนักงานใหญ่)" suffix.
	    			if (companyName) {
	    				companyName = companyName
	    					.replace(/\s*\(สำนักงานใ[^)]*\)\s*$/u, '')
	    					.replace(/\s*\(สำนักงานใ.*$/u, '')
	    					.replace(/\s*\([^)]*$/u, '')
	    					.replace(/\s{2,}/g, ' ')
	    					.trim();

	    				if (!/\(สำนักงานใหญ่\)\s*$/u.test(companyName)) {
	    					companyName = companyName + ' (สำนักงานใหญ่)';
	    				}
	    			}

	    			var rawCity = (data.city || '').trim();
	    			var resolvedCity = rawCity || (addressParts.city || '').trim();
	    			var resolvedState = (data.state || '').trim() || (addressParts.state || '').trim();
	    			var resolvedZip = (data.zipCode || data.zip_code || '').toString().trim() || (addressParts.zipCode || '').trim();

	    			// Normalize "city" to province-level for Bangkok and city strings that embed province text.
	    			if (rawCity) {
	    				if (/กรุงเทพ/.test(rawCity) || /(?:เขต|แขวง)/.test(rawCity)) {
	    					resolvedCity = 'กรุงเทพมหานคร';
	    				} else {
	    					var provinceInCity = rawCity.match(/(?:จังหวัด|จ\.)\s*([ก-๙]+)/);
	    					if (provinceInCity && provinceInCity[1]) {
	    						resolvedCity = provinceInCity[1].trim();
	    					}
	    				}
	    			}

	    			if (!resolvedState && resolvedCity) {
	    				resolvedState = resolvedCity;
	    			}
	    			if (!resolvedCity) {
	    				resolvedCity = resolvedState;
	    			}

	    			var $modal = $('.contact_modal');
	    			$modal.find('input[name="supplier_business_name"]').val(companyName);
	    			$modal.find('input[name="first_name"]').val(companyName);
	    			$modal.find('input[name="tax_number"]').val(data.taxNumber || '');
	    			$modal.find('input[name="mobile"]').val(data.mobile || $modal.find('input[name="mobile"]').val() || '');
	    			$modal.find('input[name="address_line_1"]').val(addressText);
	    			$modal.find('input[name="city"]').val(resolvedCity);
	    			$modal.find('input[name="state"]').val(resolvedState);
	    			$modal.find('input[name="zip_code"]').val(resolvedZip);
	    			$modal.find('input[name="country"]').val('Thailand');
	    			$modal.find('input[name="shipping_address"]').val(addressText);
	    		}

	    		function syncQuickContactType($modal) {
	    			var $typeSelect = $modal.find('#quick_contact_type');
	    			if (! $typeSelect.length) {
	    				return;
	    			}

	    			var selectedType = $typeSelect.val() || 'business';
	    			$modal.find('input[name="contact_type_radio"]').val(selectedType);
	    		}

	    		function ensureQuickContactRawDataPanel() {
	    			var $modal = $('.contact_modal');
	    			if (! $modal.length) {
	    				return null;
	    			}

	    			var $panel = $modal.find('#quick_contact_raw_data_panel');
	    			if (! $panel.length) {
	    				var panelHtml = ''
	    					+ '<div id="quick_contact_raw_data_panel" class="panel panel-default" style="margin-top: 12px; display: none;">'
	    					+ '  <div class="panel-heading" style="padding: 6px 10px;">'
	    					+ '    <i class="fa fa-database"></i> <span id="quick_contact_raw_data_title">ข้อมูลจากการค้นหา</span>'
	    					+ '    <button type="button" class="btn btn-xs btn-default pull-right" id="quick_contact_raw_data_toggle">ซ่อน</button>'
	    					+ '  </div>'
	    					+ '  <div class="panel-body" style="padding: 10px;">'
	    					+ '    <div id="quick_contact_data_pretty"></div>'
	    					+ '  </div>'
	    					+ '</div>';
	    				$modal.find('.modal-body').append(panelHtml);
	    				$panel = $modal.find('#quick_contact_raw_data_panel');
	    			}

	    			return $panel;
	    		}

	    		function escapeQuickContactHtml(value) {
	    			return String(value || '')
	    				.replace(/&/g, '&amp;')
	    				.replace(/</g, '&lt;')
	    				.replace(/>/g, '&gt;')
	    				.replace(/"/g, '&quot;')
	    				.replace(/'/g, '&#39;');
	    		}

	    		function normalizeQuickContactDisplayData(rawData) {
	    			var src = rawData || {};
	    			return {
	    				source: src._displaySource || '',
	    				companyName: src.companyNameTh || src.companyNameEn || src.supplier_business_name || src.name || '',
	    				taxNumber: src.taxNumber || src.tax_number || '',
	    				address: src.address || src.address_line_1 || '',
	    				city: src.city || '',
	    				state: src.state || '',
	    				zipCode: src.zipCode || src.zip_code || '',
	    				mobile: src.mobile || src.phone || '',
	    				email: src.email || ''
	    			};
	    		}

	    		function buildQuickContactPrettyHtml(displayData) {
	    			var fields = [
	    				{ label: 'แหล่งข้อมูล', value: displayData.source },
	    				{ label: 'ชื่อบริษัท', value: displayData.companyName },
	    				{ label: 'เลขประจำตัวผู้เสียภาษี', value: displayData.taxNumber },
	    				{ label: 'ที่อยู่', value: displayData.address },
	    				{ label: 'เมือง', value: displayData.city },
	    				{ label: 'จังหวัด', value: displayData.state },
	    				{ label: 'รหัสไปรษณีย์', value: displayData.zipCode },
	    				{ label: 'โทรศัพท์', value: displayData.mobile },
	    				{ label: 'อีเมล์', value: displayData.email }
	    			];

	    			var rows = fields
	    				.filter(function(field) {
	    					return String(field.value || '').trim() !== '';
	    				})
	    				.map(function(field) {
	    					return ''
	    						+ '<tr>'
	    						+ '  <th style="width: 220px; vertical-align: top; background: #f8f8f8;">' + escapeQuickContactHtml(field.label) + '</th>'
	    						+ '  <td>' + escapeQuickContactHtml(field.value) + '</td>'
	    						+ '</tr>';
	    				})
	    				.join('');

	    			if (!rows) {
	    				rows = '<tr><td>ไม่พบข้อมูล</td></tr>';
	    			}

	    			return ''
	    				+ '<div class="table-responsive">'
	    				+ '  <table class="table table-bordered table-condensed" style="margin-bottom: 0;">'
	    				+ '    <tbody>' + rows + '</tbody>'
	    				+ '  </table>'
	    				+ '</div>';
	    		}

	    		function showQuickContactRawData(rawData, titleText) {
	    			var $panel = ensureQuickContactRawDataPanel();
	    			if (! $panel) {
	    				return;
	    			}

	    			var safeTitle = titleText || 'ข้อมูลจากการค้นหา';
	    			var displayData = normalizeQuickContactDisplayData(rawData);
	    			var prettyHtml = buildQuickContactPrettyHtml(displayData);

	    			$panel.find('#quick_contact_raw_data_title').text(safeTitle);
	    			$panel.find('#quick_contact_data_pretty').html(prettyHtml);
	    			$panel.show();
	    		}

	    		function hideQuickContactRawData() {
	    			var $panel = $('.contact_modal').find('#quick_contact_raw_data_panel');
	    			if ($panel.length) {
	    				$panel.hide();
	    			}
	    		}

	    		function showQuickContactTaxLookupAlert(type, message) {
	    			var msg = message || '';
	    			if (!msg) {
	    				return;
	    			}

	    			if (window.Swal && typeof window.Swal.fire === 'function') {
	    				window.Swal.fire({
	    					toast: true,
	    					position: 'top-end',
	    					icon: type === 'success' ? 'success' : 'error',
	    					title: msg,
	    					showConfirmButton: false,
	    					timer: 2500,
	    					timerProgressBar: true
	    				});
	    				return;
	    			}

	    			if (typeof window.toastr !== 'undefined') {
	    				toastr.options = $.extend({}, toastr.options || {}, {
	    					positionClass: 'toast-top-right'
	    				});
	    				if (type === 'success') {
	    					toastr.success(msg);
	    				} else {
	    					toastr.error(msg);
	    				}
	    				return;
	    			}

	    			if (typeof window.swal === 'function') {
	    				window.swal(msg);
	    			}
	    		}
	    		function hideBillingAddressBlock() {
	    			var $billingBlock = $('#billing_address_block');
	    			if ($billingBlock.length) {
	    				$billingBlock.addClass('hide').hide();
	    			}
	    		}

	    		hideBillingAddressBlock();
	    		function ensureCustomerSelect2DropdownParent() {
	    			var $customer = $('#customer_id');
	    			if (!$customer.length || !$customer.data('select2')) {
	    				return;
	    			}

	    			var existingOptions = $.extend(true, {}, $customer.data('select2').options.options || {});
	    			existingOptions.dropdownParent = $customer.closest('.form-group');
	    			existingOptions.width = '100%';
	    			existingOptions.dropdownAutoWidth = false;

	    			$customer.select2('destroy');
	    			$customer.select2(existingOptions);
	    		}

	    		// Keep customer dropdown anchored within its form-group to avoid offset drift.
	    		ensureCustomerSelect2DropdownParent();

	    		window.customerSelect2Open = false;
	    		$('#customer_id').on('select2:open', function() {
	    			window.customerSelect2Open = true;
	    			console.log('[Select2] open -> customerSelect2Open:', window.customerSelect2Open);

	    			// Align dropdown to the full input-group width and position
	    			setTimeout(function() {
	    				var $inputGroup = $('#customer_id').closest('.input-group');
	    				var width = $inputGroup.outerWidth();
	    				var $dropdown = $('.select2-container--open .select2-dropdown').last();
	    				$dropdown.css({
	    					'left': '',
	    					'right': '',
	    					'width': width + 'px',
	    					'min-width': width + 'px',
	    					'max-width': width + 'px'
	    				});
	    			}, 0);

    			// Attach input listener to search field when dropdown opens
    			setTimeout(function() {
    				var $searchField = $('.select2-search__field');
    				console.log('[DEBUG] Found search fields:', $searchField.length);
    				if ($searchField.length) {
    					$searchField.off('input.taxlookup keyup.taxlookup').on('input.taxlookup keyup.taxlookup', function(e) {
    						var val = $(this).val();
    						console.log('[DEBUG] Search field ' + e.type + ':', val, 'length:', val.length);
    						
    						// Check if 13-digit tax ID
    						if (/^\d{13}$/.test(val.trim())) {
    							console.log('[DEBUG] 13-digit Tax ID detected! Triggering lookup...');
    							lookupTaxId(val.trim());
    						}
    					});
    					console.log('[DEBUG] Input listener attached to search field');
    				}
    			}, 100);
    		});
    		$('#customer_id').on('select2:close', function() {
    			window.customerSelect2Open = false;
    			console.log('[Select2] close -> customerSelect2Open:', window.customerSelect2Open);
    		});
			function closeCustomerSelect2() {
				var $customer = $('#customer_id');
				if ($customer.length && $customer.data('select2')) {
					$customer.select2('close');
				}
			}
	    		$(document).on('click', '.select2-selection__clear', function() {
	    			console.log('[Select2] clear (x) clicked. Dropdown open:', window.customerSelect2Open);
	    		});

	    		$(document).on('change', '#quick_contact_type', function() {
	    			syncQuickContactType($('.contact_modal'));
	    		});

	    		$(document).on('click', '#quick_contact_raw_data_toggle', function() {
	    			hideQuickContactRawData();
	    		});

	    		function mergeTaxLookupData(dbData, apiData) {
	    			return {
	    				companyNameTh: (apiData && apiData.companyNameTh) || (dbData && dbData.companyNameTh) || '',
	    				companyNameEn: (apiData && apiData.companyNameEn) || (dbData && dbData.companyNameEn) || '',
	    				taxNumber: (apiData && apiData.taxNumber) || (dbData && dbData.taxNumber) || '',
	    				address: (apiData && apiData.address) || (dbData && dbData.address) || '',
	    				mobile: (dbData && dbData.mobile) || (apiData && apiData.mobile) || '',
	    				city: (apiData && apiData.city) || (dbData && dbData.city) || '',
	    				state: (apiData && apiData.state) || (dbData && dbData.state) || '',
	    				zipCode: (apiData && (apiData.zipCode || apiData.zip_code)) || (dbData && (dbData.zipCode || dbData.zip_code)) || '',
	    				customerId: (dbData && dbData.customerId) || null,
	    				_rawApi: (apiData && apiData.__raw) || null,
	    				_rawDb: (dbData && dbData.__raw) || null
	    			};
	    		}

	    		function runQuickContactTaxLookup(taxId, options) {
	    			var opts = options || {};
	    			var silent = !!opts.silent;
	    			var force = !!opts.force;
	    			var normalizedTaxId = (taxId || '').trim();
	    			var $btn = $('#quick_contact_tax_lookup_btn');

	    			if (!/^\d{13}$/.test(normalizedTaxId)) {
	    				if (!silent) {
	    					showQuickContactTaxLookupAlert('error', 'กรุณากรอกเลขประจำตัวผู้เสียภาษี 13 หลัก');
	    				}
	    				return;
	    			}

	    			if (!force && quickContactLastSearchedTaxId === normalizedTaxId) {
	    				return;
	    			}
	    			quickContactLastSearchedTaxId = normalizedTaxId;

	    			$btn.prop('disabled', true);

	    			// DB first, then API for enrichment.
	    			searchCustomerInDB(normalizedTaxId)
	    				.then(function(dbData) {
	    					if (dbData) {
	    						fillCompactCustomerModalFromCompanyData(dbData);
	    					}

	    					return searchCompanyInAPI(normalizedTaxId).then(function(apiData) {
	    						var mergedData = mergeTaxLookupData(dbData, apiData);
	    						if (mergedData.companyNameTh || mergedData.taxNumber || mergedData.address) {
	    							fillCompactCustomerModalFromCompanyData(mergedData);
	    							mergedData._displaySource = dbData ? (apiData ? 'ฐานข้อมูล + API' : 'ฐานข้อมูล') : 'API';
	    							showQuickContactRawData(mergedData, 'ข้อมูลจากการค้นหา');
	    							if (!silent || force) {
	    								showQuickContactTaxLookupAlert('success', 'ค้นหาข้อมูลสำเร็จ');
	    							}
	    						} else if (!silent || force) {
	    							showQuickContactTaxLookupAlert('error', 'ไม่พบข้อมูลจากเลขประจำตัวผู้เสียภาษีนี้');
	    						}
	    					});
	    				})
	    				.catch(function() {
	    					if (!silent || force) {
	    						showQuickContactTaxLookupAlert('error', 'ค้นหาข้อมูลไม่สำเร็จ');
	    					}
	    				})
	    				.finally(function() {
	    					$btn.prop('disabled', false);
	    				});
	    		}

	    		$(document).on('click', '#quick_contact_tax_lookup_btn', function(e) {
	    			e.preventDefault();
	    			runQuickContactTaxLookup($('#quick_contact_tax_number').val(), { force: true, silent: false });
	    		});

	    		$(document).on('input', '#quick_contact_tax_number', function() {
	    			var taxId = ($(this).val() || '').trim();
	    			if (!/^\d{13}$/.test(taxId)) {
	    				return;
	    			}

	    			clearTimeout(quickContactTaxLookupTimer);
	    			quickContactTaxLookupTimer = setTimeout(function() {
	    				runQuickContactTaxLookup(taxId, { force: false, silent: true });
	    			}, 250);
	    		});

	    		$('#customer_id').on('change select2:select select2:clear', function(e) {
	    			hideBillingAddressBlock();

	    			if (e && e.type === 'select2:select') {
	    				var selectedData = (e.params && e.params.data) ? e.params.data : null;
	    				var selectedText = selectedData && selectedData.text ? selectedData.text : $('#customer_id option:selected').text();
	    				if (selectedText) {
	    					showQuickContactTaxLookupAlert('success', 'เลือกลูกค้า: ' + selectedText);
	    				}
	    			}
	    		});

	    		$(document).on('click', '.toggle-section-btn', function() {
	    			var $btn = $(this);
	    			var target = $btn.data('target');
	    			var $target = $(target);
	    			if (!$target.length) {
	    				return;
	    			}

	    			var showText = $btn.data('show-text') || 'แสดง';
	    			var hideText = $btn.data('hide-text') || 'ซ่อน';
	    			var $icon = $btn.find('i.fa');
	    			var $text = $btn.find('.toggle-text');

	    			if ($target.is(':visible')) {
	    				$target.slideUp(180);
	    				$icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
	    				$text.text(showText);
	    			} else {
	    				$target.slideDown(180);
	    				$icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
	    				$text.text(hideText);
	    			}
	    		});

    		$('#status').change(function(){
    			var status = $(this).val();
    			console.log('Status changed to:', status); // Debug log
    			
    			if (status == 'final') {
    				$('#payment_rows_div').removeClass('hide');
    			} else {
    				$('#payment_rows_div').addClass('hide');
    			}
    			
    			// Update invoice scheme based on status
    			var invoice_scheme_dropdown = $('#invoice_scheme_id');
    			if (invoice_scheme_dropdown.length) {
    				var scheme_id = '';
    				
    				if (status == 'quotation') {
    					// For quotation: use scheme id 1 (QUOTE2025/)
    					scheme_id = '1';
    				} else if (status == 'proforma') {
    					// For proforma: use scheme id 4 (VT2025/ format)
    					scheme_id = '4';
    				} else if (status == 'final') {
    					// For final bills: use scheme id 5 (IPAY2025/ format)
    					scheme_id = '5';
    				} else if (status == 'draft') {
    					// For draft: use scheme id 4 (VT2025/ format)
    					scheme_id = '4';
    				}
    				
    				console.log('Setting invoice scheme to:', scheme_id); // Debug log
    				
    				if (scheme_id) {
    					// Force update Select2 dropdown
    					invoice_scheme_dropdown.val(scheme_id).trigger('change');
    					
    					// Also trigger Select2 specific events
    					if (invoice_scheme_dropdown.hasClass('select2-hidden-accessible')) {
    						invoice_scheme_dropdown.select2('val', scheme_id);
    					}
    					
    					console.log('Invoice scheme updated to:', invoice_scheme_dropdown.val());
    				} else {
    					// Clear selection
    					invoice_scheme_dropdown.val('').trigger('change');
    					if (invoice_scheme_dropdown.hasClass('select2-hidden-accessible')) {
    						invoice_scheme_dropdown.select2('val', '');
    					}
    				}
    			}
    		});
    		
    		// Enhanced debug form submission with error handling
    		$('#add_sell_form').on('submit', function(e) {
    			var status = $('#status').val();
    			var invoice_scheme = $('#invoice_scheme_id').val();
    			var contact_id = $('#customer_id').val();
    			var location_id = $('#location_id').val();
    			var products = $('#pos_table tbody tr').length;
    			
    			console.log('=== FORM SUBMISSION DEBUG ===');
    			console.log('Status:', status);
    			console.log('Invoice Scheme:', invoice_scheme);
    			console.log('Customer ID:', contact_id);
    			console.log('Location ID:', location_id);
    			console.log('Products Count:', products);
    			console.log('Form Action:', $(this).attr('action'));
    			console.log('Form Method:', $(this).attr('method'));
    			
    			// If using temporary company data, add it to the form
    			if (contact_id && contact_id.startsWith('temp_company_')) {
    				var selectedOption = $('#customer_id option:selected');
    				var companyName = selectedOption.text().replace(' - Temporary', '').split(' (Tax ID:')[0];
    				var taxNumber = contact_id.replace('temp_company_', '');
    				
    				// Add hidden fields with company data
    				if (!$('input[name="temp_company_name"]').length) {
    					$(this).append('<input type="hidden" name="temp_company_name" value="' + companyName + '">');
    				}
    				if (!$('input[name="temp_company_tax"]').length) {
    					$(this).append('<input type="hidden" name="temp_company_tax" value="' + taxNumber + '">');
    				}
    				
    				console.log('Added temp company data:', companyName, taxNumber);
    			}
    			
    			// Validate required fields
    			var errors = [];
    			if (!contact_id) errors.push('Customer is required');
    			if (!location_id) errors.push('Location is required');
    			if (!status) errors.push('Status is required');
    			if (products === 0) errors.push('At least one product is required');
    			
    			if (errors.length > 0) {
    				console.error('VALIDATION ERRORS:', errors);
    				alert('Please fix the following errors:\n' + errors.join('\n'));
    				e.preventDefault();
    				return false;
    			}
    			
    			console.log('Form validation passed, submitting...');
    		});
    		
    		// Add global error handler for AJAX responses
    		$(document).ajaxError(function(event, xhr, settings, thrownError) {
    			// Ignore abort errors - these are normal for Select2 when a new request cancels the previous one
    			if (thrownError === 'abort' || xhr.statusText === 'abort') {
    				return;
    			}
    			
    			console.error('=== AJAX ERROR ===');
    			console.error('URL:', settings.url);
    			console.error('Status:', xhr.status);
    			console.error('Response:', xhr.responseText);
    			console.error('Error:', thrownError);
    			
    			// Try to parse JSON response for better error display
    			try {
    				var response = JSON.parse(xhr.responseText);
    				if (response.msg) {
    					console.error('Server Message:', response.msg);
    					if (response.debug_info) {
    						console.error('Debug Info:', response.debug_info);
    					}
    					if (response.error_id) {
    						console.error('Error ID:', response.error_id);
    					}
    				}
    			} catch (e) {
    				console.error('Could not parse error response as JSON');
    			}
    		});
    		
    		$('.paid_on').datetimepicker({
                format: moment_date_format + ' ' + moment_time_format,
                ignoreReadonly: true,
            });

            $('#shipping_documents').fileinput({
		        showUpload: false,
		        showPreview: false,
		        browseLabel: LANG.file_browse_label,
		        removeLabel: LANG.remove,
		    });

		    $(document).on('change', '#prefer_payment_method', function(e) {
			    var default_accounts = $('select#select_location_id').length ?
			                $('select#select_location_id')
			                .find(':selected')
			                .data('default_payment_accounts') : $('#location_id').data('default_payment_accounts');
			    if (typeof default_accounts === 'string') {
			        try {
			            default_accounts = JSON.parse(default_accounts);
			        } catch (e) {
			            default_accounts = {};
			        }
			    }
			    default_accounts = default_accounts || {};
			    var payment_type = $(this).val();
			    if (payment_type) {
			        var default_account = default_accounts[payment_type] && default_accounts[payment_type]['account'] ?
			            default_accounts[payment_type]['account'] : '';
			        var account_dropdown = $('select#prefer_payment_account');
			        if (account_dropdown.length) {
			            account_dropdown.val(default_account);
			            account_dropdown.change();
			        }
			    }
			});

		    function setPreferredPaymentMethodDropdown() {
			    var payment_settings = $('#location_id').data('default_payment_accounts');
			    payment_settings = payment_settings ? payment_settings : [];
			    enabled_payment_types = [];
			    for (var key in payment_settings) {
			        if (payment_settings[key] && payment_settings[key]['is_enabled']) {
			            enabled_payment_types.push(key);
			        }
			    }
			    if (enabled_payment_types.length) {
			        $("#prefer_payment_method > option").each(function() {
		                if (enabled_payment_types.indexOf($(this).val()) != -1) {
		                    $(this).removeClass('hide');
		                } else {
		                    $(this).addClass('hide');
		                }
			        });
			    }
			}
			
			setPreferredPaymentMethodDropdown();

			$('#is_export').on('change', function () {
	            if ($(this).is(':checked')) {
	                $('div.export_div').show();
	            } else {
	                $('div.export_div').hide();
	            }
	        });

			if($('.payment_types_dropdown').length){
				$('.payment_types_dropdown').change();
			}

			// Tax ID Lookup Functionality
			console.log('Tax ID lookup functionality initialized');

			// Override Select2 noResults function to show Tax ID lookup options
			$(document).ready(function() {
				// Wait for Select2 to be initialized, then override its noResults function
				setTimeout(function() {
					if ($('#customer_id').data('select2')) {
						// Store the original noResults function globally if not already stored
						if (!originalNoResults) {
							originalNoResults = $('#customer_id').data('select2').options.options.language.noResults;
						}
						
						$('#customer_id').data('select2').options.options.language.noResults = function() {
							const searchTerm = $('#customer_id').data('select2').dropdown.$search.val().trim();
							console.log('noResults called with searchTerm:', searchTerm);
							console.log('companyData available:', companyData);
							
							// If it's a 13-digit Tax ID and we have company data, show custom options
							if (/^\d{13}$/.test(searchTerm) && companyData && companyData.companyNameTh) {
								console.log('Showing custom Tax ID options for:', companyData.companyNameTh);
								
								// Determine the label based on data source
								let statusLabel = '';
								if (companyData.dataSource === 'existing') {
									statusLabel = ' (ลูกค้าเก่า)'; // Existing customer
								} else if (companyData.dataSource === 'new') {
									statusLabel = ' (ลูกค้าใหม่)'; // New customer
								}
								
								let buttonHtml = '';
								if (companyData.dataSource === 'existing') {
									// For existing customers
									buttonHtml = `
										<button type="button" class="btn btn-success btn-sm tax-lookup-use-temp-btn" data-tax-id="${searchTerm}" style="margin: 2px; width: 48%; display: inline-block;">
											<i class="fa fa-file"></i> Use for document
										</button>
										<button type="button" class="btn btn-warning btn-sm tax-lookup-edit-existing-btn" data-tax-id="${searchTerm}" style="margin: 2px; width: 48%; display: inline-block;">
											<i class="fa fa-edit"></i> Edit this customer
										</button>
									`;
								} else {
									// For new customers
									buttonHtml = `
										<button type="button" class="btn btn-success btn-sm tax-lookup-use-btn" data-tax-id="${searchTerm}" style="margin: 2px; width: 100%;">
											<i class="fa fa-check"></i> Use "${companyData.companyNameTh}" for this document
										</button>
										<button type="button" class="btn btn-primary btn-sm tax-lookup-add-btn" data-tax-id="${searchTerm}" style="margin: 2px; width: 100%;">
											<i class="fa fa-plus"></i> Add "${companyData.companyNameTh}" as new customer
										</button>
									`;
								}
								
								return `
									<div style="padding: 10px;">
										<div style="margin-bottom: 8px; font-weight: bold; color: #333;">
											Tax ID Found: ${companyData.companyNameTh}${statusLabel}
										</div>
										${buttonHtml}
									</div>
								`;
							}
							
							console.log('Using default noResults');
							// Default behavior for non-Tax ID searches
							return originalNoResults.call(this);
						};
					}
				}, 1000);
			});

			// Watch for input changes in customer field (Select2 compatible)
		// Using both 'input' and 'keyup' events for better compatibility
		$(document).on('input keyup', '.select2-search__field', function(e) {
			const input = $(this).val().trim();
			console.log('[DEBUG] Select2 search field event:', e.type, 'value:', input, 'length:', input.length);
				
				// Clear previous status
				hideTaxLookupStatus();
				
				// Check if input is a 13-digit tax ID
				if (/^\d{13}$/.test(input)) {
					console.log('[DEBUG] Valid 13-digit Tax ID detected:', input);
					console.log('[DEBUG] PDF_SERVER_URL:', typeof PDF_SERVER_URL !== 'undefined' ? PDF_SERVER_URL : 'UNDEFINED!');
					console.log('[DEBUG] lastLookedUpTaxId:', lastLookedUpTaxId);
					console.log('[DEBUG] Is new lookup needed:', input !== lastLookedUpTaxId);
					// Only lookup if it's different from last lookup
					if (input !== lastLookedUpTaxId) {
						console.log('[DEBUG] Starting new Tax ID lookup...');
						// Clear previous timeout
						clearTimeout(taxLookupTimeout);
						
						// For 13-digit Tax ID, call API immediately (shorter debounce)
						taxLookupTimeout = setTimeout(() => {
							console.log('[DEBUG] Timeout fired, calling lookupTaxId()...');
							lookupTaxId(input);
						}, 200);
					} else {
						console.log('[DEBUG] Skipping lookup - same as last lookup');
					}
				} else if (input.length >= 2) {
					console.log('General search input detected:', input);
					// For general search (company name, phone, etc.), search in DB only
					if (input !== lastLookedUpTaxId) {
						// Clear previous timeout
						clearTimeout(taxLookupTimeout);
						
						// Debounce the search (wait 300ms after user stops typing)
						taxLookupTimeout = setTimeout(() => {
							searchInDatabaseOnly(input);
						}, 300);
					}
				} else {
					console.log('Input too short or not valid format:', input);
					// Input too short, hide status indicators
					hideTaxLookupStatus();
					lastLookedUpTaxId = '';
					companyData = null;
				}
			});

			// Also watch for manual input in the underlying field
			$(document).on('input keyup', '#customer_id', function() {
				const input = $(this).val();
				console.log('Customer field direct input:', input);
				
				if (input && typeof input === 'string') {
					const trimmedInput = input.trim();
					
					// Clear previous status
					hideTaxLookupStatus();
					
					// Check if input is a 13-digit tax ID
					if (/^\d{13}$/.test(trimmedInput)) {
						console.log('Valid 13-digit Tax ID detected (direct):', trimmedInput);
						// Only lookup if it's different from last lookup
						if (trimmedInput !== lastLookedUpTaxId) {
							// Clear previous timeout
							clearTimeout(taxLookupTimeout);
							
							// For 13-digit Tax ID, call API immediately (shorter debounce)
							taxLookupTimeout = setTimeout(() => {
								lookupTaxId(trimmedInput);
							}, 200);
						}
					} else if (trimmedInput.length >= 2) {
						console.log('General search input detected (direct):', trimmedInput);
						// For general search, search in DB only
						if (trimmedInput !== lastLookedUpTaxId) {
							// Clear previous timeout
							clearTimeout(taxLookupTimeout);
							
							// Debounce the search (wait 300ms after user stops typing)
							taxLookupTimeout = setTimeout(() => {
								searchInDatabaseOnly(trimmedInput);
							}, 300);
						}
					} else {
						console.log('Input too short (direct):', trimmedInput);
						// Input too short, hide status indicators
						hideTaxLookupStatus();
						lastLookedUpTaxId = '';
						companyData = null;
					}
				}
			});

			// Tax ID lookup function with parallel DB and API search
			function lookupTaxId(taxId) {
				console.log('[DEBUG] ======== lookupTaxId CALLED ========');
				console.log('[DEBUG] Tax ID:', taxId);
				console.log('[DEBUG] PDF_SERVER_URL:', typeof PDF_SERVER_URL !== 'undefined' ? PDF_SERVER_URL : 'UNDEFINED!');
				
				// Show loading status
				showTaxLookupLoading();
				lastLookedUpTaxId = taxId;
				
				// Create promises for both DB and API searches
				console.log('[DEBUG] Creating DB search promise...');
				const dbSearch = searchCustomerInDB(taxId);
				console.log('[DEBUG] Creating API search promise...');
				const apiSearch = searchCompanyInAPI(taxId);
				console.log('[DEBUG] Both promises created, waiting for results...');
				
				// Execute both searches in parallel
				Promise.allSettled([dbSearch, apiSearch])
					.then(results => {
						const [dbResult, apiResult] = results;
						
						console.log('DB search result:', dbResult);
						console.log('API search result:', apiResult);
						
						const dbData = (dbResult.status === 'fulfilled' && dbResult.value) ? dbResult.value : null;
						const apiData = (apiResult.status === 'fulfilled' && apiResult.value) ? apiResult.value : null;
						
						if (dbData && apiData) {
							// Both found - prioritize database result, show only DB data first
							companyData = dbData;
							companyData.dataSource = 'existing';
							window.lastCompanyLookupData = companyData;
							showTaxLookupSuccess(dbData.companyNameTh, 'existing');
						} else if (dbData) {
							// Found in database only
							companyData = dbData;
							companyData.dataSource = 'existing';
							window.lastCompanyLookupData = companyData;
							showTaxLookupSuccess(dbData.companyNameTh, 'existing');
						} else if (apiData) {
							// Found in API only
							companyData = apiData;
							companyData.dataSource = 'new';
							window.lastCompanyLookupData = companyData;
							showTaxLookupSuccess(apiData.companyNameTh, 'new');
						} else {
							// Neither DB nor API found the Tax ID
							console.log('Tax ID not found in DB or API');
							showTaxLookupError('Tax ID not found');
							companyData = null;
						}
					})
					.catch(error => {
						console.error('Tax ID lookup error:', error);
						showTaxLookupError('Lookup failed');
						companyData = null;
					});
			}
			
			// Search in database only for general searches (company name, phone, etc.)
			function searchInDatabaseOnly(searchTerm) {
				console.log('Searching in database only for:', searchTerm);
				
				// Show loading status
				showTaxLookupLoading();
				lastLookedUpTaxId = searchTerm;
				
				// Search in database with broader criteria
				$.ajax({
					url: '/contacts/search-general',
					method: 'GET',
					data: { 
						search_term: searchTerm 
					},
					headers: {
						'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					},
					timeout: 5000,
					success: function(response) {
						console.log('General DB search response:', response);
						if (response && response.success && response.data && response.data.length > 0) {
							// Found matches in database
							const customer = response.data[0]; // Take first match
							const dbData = {
								companyNameTh: customer.supplier_business_name || customer.name,
								companyNameEn: customer.supplier_business_name || customer.name,
								taxNumber: customer.tax_number || '',
								businessType: '',
								address: customer.address_line_1 || '',
								mobile: customer.mobile || '',
								customerId: customer.id,
								isExisting: true,
								dataSource: 'existing'
							};
							
							companyData = dbData;
							window.lastCompanyLookupData = companyData;
							showTaxLookupSuccess(dbData.companyNameTh, 'existing');
						} else {
							// No matches found
							console.log('No matches found in database');
							hideTaxLookupStatus();
							companyData = null;
						}
					},
					error: function(xhr, status, error) {
						console.log('General DB search failed:', status, error);
						hideTaxLookupStatus();
						companyData = null;
					}
				});
			}
			
			// Search customer in database by tax number
			function searchCustomerInDB(taxId) {
				return new Promise((resolve, reject) => {
					$.ajax({
						url: '/contacts/search-by-tax',
						method: 'GET',
						data: { tax_number: taxId },
						headers: {
							'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
						},
						timeout: 5000,
						success: function(response) {
							console.log('DB search response:', response);
							if (response && response.success && response.data) {
								// Convert DB format to match API format
									const dbData = {
										companyNameTh: response.data.supplier_business_name || response.data.name,
										companyNameEn: response.data.supplier_business_name || response.data.name,
										taxNumber: response.data.tax_number,
										businessType: '',
										address: response.data.address_line_1 || '',
										mobile: response.data.mobile || '',
										city: response.data.city || '',
										state: response.data.state || '',
										zipCode: response.data.zip_code || '',
										customerId: response.data.id, // Store customer ID for direct selection
										isExisting: true,
										__raw: response.data
									};
								resolve(dbData);
							} else {
								resolve(null); // Not found in DB
							}
						},
						error: function(xhr, status, error) {
							console.log('DB search failed:', status, error);
							resolve(null); // Treat as not found, don't reject to allow API fallback
						}
					});
				});
			}
			
			// Search company in API (only called for 13-digit tax IDs)
			function searchCompanyInAPI(taxId) {
				console.log('[DEBUG] ======== searchCompanyInAPI CALLED ========');
				console.log('[DEBUG] Tax ID:', taxId);
				const apiUrl = `${PDF_SERVER_URL}/company/${taxId}`;
				console.log('[DEBUG] API URL:', apiUrl);
				console.log('[DEBUG] PDF_SERVER_URL value:', PDF_SERVER_URL);
				
				return new Promise((resolve, reject) => {
					console.log('[DEBUG] Sending AJAX request to:', apiUrl);
					$.ajax({
						// Use PDF_SERVER_URL from config (local or production based on .env)
						url: apiUrl,
						method: 'GET',
						timeout: 10000,
							success: function(data) {
								console.log('[DEBUG] API response received:', data);
								if (data && data.companyNameTh) {
									data.__raw = $.extend(true, {}, data);
									data.isExisting = false; // Mark as new customer
									resolve(data);
								} else {
								console.log('[DEBUG] API response has no companyNameTh, resolving null');
								resolve(null);
							}
						},
						error: function(xhr, status, error) {
							console.log('[DEBUG] API search FAILED!');
							console.log('[DEBUG] Status:', status);
							console.log('[DEBUG] Error:', error);
							console.log('[DEBUG] XHR Status:', xhr.status);
							console.log('[DEBUG] XHR Response:', xhr.responseText);
							console.log('API search failed:', status, error);
							resolve(null); // Treat as not found, don't reject
						}
					});
				});
			}

			// Show tax lookup status functions
			function showTaxLookupLoading() {
				$('.tax-lookup-status').remove();
			}

			function showTaxLookupSuccess(companyName, dataSource) {
				console.log('Showing success status for:', companyName, 'Source:', dataSource);
				$('.tax-lookup-status').remove();
			}


			function showTaxLookupError(errorMsg) {
				console.log('Showing error status:', errorMsg);
				$('.tax-lookup-status').remove();
				
				companyData = null;
			}

			function hideTaxLookupStatus() {
				console.log('Hiding tax lookup status');
				// Remove any status messages
				$('.tax-lookup-status').remove();
				
				// Reset Select2 noResults to default
				if (originalNoResults && $('#customer_id').data('select2')) {
					$('#customer_id').data('select2').options.options.language.noResults = originalNoResults;
				}
			}

			// Helper function to extract address parts
			function extractAddressParts(address) {
				if (!address) return { city: '', state: '', zipCode: '' };
				
				console.log('Parsing address:', address);
				
				// Basic Thai address parsing
				// Look for postal code (5 digits at the end)
				const zipMatch = address.match(/(\d{5})$/);
				const zipCode = zipMatch ? zipMatch[1] : '';
				
				// Look for province (after "จ." or "จังหวัด")
				let state = '';
				const provinceMatch = address.match(/จ\.([^0-9\s]+)|จังหวัด([^0-9\s]+)/);
				if (provinceMatch) {
					state = (provinceMatch[1] || provinceMatch[2]).trim();
				}
				
				// Look for district (after "อำเภอ" or "อ.")
				let city = '';
				const districtMatch = address.match(/อำเภอ([^0-9\s]+)|อ\.([^0-9\s]+)/);
				if (districtMatch) {
					city = (districtMatch[1] || districtMatch[2]).trim();
				}
				
				// If no district found, try to extract from general pattern
				if (!city && state) {
					// Look for text before province
					const beforeProvince = address.split(/จ\.|จังหวัด/)[0];
					const parts = beforeProvince.split(' ');
					// Take the last meaningful part before province as city
					city = parts[parts.length - 1] || '';
				}
				
				const result = {
					city: city,
					state: state,
					zipCode: zipCode
				};
				
				console.log('Extracted parts:', result);
				return result;
			}

			// Function to create customer from company data
			function createCustomerFromCompanyData(companyData) {
				console.log('Creating customer from company data:', companyData);
				
				if (!companyData) {
					console.error('No company data provided');
					return;
				}

				// Extract city, state, and zip from address
				const address = companyData.address || '';
				const addressParts = extractAddressParts(address);

				// Prepare contact data
				const contactData = {
					name: companyData.companyNameTh,
					supplier_business_name: companyData.companyNameTh,
					tax_number: companyData.taxNumber,
					contact_type: 'business',
					type: 'customer',
					business_id: 1, // Assuming business_id is 1
					city: addressParts.city,
					state: addressParts.state,
					country: 'Thailand',
					address_line_1: address,
					zip_code: addressParts.zipCode,
					mobile: '-',
					shipping_address: address
				};

				// Send AJAX request to create contact
				$.ajax({
					url: '/contacts',
					method: 'POST',
					data: contactData,
					headers: {
						'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					},
					success: function(response) {
						console.log('Contact created successfully:', response);
						
						// Add the new customer to the dropdown and select it
						if (response && response.data && response.data.id) {
							const newOption = new Option(
								response.data.name,
								response.data.id,
								true,
								true
							);
							$('#customer_id').append(newOption).trigger('change');
							
							// Hide the lookup status and close dropdown
							hideTaxLookupStatus();
							closeCustomerSelect2();
							
							toastr.success('Customer created successfully: ' + response.data.name);
						} else {
							toastr.error('Contact created but response format unexpected');
						}
					},
					error: function(xhr, status, error) {
						console.error('Error creating contact:', xhr, status, error);
						let errorMsg = 'Failed to create customer';
						
						if (xhr.responseJSON && xhr.responseJSON.message) {
							errorMsg = xhr.responseJSON.message;
						}
						
						toastr.error(errorMsg);
					}
				});
			}

			// Event handlers for dropdown Tax ID lookup buttons
			$(document).on('click', '.tax-lookup-use-temp-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('Use for document (existing customer) button clicked');
				
				const dataSource = $(this).data('source') || 'existing';
				let companyData = null;
				
				if (window.lastCompanyLookupData && window.lastCompanyLookupData.dbData && window.lastCompanyLookupData.apiData) {
					// Both results available - use DB data for existing customer
					companyData = window.lastCompanyLookupData.dbData;
				} else if (window.lastCompanyLookupData) {
					// Single result
					companyData = window.lastCompanyLookupData;
				}
				
				if (companyData) {
					console.log('Using existing customer data temporarily:', companyData);
					
					// Close the Select2 dropdown first
					closeCustomerSelect2();
					
					// Remove any existing temp options first
					$('#customer_id option[value^="temp_company_"]').remove();
					
					// Create a temporary option with company data and select it
					var tempOption = new Option(
						companyData.companyNameTh + ' (Tax ID: ' + companyData.taxNumber + ') - Temporary',
						'temp_company_' + companyData.taxNumber,
						true,
						true
					);
					$('#customer_id').append(tempOption).trigger('change');
					
					// Store the company data for later use when saving
					$('#customer_id').data('temp-company-data', companyData);
					
					console.log('Customer field updated with temp option for existing customer');
					
					// Update address displays
					updateAddressDisplays(companyData);
					
					// Hide any existing status messages
					hideTaxLookupStatus();
					
					// Show success message
					toastr.success('Using customer: ' + companyData.companyNameTh + ' for this document (temporary)');
					console.log('Existing customer data applied temporarily');
				} else {
					console.error('No company data available');
					toastr.error('No company data available');
				}
			});
			
			$(document).on('click', '.tax-lookup-edit-existing-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('Edit existing customer button clicked');
				
				const dataSource = $(this).data('source') || 'existing';
				let companyData = null;
				
				if (window.lastCompanyLookupData && window.lastCompanyLookupData.dbData && window.lastCompanyLookupData.apiData) {
					// Both results available - use DB data for editing existing customer
					companyData = window.lastCompanyLookupData.dbData;
				} else if (window.lastCompanyLookupData && window.lastCompanyLookupData.customerId) {
					// Single result with customer ID
					companyData = window.lastCompanyLookupData;
				}
				
				if (companyData && companyData.customerId) {
					// Close the Select2 dropdown first
					closeCustomerSelect2();
					
					console.log('Opening edit modal for existing customer:', companyData);
					
					// Store the company data for the modal
					window.pendingCompanyData = companyData;
					window.pendingCompanyData.isEditMode = true; // Flag to indicate edit mode
					window.pendingCompanyData.existingCustomerId = companyData.customerId; // Store existing customer ID
					
					// Open the existing "Add Contact" modal
					$('.contact_modal').modal('show');
					
					// Pre-fill the modal with existing customer data
					setTimeout(function() {
						if (window.pendingCompanyData) {
							console.log('Filling modal fields for editing...');
							
							// Extract address parts first
							const addressParts = extractAddressParts(window.pendingCompanyData.address);
							console.log('Extracted address parts:', addressParts);
							
							// Try different field selectors
							const businessNameField = $('.contact_modal input[name="supplier_business_name"]');
							const firstNameField = $('.contact_modal input[name="first_name"]');
							const taxNumberField = $('.contact_modal input[name="tax_number"]');
							const addressField = $('.contact_modal textarea[name="address_line_1"], .contact_modal input[name="address_line_1"]');
							const cityField = $('.contact_modal input[name="city"]');
							const stateField = $('.contact_modal input[name="state"]');
							const zipField = $('.contact_modal input[name="zip_code"]');
							const countryField = $('.contact_modal input[name="country"]');
							const shippingAddressField = $('.contact_modal textarea[name="shipping_address"], .contact_modal input[name="shipping_address"]');
							const mobileField = $('.contact_modal input[name="mobile"]');
							
							// Fill the fields with existing data
							businessNameField.val(window.pendingCompanyData.companyNameTh);
							firstNameField.val(window.pendingCompanyData.companyNameTh);
							taxNumberField.val(window.pendingCompanyData.taxNumber);
							addressField.val(window.pendingCompanyData.address);
							cityField.val(addressParts.city);
							stateField.val(addressParts.state);
							zipField.val(addressParts.zipCode);
							countryField.val('Thailand');
							shippingAddressField.val(window.pendingCompanyData.address);
							mobileField.val(window.pendingCompanyData.mobile || ''); // Include existing mobile
							
							// Set contact type to business and show business fields
							$('.contact_modal input[name="contact_type_radio"][value="business"]').prop('checked', true).trigger('change');
							$('.contact_modal select[name="type"], .contact_modal select#contact_type').val('customer').trigger('change');
							
							// Show business fields and hide individual fields
							$('.contact_modal .business').show();
							$('.contact_modal .individual').hide();
							
							// Set assigned to current user if available
							const assignedToField = $('.contact_modal select[name="assigned_to_users[]"]');
							if (assignedToField.length && window.currentUserId) {
								assignedToField.val([window.currentUserId]).trigger('change');
							}
							
							// Store the customer ID in a hidden field for update
							let customerIdField = $('.contact_modal input[name="contact_id"]');
							if (customerIdField.length === 0) {
								// Create hidden field if it doesn't exist
								$('.contact_modal form').append('<input type="hidden" name="contact_id" value="' + window.pendingCompanyData.existingCustomerId + '">');
							} else {
								customerIdField.val(window.pendingCompanyData.existingCustomerId);
							}
							
							// Change modal title to indicate edit mode
							$('.contact_modal .modal-title').text('Edit Customer Information');
							
							console.log('Modal filled for editing existing customer with ID:', window.pendingCompanyData.existingCustomerId);
						}
					}, 300);
				} else {
					console.error('No customer data available for editing');
					toastr.error('Customer data not found');
				}
			});

			$(document).on('click', '.tax-lookup-use-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('Use company button clicked');
				
				const dataSource = $(this).data('source') || 'new';
				let companyData = null;
				
				if (window.lastCompanyLookupData && window.lastCompanyLookupData.dbData && window.lastCompanyLookupData.apiData) {
					// Both results available - use API data for new customer
					companyData = window.lastCompanyLookupData.apiData;
				} else if (window.lastCompanyLookupData) {
					// Single result
					companyData = window.lastCompanyLookupData;
				}
				
				if (companyData) {
					console.log('Using company data:', companyData);
					
					// Close the Select2 dropdown first
					closeCustomerSelect2();
					
					// Remove any existing temp options first
					$('#customer_id option[value^="temp_company_"]').remove();
					
					// Create a temporary option with company data and select it
					var tempOption = new Option(
						companyData.companyNameTh + ' (Tax ID: ' + companyData.taxNumber + ')',
						'temp_company_' + companyData.taxNumber,
						true,
						true
					);
					$('#customer_id').append(tempOption).trigger('change');
					
					// Store the company data for later use when saving
					$('#customer_id').data('temp-company-data', companyData);
					
					console.log('Customer field updated with temp option');
					console.log('Selected value:', $('#customer_id').val());
					console.log('Selected text:', $('#customer_id option:selected').text());
					
					// Update address displays
					updateAddressDisplays(companyData);
					
					// Hide any existing status messages
					hideTaxLookupStatus();
					
					// Show success message
					toastr.success('Using company: ' + companyData.companyNameTh + ' for this document');
					console.log('Company data applied to document successfully');
				} else {
					console.error('No company data available');
					toastr.error('No company data available');
				}
			});
			
			// Helper function to update address displays
			function updateAddressDisplays(companyData) {
				hideBillingAddressBlock();

				// Update billing address display if exists
				var addressDisplay = $('#billing_address_div');
				if (addressDisplay.length) {
					var addressHtml = '<strong>' + companyData.companyNameTh + '</strong><br>' +
									 (companyData.address || '') + '<br>' +
									 '<em>Tax ID: ' + companyData.taxNumber + '</em>';
					addressDisplay.html(addressHtml);
					console.log('Billing address updated');
				}
				
				// Update shipping address display if exists
				var shippingDisplay = $('#shipping_address_div');
				if (shippingDisplay.length) {
					var shippingHtml = '<strong>' + companyData.companyNameTh + '</strong><br>' +
									  (companyData.address || '');
					shippingDisplay.html(shippingHtml);
					console.log('Shipping address updated');
				}
				
				// IMPORTANT: Also populate the actual form fields for billing/invoice
				// Extract address parts for proper field population
				const addressParts = extractAddressParts(companyData.address || '');
				console.log('Populating form fields with extracted address parts:', addressParts);
				
				// Store customer address data in the form for invoice generation
				// Update or create hidden fields that will be used when saving the invoice
				updateOrCreateHiddenField('customer_address_line_1', companyData.address || '');
				updateOrCreateHiddenField('customer_city', addressParts.city);
				updateOrCreateHiddenField('customer_state', addressParts.state);
				updateOrCreateHiddenField('customer_zip_code', addressParts.zipCode);
				updateOrCreateHiddenField('customer_country', 'Thailand');
				updateOrCreateHiddenField('customer_shipping_address', companyData.address || '');
				updateOrCreateHiddenField('customer_business_name', companyData.companyNameTh);
				updateOrCreateHiddenField('customer_tax_number', companyData.taxNumber);
				
				console.log('Form fields populated with customer address data');
			}
			
			// Helper function to update or create hidden fields
			function updateOrCreateHiddenField(name, value) {
				let field = $('input[name="' + name + '"]');
				if (field.length === 0) {
					// Create the field if it doesn't exist
					$('#add_sell_form').append('<input type="hidden" name="' + name + '" value="' + (value || '') + '">');
					console.log('Created hidden field:', name, '=', value);
				} else {
					// Update existing field
					field.val(value || '');
					console.log('Updated field:', name, '=', value);
				}
			}

			$(document).on('click', '.tax-lookup-add-btn', function(e) {
				e.preventDefault();
				e.stopPropagation();
				console.log('Add company button clicked');
				
				// Close the Select2 dropdown first
				closeCustomerSelect2();
				
				const dataSource = $(this).data('source') || 'new';
				let companyData = null;
				
				if (window.lastCompanyLookupData && window.lastCompanyLookupData.dbData && window.lastCompanyLookupData.apiData) {
					// Both results available - use API data for adding new customer
					companyData = window.lastCompanyLookupData.apiData;
				} else if (window.lastCompanyLookupData) {
					// Single result
					companyData = window.lastCompanyLookupData;
				}
				
				if (companyData) {
					// Store the company data for the modal
					window.pendingCompanyData = companyData;
					window.pendingCompanyData.isEditMode = false; // Flag to indicate add mode
					console.log('Company data to fill:', window.pendingCompanyData);
					
					// Open the existing "Add Contact" modal
					$('.contact_modal').modal('show');
					
					// Pre-fill the modal with company data
					setTimeout(function() {
						if (window.pendingCompanyData) {
							console.log('Filling modal fields...');
							
							// Reset the form first
							$('.contact_modal form')[0].reset();
							
							// Remove any existing contact_id field (for add mode)
							$('.contact_modal input[name="contact_id"]').remove();
							
							// Extract address parts first
							const addressParts = extractAddressParts(window.pendingCompanyData.address);
							console.log('Extracted address parts:', addressParts);
							
							// Try different field selectors
							const businessNameField = $('.contact_modal input[name="supplier_business_name"]');
							const firstNameField = $('.contact_modal input[name="first_name"]');
							const taxNumberField = $('.contact_modal input[name="tax_number"]');
							const addressField = $('.contact_modal textarea[name="address_line_1"], .contact_modal input[name="address_line_1"]');
							const cityField = $('.contact_modal input[name="city"]');
							const stateField = $('.contact_modal input[name="state"]');
							const zipField = $('.contact_modal input[name="zip_code"]');
							const countryField = $('.contact_modal input[name="country"]');
							const shippingAddressField = $('.contact_modal textarea[name="shipping_address"], .contact_modal input[name="shipping_address"]');
							
							console.log('Fields found:');
							console.log('Business name field:', businessNameField.length);
							console.log('First name field:', firstNameField.length);
							console.log('Tax number field:', taxNumberField.length);
							console.log('Address field:', addressField.length);
							console.log('City field:', cityField.length);
							console.log('State field:', stateField.length);
							console.log('Zip field:', zipField.length);
							console.log('Country field:', countryField.length);
							console.log('Shipping address field:', shippingAddressField.length);
							
							// Fill the fields
							businessNameField.val(window.pendingCompanyData.companyNameTh);
							firstNameField.val(window.pendingCompanyData.companyNameTh);
							taxNumberField.val(window.pendingCompanyData.taxNumber);
							addressField.val(window.pendingCompanyData.address);
							cityField.val(addressParts.city);
							stateField.val(addressParts.state);
							zipField.val(addressParts.zipCode);
							countryField.val('Thailand');
							shippingAddressField.val(window.pendingCompanyData.address);
							
							// Set contact type to business and show business fields
							$('.contact_modal input[name="contact_type_radio"][value="business"]').prop('checked', true).trigger('change');
							$('.contact_modal select[name="type"], .contact_modal select#contact_type').val('customer').trigger('change');
							
							// Show business fields and hide individual fields
							$('.contact_modal .business').show();
							$('.contact_modal .individual').hide();
							
							// Set assigned to current user if available
							const assignedToField = $('.contact_modal select[name="assigned_to_users[]"]');
							if (assignedToField.length && window.currentUserId) {
								assignedToField.val([window.currentUserId]).trigger('change');
							}
							
							console.log('Fields filled with values:');
							console.log('City:', addressParts.city);
							console.log('State:', addressParts.state);
							console.log('Zip:', addressParts.zipCode);
						}
					}, 300);
				}
			});

			// Test function for manual testing
			window.testTaxLookup = function() {
				console.log('Manual test triggered');
				lookupTaxId('0103555019171'); // Test with known working Tax ID
			};

			// Handle create contact with company data
			$(document).on('click', '#tax_lookup_create_btn', function() {
				if (!companyData) {
					toastr.error('No company data available');
					return;
				}

				// Extract city, state, and zip from address
				const address = companyData.address || '';
				const addressParts = extractAddressParts(address);

				// Prepare contact data
				const contactData = {
					name: companyData.companyNameTh,
					supplier_business_name: companyData.companyNameTh,
					tax_number: companyData.taxNumber,
					contact_type: 'business',
					type: 'customer',
					business_id: 1, // Assuming business_id is 1
					city: addressParts.city,
					state: addressParts.state,
					country: 'Thailand',
					address_line_1: address,
					zip_code: addressParts.zipCode,
					mobile: '-',
					shipping_address: address
				};

				console.log('Creating contact with data:', contactData);

				// Show loading
				toastr.info('Creating customer...', 'Please wait');

				// Create contact via AJAX
				$.ajax({
					url: '{{ route("contacts.store") }}',
					method: 'POST',
					data: contactData,
					headers: {
						'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
					},
					success: function(response) {
						if (response.success) {
							toastr.success('Customer created successfully!');
							
							// Add the new contact to the dropdown and select it
							const newOption = new Option(response.data.name, response.data.id, true, true);
							$('#customer_id').append(newOption).trigger('change');
							
							// Hide lookup status
							hideTaxLookupStatus();
							
							// Clear the company data
							companyData = null;
							lastLookedUpTaxId = '';
						} else {
							toastr.error(response.msg || 'Failed to create customer');
						}
					},
					error: function(xhr, status, error) {
						console.error('Contact creation failed:', xhr, status, error);
						let errorMsg = 'Failed to create customer';
						
						if (xhr.responseJSON && xhr.responseJSON.msg) {
							errorMsg = xhr.responseJSON.msg;
						} else if (xhr.responseJSON && xhr.responseJSON.message) {
							errorMsg = xhr.responseJSON.message;
						}
						
						toastr.error(errorMsg);
					}
				});
			});

			// Handle edit contact data (open modal)
			$(document).on('click', '#tax_lookup_edit_btn', function() {
				if (!companyData) {
					toastr.error('No company data available');
					return;
				}

				// Store company data and trigger the add new customer modal
				$('.add_new_customer').data('company-data', companyData);
				$('.add_new_customer').trigger('click');
			});

			// Override the add_new_customer click handler to handle company data
			$(document).off('click', '.add_new_customer').on('click', '.add_new_customer', function() {
				closeCustomerSelect2();
				var name = $(this).data('name');
				var companyData = $(this).data('company-data');
				
				// Clear the modal first
				$('.contact_modal').find('form')[0].reset();
				
				if (companyData) {
					// Pre-fill with company data
					const addressParts = extractAddressParts(companyData.address || '');
					
					// Set contact type to business first and trigger change to show/hide fields
					$('.contact_modal').find('input[name="contact_type_radio"][value="business"]').prop('checked', true).trigger('change');
					$('.contact_modal').find('select#contact_type').val('customer').trigger('change');
					
					// Fill business fields
					$('.contact_modal').find('input[name="supplier_business_name"]').val(companyData.companyNameTh);
					$('.contact_modal').find('input[name="first_name"]').val(companyData.companyNameTh);
					$('.contact_modal').find('input[name="tax_number"]').val(companyData.taxNumber);
					$('.contact_modal').find('input[name="address_line_1"]').val(companyData.address);
					$('.contact_modal').find('input[name="city"]').val(addressParts.city);
					$('.contact_modal').find('input[name="state"]').val(addressParts.state);
					$('.contact_modal').find('input[name="country"]').val('Thailand');
					$('.contact_modal').find('input[name="zip_code"]').val(addressParts.zipCode);
					$('.contact_modal').find('input[name="mobile"]').val('-');
					$('.contact_modal').find('input[name="shipping_address"]').val(companyData.address);
					
					// Show business fields and hide individual fields
					$('.contact_modal').find('.business').show();
					$('.contact_modal').find('.individual').hide();
					
					// Set assigned to current user
					const assignedToField = $('.contact_modal select[name="assigned_to_users[]"]');
					if (assignedToField.length && window.currentUserId) {
						assignedToField.val([window.currentUserId]).trigger('change');
					}
					
					// Clear company data after use
					$(this).removeData('company-data');
				} else {
					// Regular behavior - set to individual by default
					$('.contact_modal').find('input[name="contact_type_radio"][value="individual"]').prop('checked', true);
					$('.contact_modal').find('input[name="first_name"]').val(name);
					
					// Show individual fields and hide business fields
					$('.contact_modal').find('.individual').show();
					$('.contact_modal').find('.business').hide();
				}
				
				$('.contact_modal')
					.find('select#contact_type')
					.val('customer')
					.closest('div.contact_type_div')
					.addClass('hide');
				$('.contact_modal').modal('show');
			});

			// Edit selected customer from create page
			$(document).on('click', '.edit_customer_btn', function(e) {
				e.preventDefault();
				closeCustomerSelect2();

				var contactId = $('#customer_id').val();
				if (!contactId) {
					toastr.error('Please select a customer first');
					return;
				}

				if (typeof contactId === 'string' && contactId.indexOf('temp_company_') === 0) {
					toastr.error('Please save the customer first before editing');
					return;
				}

				$('.contact_modal').modal('show');
				$.ajax({
					url: '/contacts/' + contactId + '/edit?compact=1',
					type: 'GET',
					success: function(response) {
						$('.contact_modal .modal-content').html(response);

						// Setup radio button change handler and default selection
						setTimeout(function() {
							simplifyCustomerModalAdditionalFields($('.contact_modal'));
							var $radios = $('.contact_modal input[name="contact_type_radio"]');
							var contactName = $('.contact_modal #hidden_contact_name').val() || '';

							// Bind change event for individual/business toggle
							$radios.off('change').on('change', function() {
								if (this.value == 'individual') {
									$('.contact_modal div.individual').show();
									$('.contact_modal div.business').hide();

									// Auto-populate first_name/last_name from contact name if empty
									var $firstName = $('.contact_modal input[name="first_name"]');
									var $lastName = $('.contact_modal input[name="last_name"]');

									if (contactName && !$firstName.val() && !$lastName.val()) {
										// Split Thai name: first word = first name, rest = last name
										var nameParts = contactName.trim().split(/\s+/);
										if (nameParts.length >= 2) {
											$firstName.val(nameParts[0]);
											$lastName.val(nameParts.slice(1).join(' '));
										} else if (nameParts.length === 1) {
											$firstName.val(nameParts[0]);
										}
										console.log('Auto-populated name from:', contactName, '-> First:', $firstName.val(), 'Last:', $lastName.val());
									}
								} else if (this.value == 'business') {
									$('.contact_modal div.individual').hide();
									$('.contact_modal div.business').show();
								}
							});

							// If no contact_type radio is checked, default to 'individual'
							if ($radios.length && !$radios.filter(':checked').length) {
								$radios.filter('[value="individual"]').prop('checked', true);
								$('.contact_modal div.individual').show();
								$('.contact_modal div.business').hide();

								// Also populate names for default individual selection
								var $firstName = $('.contact_modal input[name="first_name"]');
								var $lastName = $('.contact_modal input[name="last_name"]');

								if (contactName && !$firstName.val() && !$lastName.val()) {
									var nameParts = contactName.trim().split(/\s+/);
									if (nameParts.length >= 2) {
										$firstName.val(nameParts[0]);
										$lastName.val(nameParts.slice(1).join(' '));
									} else if (nameParts.length === 1) {
										$firstName.val(nameParts[0]);
									}
								}
							} else {
								// Trigger change to show/hide correct fields based on current selection
								$radios.filter(':checked').trigger('change');
							}
						}, 100);
					},
					error: function(xhr, status, error) {
						console.error('Error loading customer edit form:', error);
						toastr.error('Error loading customer edit form');
					}
				});
			});

			// Handle successful contact creation/update from modal
			$(document).on('submit', '.contact_modal form', function(e) {
				const isEditMode = window.pendingCompanyData && window.pendingCompanyData.isEditMode;
				const existingCustomerId = window.pendingCompanyData && window.pendingCompanyData.existingCustomerId;
				
				console.log('Contact form submitted. Edit mode:', isEditMode, 'Customer ID:', existingCustomerId);
				
				// Don't prevent default - let the form submit normally
				// But listen for the success response
			});

			// Listen for AJAX success on contact form
			$(document).ajaxSuccess(function(event, xhr, settings) {
				// Only handle contact create/update responses (not search/details GET endpoints).
				const requestMethod = (settings.type || 'GET').toUpperCase();
				const requestUrl = settings.url || '';
				const urlParser = document.createElement('a');
				urlParser.href = requestUrl;
				const requestPath = (urlParser.pathname || requestUrl).replace(/\/+$/, '');
				const isContactCreateOrUpdatePath = /\/contacts(?:\/\d+)?$/.test(requestPath);

				if (requestMethod !== 'GET' && isContactCreateOrUpdatePath) {
					try {
						const response = JSON.parse(xhr.responseText);
						
						if (response && response.success && response.data) {
							console.log('Contact saved successfully:', response.data);
							
							// Check if this was an edit operation
							const isEditMode = window.pendingCompanyData && window.pendingCompanyData.isEditMode;
							
							if (isEditMode) {
								// This was an update - refresh the customer dropdown to show updated info
								toastr.success('Customer updated successfully: ' + response.data.name);
								
								// Update the customer dropdown option if it exists
								const customerId = response.data.id;
								const existingOption = $('#customer_id option[value="' + customerId + '"]');
								if (existingOption.length > 0) {
									existingOption.text(response.data.name);
									existingOption.attr('selected', true);
									$('#customer_id').trigger('change');
								} else {
									// Add new option and select it
									const newOption = new Option(response.data.name, customerId, true, true);
									$('#customer_id').append(newOption).trigger('change');
								}
							} else {
								// This was a new contact creation
								toastr.success('Customer created successfully: ' + response.data.name);
								
								// Add the new customer to the dropdown and select it
								const newOption = new Option(response.data.name, response.data.id, true, true);
								$('#customer_id').append(newOption).trigger('change');
							}
							
							// Close the modal
							$('.contact_modal').modal('hide');
							
							// Clear pending data
							window.pendingCompanyData = null;
							
							// Hide tax lookup status
							hideTaxLookupStatus();
						}
					} catch (e) {
						console.log('Response parsing failed:', e);
					}
				}
			});

			// Function to extract address parts
			function extractAddressParts(address) {
				const parts = {
					city: '',
					state: '',
					zipCode: ''
				};

				if (!address) {
					return parts;
				}

				console.log('Parsing Thai address:', address);

				const zipMatch = address.match(/(\d{5})(?!.*\d)/);
				if (zipMatch) {
					parts.zipCode = zipMatch[1];
				}

				const addressWithoutZip = address.replace(/\s*\d{5}\s*$/, '').trim();

				const thaiProvinces = [
					'กรุงเทพมหานคร', 'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี', 'ฉะเชิงเทรา',
					'ชลบุรี', 'ชัยนาท', 'ชัยภูมิ', 'ชุมพร', 'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด', 'ตาก', 'นครนายก',
					'นครปฐม', 'นครพนม', 'นครราชสีมา', 'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี', 'นราธิวาส', 'น่าน',
					'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์', 'ปราจีนบุรี', 'ปัตตานี', 'พระนครศรีอยุธยา', 'พะเยา',
					'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก', 'เพชรบุรี', 'เพชรบูรณ์', 'แพร่', 'ภูเก็ต', 'มหาสารคาม',
					'มุกดาหาร', 'แม่ฮ่องสอน', 'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง', 'ราชบุรี', 'ลพบุรี',
					'ลำปาง', 'ลำพูน', 'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ', 'สมุทรสงคราม',
					'สมุทรสาคร', 'สระแก้ว', 'สระบุรี', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี', 'สุราษฎร์ธานี', 'สุรินทร์',
					'หนองคาย', 'หนองบัวลำภู', 'อ่างทอง', 'อำนาจเจริญ', 'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี', 'อุบลราชธานี'
				];

				let provinceFound = '';
				const provinceMatch = addressWithoutZip.match(/(?:จังหวัด|จ\.)\s*([ก-๙]+)/);
				if (provinceMatch) {
					provinceFound = provinceMatch[1].trim();
				}

				if (!provinceFound) {
					for (let i = 0; i < thaiProvinces.length; i++) {
						if (addressWithoutZip.includes(thaiProvinces[i])) {
							provinceFound = thaiProvinces[i];
							break;
						}
					}
				}

				// Bangkok addresses often use เขต/แขวง without explicit province text.
				if (!provinceFound && /(เขต|แขวง)/.test(addressWithoutZip)) {
					provinceFound = 'กรุงเทพมหานคร';
				}

				const districtMatch = addressWithoutZip.match(/(?:อำเภอ|อ\.|เขต)\s*([ก-๙]+)/);
				const subDistrictMatch = addressWithoutZip.match(/(?:ตำบล|ต\.|แขวง)\s*([ก-๙]+)/);

					if (districtMatch && districtMatch[1]) {
						parts.city = districtMatch[1].trim();
					} else if (subDistrictMatch && subDistrictMatch[1]) {
						parts.city = subDistrictMatch[1].trim();
					}

					if (provinceFound) {
						parts.state = provinceFound;
					}

					// For Bangkok, city should be province-level, not district/sub-district.
					if (parts.state === 'กรุงเทพมหานคร') {
						parts.city = 'กรุงเทพมหานคร';
					}

					// Final fallback: keep city non-empty when possible.
					if (!parts.city && parts.state) {
						parts.city = parts.state;
					}

				console.log('Extracted address parts:', parts);
				return parts;
			}

				// Auto-fill from AI quotation plan
				var aiQuotationItems = sessionStorage.getItem('ai_quotation_items');
				if (aiQuotationItems) {
					try {
						var items = JSON.parse(aiQuotationItems);
						if (Array.isArray(items) && items.length > 0) {
							var delay = 100;
							items.forEach(function(item) {
								setTimeout(function() {
									if (item.variation_id && item.quantity) {
										pos_product_row(item.variation_id, null, null, item.quantity);
									}
								}, delay);
								delay += 300;
							});
						}
					} catch(e) {
						console.error("Failed to parse AI quotation items", e);
					}
					// Clear it after using
					sessionStorage.removeItem('ai_quotation_items');
				}

    	});
    </script>
@endsection
