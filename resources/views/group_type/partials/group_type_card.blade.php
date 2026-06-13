<div class="tw-bg-white tw-rounded-lg tw-shadow tw-p-4 group-type-card" data-group-type-id="{{ $groupType->id }}">
    <!-- Group Type Header -->
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-4 tw-border-b tw-pb-3">
        <div class="tw-flex tw-items-center tw-gap-3">
            <span class="tw-cursor-move group-type-drag-handle tw-text-gray-400 hover:tw-text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                    <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                </svg>
            </span>
            <div>
                <h3 class="tw-text-lg tw-font-bold tw-text-indigo-600">{{ $groupType->name }}</h3>
                @if($groupType->description)
                    <p class="tw-text-sm tw-text-gray-500">{{ $groupType->description }}</p>
                @endif
            </div>
        </div>
        <div class="tw-flex tw-items-center tw-gap-2">
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary btn-modal"
                data-href="{{ action([\App\Http\Controllers\GroupTypeController::class, 'edit'], [$groupType->id]) }}"
                data-container=".group_type_modal">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
            </button>
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-success btn-modal"
                data-href="{{ action([\App\Http\Controllers\GroupSubTypeController::class, 'create']) }}?group_type_id={{ $groupType->id }}"
                data-container=".group_type_modal">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                @lang('group_type.add_sub_type')
            </button>
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error delete-group-type-btn" data-id="{{ $groupType->id }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Group Type Products -->
    <div class="tw-mb-4">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
            <span class="tw-text-sm tw-font-semibold tw-text-gray-700">@lang('group_type.products')</span>
            <button class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-ghost add-product-btn" data-type="group_type" data-id="{{ $groupType->id }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                @lang('group_type.add_product')
            </button>
        </div>
        <div id="group_type_products_{{ $groupType->id }}" class="group-type-products tw-space-y-1" data-group-type-id="{{ $groupType->id }}">
            @forelse($groupType->products as $product)
                <div class="tw-flex tw-items-center tw-gap-2 tw-p-2 tw-bg-gray-50 tw-rounded product-item" data-product-id="{{ $product->id }}">
                    <span class="tw-cursor-move drag-handle tw-text-gray-400 hover:tw-text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/>
                            <circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/>
                        </svg>
                    </span>
                    <img src="{{ $product->image_url }}" class="tw-w-10 tw-h-10 tw-object-cover tw-rounded" alt="">
                    <span class="tw-flex-1">{{ $product->name }} <small class="tw-text-gray-500">({{ $product->sku }})</small></span>
                    <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-error remove-product-btn"
                        data-type="group_type" data-id="{{ $groupType->id }}" data-product-id="{{ $product->id }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 6L6 18M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @empty
                <p class="tw-text-sm tw-text-gray-400 tw-italic">@lang('group_type.no_products')</p>
            @endforelse
        </div>
    </div>

    <!-- Sub Types -->
    @if($groupType->subTypes->count() > 0)
        <div class="tw-border-t tw-pt-4">
            <span class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2 tw-block">@lang('group_type.sub_types')</span>
            <div class="sub-types-container tw-pl-6 tw-space-y-3" data-group-type-id="{{ $groupType->id }}">
                @foreach($groupType->subTypes as $subType)
                    @include('group_type.partials.sub_type_card', ['subType' => $subType])
                @endforeach
            </div>
        </div>
    @endif
</div>
