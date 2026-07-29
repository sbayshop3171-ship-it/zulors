<article class="business-market-card">
    <div class="business-market-card-main">
        <a href="{{ route('business.market.show', $productData->id) }}" class="business-market-card-image">
            <img src="{{ $productData->preview_image_url }}" alt="{{ $productData->title }}">
        </a>

        <div class="business-market-card-body">
            <a href="{{ route('business.market.show', $productData->id) }}" class="business-market-card-title">
                {{ $productData->title }}
            </a>

            <div class="business-market-card-meta">
                <span>{{ $productData->category_name }}</span>
                <span>{{ $productData->type->label() }}</span>
            </div>

            <div class="business-market-card-badges">
                <x-badge variant="{{ $productData->status->badgeVariant() }}">
                    {{ $productData->status->label() }} {{ $productData->status->emoji() }}
                </x-badge>

                <x-badge variant="{{ $productData->approval->badgeVariant() }}">
                    {{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
                </x-badge>
            </div>
        </div>
    </div>

    <div class="business-market-card-stats">
        <div>
            <span>{{ __('business/table.th.price') }}</span>
            <strong>{{ $productData->formatted_price }}</strong>
        </div>

        <div>
            <span>{{ __('business/table.th.quantity') }}</span>
            <strong>{{ $productData->stock_quantity }}</strong>
        </div>
    </div>

    <div class="business-market-card-actions">
        <a href="{{ route('business.market.show', $productData->id) }}" class="business-market-card-action" aria-label="{{ __('business/dd.product.view_product') }}">
            <span class="business-market-card-action-icon">
                <x-ui-icon type="line" name="layout-alt-02"></x-ui-icon>
            </span>
        </a>

        <a href="{{ route('business.market.edit', $productData->id) }}" class="business-market-card-action" aria-label="{{ __('business/dd.product.edit_product') }}">
            <span class="business-market-card-action-icon">
                <x-ui-icon type="line" name="edit-03"></x-ui-icon>
            </span>
        </a>

        <a href="{{ $productData->url }}" class="business-market-card-action" aria-label="{{ __('business/dd.product.open_product') }}">
            <span class="business-market-card-action-icon">
                <x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
            </span>
        </a>

        <button type="button" class="business-market-card-action business-market-card-action-danger" x-on:click="deleteProduct('{{ route('business.market.destroy', $productData->id) }}')" aria-label="{{ __('business/dd.product.delete_product') }}">
            <span class="business-market-card-action-icon">
                <x-ui-icon type="line" name="trash-04"></x-ui-icon>
            </span>
        </button>
    </div>
</article>
