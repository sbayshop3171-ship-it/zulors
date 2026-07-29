@extends('businessLayout::index')

@section('pageContent')
    <div x-data="jobsIndex" class="business-jobs-page">
        <div class="business-jobs-header">
            <div class="min-w-0">
                <span class="business-jobs-kicker">{{ __('business/labels.business_account') }}</span>
                <x-page-title titleText="{{ __('business/jobs.index_title') }}"></x-page-title>
            </div>
        </div>

        <div class="business-jobs-tabs">
            <x-tabs.tabs>
                <x-tabs.tab-item :active="$type == 'all'" href="{{ route('business.jobs.index', ['type' => 'all']) }}" textLabel="{{ __('business/jobs.tabs.all') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'active'" href="{{ route('business.jobs.index', ['type' => 'active']) }}" textLabel="{{ __('business/jobs.tabs.active') }}"></x-tabs.tab-item>
                <x-tabs.tab-item :active="$type == 'archived'" href="{{ route('business.jobs.index', ['type' => 'archived']) }}" textLabel="{{ __('business/jobs.tabs.archived') }}"></x-tabs.tab-item>
            </x-tabs.tabs>
        </div>

        @if($jobsList->isNotEmpty())
            <div class="business-jobs-grid">
                @foreach ($jobsList as $jobData)
                    @include('business::jobs.parts.index.job-card', [
                        'jobData' => $jobData
                    ])
                @endforeach
            </div>
        @else
            @if($type == 'all')
                <div class="business-jobs-empty">
                    <span class="business-jobs-empty-icon">
                        <x-ui-icon name="case-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/jobs.empty_state.index_all.title') }}</strong>
                    <p>{{ __('business/jobs.empty_state.index_all.desc') }}</p>
                    <a href="{{ route('business.jobs.create') }}" class="business-jobs-empty-action">
                        {{ __('business/jobs.create_title') }}
                    </a>
                </div>
            @elseif($type == 'active')
                <div class="business-jobs-empty">
                    <span class="business-jobs-empty-icon">
                        <x-ui-icon name="case-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/jobs.empty_state.index_active.title') }}</strong>
                    <p>{{ __('business/jobs.empty_state.index_active.desc') }}</p>
                    <a href="{{ route('business.jobs.create') }}" class="business-jobs-empty-action">
                        {{ __('business/jobs.create_title') }}
                    </a>
                </div>
            @else
                <div class="business-jobs-empty">
                    <span class="business-jobs-empty-icon">
                        <x-ui-icon name="case-01" type="solar"></x-ui-icon>
                    </span>
                    <strong>{{ __('business/jobs.empty_state.index_archived.title') }}</strong>
                    <p>{{ __('business/jobs.empty_state.index_archived.desc') }}</p>
                </div>
            @endif
        @endif

        @unless($jobsList->isEmpty())
            <div class="business-jobs-pagination">
                {{ $jobsList->onEachSide(1)->withQueryString()->links('pagination.index') }}
            </div>
        @endif
    </div>

    <script>
        window.addEventListener('alpine:init', () => {
            Alpine.data('jobsIndex', () => ({
                deleteJob(formAction) {
                    Alpine.store('confirmModal').open({
                        title: "{{ __('business/prompt.delete_job.title') }}",
                        desc: "{{ __('business/prompt.delete_job.description') }}",
                        formAction
                    });
                }
            }));
        });
    </script>
@endsection
