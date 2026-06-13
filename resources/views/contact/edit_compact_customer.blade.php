@php
    $url = action([\App\Http\Controllers\ContactController::class, 'update'], [$contact->id]);
    $contactType = $contact->contact_type === 'individual' ? 'individual' : 'business';
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
        {!! Form::open(['url' => $url, 'method' => 'PUT', 'id' => 'contact_edit_form', 'class' => 'compact-customer-form']) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">แก้ไข ลูกค้า</h4>
        </div>

        <div class="modal-body">
            <p class="compact-intro">กรุณากรอก ข้อมูลด้านล่าง..ป้ายชื่อ สาขา ที่มีเครื่องหมาย * จำเป็นต้องใส่.</p>

            {!! Form::hidden('type', 'customer', ['id' => 'contact_type']) !!}
            {!! Form::hidden('contact_type_radio', $contactType) !!}
            {!! Form::hidden('prefix', $contact->prefix) !!}
            {!! Form::hidden('middle_name', $contact->middle_name) !!}
            {!! Form::hidden('last_name', $contact->last_name) !!}
            {!! Form::hidden('opening_balance', $opening_balance ?? 0) !!}
            {!! Form::hidden('pay_term_number', $contact->pay_term_number) !!}
            {!! Form::hidden('pay_term_type', $contact->pay_term_type) !!}
            {!! Form::hidden('country', !empty($contact->country) ? $contact->country : 'Thailand') !!}
            {!! Form::hidden('state', $contact->state) !!}
            {!! Form::hidden('address_line_2', $contact->address_line_2) !!}
            {!! Form::hidden('shipping_address', $contact->shipping_address) !!}
            {!! Form::hidden('contact_id', $contact->contact_id, ['id' => 'contact_id']) !!}
            <input type="hidden" id="hidden_id" value="{{ $contact->id }}">
            <input type="hidden" id="hidden_contact_name" value="{{ $contact->name }}">

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('customer_group_id', 'กลุ่มลูกค้า *') !!}
                        {!! Form::select('customer_group_id', $customer_groups ?? [], $contact->customer_group_id, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('quick_contact_type', 'Contact Type') !!}
                        {!! Form::select('quick_contact_type', ['business' => 'Company', 'individual' => 'Individual'], $contactType, ['class' => 'form-control', 'id' => 'quick_contact_type']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('supplier_business_name', 'บริษัท') !!}
                        {!! Form::text('supplier_business_name', $contact->supplier_business_name, ['class' => 'form-control']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('mobile', 'โทรศัพท์ *') !!}
                        {!! Form::text('mobile', $contact->mobile, ['class' => 'form-control', 'id' => 'mobile', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('first_name', 'ชื่อ') !!}
                        {!! Form::text('first_name', !empty($contact->first_name) ? $contact->first_name : $contact->name, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('address_line_1', 'ที่อยู่') !!}
                        {!! Form::text('address_line_1', $contact->address_line_1, ['class' => 'form-control']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('tax_number', 'เลขประจำตัวผู้เสียภาษี') !!}
                        <div class="input-group">
                            {!! Form::text('tax_number', $contact->tax_number, ['class' => 'form-control', 'id' => 'quick_contact_tax_number']) !!}
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
                        {!! Form::text('city', $contact->city, ['class' => 'form-control']) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('email', 'อีเมล์ *') !!}
                        {!! Form::email('email', $contact->email, ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('zip_code', 'รหัสไปรษณีย์') !!}
                        {!! Form::text('zip_code', $contact->zip_code, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">อัปเดต ลูกค้า</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
