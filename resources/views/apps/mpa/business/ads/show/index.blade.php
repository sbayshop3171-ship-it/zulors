@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('business/ads.show_title') }}"></x-page-title>
    <div x-data="alpineComponent">
        <x-sided-content>
            <x-slot:sideContent>
                <x-entity.previews.ad :adData="$adData"></x-entity.previews.ad>
                @if($adData->status->isPaused())
                    <div class="mt-4">
                        <div class="bg-lime-50 border-lime-300 border rounded-2xl py-4 px-5">
                            <p class="text-lime-800 text-par-s font-normal">
                                {{ __('business/ads.ad_is_paused') }}
                            </p>
                        </div>
                    </div>
                @endif
            </x-slot:sideContent>
            <div class="mb-4">
                <x-entity.header
                    avatarUrl="{{ $adData->user->avatar_url }}"
                    name="{{ $adData->user->name }}"
                caption="{{ $adData->user->caption }}">

                    <x-slot:controlOptions>
                        <x-ui.dropdown.dropdown>
                            <x-slot:dropdownButton>
                                <span class="opacity-50 hover:opacity-100">
                                    <x-ui.buttons.icon></x-ui.buttons.icon>
                                </span>
                            </x-slot:dropdownButton>
                            @if($adData->approval->isApproved())
                                @if($adData->status->isPaused())
                                    <x-ui.dropdown.item x-on:click="publishAd" itemText="{{ __('business/dd.ad.publish_ad') }}">
                                        <x-slot:itemIcon>
                                            <x-ui-icon type="line" name="eye"></x-ui-icon>
                                        </x-slot:itemIcon>
                                    </x-ui.dropdown.item>
                                @elseif($adData->status->isPublished())
                                    <x-ui.dropdown.item x-on:click="pauseAd" itemText="{{ __('business/dd.ad.pause_ad') }}">
                                        <x-slot:itemIcon>
                                            <x-ui-icon type="line" name="eye-off"></x-ui-icon>
                                        </x-slot:itemIcon>
                                    </x-ui.dropdown.item>
                                @endif
                            @endif

                            <x-ui.dropdown.item tag="a" href="{{ route('business.ads.edit', $adData->id) }}" itemText="{{ __('business/dd.ad.edit_ad') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="edit-03"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>
                            <x-div></x-div>

                            <x-ui.dropdown.item x-on:click="deleteAd" :danger="true" itemText="{{ __('business/dd.ad.delete_ad') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="trash-04"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>
                        </x-ui.dropdown.dropdown>
                    </x-slot:controlOptions>
                </x-entity.header>
            </div>
            <div class="mb-4">
                <x-entity.title title="{{ __('business/ads.about_ad') }}" captionTitle="{{ __('business/ads.last_show_date') }}" caption="{{ $adData->last_show_at ? $adData->last_show_at->getFormatted() : __('labels.never') }}"></x-entity.title>
            </div>
            <div class="mb-6">
                <x-counter.counter>
                    <x-counter.counter-item counterValue="{{ $adData->formatted_spent_budget }}" captionText="{{ __('business/ads.spent_budget') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $adData->formatted_total_budget }}" captionText="{{ __('business/ads.total_budget') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $adData->formatted_remaining_budget }}" captionText="{{ __('business/ads.remaining_budget') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $adData->formatted_views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $adData->formatted_clicks_count }}" captionText="{{ __('business/ads.clicks') }}"></x-counter.counter-item>
                </x-counter.counter>
            </div>
            <div class="mb-8">
                <x-line-table.table>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.publisher') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->user->name }} ({{ $adData->user->caption }})
                        </x-slot:labelValue>
                    </x-line-table.row>
                    @if(! $adData->approval->isApproved())
                        <x-line-table.row>
                            <x-slot:labelText>
                                {{ __('labels.approval') }}
                            </x-slot:labelText>
                            <x-slot:labelValue>
                                <span class="text-red-900">
                                    {{ $adData->approval->label() }} {{ $adData->approval->emoji() }}
                                </span>
                            </x-slot:labelValue>
                        </x-line-table.row>
                    @endif
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.status') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->status->label() }} {{ $adData->status->emoji() }}
                        </x-slot:labelValue>
                    </x-line-table.row>

                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.price_per_view') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_price_per_view }}
                        </x-slot:labelValue>
                    </x-line-table.row>

                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.allocated_budget') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_total_budget }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.total_budget') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_total_budget }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.spent_budget') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_spent_budget }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.views') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_views_count }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.clicks') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_clicks_count }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.remaining_budget') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_remaining_budget }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/ads.target_topics') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->target_topics_text ?: __('labels.not_available') }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                </x-line-table.table>
            </div>
            <div class="mb-2">
                <x-entity.title title="{{ __('business/ads.ad_show_stats') }}" caption="{{ now()->locale(app()->getLocale())->format('F, Y') }}"></x-entity.title>
            </div>
            <div class="mb-6">
                <div id="chart" class="bg-input-pr"></div>
            </div>
            <div class="block">
                <div class="mb-2">
                    <x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
                </div>
                <x-striped-table.table>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            #ID
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->formatted_id }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('labels.create_date') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $adData->created_at->getFormatted() }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                </x-striped-table.table>
            </div>
        </x-sided-content>
    </div>

    <script >
        const alpineComponent = {
            deleteAd: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.delete_ad.title') }}",
                    desc: "{{ __('business/prompt.delete_ad.description') }}",
                    formAction: "{{ route('business.ads.destroy', $adData->id) }}"
                });
            },
            pauseAd: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.pause_ad.title') }}",
                    desc: "{{ __('business/prompt.pause_ad.description') }}",
                    formAction: "{{ route('business.ads.pause', $adData->id) }}",
					confirmButtonText: "{{ __('business/prompt.pause_ad.confirm_btn_text') }}"
                });
            },
            publishAd: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.publish_ad.title') }}",
                    desc: "{{ __('business/prompt.publish_ad.description') }}",
                    formAction: "{{ route('business.ads.publish', $adData->id) }}",
					confirmButtonText: "{{ __('business/prompt.publish_ad.confirm_btn_text') }}"
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
