@extends('layouts.app')
@section('title', __('lang_v1.customer_groups'))

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.customer_groups')</h1>
        <!-- <ol class="breadcrumb">
                <li><a href="#"><i class="fa fa-dashboard"></i> Level</a></li>
                <li class="active">Here</li>
            </ol> -->
    </section>

    <!-- Main content -->
    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.all_your_customer_groups')])
            @can('customer.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal"
                            data-href="{{ action([\App\Http\Controllers\CustomerGroupController::class, 'create']) }}"
                            data-container=".customer_groups_modal">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                    </div>
                @endslot
            @endcan
            @can('customer.view')
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="customer_groups_table">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.customer_group_name')</th>
                                <th>@lang('lang_v1.calculation_percentage')</th>
                                <th>@lang('lang_v1.selling_price_group')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcan
        @endcomponent

        <div class="modal fade customer_groups_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        </div>

    </section>
    <!-- /.content -->
@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            // DataTable initialization
            var customer_groups_table = $('#customer_groups_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/customer-group',
                columnDefs: [{
                    "targets": 3,
                    "orderable": false,
                    "searchable": false
                }]
            });

            // Add form submit handler
            $(document).on('submit', 'form#customer_group_add_form', function(e) {
                e.preventDefault();
                var data = $(this).serialize();
                $.ajax({
                    method: "POST",
                    url: $(this).attr("action"),
                    dataType: "json",
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            $('div.customer_groups_modal').modal('hide');
                            toastr.success(result.msg);
                            customer_groups_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

            // Edit button click handler
            $(document).on('click', 'button.edit_customer_group_button', function() {
                $('div.customer_groups_modal').load($(this).data('href'), function() {
                    $(this).modal('show');
                    $('form#customer_group_edit_form').find('#price_calculation_type').trigger('change');
                });
            });

            // Edit form submit handler
            $(document).on('submit', 'form#customer_group_edit_form', function(e) {
                e.preventDefault();
                var data = $(this).serialize();
                $.ajax({
                    method: "POST",
                    url: $(this).attr("action"),
                    dataType: "json",
                    data: data,
                    success: function(result) {
                        if (result.success == true) {
                            $('div.customer_groups_modal').modal('hide');
                            toastr.success(result.msg);
                            customer_groups_table.ajax.reload();
                        } else {
                            toastr.error(result.msg);
                        }
                    }
                });
            });

            // Delete button handler
            $(document).on('click', 'button.delete_customer_group_button', function() {
                swal({
                    title: LANG.sure,
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((willDelete) => {
                    if (willDelete) {
                        var href = $(this).data('href');
                        $.ajax({
                            method: "DELETE",
                            url: href,
                            dataType: "json",
                            success: function(result) {
                                if (result.success == true) {
                                    toastr.success(result.msg);
                                    customer_groups_table.ajax.reload();
                                } else {
                                    toastr.error(result.msg);
                                }
                            }
                        });
                    }
                });
            });

            // Price calculation type toggle
            $(document).on('change', '#price_calculation_type', function() {
                var price_calculation_type = $(this).val();

                if (price_calculation_type == 'percentage') {
                    $('.percentage-field').removeClass('hide');
                    $('.selling_price_group-field').addClass('hide');
                } else {
                    $('.percentage-field').addClass('hide');
                    $('.selling_price_group-field').removeClass('hide');
                }
            });
        });
    </script>
@endsection
