<div class="payment_details_div @if( $payment_line->method !== 'card' ) {{ 'hide' }} @endif" data-type="card" >
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_number", __('lang_v1.card_no')) !!}
			{!! Form::text("card_number", $payment_line->card_number, ['class' => 'form-control', 'placeholder' => __('lang_v1.card_no')]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_holder_name", __('lang_v1.card_holder_name')) !!}
			{!! Form::text("card_holder_name", $payment_line->card_holder_name, ['class' => 'form-control', 'placeholder' => __('lang_v1.card_holder_name')]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label("card_transaction_number",__('lang_v1.card_transaction_no')) !!}
			{!! Form::text("card_transaction_number", $payment_line->card_transaction_number, ['class' => 'form-control', 'placeholder' => __('lang_v1.card_transaction_no')]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_type", __('lang_v1.card_type')) !!}
			{!! Form::select("card_type", ['credit' => 'Credit Card', 'debit' => 'Debit Card', 'visa' => 'Visa', 'master' => 'MasterCard'], $payment_line->card_type,['class' => 'form-control select2']); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_month", __('lang_v1.month')) !!}
			{!! Form::text("card_month", $payment_line->card_month, ['class' => 'form-control', 
			'placeholder' => __('lang_v1.month') ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_year", __('lang_v1.year')) !!}
			{!! Form::text("card_year", $payment_line->card_year, ['class' => 'form-control', 'placeholder' => __('lang_v1.year') ]); !!}
		</div>
	</div>
	<div class="col-md-3">
		<div class="form-group">
			{!! Form::label("card_security",__('lang_v1.security_code')) !!}
			{!! Form::text("card_security", $payment_line->card_security, ['class' => 'form-control', 'placeholder' => __('lang_v1.security_code')]); !!}
		</div>
	</div>
	<div class="clearfix"></div>
</div>
<div class="payment_details_div @if( $payment_line->method !== 'cheque' ) {{ 'hide' }} @endif" data-type="cheque" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("cheque_number",__('lang_v1.cheque_no')) !!}
			{!! Form::text("cheque_number", $payment_line->cheque_number, ['class' => 'form-control', 'placeholder' => __('lang_v1.cheque_no')]); !!}
		</div>
	</div>
</div>
<div class="payment_details_div @if( $payment_line->method !== 'bank_transfer' ) {{ 'hide' }} @endif" data-type="bank_transfer" >
	<div class="col-md-6">
		<div class="form-group">
			{!! Form::label("bank_account_number",__('lang_v1.bank_account_number')) !!}
			{!! Form::text( "bank_account_number", $payment_line->bank_account_number, ['class' => 'form-control', 'placeholder' => __('lang_v1.bank_account_number')]); !!}
		</div>
	</div>
	<div class="col-md-4">
		<div class="form-group">
			{!! Form::label('bank_name', 'เลือกธนาคาร (Bank)') !!}
			<select name="bank_name" class="form-control select2 bank-name-select">
				<option value="">— เลือกธนาคาร —</option>
				<option value="ธนาคารกรุงเทพ (Bangkok Bank - BBL)" data-logo="Bank_(BBL).jpg" @if(($payment_line->bank_name ?? '')=='ธนาคารกรุงเทพ (Bangkok Bank - BBL)') selected @endif>ธนาคารกรุงเทพ (Bangkok Bank - BBL)</option>
				<option value="ธนาคารกสิกรไทย (Kasikornbank - KBank)" data-logo="Kasikornbank_(KBank.png" @if(($payment_line->bank_name ?? '')=='ธนาคารกสิกรไทย (Kasikornbank - KBank)') selected @endif>ธนาคารกสิกรไทย (Kasikornbank - KBank)</option>
				<option value="ธนาคารไทยพาณิชย์ (Siam Commercial Bank - SCB)" data-logo="Siam_Commercial_Bank_(SCB).png" @if(($payment_line->bank_name ?? '')=='ธนาคารไทยพาณิชย์ (Siam Commercial Bank - SCB)') selected @endif>ธนาคารไทยพาณิชย์ (Siam Commercial Bank - SCB)</option>
				<option value="ธนาคารกรุงไทย (Krungthai Bank - KTB)" data-logo="Krungthai_Bank_(KTB.jpeg" @if(($payment_line->bank_name ?? '')=='ธนาคารกรุงไทย (Krungthai Bank - KTB)') selected @endif>ธนาคารกรุงไทย (Krungthai Bank - KTB)</option>
				<option value="ธนาคารกรุงศรีอยุธยา (Bank of Ayudhya - Krungsri)" data-logo="Bank_of_Ayudhya_(Krungsri).png" @if(($payment_line->bank_name ?? '')=='ธนาคารกรุงศรีอยุธยา (Bank of Ayudhya - Krungsri)') selected @endif>ธนาคารกรุงศรีอยุธยา (Bank of Ayudhya - Krungsri)</option>
				<option value="ทหารไทยธนชาต (TMBThanachart Bank - TTB)" data-logo="TMBThanachart_Bank_TTB).jpeg" @if(($payment_line->bank_name ?? '')=='ทหารไทยธนชาต (TMBThanachart Bank - TTB)') selected @endif>ทหารไทยธนชาต (TMBThanachart Bank - TTB)</option>
				<option value="ยูโอบี (United Overseas Bank (Thai) - UOB)" data-logo="United_Overseas_Bank_(Thai)_(UOB).jpg" @if(($payment_line->bank_name ?? '')=='ยูโอบี (United Overseas Bank (Thai) - UOB)') selected @endif>ยูโอบี (United Overseas Bank (Thai) - UOB)</option>
				<option value="ธนาคารออมสิน (Government Savings Bank - GSB)" data-logo="Government_Savings_Bank_(GSB).png" @if(($payment_line->bank_name ?? '')=='ธนาคารออมสิน (Government Savings Bank - GSB)') selected @endif>ธนาคารออมสิน (Government Savings Bank - GSB)</option>
				<option value="ซิตี้แบงก์ (Citibank)" data-logo="Citibank.png" @if(($payment_line->bank_name ?? '')=='ซิตี้แบงก์ (Citibank)') selected @endif>ซิตี้แบงก์ (Citibank)</option>
			</select>
		</div>
	</div>
	<div class="col-md-2">
		<!-- <div class="form-group">
			{!! Form::label('bank_logo_preview', 'โลโก้') !!}
			<div class="bank-logo-preview" style="height:40px; display:flex; align-items:center;">
				@if(!empty($payment_line->bank_logo))
					<img src="{{ $payment_line->bank_logo_url }}" alt="Bank Logo" style="max-height:40px; max-width:100%;">
				@endif
			</div>
			{!! Form::hidden('bank_logo', $payment_line->bank_logo ?? null, ['class' => 'bank-logo-input']) !!}
			<small class="help-block">เลือกธนาคารเพื่อผูกโลโก้อัตโนมัติ</small>
		</div> -->
	</div>
</div>

@push('scripts')
<script>
(function() {
    function formatBankOption(bank) {
        if (!bank.id) { return bank.text; }
        var logo = $(bank.element).data('logo');
        if (logo) {
            return $('<span><img src="/img/bank_logo/' + logo + '" style="height:18px;width:auto;margin-right:8px;" />' + bank.text + '</span>');
        }
        return bank.text;
    }
    function initBankSelectLocal(scope) {
        scope = scope || $(document);
        scope.find('.bank-name-select').each(function() {
            var $select = $(this);
            if ($select.data('select2')) {
                $select.select2('destroy');
            }
            var parent = $select.closest('.modal');
            $select.select2({
                width: '100%',
                templateResult: formatBankOption,
                templateSelection: formatBankOption,
                dropdownParent: parent.length ? parent : undefined
            });

            // trigger logo sync
            $select.trigger('change');
        });
    }
    $(document).ready(function() { initBankSelectLocal($(document)); });
    $(document).on('shown.bs.modal', '.modal', function() { initBankSelectLocal($(this)); });
})();
</script>
@endpush
<div class="payment_details_div @if( $payment_line->method !== 'custom_pay_1' ) {{ 'hide' }} @endif" data-type="custom_pay_1" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("transaction_no_1", __('lang_v1.transaction_no')) !!}
			{!! Form::text("transaction_no_1", $payment_line->transaction_no, ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no')]); !!}
		</div>
	</div>
</div>
<div class="payment_details_div @if( $payment_line->method !== 'custom_pay_2' ) {{ 'hide' }} @endif" data-type="custom_pay_2" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("transaction_no_2", __('lang_v1.transaction_no')) !!}
			{!! Form::text("transaction_no_2", $payment_line->transaction_no, ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no')]); !!}
		</div>
	</div>
</div>
<div class="payment_details_div @if( $payment_line->method !== 'custom_pay_3' ) {{ 'hide' }} @endif" data-type="custom_pay_3" >
	<div class="col-md-12">
		<div class="form-group">
			{!! Form::label("transaction_no_3", __('lang_v1.transaction_no')) !!}
			{!! Form::text("transaction_no_3", $payment_line->transaction_no, ['class' => 'form-control', 'placeholder' => __('lang_v1.transaction_no')]); !!}
		</div>
	</div>
</div>
