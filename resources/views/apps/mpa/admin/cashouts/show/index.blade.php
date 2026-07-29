@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/cashouts.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-content>
			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ $cashoutData->user->avatar_url }}"
					name="{{ $cashoutData->user->name }}"
				caption="{{ $cashoutData->user->caption }}">
					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>

							<x-ui.dropdown.item tag="a" href="{{ $cashoutData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="user-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>

                            @if($cashoutData->status->isPending())
                                <x-div></x-div>
                                <x-ui.dropdown.item x-on:click="markAsPaid" itemText="{{ __('admin/dd.cashout.mark_as_paid') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="line" name="shield-tick"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                                <x-ui.dropdown.item :danger="true" x-on:click="rejectCashout" itemText="{{ __('admin/dd.cashout.reject') }}">
                                    <x-slot:itemIcon>
                                        <x-ui-icon type="solid" name="slash-circle-01"></x-ui-icon>
                                    </x-slot:itemIcon>
                                </x-ui.dropdown.item>
                            @endif
                            <x-div></x-div>
                            <x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteCashout" itemText="{{ __('admin/dd.cashout.delete') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="trash-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/cashouts.about_cashout') }}" caption="{{ $cashoutData->created_at->getFormatted() }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $cashoutData->formatted_amount }}" captionText="{{ __('table.labels.amount') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $cashoutData->currency }}" captionText="{{ __('labels.currency') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.user') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							<a href="{{ route('admin.users.show', $cashoutData->user->id) }}" target="_blank" class="underline">
								{{ $cashoutData->user->name }}
							</a>
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.method') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $cashoutData->payment_method }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.status') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							<x-badge variant="{{ $cashoutData->status->badgeVariant() }}">
								{{ $cashoutData->status->label() }}
							</x-badge>
						</x-slot:labelValue>
					</x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('table.labels.commission') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ config('wallet.commission.withdraw') }}%
                        </x-slot:labelValue>
                    </x-line-table.row>
                    <x-line-table.row>
                        <x-slot:labelText>
                            {{ __('table.labels.to_pay') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $cashoutData->formatted_to_pay }}
                        </x-slot:labelValue>
                    </x-line-table.row>
				</x-line-table.table>
			</div>
            <div class="mb-6">
                <x-form.text-input
                    labelText="{{ __('table.labels.credentials') }}"
                    value="{{ $cashoutData->credentials }}"
                    :asText="true"
                readonly></x-form.text-input>
            </div>

			<div class="mb-4">
				<x-entity.title title="{{ __('labels.additional_info') }}"></x-entity.title>
			</div>
            <div class="mb-6">
                <x-striped-table.table>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('table.labels.request_code') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $cashoutData->request_code }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            #ID
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $cashoutData->id }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('table.labels.created_at') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $cashoutData->created_at->getFormatted() }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                    <x-striped-table.row>
                        <x-slot:labelText>
                            {{ __('labels.currency') }}
                        </x-slot:labelText>
                        <x-slot:labelValue>
                            {{ $cashoutData->currency }}
                        </x-slot:labelValue>
                    </x-striped-table.row>
                </x-striped-table.table>
            </div>
            <div class="mb-4">
				<x-entity.title title="{{ __('table.labels.reviewer_notes') }}"></x-entity.title>
			</div>

            @if($cashoutData->reviewer_notes)
                <p class="text-par-l text-lab-pr2">
                    {{ $cashoutData->reviewer_notes }}
                </p>
            @else
                <p class="text-par-n text-lab-sc italic text-center">
                    {{ __('admin/cashouts.no_reviewer_notes') }}
                </p>
            @endif
		</x-content>
	</div>

<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteCashout: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_cashout.title') }}",
						desc: "{{ __('admin/prompt.delete_cashout.description') }}",
						formAction: "{{ route('admin.cashouts.destroy', $cashoutData->id) }}"
					});
				},
                rejectCashout: () => {
                    Alpine.store('confirmModal').open({
                        title: "{{ __('admin/prompt.reject_cashout.title') }}",
                        desc: "{{ __('admin/prompt.reject_cashout.description') }}",
                        confirmButtonText: "{{ __('admin/prompt.reject_cashout.confirm_btn_text') }}",
                        formAction: "{{ route('admin.cashouts.reject', $cashoutData->id) }}"
                    });
                },
                markAsPaid: () => {
                    Alpine.store('confirmModal').open({
                        title: "{{ __('admin/prompt.mark_as_paid.title') }}",
                        desc: "{{ __('admin/prompt.mark_as_paid.description') }}",
                        confirmButtonText: "{{ __('admin/prompt.mark_as_paid.confirm_btn_text') }}",
                        formAction: "{{ route('admin.cashouts.mark_as_paid', $cashoutData->id) }}"
                    });
                },
			}
		});
	});
</script>
@endsection
