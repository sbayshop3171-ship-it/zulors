@props(['format'])	

<div class="w-full flex-center h-40 bg-fill-qt relative">
	<div class="size-12">
		<img src="{{ asset('assets/icons/file-format/'.$format.'.png') }}" alt="{{ $format }}" class="w-full">
	</div>
</div>