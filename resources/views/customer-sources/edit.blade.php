<div class="modal-dialog" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\CustomerSourceController::class, 'update'], [$source->id]), 'method' => 'put', 'id' => 'customer_source_edit_form', 'files' => true ]) !!}

    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'messages.edit' ) Customer Source</h4>
    </div>

    <div class="modal-body">
      <div class="form-group">
        {!! Form::label('name', 'Name:*') !!}
          {!! Form::text('name', $source->name, ['class' => 'form-control', 'required', 'placeholder' => 'Enter source name']); !!}
      </div>

      @if($source->logo_path)
        <div class="form-group">
          <label>Current Logo:</label><br>
          <img src="{{ asset('uploads/customer_sources/' . $source->logo_path) }}" alt="{{ $source->name }}" style="max-width: 100px; max-height: 100px; object-fit: contain;">
        </div>
      @endif

      <div class="form-group">
        {!! Form::label('logo', 'Upload New Logo:') !!}
        {!! Form::file('logo', ['accept' => 'image/*']); !!}
        <p class="help-block">Max size: 2MB. Formats: jpeg, png, jpg, gif, svg</p>
      </div>

      <div class="form-group">
        {!! Form::label('sort_order', 'Sort Order:') !!}
        {!! Form::number('sort_order', $source->sort_order, ['class' => 'form-control', 'placeholder' => 'Display order']); !!}
      </div>

      <div class="form-group">
        <div class="checkbox">
          <label>
            {!! Form::checkbox('is_active', 1, $source->is_active, ['class' => 'input-icheck']); !!} Active
          </label>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">@lang( 'messages.update' )</button>
      <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->
