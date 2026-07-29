@extends('adminLayout::index')

@section('pageContent')
<div x-data="alpineComponent">
    <x-page-title titleText=" {{ __('admin/ads.show_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.ad :adData="$adData"></x-entity.previews.ad>
		</x-slot:sideContent>

		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $adData->user->avatar_url }}"
				name="{{ $adData->user->name }}"
			caption="{{ $adData->user->caption }}">
				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>

						<x-ui.dropdown.item tag="a" href="{{ $adData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="user-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-div></x-div>
						@if($adData->approval->isPending())
							<x-ui.dropdown.item x-on:click="approveAd" itemText="{{ __('admin/dd.ad.approve') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="shield-tick"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
							<x-ui.dropdown.item :danger="true" x-on:click="rejectAd" itemText="{{ __('admin/dd.ad.reject') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="solid" name="slash-circle-01"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif
						<x-div></x-div>
						<x-ui.dropdown.item :danger="true" x-on:click="deleteAd" itemText="{{ __('admin/dd.ad.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>

		<div class="mb-4">
			<x-entity.title title="{{ __('admin/ads.about_ad') }}" caption="{{ $adData->created_at->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $adData->formatted_total_budget }}" captionText="{{ __('table.labels.total_budget') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $adData->formatted_spent_budget }}" captionText="{{ __('table.labels.spends') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $adData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>

		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.author') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<a href="{{ route('admin.users.show', $adData->user->id) }}" target="_blank" class="underline">
							{{ $adData->user->name }}
						</a>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.approval') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->approval->label() }} {{ $adData->approval->emoji() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.status') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->status->label() }} {{ $adData->status->emoji() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.total_budget') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->formatted_total_budget }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.spends') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->formatted_spent_budget }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.price_per_view') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->formatted_price_per_view }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.last_charge_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						@if($adData->last_charge_at)
							{{ $adData->last_charge_at->getFormatted() }}
						@else
							{{ __('labels.never') }}
						@endif
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('labels.views') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->views_count }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('business/ads.clicks') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->clicks_count }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('business/ads.remaining_budget') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->formatted_remaining_budget }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('business/ads.target_topics') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $adData->target_topics_text ?: __('labels.not_available') }}
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
					{{ $adData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.last_show_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					@if($adData->last_show_at)
						{{ $adData->last_show_at->getFormatted() }}
					@else
						{{ __('labels.never') }}
					@endif
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>

<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteAd: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_ad.title') }}",
						desc: "{{ __('admin/prompt.delete_ad.description') }}",
						formAction: "{{ route('admin.ads.destroy', $adData->id) }}"
					});
				},
				approveAd: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.approve_ad.title') }}",
						desc: "{{ __('admin/prompt.approve_ad.description') }}",
						formAction: "{{ route('admin.ads.approve', $adData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.approve_ad.confirm_btn_text') }}"
					});
				},
				rejectAd: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.reject_ad.title') }}",
						desc: "{{ __('admin/prompt.reject_ad.description') }}",
						formAction: "{{ route('admin.ads.reject', $adData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.reject_ad.confirm_btn_text') }}"
					});
				},

			}
		});
	});
</script>
@endsection
