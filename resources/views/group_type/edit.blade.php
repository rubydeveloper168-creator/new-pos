<div class="modal-dialog" role="document">
    <div class="modal-content">

        {!! Form::open(['url' => action([\App\Http\Controllers\GroupTypeController::class, 'update'], [$groupType->id]), 'method' => 'PUT', 'id' => 'group_type_edit_form']) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('group_type.edit_group_type')</h4>
        </div>

        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('group_type.name') . ':*') !!}
                {!! Form::text('name', $groupType->name, ['class' => 'form-control', 'required', 'placeholder' => __('group_type.name')]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('description', __('group_type.description') . ':') !!}
                {!! Form::textarea('description', $groupType->description, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('group_type.description')]) !!}
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}

    </div>
</div>

<script>
$(document).ready(function() {
    $('#group_type_edit_form').submit(function(e) {
        e.preventDefault();
        var form = $(this);
        var data = form.serialize();

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: data + '&_method=PUT',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg);
                    $('.group_type_modal').modal('hide');
                    // Reload page to show updated group type
                    location.reload();
                } else {
                    toastr.error(response.msg);
                }
            },
            error: function() {
                toastr.error('@lang("messages.something_went_wrong")');
            }
        });
    });
});
</script>
