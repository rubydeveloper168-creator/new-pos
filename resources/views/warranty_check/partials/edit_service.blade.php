<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        @php
            $serviceRecord = $sell_line ?? null;
            $cycle3 = !empty($service_cycles) ? $service_cycles->get(3) : null;
            $cycle6 = !empty($service_cycles) ? $service_cycles->get(6) : null;
        @endphp
        {!! Form::open([
            'url' => action([\App\Http\Controllers\WarrantyCheckController::class, 'updateService'], [$sell_line->id]),
            'method' => 'post',
            'id' => 'warranty_service_form',
        ]) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">อัปเดตสถานะการเข้ารับบริการ</h4>
        </div>

        <div class="modal-body">
            {!! Form::hidden('sell_line_id', data_get($serviceRecord, 'id')) !!}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('invoice_no', 'เลขที่บิล') !!}
                        {!! Form::text('invoice_no', data_get($serviceRecord, 'transaction.invoice_no'), [
                            'class' => 'form-control',
                            'readonly' => true,
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('customer', 'ลูกค้า') !!}
                        {!! Form::text('customer', data_get($serviceRecord, 'transaction.contact.name', 'ลูกค้าเงินสด'), [
                            'class' => 'form-control',
                            'readonly' => true,
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('product_name', 'สินค้า') !!}
                        {!! Form::text('product_name', data_get($serviceRecord, 'product.name'), [
                            'class' => 'form-control',
                            'readonly' => true,
                        ]) !!}
                    </div>
                </div>

                @if(!empty(data_get($serviceRecord, 'product.service_cycle_3_month')))
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong>รอบเข้ารับบริการ 3 เดือน</strong>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            {!! Form::label('cycle_3_status', 'สถานะ') !!}
                                            {!! Form::select('cycle_3_status', [
                                                'pending' => 'รอดำเนินการ',
                                                'notified' => 'แจ้งแล้ว',
                                                'completed' => 'เสร็จสิ้น',
                                            ], data_get($cycle3, 'status', 'pending'), ['class' => 'form-control']) !!}
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            {!! Form::label('cycle_3_note', 'หมายเหตุ') !!}
                                            {!! Form::textarea('cycle_3_note', data_get($cycle3, 'note'), [
                                                'class' => 'form-control',
                                                'rows' => 3,
                                                'placeholder' => 'เพิ่มหมายเหตุสำหรับรอบ 3 เดือน',
                                            ]) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty(data_get($serviceRecord, 'product.service_cycle_6_month')))
                    <div class="col-md-12">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <strong>รอบเข้ารับบริการ 6 เดือน</strong>
                            </div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            {!! Form::label('cycle_6_status', 'สถานะ') !!}
                                            {!! Form::select('cycle_6_status', [
                                                'pending' => 'รอดำเนินการ',
                                                'notified' => 'แจ้งแล้ว',
                                                'completed' => 'เสร็จสิ้น',
                                            ], data_get($cycle6, 'status', 'pending'), ['class' => 'form-control']) !!}
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            {!! Form::label('cycle_6_note', 'หมายเหตุ') !!}
                                            {!! Form::textarea('cycle_6_note', data_get($cycle6, 'note'), [
                                                'class' => 'form-control',
                                                'rows' => 3,
                                                'placeholder' => 'เพิ่มหมายเหตุสำหรับรอบ 6 เดือน',
                                            ]) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="modal-footer">
                <a href="#"
               class="btn-modal tw-dw-btn tw-dw-btn-outline"
               data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [data_get($serviceRecord, 'transaction.id')]) }}"
               data-container=".view_modal">
                <i class="fa fa-file-text-o"></i> ดูรายละเอียดบิลทั้งหมด
            </a>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>
