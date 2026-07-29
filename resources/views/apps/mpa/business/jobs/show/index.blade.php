@extends('businessLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('business/jobs.show_title') }}"></x-page-title>

    <div x-data="alpineComponent">
        <x-sided-content>
            <x-slot:sideContent>
                <x-entity.previews.job :jobData="$jobData"></x-entity.previews.job>

                @if(! $jobData->status->isActive())
                    <div class="mt-4">
                        <div class="bg-lime-50 border-lime-300 border rounded-2xl py-4 px-5">
                            <p class="text-lime-800 text-par-s font-normal">
                                {{ __('business/jobs.job_is_inactive') }}
                            </p>
                        </div>
                    </div>
                @endif

                @if($jobData->approval->isPending())
                    <div class="mt-4">
                        <div class="bg-amber-50 border-amber-300 border rounded-2xl py-4 px-5">
                            <p class="text-amber-700 text-par-s font-normal">
                                {{ __('business/jobs.job_is_pending') }}
                            </p>
                        </div>
                    </div>
                @elseif($jobData->approval->isRejected())
                    <div class="mt-4">
                        <div class="bg-red-50 border-red-300 border rounded-2xl py-4 px-5">
                            <p class="text-red-900 text-par-s font-normal">
                                {{ __('business/jobs.job_is_rejected') }}
                            </p>
                        </div>
                    </div>
                @endif
            </x-slot:sideContent>
            <div class="mb-4">
                <x-entity.header
                    avatarUrl="{{ $jobData->user->avatar_url }}"
                    name="{{ $jobData->user->name }}"
                caption="{{ $jobData->user->caption }}">

                    <x-slot:controlOptions>
                        <x-ui.dropdown.dropdown>
                            <x-slot:dropdownButton>
                                <span class="opacity-50 hover:opacity-100">
                                    <x-ui.buttons.icon></x-ui.buttons.icon>
                                </span>
                            </x-slot:dropdownButton>

                            <x-ui.dropdown.item tag="a" href="{{ $jobData->url }}" target="_blank" itemText="{{ __('business/dd.job.open_job') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>
                            <x-ui.dropdown.item tag="a" href="{{ route('business.jobs.edit', $jobData->id) }}" itemText="{{ __('business/dd.job.edit_job') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="edit-03"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>

                            @if($jobData->status->isActive())
                                <x-ui.dropdown.item x-on:click="unpublishJob" itemText="{{ __('business/dd.job.unpublish_job') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="line" name="eye-off"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                            @else
                                <x-ui.dropdown.item x-on:click="publishJob" itemText="{{ __('business/dd.job.publish_job') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="line" name="eye"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                            @endif

                            <x-ui.dropdown.item x-on:click="deleteJob" :danger="true" itemText="{{ __('business/dd.job.delete_job') }}">
                                <x-slot:itemIcon>
                                    <x-ui-icon type="line" name="trash-04"></x-ui-icon>
                                </x-slot:itemIcon>
                            </x-ui.dropdown.item>
                        </x-ui.dropdown.dropdown>
                    </x-slot:controlOptions>
                </x-entity.header>
            </div>
            <div class="mb-4">
                <x-entity.title
                    title="{{ __('business/jobs.about_job') }}"
                    captionTitle="{{ __('business/jobs.last_contact_date') }}"
                caption="{{ $jobData->last_contacted_at ? $jobData->last_contacted_at->getFormatted() : __('labels.never') }}"></x-entity.title>
            </div>
            <div class="mb-6">
                <x-counter.counter>
                    <x-counter.counter-item counterValue="{{ $jobData->formatted_income }}" captionText="{{ $jobData->is_start_income ? __('business/jobs.income_is_from') : __('business/jobs.income_is_to') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $jobData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
                    <x-counter.counter-item counterValue="{{ $jobData->applications_count }}" captionText="{{ __('labels.contacts_count') }}"></x-counter.counter-item>
                </x-counter.counter>
            </div>
            <div class="mb-8">
                <x-line-table.table>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.publisher') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $jobData->user->name }} ({{ $jobData->user->caption }})
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.status') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            @if($jobData->approval->isApproved())
                                <x-badge variant="{{ $jobData->status->badgeVariant() }}">
                                    {{ $jobData->status->label() }} {{ $jobData->status->emoji() }}
                                </x-badge>
                            @else
                                <x-badge variant="{{ $jobData->approval->badgeVariant() }}">
                                    {{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}
                                </x-badge>
                            @endif
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.salary') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            @if($jobData->is_start_income)
                                {{ __('labels.income_from', ['amount' => $jobData->formatted_income]) }}
                            @else
                                {{ __('labels.income_to', ['amount' => $jobData->formatted_income]) }}
                            @endif
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.urgency') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            @if($jobData->is_urgent)
                                {{ __('labels.urgency_labels.urgent') }}
                            @else
                                {{ __('labels.urgency_labels.not_urgent') }}
                            @endif
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('labels.category') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $jobData->category_name }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('business/jobs.job_type') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $jobData->type->label() }}
                        </x-slot:labelValue>
                    </x-line-table.row>
                    @if($jobData->location)
                        <x-line-table.row>
                            <x-slot:labelText>
                                {{ __('labels.address') }}
                            </x-slot:labelText>
                            <x-slot:labelValue>
                                {{ $jobData->location }}
                            </x-slot:labelValue>
                        </x-line-table.row>
                    @endif
                </x-line-table.table>
            </div>
            <div class="mb-2">
                <x-entity.title title="{{ __('business/jobs.applications') }}" caption="{{ now()->locale(app()->getLocale())->format('F, Y') }}"></x-entity.title>
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
                            {{ $jobData->currency_name }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            ID
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            #{{ $jobData->id }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('labels.create_date') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $jobData->created_at->getIso() }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                </x-striped-table.table>
            </div>
        </x-sided-content>
    </div>

    <script>
        const alpineComponent = {
            deleteJob: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.delete_job.title') }}",
                    desc: "{{ __('business/prompt.delete_job.description') }}",
                    formAction: "{{ route('business.jobs.destroy', $jobData->id) }}"
                });
            },
            unpublishJob: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.unpublish_job.title') }}",
                    desc: "{{ __('business/prompt.unpublish_job.description') }}",
                    formAction: "{{ route('business.jobs.unpublish', $jobData->id) }}",
                    confirmButtonText: "{{ __('business/prompt.unpublish_job.confirm_btn_text') }}",
                });
            },
            publishJob: () => {
                Alpine.store('confirmModal').open({
                    title: "{{ __('business/prompt.publish_job.title') }}",
                    desc: "{{ __('business/prompt.publish_job.description') }}",
                    formAction: "{{ route('business.jobs.publish', $jobData->id) }}",
                    confirmButtonText: "{{ __('business/prompt.publish_job.confirm_btn_text') }}",
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
