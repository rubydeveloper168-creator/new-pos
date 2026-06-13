<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        @php
            $productRecord = $product ?? null;
        @endphp
        {!! Form::open([
            'url' => action([\App\Http\Controllers\WarrantyCheckController::class, 'updateProduct'], [$product->id]),
            'method' => 'post',
            'id' => 'warranty_product_form',
        ]) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">ตั้งค่าประกันสินค้า</h4>
        </div>

        <div class="modal-body">
            {!! Form::hidden('product_id', data_get($productRecord, 'id')) !!}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('product_name', 'สินค้า') !!}
                        {!! Form::text('product_name', data_get($productRecord, 'name'), [
                            'class' => 'form-control',
                            'readonly' => true,
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('sku', 'SKU') !!}
                        {!! Form::text('sku', data_get($productRecord, 'sku'), [
                            'class' => 'form-control',
                            'readonly' => true,
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-12">
                    <div class="form-group">
                        {!! Form::label('warranty_check_status', 'สถานะประกัน') !!}
                        {!! Form::select('warranty_check_status', [
                            '' => 'ยังไม่ตั้งค่า',
                            'has_warranty' => 'มีประกัน',
                        ], data_get($productRecord, 'warranty_check_status'), ['class' => 'form-control select2']) !!}
                        <p class="help-block">หากไม่ต้องการให้สินค้านี้ถูกนับในรอบประกัน 365 วัน ให้เว้นค่านี้ว่างไว้</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('service_cycle_3_month', 1, !empty(data_get($productRecord, 'service_cycle_3_month')), ['class' => 'input-icheck']) !!}
                            เปิดการแจ้งเตือนเข้ารับบริการรอบ 3 เดือน
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('service_cycle_6_month', 1, !empty(data_get($productRecord, 'service_cycle_6_month')), ['class' => 'input-icheck']) !!}
                            เปิดการแจ้งเตือนเข้ารับบริการรอบ 6 เดือน
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>
