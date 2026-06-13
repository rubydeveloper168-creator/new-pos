@extends('layouts.app')
@section('title', __('report.sell_payment_report'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>{{ __('report.sell_payment_report')}}</h1>
</section>

<!-- Main content -->
<section class="content sell-payment-report-monthly-yearly">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
            {!! Form::open(['url' => '/reports/sell-payment-report-monthly-yearly', 'method' => 'get', 'id' => 'sell_payment_report_form']) !!}
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('customer_id', __('contact.customer') . ':') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-user"></i>
                        </span>
                        {!! Form::select('customer_id', $customers, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'id' => 'customer_id']); !!}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('customer_group_filter', __('lang_v1.customer_group').':') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-users"></i>
                        </span>
                        {!! Form::select('customer_group_filter', $customer_groups, null, ['class' => 'form-control select2', 'placeholder' => __('lang_v1.all')]); !!}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fa fa-map-marker"></i>
                        </span>
                        {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'id' => 'location_id']); !!}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('payment_types', __('lang_v1.payment_method').':') !!}
                    <div class="input-group">
                        <span class="input-group-addon">
                            <i class="fas fa-money-bill-alt"></i>
                        </span>
                        {!! Form::select('payment_types', $payment_types, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'id' => 'payment_types']); !!}
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('spr_date_filter', __('report.date_range') . ':') !!}
                    {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'spr_date_filter', 'readonly']); !!}
                    {!! Form::hidden('start_date', request()->start_date, ['id' => 'spr_start_date']) !!}
                    {!! Form::hidden('end_date', request()->end_date, ['id' => 'spr_end_date']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> @lang('report.apply_filters')</button>
                    <button type="button" class="btn btn-success spr-export-btn" data-url="{{ url('/reports/sell-payment-report-monthly-yearly/export-daily') }}">
                        <i class="fa fa-download"></i> @lang('report.export') @lang('report.daily') CSV
                    </button>
                    <button type="button" class="btn btn-success spr-export-btn" data-url="{{ url('/reports/sell-payment-report-monthly-yearly/export-monthly') }}">
                        <i class="fa fa-download"></i> @lang('report.export') @lang('report.monthly') CSV
                    </button>
                </div>
            </div>
            {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('report.payment_received') - Daily</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            {!! $chart_daily->container() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">@lang('report.payment_received') - Monthly</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            {!! $chart_monthly->container() !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- /.content -->
@stop
@section('css')
    <style>
        .sell-payment-report-monthly-yearly .select2-container {
            width: 100% !important;
        }
        .sell-payment-report-monthly-yearly .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: #fff !important;
            color: #333;
        }
        .sell-payment-report-monthly-yearly .select2-container--default .select2-search--dropdown {
            background-color: #fff;
        }
    </style>
@endsection
@section('javascript')
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
    {!! $chart_daily->script() !!}
    {!! $chart_monthly->script() !!}
    <script type="text/javascript">
        $(document).ready(function(){
            $('#sell_payment_report_form .select2').each(function() {
                var $select = $(this);
                if ($select.data('select2')) {
                    $select.select2('destroy');
                }
                $select.select2({ dropdownParent: $select.parent() });
            });
            $('.spr-export-btn').on('click', function() {
                var export_url = $(this).data('url');
                var params = $('#sell_payment_report_form').serializeArray();
                var query = [];
                $.each(params, function(i, v) {
                    if (v.name !== 'date_range') {
                        query.push(encodeURIComponent(v.name) + '=' + encodeURIComponent(v.value));
                    }
                });
                window.location.href = export_url + '?' + query.join('&');
            });
            if($('#spr_date_filter').length == 1){
                var drp_settings = $.extend(true, {}, dateRangeSettings, {
                    autoUpdateInput: false,
                    locale: $.extend({}, dateRangeSettings.locale, {
                        separator: ' ~ '
                    })
                });
                $('#spr_date_filter').daterangepicker(drp_settings, function(start, end) {
                    $('#spr_date_filter').val(
                        start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format)
                    );
                    $('#spr_start_date').val(start.format('YYYY-MM-DD'));
                    $('#spr_end_date').val(end.format('YYYY-MM-DD'));
                });
                $('#spr_date_filter').on('cancel.daterangepicker', function(ev, picker) {
                    $('#spr_date_filter').val('');
                    $('#spr_start_date').val('');
                    $('#spr_end_date').val('');
                });
                var start = "{{ request()->start_date }}";
                var end = "{{ request()->end_date }}";
                if(start != '' && end != ''){
                    var start_moment = moment(start, 'YYYY-MM-DD');
                    var end_moment = moment(end, 'YYYY-MM-DD');
                    $('#spr_date_filter').data('daterangepicker').setStartDate(start_moment);
                    $('#spr_date_filter').data('daterangepicker').setEndDate(end_moment);
                    $('#spr_date_filter').val(
                        start_moment.format(moment_date_format) + ' ~ ' + end_moment.format(moment_date_format)
                    );
                    $('#spr_start_date').val(start_moment.format('YYYY-MM-DD'));
                    $('#spr_end_date').val(end_moment.format('YYYY-MM-DD'));
                } else{
                    var default_start = moment().subtract(29, 'days');
                    var default_end = moment();
                    $('#spr_date_filter').data('daterangepicker').setStartDate(default_start);
                    $('#spr_date_filter').data('daterangepicker').setEndDate(default_end);
                    $('#spr_date_filter').val(
                        default_start.format(moment_date_format) + ' ~ ' + default_end.format(moment_date_format)
                    );
                    $('#spr_start_date').val(default_start.format('YYYY-MM-DD'));
                    $('#spr_end_date').val(default_end.format('YYYY-MM-DD'));
                }
            }
        });
    </script>
@endsection
