@php
    $incomeLabel = $jobData->is_start_income
        ? __('labels.income_from', ['amount' => $jobData->formatted_income])
        : __('labels.income_to', ['amount' => $jobData->formatted_income]);

    $locationLabel = $jobData->is_remote ? __('business/jobs.remote_work') : $jobData->location;
@endphp

<article
    class="business-jobs-card"
    x-data="{ menuOpen: false }"
    x-on:keydown.escape.window="menuOpen = false">
    <div class="business-jobs-card-menu" x-on:click.outside="menuOpen = false">
        <button
            type="button"
            class="business-jobs-menu-button"
            aria-label="{{ __('labels.table.actions') }}"
            x-on:click.stop="menuOpen = ! menuOpen"
            x-bind:aria-expanded="menuOpen.toString()">
            <span>
                <x-ui-icon type="solid" name="dots-horizontal"></x-ui-icon>
            </span>
        </button>

        <div
            x-show="menuOpen"
            x-transition.origin.top.right
            class="business-jobs-action-menu"
            style="display: none;">
            <a
                href="{{ route('business.jobs.show', ['jobId' => $jobData->id]) }}"
                class="business-jobs-action-item"
                x-on:click="menuOpen = false">
                <span>{{ __('business/dd.job.view_job') }}</span>
                <span class="business-jobs-action-icon">
                    <x-ui-icon type="line" name="layout-alt-02"></x-ui-icon>
                </span>
            </a>

            <a
                href="{{ route('business.jobs.edit', $jobData->id) }}"
                class="business-jobs-action-item"
                x-on:click="menuOpen = false">
                <span>{{ __('business/dd.job.edit_job') }}</span>
                <span class="business-jobs-action-icon">
                    <x-ui-icon type="line" name="edit-03"></x-ui-icon>
                </span>
            </a>

            <button
                type="button"
                class="business-jobs-action-item business-jobs-action-item-danger"
                x-on:click.stop="menuOpen = false; deleteJob('{{ route('business.jobs.destroy', $jobData->id) }}')">
                <span>{{ __('business/dd.job.delete_job') }}</span>
                <span class="business-jobs-action-icon">
                    <x-ui-icon type="line" name="trash-04"></x-ui-icon>
                </span>
            </button>
        </div>
    </div>

    <a href="{{ route('business.jobs.show', ['jobId' => $jobData->id]) }}" class="business-jobs-card-title">
        {{ $jobData->title }}
    </a>

    <p class="business-jobs-card-desc">
        {{ $jobData->overview }}
    </p>

    <div class="business-jobs-card-badges">
        @if($jobData->approval->isPending())
            <x-badge variant="{{ $jobData->approval->badgeVariant() }}">
                {{ $jobData->approval->label() }} {{ $jobData->approval->emoji() }}
            </x-badge>
        @else
            <x-badge variant="{{ $jobData->status->badgeVariant() }}">
                {{ $jobData->status->label() }} {{ $jobData->status->emoji() }}
            </x-badge>
        @endif

        @if($jobData->is_urgent)
            <span class="business-jobs-urgent-badge">{{ __('business/jobs.form.urgent') }}</span>
        @endif
    </div>

    <div class="business-jobs-card-facts">
        <span><strong>{{ $incomeLabel }}</strong></span>
        <span>{{ $jobData->category_name }}</span>
        <span>{{ $locationLabel }}</span>
    </div>

    <div class="business-jobs-card-footer">
        <span>{{ $jobData->created_at->getIso() }}</span>
        <span>{{ __('labels.views') }} {{ $jobData->views_count }}</span>
    </div>
</article>
