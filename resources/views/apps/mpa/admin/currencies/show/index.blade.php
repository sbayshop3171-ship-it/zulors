@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/currencies.show_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.currency></x-entity.previews.currency>
		</x-slot:sideContent>

		<div class="mb-4">
			<x-entity.header
				avatarUrl="{{ asset('assets/icons/avatar/currency-euro-circle.png') }}"
				name="{{ $currencyData->name }} ({{ strtoupper($currencyData->alpha_3_code) }})">
			</x-entity.header>
		</div>
		<div class="mb-4">
			<x-entity.title title="{{ __('admin/currencies.about_currency') }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $currencyData->usage_count }}" captionText="{{ __('table.labels.usage') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $currencyData->alpha_3_code }}" captionText="{{ __('table.labels.code') }} Alpha 3"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $currencyData->order }}" captionText="{{ __('table.labels.order') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>
		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.name') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $currencyData->name }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.status') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						@if($currencyData->status)
							{{ __('labels.status_labels.active') }} ✅
						@else
							{{ __('labels.status_labels.inactive') }} ❌
						@endif
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.code') }} Alpha 3
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $currencyData->alpha_3_code }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.symbol') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $currencyData->symbol }}
					</x-slot:labelValue>
				</x-line-table.row>
			</x-line-table.table>
		</div>
		<div class="mb-4">
			<x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
		</div>

		<x-striped-table.table>
			<x-striped-table.row>
				<x-slot:labelText>
					#ID
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $currencyData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.created_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $currencyData->created_at->getFormatted() }}
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
@endsection
