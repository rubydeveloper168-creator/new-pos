<!-- business information here -->
<style>
.receipt-items-legacy td {
    padding: 3px 0 !important;
    border-top: 0 !important;
    vertical-align: top !important;
}
.receipt-items-legacy .receipt-item-heading td {
    padding-top: 6px !important;
}
.receipt-items-legacy .receipt-item-second td {
    padding-top: 0 !important;
    padding-bottom: 2px !important;
}
.receipt-items-legacy .receipt-item-price td {
    border-bottom: 1px solid #d8d8d8 !important;
    padding-bottom: 6px !important;
}
.receipt-items-legacy .receipt-item-total-row th {
    border-top: 1px solid #d8d8d8 !important;
    font-weight: 700 !important;
    padding-top: 4px !important;
    padding-bottom: 4px !important;
}
.receipt-summary-payment td,
.receipt-summary-payment th,
.receipt-summary-totals td,
.receipt-summary-totals th {
    white-space: nowrap !important;
    vertical-align: middle !important;
    padding: 3px 4px !important;
}
.receipt-summary-payment td {
    font-size: 11px !important;
}
.receipt-summary-payment td:last-child,
.receipt-summary-totals td {
    text-align: right !important;
}
.receipt-payment-line-table td {
    white-space: nowrap !important;
    padding: 4px 6px !important;
    border-top: 1px solid #d8d8d8 !important;
    border-bottom: 1px solid #d8d8d8 !important;
    font-size: 11px !important;
    vertical-align: middle !important;
    width: 33.33% !important;
}
.receipt-payment-line-table td:nth-child(2) {
    text-align: center !important;
}
.receipt-payment-line-table td:nth-child(3) {
    text-align: right !important;
}
.receipt-payment-line-table {
    margin-bottom: 0 !important;
}
</style>

