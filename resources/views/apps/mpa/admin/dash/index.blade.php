@extends('adminLayout::index')

@section('headerButtons')
    <x-header-btn link="{{ route('admin.cache.reset') }}" btnText="{{ __('admin/labels.reset_cache') }}" iconName="data" iconType="solid"></x-header-btn>
@endsection

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/dashboard.title') }}"></x-page-title>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('admin.users.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.users.title') }}"
                desc="{{ __('admin/dashboard.metrics.users.description') }}"
                value="{{ $metrics['users'] }}"
            iconName="users-01"/>
        </a>
        <a href="{{ route('admin.posts.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.publications.title') }}"
                desc="{{ __('admin/dashboard.metrics.publications.description') }}"
                value="{{ $metrics['publications'] }}"
            iconName="video-lib"/>
        </a>
        <a href="{{ route('admin.market.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.products.title') }}"
                desc="{{ __('admin/dashboard.metrics.products.description') }}"
                value="{{ $metrics['products'] }}"
            iconName="bag-01"/>
        </a>

        <a href="{{ route('admin.jobs.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.jobs.title') }}"
                desc="{{ __('admin/dashboard.metrics.jobs.description') }}"
                value="{{ $metrics['jobs'] }}"
            iconName="case-01"/>
        </a>
        <a href="{{ route('admin.ads.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.advertising.title') }}"
                desc="{{ __('admin/dashboard.metrics.advertising.description') }}"
                value="{{ $metrics['advertising'] }}"
            iconName="chart-01"/>
        </a>
        <a href="{{ route('admin.stories.index') }}">
            <x-metric.card
                title="{{ __('admin/dashboard.metrics.stories.title') }}"
                desc="{{ __('admin/dashboard.metrics.stories.description') }}"
                value="{{ $metrics['stories'] }}"
            iconName="story-01"/>
        </a>
    </div>

    <x-info.cache-notice :ttl="config('cache.ttl.admin_metrics')"></x-info.cache-notice>
@endsection
