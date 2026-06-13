@extends('layouts.app')
@section('title', __('group_type.group_types'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('group_type.group_types')
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('group_type.manage_group_types')</small>
    </h1>
</section>

<!-- Main content -->
<section class="content">
    @component('components.widget', ['class' => 'box-primary', 'title' => __('group_type.all_group_types')])
        @slot('tool')
            <div class="box-tools">
                <button class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full btn-modal"
                    data-href="{{ action([\App\Http\Controllers\GroupTypeController::class, 'create']) }}"
                    data-container=".group_type_modal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                        class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                        <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                        <path d="M12 5l0 14" />
                        <path d="M5 12l14 0" />
                    </svg> @lang('group_type.add_group_type')
                </button>
            </div>
        @endslot

        <div id="group_types_container" class="tw-space-y-4">
            @forelse($groupTypes as $groupType)
                @include('group_type.partials.group_type_card', ['groupType' => $groupType])
            @empty
                <div class="tw-text-center tw-py-8 tw-text-gray-500">
                    <p>@lang('group_type.no_group_types')</p>
                </div>
            @endforelse
        </div>
    @endcomponent

    <div class="modal fade group_type_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    <!-- Product Search Modal -->
    <div class="modal fade" id="product_search_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">@lang('group_type.search_add_product')</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="product_search_target_type" value="">
                    <input type="hidden" id="product_search_target_id" value="">
                    <div class="form-group">
                        <label>@lang('group_type.search_product'):</label>
                        <select id="product_search_select" class="form-control" style="width: 100%;">
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
                </div>
            </div>
        </div>
    </div>

</section>

@endsection

@section('javascript')
<style>
/* Fix Select2 dropdown alignment inside modal */
.select2-container--open {
    z-index: 9999 !important;
}
#product_search_modal .select2-container {
    width: 100% !important;
}
#product_search_modal .select2-dropdown {
    z-index: 9999 !important;
}
</style>
<script>
$(document).ready(function() {
    // Initialize Select2 for product search
    $('#product_search_select').select2({
        ajax: {
            url: '{{ route("group-types.search-products") }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    term: params.term
                };
            },
            processResults: function (data, params) {
                console.log('Raw search results:', data);
                // Handle error response
                if (data && data.error) {
                    console.error('Search error:', data.error);
                    return { results: [] };
                }
                // Ensure data is an array
                var results = Array.isArray(data) ? data : [];
                console.log('Processed results:', results);
                return {
                    results: results
                };
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response:', xhr.responseText);
            },
            cache: true
        },
        minimumInputLength: 1,
        placeholder: '@lang("group_type.type_to_search")',
        allowClear: true,
        dropdownParent: $('#product_search_modal .modal-content')
    });

    // When product is selected
    $('#product_search_select').on('select2:select', function (e) {
        var data = e.params.data;
        var targetType = $('#product_search_target_type').val();
        var targetId = $('#product_search_target_id').val();

        var url = targetType === 'group_type'
            ? '{{ url("group-types") }}/' + targetId + '/add-product'
            : '{{ url("group-sub-types") }}/' + targetId + '/add-product';

        $.ajax({
            url: url,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                product_id: data.id
            },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.msg);
                    // Add product to the list
                    var productHtml = `
                        <div class="tw-flex tw-items-center tw-gap-2 tw-p-2 tw-bg-gray-50 tw-rounded tw-mb-2 product-item" data-product-id="${response.product.id}">
                            <span class="tw-cursor-move drag-handle">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                                </svg>
                            </span>
                            <img src="${response.product.image}" class="tw-w-10 tw-h-10 tw-object-cover tw-rounded" alt="">
                            <span class="tw-flex-1">${response.product.name} <small class="tw-text-gray-500">(${response.product.sku})</small></span>
                            <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-error remove-product-btn"
                                data-type="${targetType}" data-id="${targetId}" data-product-id="${response.product.id}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M18 6L6 18M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    `;

                    var containerId = targetType === 'group_type'
                        ? '#group_type_products_' + targetId
                        : '#sub_type_products_' + targetId;
                    $(containerId).append(productHtml);

                    // Clear selection
                    $('#product_search_select').val(null).trigger('change');
                } else {
                    toastr.error(response.msg);
                }
            },
            error: function() {
                toastr.error('@lang("messages.something_went_wrong")');
            }
        });
    });

    // Open product search modal
    $(document).on('click', '.add-product-btn', function() {
        var type = $(this).data('type');
        var id = $(this).data('id');
        $('#product_search_target_type').val(type);
        $('#product_search_target_id').val(id);
        $('#product_search_modal').modal('show');
    });

    // Remove product
    $(document).on('click', '.remove-product-btn', function() {
        var btn = $(this);
        var type = btn.data('type');
        var id = btn.data('id');
        var productId = btn.data('product-id');

        var url = type === 'group_type'
            ? '{{ url("group-types") }}/' + id + '/remove-product'
            : '{{ url("group-sub-types") }}/' + id + '/remove-product';

        if (confirm('@lang("group_type.confirm_remove_product")')) {
            $.ajax({
                url: url,
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                        btn.closest('.product-item').remove();
                    } else {
                        toastr.error(response.msg);
                    }
                },
                error: function() {
                    toastr.error('@lang("messages.something_went_wrong")');
                }
            });
        }
    });

    // Initialize drag and drop for products in group types
    $('.group-type-products').sortable({
        handle: '.drag-handle',
        update: function(event, ui) {
            var groupTypeId = $(this).data('group-type-id');
            var productIds = $(this).find('.product-item').map(function() {
                return $(this).data('product-id');
            }).get();

            $.ajax({
                url: '{{ url("group-types") }}/' + groupTypeId + '/update-product-order',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                    }
                }
            });
        }
    });

    // Initialize drag and drop for products in sub types
    $('.sub-type-products').sortable({
        handle: '.drag-handle',
        update: function(event, ui) {
            var subTypeId = $(this).data('sub-type-id');
            var productIds = $(this).find('.product-item').map(function() {
                return $(this).data('product-id');
            }).get();

            $.ajax({
                url: '{{ url("group-sub-types") }}/' + subTypeId + '/update-product-order',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_ids: productIds
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                    }
                }
            });
        }
    });

    // Initialize drag and drop for group types
    $('#group_types_container').sortable({
        handle: '.group-type-drag-handle',
        items: '.group-type-card',
        update: function(event, ui) {
            var groupTypeIds = $(this).find('.group-type-card').map(function() {
                return $(this).data('group-type-id');
            }).get();

            $.ajax({
                url: '{{ route("group-types.update-order") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    group_type_ids: groupTypeIds
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                    }
                }
            });
        }
    });

    // Initialize drag and drop for sub types
    $('.sub-types-container').sortable({
        handle: '.sub-type-drag-handle',
        items: '.sub-type-card',
        update: function(event, ui) {
            var subTypeIds = $(this).find('.sub-type-card').map(function() {
                return $(this).data('sub-type-id');
            }).get();

            $.ajax({
                url: '{{ route("group-sub-types.update-order") }}',
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    sub_type_ids: subTypeIds
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                    }
                }
            });
        }
    });

    // Delete group type
    $(document).on('click', '.delete-group-type-btn', function() {
        var id = $(this).data('id');
        var card = $(this).closest('.group-type-card');

        if (confirm('@lang("group_type.confirm_delete")')) {
            $.ajax({
                url: '{{ url("group-types") }}/' + id,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                        card.remove();
                    } else {
                        toastr.error(response.msg);
                    }
                },
                error: function() {
                    toastr.error('@lang("messages.something_went_wrong")');
                }
            });
        }
    });

    // Delete sub type
    $(document).on('click', '.delete-sub-type-btn', function() {
        var id = $(this).data('id');
        var card = $(this).closest('.sub-type-card');

        if (confirm('@lang("group_type.confirm_delete_sub_type")')) {
            $.ajax({
                url: '{{ url("group-sub-types") }}/' + id,
                method: 'DELETE',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.msg);
                        card.remove();
                    } else {
                        toastr.error(response.msg);
                    }
                },
                error: function() {
                    toastr.error('@lang("messages.something_went_wrong")');
                }
            });
        }
    });
});
</script>
@endsection
