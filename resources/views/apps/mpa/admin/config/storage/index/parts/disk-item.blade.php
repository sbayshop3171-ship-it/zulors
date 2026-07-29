<a href="{{ route('admin.storage.show', $diskData['id']) }}" class="border border-fill-tr rounded-2xl p-4 flex items-center gap-2 overflow-hidden">
	<div class="size-12">
		@if($diskData['driver'] === 's3')
			<img src="{{ asset('assets/icons/file-format/s3.png') }}" alt="S3 storage" class="w-full">
		@elseif($diskData['driver'] === 'ftp')
			<img src="{{ asset('assets/icons/file-format/ftp.png') }}" alt="FTP storage" class="w-full">
		@else
			<img src="{{ asset('assets/icons/file-format/pub.png') }}" alt="Public storage" class="w-full">
		@endif
	</div>
	<div class="flex-1">
		<h5 class="text-par-m font-semibold text-lab-pr2">
			@if(isset($diskData['name']))
				{{ $diskData['name'] }}
			@else
				{{ __('labels.unknown') }}
			@endif
		</h5>
		<p class="text-lab-sc text-par-s">
			@if(isset($diskData['description']))
				{{ $diskData['description'] }}
			@else
				{{ __('admin/storage.no_disk_description') }}
			@endif
		</p>
	</div>
	<div class="shrink-0">
		<x-ui.buttons.icon iconName="arrow-up-right" iconType="line"></x-ui.buttons.icon>
	</div>
</a>
