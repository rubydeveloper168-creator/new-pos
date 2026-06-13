<div class="tw-bg-gray-100 tw-rounded-lg tw-p-3 sub-type-card" data-sub-type-id="{{ $subType->id }}">
    <!-- Sub Type Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-3">
        <div class="tw-flex tw-items-center tw-gap-2">
            <span class="tw-cursor-move sub-type-drag-handle tw-text-gray-400 hover:tw-text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
            </span>
            <div>
                <h4 class="tw-font-semibold tw-text-gray-700">{{ $subType->name }}</h4>
                @if($subType->description)
                    <p class="tw-text-xs tw-text-gray-500">{{ $subType->description }}</p>
                @endif
            </div>
        </div>
        <div class="tw-flex tw-items-center tw-gap-1">
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal"
                data-href="{{ action([\App\Http\Controllers\GroupSubTypeController::class, 'edit'], [$subType->id]) }}"
                data-container=".group_type_modal">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </button>
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-sub-type-btn" data-id="{{ $subType->id }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Sub Type Products -->
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
        <span class="tw-text-xs tw-font-medium tw-text-gray-600">@lang('group_type.products')</span>
        <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-ghost add-product-btn" data-type="sub_type" data-id="{{ $subType->id }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            @lang('group_type.add_product')
        </button>
    </div>
    <div id="sub_type_products_{{ $subType->id }}" class="sub-type-products tw-space-y-1" data-sub-type-id="{{ $subType->id }}">
        @forelse($subType->products as $product)
            <div class="tw-flex tw-items-center tw-gap-2 tw-p-2 tw-bg-white tw-rounded product-item" data-product-id="{{ $product->id }}">
                <span class="tw-cursor-move drag-handle tw-text-gray-400 hover:tw-text-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                        <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                    </svg>
                </span>
                <img src="{{ $product->image_url }}" class="tw-w-8 tw-h-8 tw-object-cover tw-rounded" alt="">
                <span class="tw-flex-1 tw-text-sm">{{ $product->name }} <small class="tw-text-gray-500">({{ $product->sku }})</small></span>
                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-error remove-product-btn"
                    data-type="sub_type" data-id="{{ $subType->id }}" data-product-id="{{ $product->id }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @empty
            <p class="tw-text-xs tw-text-gray-400 tw-italic">@lang('group_type.no_products')</p>
        @endforelse
    </div>
</div>