<div class="row" style="color: #000000 !important;">
		<!-- Logo -->
		@if(empty($receipt_details->letter_head))
			@if(!empty($receipt_details->logo))
				<img style="max-height: 120px; width: auto;" src="{{$receipt_details->logo}}" class="img img-responsive center-block">
			@endif

			<!-- Header text -->
			@if(!empty($receipt_details->header_text))
				<div class="col-xs-12">
					{!! $receipt_details->header_text !!}
				</div>
			@endif

			<!-- business information here -->
			<div class="col-xs-12 text-center">
				<h2 class="text-center">
					<!-- Shop & Location Name  -->
					@if(!empty($receipt_details->display_name))
						{{$receipt_details->display_name}}
					@endif
				</h2>

				<!-- Address -->
				<p>
				@if(!empty($receipt_details->address))
						<small class="text-center">
						{!! $receipt_details->address !!}
						</small>
				@endif
				@if(!empty($receipt_details->contact))
					<br/>{!! $receipt_details->contact !!}
				@endif	
				@if(!empty($receipt_details->contact) && !empty($receipt_details->website))
					, 
				@endif
				@if(!empty($receipt_details->website))
					{{ $receipt_details->website }}
				@endif
				@if(!empty($receipt_details->location_custom_fields))
					<br>{{ $receipt_details->location_custom_fields }}
				@endif
				</p>
				<p>
				@if(!empty($receipt_details->sub_heading_line1))
					{{ $receipt_details->sub_heading_line1 }}
				@endif
				@if(!empty($receipt_details->sub_heading_line2))
					<br>{{ $receipt_details->sub_heading_line2 }}
				@endif
				@if(!empty($receipt_details->sub_heading_line3))
					<br>{{ $receipt_details->sub_heading_line3 }}
				@endif
				@if(!empty($receipt_details->sub_heading_line4))
					<br>{{ $receipt_details->sub_heading_line4 }}
				@endif		
				@if(!empty($receipt_details->sub_heading_line5))
					<br>{{ $receipt_details->sub_heading_line5 }}
				@endif
				</p>
				<p>
				@if(!empty($receipt_details->tax_info1))
					<b>{{ $receipt_details->tax_label1 }}</b> {{ $receipt_details->tax_info1 }}
				@endif

				@if(!empty($receipt_details->tax_info2))
					<b>{{ $receipt_details->tax_label2 }}</b> {{ $receipt_details->tax_info2 }}
				@endif
				</p>
			@endif


				<!-- Title removed to match receipt reference -->
			</div>
		@if(!empty($receipt_details->letter_head))
			<div class="col-xs-12 text-center">
				<img style="width: 100%;margin-bottom: 10px;" src="{{$receipt_details->letter_head}}">
			</div>
		@endif
	<div class="col-xs-12 text-center">
		<!-- Invoice  number, Date  -->
		<p style="width: 100% !important" class="word-wrap">
					<span class="pull-left text-left word-wrap">
						@php
							$sale_number_text = trim((string) ($receipt_details->sale_number ?? ''));
							$sale_ref_text = trim((string) ($receipt_details->invoice_no ?? ''));
							$sale_date_text = trim((string) ($receipt_details->invoice_date ?? ''));
							$sales_support_text = trim((string) ($receipt_details->sales_support_user ?? ''));
							$customer_text = trim((string) ($receipt_details->customer_name ?? ''));
							if ($customer_text === '') {
								$customer_info_raw = str_ireplace(['<br/>', '<br />', '<br>'], "\n", (string) ($receipt_details->customer_info ?? ''));
								$customer_info_raw = trim(strip_tags($customer_info_raw));
								$customer_text = trim(explode("\n", $customer_info_raw)[0] ?? '');
							}
						@endphp
						sale number: {{$sale_number_text}}
						<br>วันที่: {{$sale_date_text}}
						<br>sale ref: {{$sale_ref_text}}
						<br>ขายสมทบ: {{$sales_support_text}}
						<br>ลูกค้า: {{$customer_text}}

					@if(!empty($receipt_details->types_of_service))
						<br/>
					<span class="pull-left text-left">
						<strong>{!! $receipt_details->types_of_service_label !!}:</strong>
						{{$receipt_details->types_of_service}}
						<!-- Waiter info -->
						@if(!empty($receipt_details->types_of_service_custom_fields))
							@foreach($receipt_details->types_of_service_custom_fields as $key => $value)
								<br><strong>{{$key}}: </strong> {{$value}}
							@endforeach
						@endif
					</span>
				@endif

				<!-- Table information-->
		        @if(!empty($receipt_details->table_label) || !empty($receipt_details->table))
		        	<br/>
					<span class="pull-left text-left">
						@if(!empty($receipt_details->table_label))
							<b>{!! $receipt_details->table_label !!}</b>
						@endif
						{{$receipt_details->table}}

						<!-- Waiter info -->
					</span>
		        @endif

				</span>

				<span class="pull-right text-left">
					@if(!empty($receipt_details->brand_label) || !empty($receipt_details->repair_brand))
						<br>
						@if(!empty($receipt_details->brand_label))
						<b>{!! $receipt_details->brand_label !!}</b>
					@endif
					{{$receipt_details->repair_brand}}
		        @endif


		        @if(!empty($receipt_details->device_label) || !empty($receipt_details->repair_device))
					<br>
					@if(!empty($receipt_details->device_label))
						<b>{!! $receipt_details->device_label !!}</b>
					@endif
					{{$receipt_details->repair_device}}
		        @endif

				@if(!empty($receipt_details->model_no_label) || !empty($receipt_details->repair_model_no))
					<br>
					@if(!empty($receipt_details->model_no_label))
						<b>{!! $receipt_details->model_no_label !!}</b>
					@endif
					{{$receipt_details->repair_model_no}}
		        @endif

				@if(!empty($receipt_details->serial_no_label) || !empty($receipt_details->repair_serial_no))
					<br>
					@if(!empty($receipt_details->serial_no_label))
						<b>{!! $receipt_details->serial_no_label !!}</b>
					@endif
					{{$receipt_details->repair_serial_no}}<br>
		        @endif
				@if(!empty($receipt_details->repair_status_label) || !empty($receipt_details->repair_status))
					@if(!empty($receipt_details->repair_status_label))
						<b>{!! $receipt_details->repair_status_label !!}</b>
					@endif
					{{$receipt_details->repair_status}}<br>
		        @endif
		        
		        @if(!empty($receipt_details->repair_warranty_label) || !empty($receipt_details->repair_warranty))
					@if(!empty($receipt_details->repair_warranty_label))
						<b>{!! $receipt_details->repair_warranty_label !!}</b>
					@endif
					{{$receipt_details->repair_warranty}}
					<br>
		        @endif
		        
				<!-- Waiter info -->
				@if(!empty($receipt_details->service_staff_label) || !empty($receipt_details->service_staff))
		        	<br/>
					@if(!empty($receipt_details->service_staff_label))
						<b>{!! $receipt_details->service_staff_label !!}</b>
					@endif
					{{$receipt_details->service_staff}}
		        @endif
		        @if(!empty($receipt_details->shipping_custom_field_1_label))
					<br><strong>{!!$receipt_details->shipping_custom_field_1_label!!} :</strong> {!!$receipt_details->shipping_custom_field_1_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->shipping_custom_field_2_label))
					<br><strong>{!!$receipt_details->shipping_custom_field_2_label!!}:</strong> {!!$receipt_details->shipping_custom_field_2_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->shipping_custom_field_3_label))
					<br><strong>{!!$receipt_details->shipping_custom_field_3_label!!}:</strong> {!!$receipt_details->shipping_custom_field_3_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->shipping_custom_field_4_label))
					<br><strong>{!!$receipt_details->shipping_custom_field_4_label!!}:</strong> {!!$receipt_details->shipping_custom_field_4_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->shipping_custom_field_5_label))
					<br><strong>{!!$receipt_details->shipping_custom_field_2_label!!}:</strong> {!!$receipt_details->shipping_custom_field_5_value ?? ''!!}
				@endif
				{{-- sale order --}}
				@if(!empty($receipt_details->sale_orders_invoice_no))
					<br>
					<strong>@lang('restaurant.order_no'):</strong> {!!$receipt_details->sale_orders_invoice_no ?? ''!!}
				@endif

				@if(!empty($receipt_details->sale_orders_invoice_date))
					<br>
					<strong>@lang('lang_v1.order_dates'):</strong> {!!$receipt_details->sale_orders_invoice_date ?? ''!!}
				@endif

				@if(!empty($receipt_details->sell_custom_field_1_value))
					<br>
					<strong>{{ $receipt_details->sell_custom_field_1_label }}:</strong> {!!$receipt_details->sell_custom_field_1_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->sell_custom_field_2_value))
					<br>
					<strong>{{ $receipt_details->sell_custom_field_2_label }}:</strong> {!!$receipt_details->sell_custom_field_2_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->sell_custom_field_3_value))
					<br>
					<strong>{{ $receipt_details->sell_custom_field_3_label }}:</strong> {!!$receipt_details->sell_custom_field_3_value ?? ''!!}
				@endif

				@if(!empty($receipt_details->sell_custom_field_4_value))
					<br>
					<strong>{{ $receipt_details->sell_custom_field_4_label }}:</strong> {!!$receipt_details->sell_custom_field_4_value ?? ''!!}
				@endif

			</span>
		</p>
	</div>
