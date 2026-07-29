@extends('businessLayout::index')

@section('pageContent')
    <div x-data="marketIndex" class="business-market-page">
        <div class="business-market-header">
            <div class="min-w-0">
                <span class="business-market-kicker">{{ __('business/labels.marketplace') }}</span>
                <x-page-title titleText="{{ __('business/market.index_title') }}"></x-page-title>
                <p class="business-market-caption">
                    {{ __('business/market.index_caption') }}
                </p>
            </div>
        </div>

        <div class="business-market-tabs">
            <x-tabs.tabs>
                <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.market.index', ['type' => 'all']) }}" textLabel="{{ __('business/market.tabs.all') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.market.index', ['type' => 'active']) }}" textLabel="{{ __('business/market.tabs.active') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.market.index', ['type' => 'archived']) }}" textLabel="{{ __('business/market.tabs.archived') }}"></x-tabs.tab-item>
            </x-tabs.tabs>
        </div>

        @php
            $emptyStateTitle = __('business/market.empty_state.index_' . $type . '.title');
        @endphp

        <div class="business-market-mobile-list md:hidden">
            @if($productsList->isNotEmpty())
                @foreach ($productsList as $productData)
                    @include('business::market.index.parts.product-card', ['productData' => $productData])
                @endforeach
            @else
                <div class="business-market-empty">
                    <span class="business-market-empty-icon">
                        <x-ui-icon name="bag-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ $emptyStateTitle }}</strong>
                    <a href="{{ route('business.market.create') }}" class="business-market-empty-action">
                        {{ __('business/market.create_title') }}
                    </a>
                </div>
            @endif
        </div>

        <div class="business-market-table hidden md:block">
            <x-table.table>
                <x-table.thead>
                    <x-table.th>
                        {{ __('business/table.th.name') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.quantity') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.price') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.status') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.approval') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.category') }}
                    </x-table.th>
                    <x-table.th>
                        {{ __('business/table.th.product_type') }}
                    </x-table.th>
                    <x-table.th classes="text-right">
                        {{ __('labels.table.actions') }}
                    </x-table.th>
                </x-table.thead>
                <x-table.tbody>
                    @if($productsList->isNotEmpty())
                        @foreach ($productsList as $productData)
                            @include('business::market.index.parts.product-item', ['productData' => $productData])
                        @endforeach
                    @else
                        <x-table.empty colspan="8" message="{{ $emptyStateTitle }}"></x-table.empty>
                    @endif
                </x-table.tbody>
            </x-table.table>
        </div>

        @unless($productsList->isEmpty())
            <div class="business-market-pagination">
                {{ $productsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
            </div>
        @endif
    </div>

    <script>
        window.addEventListener('alpine:init', () => {
            Alpine.data('marketIndex', () => ({
                deleteProduct(formAction) {
                    Alpine.store('confirmModal').open({
                        title: "{{ __('business/prompt.delete_product.title') }}",
                        desc: "{{ __('business/prompt.delete_product.description') }}",
                        formAction
                    });
                }
            }));
        });
    </script>
@endsection
