@php
    $form_id = 'contact_add_form';
    if (isset($quick_add)) {
        $form_id = 'quick_add_contact';
    }

    $url = action([\App\Http\Controllers\ContactController::class, 'store']);
@endphp

<style>
.contact_modal .compact-customer-form .form-group {
    margin-bottom: 12px;
}
.contact_modal .compact-customer-form .help-block {
    margin-top: 4px;
    margin-bottom: 0;
}
.contact_modal .compact-customer-form .compact-intro {
    margin-bottom: 12px;
    color: #555;
}
.contact_modal .compact-customer-form .input-group-btn .btn {
    height: 34px;
}
</style>

<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => $url, 'method' => 'post', 'id' => $form_id, 'class' => 'compact-customer-form']) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">เพิ่ม ลูกค้า</h4>
        </div>

        <div class="modal-body">
            <p class="compact-intro">กรุณากรอก ข้อมูลด้านล่าง..ป้ายชื่อ สาขา ที่มีเครื่องหมาย * จำเป็นต้องใส่.</p>

            {!! Form::hidden('type', 'customer', ['id' => 'contact_type']) !!}
            {!! Form::hidden('contact_type_radio', 'business') !!}
            {!! Form::hidden('prefix', null) !!}
            {!! Form::hidden('middle_name', null) !!}
            {!! Form::hidden('last_name', null) !!}
            {!! Form::hidden('opening_balance', 0) !!}
            {!! Form::hidden('pay_term_number', null) !!}
            {!! Form::hidden('pay_term_type', null) !!}
            {!! Form::hidden('country', 'Thailand') !!}
            {!! Form::hidden('state', null) !!}
            {!! Form::hidden('address_line_2', null) !!}
            {!! Form::hidden('shipping_address', null) !!}
            {!! Form::hidden('contact_id', null, ['id' => 'contact_id']) !!}

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('customer_group_id', 'กลุ่มลูกค้า *') !!}
                        {!! Form::select('customer_group_id', $customer_groups ?? [], null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('quick_contact_type', 'Contact Type') !!}
                        {!! Form::select('quick_contact_type', ['business' => 'Company', 'individual' => 'Individual'], 'business', ['class' => 'form-control', 'id' => 'quick_contact_type']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('supplier_business_name', 'บริษัท') !!}
                        {!! Form::text('supplier_business_name', null, ['class' => 'form-control', 'placeholder' => '']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('mobile', 'โทรศัพท์ *') !!}
                        {!! Form::text('mobile', null, ['class' => 'form-control', 'id' => 'mobile', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('first_name', 'ชื่อ') !!}
                        {!! Form::text('first_name', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('address_line_1', 'ที่อยู่') !!}
                        {!! Form::text('address_line_1', null, ['class' => 'form-control', 'rows' => 3]) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('tax_number', 'เลขประจำตัวผู้เสียภาษี') !!}
                        <div class="input-group">
                            {!! Form::text('tax_number', null, ['class' => 'form-control', 'id' => 'quick_contact_tax_number']) !!}
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-primary" id="quick_contact_tax_lookup_btn"><i class="fa fa-search"></i> ค้นหา</button>
                            </span>
                        </div>
                        <p class="help-block">กรอกเลขประจำตัวผู้เสียภาษี 13 หลัก แล้วคลิก “ค้นหา” หรือระบบจะค้นหาอัตโนมัติ</p>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('city', 'เมือง') !!}
                        {!! Form::text('city', null, ['class' => 'form-control']) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('email', 'อีเมล์ *') !!}
                        {!! Form::email('email', null, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('zip_code', 'รหัสไปรษณีย์') !!}
                        {!! Form::text('zip_code', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">เพิ่ม ลูกค้า</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