</div>

<div class="row" style="color: #000000 !important;">
	@includeIf('sale_pos.receipts.partial.common_repair_invoice')
</div>

<div class="row" style="color: #000000 !important;">
	<div class="col-xs-12">
		<br/>
		<table class="table table-slim receipt-items-legacy">
			<tbody>
				@forelse($receipt_details->lines as $line)
					@php
						$line_name = trim($line['name'] ?? '');
						$second_line = trim(
							collect([
								$line['product_variation'] ?? '',
								$line['variation'] ?? '',
							])->filter()->implode(' ')
						);
						if (empty($second_line) && !empty($line['product_description'])) {
							$second_line = trim(strip_tags($line['product_description']));
						}
						if (empty($second_line) && !empty($line['sell_line_note'])) {
							$second_line = trim(strip_tags($line['sell_line_note']));
						}
						$tax_code = '';
						if (isset($line['tax_unformatted']) && (float) $line['tax_unformatted'] == 0.0) {
							$tax_code = 'NT';
						} elseif (!empty($line['tax_name'])) {
							$tax_code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $line['tax_name']));
							$tax_code = substr($tax_code, 0, 4);
						}
					@endphp
					<tr class="receipt-item-heading">
						<td colspan="2">#{{$loop->iteration}}:&nbsp;&nbsp;{{$line_name}}</td>
						<td class="text-right">@if(!empty($tax_code))*{{$tax_code}}@endif</td>
					</tr>
					@if(!empty($second_line))
						<tr class="receipt-item-second">
							<td colspan="3">{{$second_line}}</td>
						</tr>
					@endif
					<tr class="receipt-item-price">
						<td colspan="2">{{$line['quantity']}}{{$line['units']}} x {{$line['unit_price_before_discount']}}</td>
						<td class="text-right">{{$line['line_total']}}</td>
					</tr>
				@empty
					<tr>
						<td colspan="3">&nbsp;</td>
					</tr>
				@endforelse
				@if(empty($receipt_details->hide_price))
					<tr class="receipt-item-total-row">
						<th colspan="2">ทั้งหมด</th>
						<th class="text-right">{{$receipt_details->subtotal}}</th>
					</tr>
					<tr class="receipt-item-total-row">
						<th colspan="2">ทั้งหมด</th>
						<th class="text-right">{{$receipt_details->total}}</th>
					</tr>
				@endif
			</tbody>
		</table>
	</div>
