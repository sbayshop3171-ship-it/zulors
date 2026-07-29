@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/payments.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				<x-entity.previews.payment :paymentData="$paymentData"></x-entity.previews.payment>
			</x-slot:sideContent>

			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ $paymentData->user->avatar_url }}"
					name="{{ $paymentData->user->name }}"
				caption="{{ $paymentData->user->caption }}">
					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>

							<x-ui.dropdown.item tag="a" href="{{ $paymentData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="user-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/payments.about_payment') }}" caption="{{ $paymentData->created_at->getFormatted() }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $paymentData->formatted_amount }}" captionText="{{ __('table.labels.amount') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $paymentData->currency }}" captionText="{{ __('labels.currency') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.user') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							<a href="{{ route('admin.users.show', $paymentData->user->id) }}" target="_blank" class="underline">
								{{ $paymentData->user->name }}
							</a>
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.type') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->payment_type->label() }} {{ $paymentData->payment_type->emoji() }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.status') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->status->label() }} {{ $paymentData->status->emoji() }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.method') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->provider_name }}
						</x-slot:labelValue>
					</x-line-table.row>
				</x-line-table.table>
			</div>

			<div class="mb-4">
				<x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
			</div>

			<div class="mb-6">
				<x-striped-table.table>
					<x-striped-table.row>
						<x-slot:labelText>
							#ID
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->id }}
						</x-slot:labelValue>
					</x-striped-table.row>
					<x-striped-table.row>
						<x-slot:labelText>
							{{ __('table.labels.created_at') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->created_at->getFormatted() }}
						</x-slot:labelValue>
					</x-striped-table.row>
					<x-striped-table.row>
						<x-slot:labelText>
							{{ __('labels.currency') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->currency }}
						</x-slot:labelValue>
					</x-striped-table.row>
					<x-striped-table.row>
						<x-slot:labelText>
							UUID
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->payment_uuid }}
						</x-slot:labelValue>
					</x-striped-table.row>
					<x-striped-table.row>
						<x-slot:labelText>
							{{ __('table.labels.reference_id') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $paymentData->reference_id }}
						</x-slot:labelValue>
					</x-striped-table.row>
				</x-striped-table.table>
			</div>

			@if($metadata)
				<div class="mb-4">
					<x-entity.title title="{{ __('labels.metadata') }}"></x-entity.title>
				</div>

				<x-striped-table.table>
					@foreach($metadata as $key => $value)
						<x-striped-table.row>
							<x-slot:labelText>
								{{ strtoupper($key) }}
							</x-slot:labelText>
							<x-slot:labelValue>
								{{ $value }}
							</x-slot:labelValue>
						</x-striped-table.row>
						@endforeach
				</x-striped-table.table>
			@endif
		</x-sided-content>
	</div>
@endsection
