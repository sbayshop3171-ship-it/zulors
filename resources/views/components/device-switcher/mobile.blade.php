@php
	$isWalletPaymentFlow = request()->is('wallet') || request()->is('wallet/*') || request()->query->has('payment');
@endphp

@unless($isWalletPaymentFlow)
<div class="fixed inset-0 z-[1000] hidden bg-bg-pr px-6 py-10 lg:flex lg:items-center lg:justify-center">
	<div class="mx-auto flex w-full max-w-[420px] flex-col items-center gap-5 rounded-2xl border border-bord-pr bg-bg-pr px-6 py-8 text-center shadow-2xl">
		<div class="shrink-0 rounded-full border border-bord-pr overflow-hidden bg-bg-sc" style="width:72px;height:72px;">
			<img src="{{ $logotypeUrl }}" alt="{{ config('app.name') }}" class="w-full" style="width:72px;height:72px;object-fit:contain;display:block;">
		</div>

		<div class="min-w-0">
			<div class="mb-2 flex items-center justify-center gap-2">
				<div class="size-5 text-lab-pr2 shrink-0" style="width:20px;height:20px;">
					<x-ui-icon name="monitor-03" type="line"></x-ui-icon>
				</div>
				<h4 class="text-title-3 font-bold text-lab-pr2">
					{{ __('switcher.mobile.title') }}
				</h4>
			</div>
			<p class="text-par-s text-lab-sc">
				{{ __('switcher.mobile.description') }}
			</p>
		</div>

		<a href="{{ route('device.switch', 'desktop') }}" class="block w-full">
			<x-ui.buttons.pill variant="accent" size="md" btnText="{{ __('switcher.mobile.button') }}" width="w-full"></x-ui.buttons.pill>
		</a>
	</div>
</div>
@endunless