</div>

<div class="row" style="color: #000000 !important;">
	<div class="col-md-12"><hr/></div>
	@php
		$summary_payment_method = '';
		$summary_payment_amount = '0.00';
		$summary_change = '0';
		$has_payment_summary = false;
		$parse_amount = function ($value) {
			$clean = str_replace(',', '', (string) $value);
			$clean = preg_replace('/[^0-9\.\-]/', '', $clean);
			return is_numeric($clean) ? (float) $clean : 0.0;
		};
		$format_amount = function ($value) use ($parse_amount) {
			return number_format($parse_amount($value), 2);
		};
		if (!empty($receipt_details->payments) && is_array($receipt_details->payments)) {
			$first_payment = $receipt_details->payments[0];
			$raw_method = (string) ($first_payment['method'] ?? '');
			$method_text = trim(strip_tags(str_ireplace(['<br/>', '<br />', '<br>'], "\n", $raw_method)));
			$method_text = trim(explode("\n", $method_text)[0] ?? '');
			$payment_method_map = [
				'Cash' => 'เงินสด',
				'Card' => 'บัตรเครดิต',
				'Cheque' => 'เช็ค',
				'Bank Transfer' => 'โอนเงินผ่านธนาคาร',
				'Other' => 'อื่นๆ',
				'Advance' => 'เงินมัดจำ',
			];
			foreach ($payment_method_map as $en => $th) {
				if (stripos($method_text, $en) === 0) {
					$method_text = preg_replace('/^' . preg_quote($en, '/') . '/i', $th, $method_text);
					break;
				}
			}
			$summary_payment_method = !empty($method_text) ? $method_text : 'เงินสด';
			$summary_payment_amount = $format_amount($receipt_details->total_paid ?? ($first_payment['amount'] ?? '0'));
			$has_payment_summary = true;
		} elseif (!empty($receipt_details->total_paid) || !empty($receipt_details->total)) {
			$summary_payment_method = 'เงินสด';
			$summary_payment_amount = $format_amount($receipt_details->total_paid ?? $receipt_details->total ?? '0');
			$has_payment_summary = true;
		}
		$paid_num = $parse_amount($receipt_details->total_paid ?? $summary_payment_amount);
		$total_num = $parse_amount($receipt_details->total ?? '0');
		$change_num = $paid_num - $total_num;
		if ($change_num > 0.00001) {
			$summary_change = number_format($change_num, 2);
		}
	@endphp
	@if($has_payment_summary)
		<div class="col-xs-12">
			<table class="table table-slim receipt-payment-line-table">
				<tr>
					<td>ชำระเงินโดย: {{$summary_payment_method}}</td>
					<td>จำนวนเงิน: {{$summary_payment_amount}}</td>
					<td>เปลี่ยน: {{$summary_change}}</td>
				</tr>
			</table>
		</div>
	@endif
	<div class="col-xs-6">

			<table class="table table-slim receipt-summary-payment">
			<!-- Total Due-->
			@if(!empty($receipt_details->total_due) && !empty($receipt_details->total_due_label))
			<tr>
				<th>
					{!! $receipt_details->total_due_label !!}
				</th>
				<td class="text-right">
					{{$receipt_details->total_due}}
				</td>
			</tr>
			@endif

			@if(!empty($receipt_details->all_due))
			<tr>
				<th>
					{!! $receipt_details->all_bal_label !!}
				</th>
				<td class="text-right">
					{{$receipt_details->all_due}}
				</td>
			</tr>
			@endif
		</table>
	</div>

	<div class="col-xs-6">
        <div class="table-responsive">
	          	<table class="table table-slim receipt-summary-totals">
				<tbody>
					@if(!empty($receipt_details->total_quantity_label))
						<tr>
							<th style="width:70%">
								{!! $receipt_details->total_quantity_label !!}
							</th>
							<td class="text-right">
								{{$receipt_details->total_quantity}}
							</td>
						</tr>
					@endif

					@if(!empty($receipt_details->total_items_label))
						<tr>
							<th style="width:70%">
								{!! $receipt_details->total_items_label !!}
							</th>
							<td class="text-right">
								{{$receipt_details->total_items}}
							</td>
						</tr>
					@endif
					@if(!empty($receipt_details->total_exempt_uf))
					<tr>
						<th style="width:70%">
							@lang('lang_v1.exempt')
						</th>
						<td class="text-right">
							{{$receipt_details->total_exempt}}
						</td>
					</tr>
					@endif
					<!-- Shipping Charges -->
					@if(!empty($receipt_details->shipping_charges))
						<tr>
							<th style="width:70%">
								{!! $receipt_details->shipping_charges_label !!}
							</th>
							<td class="text-right">
								{{$receipt_details->shipping_charges}}
							</td>
						</tr>
					@endif

					@if(!empty($receipt_details->packing_charge))
						<tr>
							<th style="width:70%">
								{!! $receipt_details->packing_charge_label !!}
							</th>
							<td class="text-right">
								{{$receipt_details->packing_charge}}
							</td>
						</tr>
					@endif

					<!-- Discount -->
					@if( !empty($receipt_details->discount) )
						<tr>
							<th>
								{!! $receipt_details->discount_label !!}
							</th>

							<td class="text-right">
								(-) {{$receipt_details->discount}}
							</td>
						</tr>
					@endif

					@if( !empty($receipt_details->total_line_discount) )
						<tr>
							<th>
								{!! $receipt_details->line_discount_label !!}
							</th>

							<td class="text-right">
								(-) {{$receipt_details->total_line_discount}}
							</td>
						</tr>
					@endif

					@if( !empty($receipt_details->additional_expenses) )
						@foreach($receipt_details->additional_expenses as $key => $val)
							<tr>
								<td>
									{{$key}}:
								</td>

								<td class="text-right">
									(+) {{$val}}
								</td>
							</tr>
						@endforeach
					@endif

					@if( !empty($receipt_details->reward_point_label) )
						<tr>
							<th>
								{!! $receipt_details->reward_point_label !!}
							</th>

							<td class="text-right">
								(-) {{$receipt_details->reward_point_amount}}
							</td>
						</tr>
					@endif

					<!-- Tax -->
					@if( !empty($receipt_details->tax) )
						<tr>
							<th>
								{!! $receipt_details->tax_label !!}
							</th>
							<td class="text-right">
								(+) {{$receipt_details->tax}}
							</td>
						</tr>
					@endif

					@if( $receipt_details->round_off_amount > 0)
						<tr>
							<th>
								{!! $receipt_details->round_off_label !!}
							</th>
							<td class="text-right">
								{{$receipt_details->round_off}}
							</td>
						</tr>
					@endif

					@if(!empty($receipt_details->total_in_words))
						<tr>
							<td colspan="2" class="text-right">
								<small>({{$receipt_details->total_in_words}})</small>
							</td>
						</tr>
					@endif
				</tbody>
        	</table>
        </div>
    </div>

    <div class="border-bottom col-md-12">
	    @if(empty($receipt_details->hide_price) && !empty($receipt_details->tax_summary_label) )
	        <!-- tax -->
	        @if(!empty($receipt_details->taxes))
	        	<table class="table table-slim table-bordered">
	        		<tr>
	        			<th colspan="2" class="text-center">{{$receipt_details->tax_summary_label}}</th>
	        		</tr>
	        		@foreach($receipt_details->taxes as $key => $val)
	        			<tr>
	        				<td class="text-center"><b>{{$key}}</b></td>
	        				<td class="text-center">{{$val}}</td>
	        			</tr>
	        		@endforeach
	        	</table>
	        @endif
	    @endif
	</div>

	@if(!empty($receipt_details->additional_notes))
	    <div class="col-xs-12">
	    	<p><strong>บันทึกการขาย:</strong><br>{!! nl2br($receipt_details->additional_notes) !!}</p>
	    </div>
    @endif
    
</div>
<div class="row" style="color: #000000 !important;">
	@if(!empty($receipt_details->footer_text))
	<div class="@if($receipt_details->show_barcode || $receipt_details->show_qr_code) col-xs-8 @else col-xs-12 @endif">
		{!! $receipt_details->footer_text !!}
	</div>
	@endif
@if($receipt_details->show_barcode || $receipt_details->show_qr_code)
		<div class="@if(!empty($receipt_details->footer_text)) col-xs-4 @else col-xs-12 @endif text-center">
			@if($receipt_details->show_barcode)
				{{-- Barcode --}}
				<img class="center-block" src="data:image/png;base64,{{DNS1D::getBarcodePNG($receipt_details->invoice_no, 'C128', 1,65,array(39, 48, 54), false)}}">
			@endif

			@if($receipt_details->show_qr_code && !empty($receipt_details->qr_code_text))
				<img class="center-block mt-5" src="data:image/png;base64,{{DNS2D::getBarcodePNG($receipt_details->qr_code_text, 'QRCODE', 3, 3, [39, 48, 54])}}">
			@endif
		</div>
	@endif
</div>
