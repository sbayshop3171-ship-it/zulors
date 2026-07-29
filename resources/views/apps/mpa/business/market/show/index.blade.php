@extends('businessLayout::index')

@section('pageContent')
    <div x-data="alpineComponent">
        <x-page-title titleText="{{ __('business/market.show_title') }}"></x-page-title>
        <x-sided-content>
            <x-slot:sideContent>
                <x-entity.previews.product :productData="$productData"></x-entity.previews.product>

				@if(! $productData->status->isActive())
					<div class="mt-4">
						<div class="bg-lime-50 border-lime-300 border rounded-2xl py-4 px-5">
							<p class="text-lime-800 text-par-s font-normal">
								{{ __('business/market.product_is_inactive') }}
							</p>
						</div>
					</div>
				@endif

				@if($productData->approval->isPending())
					<div class="mt-4">
						<div class="bg-amber-50 border-amber-300 border rounded-2xl py-4 px-5">
							<p class="text-amber-700 text-par-s font-normal">
								{{ __('business/market.product_is_pending') }}
							</p>
						</div>
					</div>
				@elseif($productData->approval->isRejected())
					<div class="mt-4">
						<div class="bg-red-50 border-red-300 border rounded-2xl py-4 px-5">
							<p class="text-red-900 text-par-s font-normal">
								{{ __('business/market.product_is_rejected') }}
							</p>
						</div>
					</div>
				@endif
			</x-slot:sideContent>

            <div class="mb-4">
                <x-entity.header
                    avatarUrl="{{ $productData->user->avatar_url }}"
                    name="{{ $productData->user->name }}"
                    caption="{{ $productData->user->caption }}">

                    <x-slot:controlOptions>
                        <x-ui.dropdown.dropdown>
                            <x-slot:dropdownButton>
                                <span class="opacity-50 hover:opacity-100">
                                    <x-ui.buttons.icon></x-ui.buttons.icon>
                                </span>
                            </x-slot:dropdownButton>
                            <x-ui.dropdown.item tag="a" href="{{ route('business.market.edit', $productData->id) }}" itemText="{{ __('business/dd.product.edit_product') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="edit-03"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>

                            @if($productData->status->isActive())
                                <x-ui.dropdown.item x-on:click="unpublishProduct" tag="a" itemText="{{ __('business/dd.product.unpublish_product') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="line" name="eye-off"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                            @else
                                <x-ui.dropdown.item x-on:click="publishProduct" tag="a" itemText="{{ __('business/dd.product.publish_product') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="line" name="eye"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                            @endif

                            <x-ui.dropdown.item tag="a" href="{{ $productData->url }}" target="_blank" itemText="{{ __('business/dd.product.open_product') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>

                            <x-div></x-div>
                            <x-ui.dropdown.item x-on:click="deleteProduct" :danger="true" itemText="{{ __('business/dd.product.delete_product') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="trash-04"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>
                        </x-ui.dropdown.dropdown>
                    </x-slot:controlOptions>
                </x-entity.header>
            </div>
            <div class="mb-4">
                <x-entity.title title="{{ __('business/market.about_product') }}" captionTitle="{{ __('business/market.last_contact_date') }}" caption="{{ $productData->last_contacted_at ? $productData->last_contacted_at->getFormatted() : __('labels.never') }}"></x-entity.title>
            </div>
            <div class="mb-6">
                <x-counter.counter>
                    <x-counter.counter-item counterValue="{{ $productData->formatted_price }}" captionText="{{ __('labels.price') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $productData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $productData->contacts_count }}" captionText="{{ __('labels.contacts_count') }}"></x-counter.counter-item>
                </x-counter.counter>
            </div>
            <div class="mb-8">
                <x-line-table.table>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.status') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            @if($productData->approval->isApproved())
                                <x-badge variant="{{ $productData->status->badgeVariant() }}">
                                    {{ $productData->status->label() }} {{ $productData->status->emoji() }}
                                </x-badge>
                            @else
                                <x-badge variant="{{ $productData->approval->badgeVariant() }}">
                                    {{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
                                </x-badge>
                            @endif
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.price') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->formatted_price }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.discount') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->formatted_discount_amount }} ({{ $productData->discount }}%)
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.category') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->category_name }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.product_type') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->type->label() }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.ratings') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ __('labels.no_ratings') }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.address') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            @if($productData->address)
                                {{ $productData->address }}
                            @else
                                {{ __('labels.not_set') }}
                            @endif
                        </x-slot:labelValue>
                    </x-line-table.row>
                </x-line-table.table>
            </div>

            <div class="mb-2">
                <x-entity.title title="{{ __('business/labels.product_contacts') }}" caption="{{ now()->locale(app()->getLocale())->format('F, Y') }}"></x-entity.title>
            </div>
            <div class="mb-6">
                <div id="chart" class="bg-fill-qt"></div>
            </div>
            <div class="block">
                <div class="mb-2">
                    <x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
                </div>
                <x-striped-table.table>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('labels.currency') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->currency_name }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            ID
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            #{{ $productData->id }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('labels.create_date') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $productData->created_at->getIso() }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                </x-striped-table.table>
            </div>
        </x-sided-content>
    </div>

    <script>
        const alpineComponent = {
            deleteProduct: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.delete_product.title') }}",
                    desc: "{{ __('business/prompt.delete_product.description') }}",
                    formAction: "{{ route('business.market.destroy', $productData->id) }}"
                });
            },
            unpublishProduct: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.unpublish_product.title') }}",
                    desc: "{{ __('business/prompt.unpublish_product.description') }}",
                    confirmButtonText: "{{ __('business/prompt.unpublish_product.confirm_btn_text') }}",
                    formAction: "{{ route('business.market.unpublish', $productData->id) }}"
                });
            },
            publishProduct: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.publish_product.title') }}",
                    desc: "{{ __('business/prompt.publish_product.description') }}",
                    confirmButtonText: "{{ __('business/prompt.publish_product.confirm_btn_text') }}",
                    formAction: "{{ route('business.market.publish', $productData->id) }}"
                });
            }
        };

        window.addEventListener('load', () => {
            new ApexCharts(document.querySelector("#chart"), {
                chart: {
                    type: 'area', // or whichever chart type you're using
                    height: 240,
                    toolbar: {
                        show: false  // This hides the zoom, pan, reset (home), and menu controls
                    }
                },
                series: [{
                    name: 'Data',
                    data: [150, 200, 170, 240, 300, 250, 220],
                }],
                xaxis: {
                    categories: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    type: 'category',
                    labels: {
                        rotate: -90, // Rotates the datetime labels on the X-axis
                    }
                },
                grid: {
                    xaxis: {
                        lines: {
                            show: true // Enable vertical grid lines along the X-axis
                        }
                    },
                    yaxis: {
                        lines: {
                            show: false // Disable horizontal grid lines along the Y-axis (optional)
                        }
                    },
                    borderColor: '#ffffff', // Set grid lines color to light gray
                    strokeDashArray: 0, // Solid line
                    position: 'back', // Ensure grid lines are behind the chart
                }
            }).render();
        });
    </script>
@endsection

@push('scripts')
    @vite('resources/js/mpa/apexcharts.js')
@endpush
