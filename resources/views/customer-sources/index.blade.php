@extends('layouts.app')
@section('title', 'Customer Sources')

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>Customer Sources
        <small>Manage customer source options</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => 'All Customer Sources'])
        @can('user.create')
            @slot('tool')
                <div class="box-tools">
                    <button type="button" class="btn btn-block btn-primary btn-modal"
                        data-href="{{action([\App\Http\Controllers\CustomerSourceController::class, 'create'])}}"
                        data-container=".customer_source_modal">
                        <i class="fa fa-plus"></i> @lang( 'messages.add' )</button>
                </div>
            @endslot
        @endcan
        @can('user.view')
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="customer_source_table">
                    <thead>
                        <tr>
                            <th>Logo</th>
                            <th>Name</th>
                            <th>Sort Order</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcan
    @endcomponent

    <div class="modal fade customer_source_modal" tabindex="-1" role="dialog"
        aria-labelledby="gridSystemModalLabel">
    </div>

</section>
<!-- /.content -->
@stop
@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        var customer_source_table = $('#customer_source_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '/customer-sources',
            columnDefs: [{
                "targets": [4],
                "orderable": false,
                "searchable": false
            }],
            columns: [
                { data: 'logo', name: 'logo' },
                { data: 'name', name: 'name' },
                { data: 'sort_order', name: 'sort_order' },
                { data: 'status', name: 'is_active' },
                { data: 'action', name: 'action' }
            ]
        });

        $(document).on('click', 'button.delete_customer_source_button', function(){
            swal({
                title: LANG.sure,
                text: "Once deleted, you will not be able to recover this customer source!",
                icon: "warning",
                buttons: true,
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    var href = $(this).data('href');
                    var data = $(this).serialize();

                    $.ajax({
                        method: "DELETE",
                        url: href,
                        dataType: "json",
                        data: data,
                        success: function(result){
                            if(result.success == true){
                                toastr.success(result.msg);
                                customer_source_table.ajax.reload();
                            } else {
                                toastr.error(result.msg);
                            }
                        }
                    });
                }
            });
        });
    });

    $(document).on('shown.bs.modal', '.customer_source_modal', function(e) {
        $('form#customer_source_add_form, form#customer_source_edit_form').validate({
            rules: {
                name: {
                    required: true,
                },
            },
            submitHandler: function(form) {
                var form_data = new FormData(form);

                $.ajax({
                    method: $(form).attr('method'),
                    url: $(form).attr('action'),
                    data: form_data,
                    processData: false,
                    contentType: false,
                    success: function(result){
                        $('div.customer_source_modal').modal('hide');
                        location.reload();
                    },
                    error: function(xhr){
                        toastr.error('An error occurred');
                    }
                });
                return false;
            }
        });
    });
</script>
@endsection
