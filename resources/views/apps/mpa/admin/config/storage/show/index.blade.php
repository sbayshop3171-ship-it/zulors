@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/storage.show_title') }} ({{ $diskId }})"></x-page-title>

    <div class="grid grid-cols-2 md:grid-cols-2 lg:grid-cols-2 gap-4">
        <x-metric.card title="{{ __('admin/storage.metrics.image.title') }}" desc="{{ __('admin/storage.metrics.image.description') }}" value="{{ file_size_format($diskStats['image']['content_size']) }}" iconName="image-01"/>
        <x-metric.card title="{{ __('admin/storage.metrics.video.title') }}" desc="{{ __('admin/storage.metrics.video.description') }}" value="{{ file_size_format($diskStats['video']['content_size']) }}" iconName="video-lib"/>
        <x-metric.card title="{{ __('admin/storage.metrics.audio.title') }}" desc="{{ __('admin/storage.metrics.audio.description') }}" value="{{ file_size_format($diskStats['audio']['content_size']) }}" iconName="play-01"/>
        <x-metric.card title="{{ __('admin/storage.metrics.document.title') }}" desc="{{ __('admin/storage.metrics.document.description') }}" value="{{ file_size_format($diskStats['document']['content_size']) }}" iconName="doc-01"/>
    </div>
@endsection
