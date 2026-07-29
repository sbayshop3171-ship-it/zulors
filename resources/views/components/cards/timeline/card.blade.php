<div class="relative bg-bg-pr overflow-hidden last:border-none rounded-2xl shadow-2xl shadow-fill-qt">
	@if(isset($cardHeader))
		{{ $cardHeader }}
	@endif

	<div class="p-5">
		{{ $slot }}
	</div>

	@if(isset($cardFooter))
		{{ $cardFooter }}
	@endif
</div>