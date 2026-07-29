@extends('adminLayout::index')

@section('pageContent')
<div x-data="alpineComponent">
    <x-page-title titleText=" {{ __('admin/users.show_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.user :userData="$userData"></x-entity.previews.user>
		</x-slot:sideContent>
		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $userData->avatar_url }}"
				name="{{ $userData->name }}"
			caption="{{ $userData->caption }}">

				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>
						@unless($userData->verified)
							<x-ui.dropdown.item x-on:click="verifyUser" itemText="{{ __('admin/dd.user.verify_user') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="check-verified-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@else
							<x-ui.dropdown.item :danger="true" x-on:click="unverifyUser" itemText="{{ __('admin/dd.user.unverify_user') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="check-verified-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif
						<x-div></x-div>

						@if($userData->isAuthor())
							<x-ui.dropdown.item :danger="true" x-on:click="unauthorizeUser" itemText="{{ __('admin/dd.user.unauthorize') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="star-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@else
							<x-ui.dropdown.item x-on:click="authorizeUser" itemText="{{ __('admin/dd.user.authorize') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="star-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif

						<x-ui.dropdown.item tag="a" href="{{ $userData->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-div></x-div>
						<x-ui.dropdown.item tag="a" href="{{ route('admin.users.wallet', $userData->id) }}" itemText="{{ __('admin/dd.user.wallet') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="credit-card-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
                        <x-div></x-div>
						<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteUser" itemText="{{ __('admin/dd.user.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>

		@if($authorshipRequest)
			<div class="mb-6">
				<div class="mb-6">
					<div class="p-4 bg-input-pr rounded-2xl">
						<div class="mb-8">
							<h4 class="text-par-m font-medium mb-1">
								{{ __('admin/users.authorship_request_info.title') }} <span class="text-amber-500 font-normal">({{ $authorshipRequest->status->label() }} {{ $authorshipRequest->status->emoji() }})</span>
							</h4>
							<p class="text-par-n text-lab-sc">
								{{ __('admin/users.authorship_request_info.description') }}
								<a target="_blank" class="underline text-brand-900" href="{{ route('document.author.index') }}">
									{{ __('admin/users.authorship_request_info.authorship_rules') }}
								</a>
							</p>
						</div>
						<div class="flex gap-2">
							<x-ui.buttons.pill x-on:click="authorizeUser" size="sm" btnText="{{ __('admin/dd.user.authorize') }}"></x-ui.buttons.pill>
							<x-ui.buttons.pill x-on:click="rejectAuthorization" variant="danger" size="sm" btnText="{{ __('admin/dd.user.reject') }}"></x-ui.buttons.pill>
						</div>
					</div>
				</div>
			</div>
		@endif

		<div class="mb-4">
			<x-entity.title title="{{ __('admin/users.about_user') }}" caption="{{ __('table.labels.last_seen') }}: {{ $userData->getLastActive()->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $userData->followers_count }}" captionText="{{ __('labels.followers') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $userData->following_count }}" captionText="{{ __('labels.following') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $userData->publications_count }}" captionText="{{ __('labels.posts') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>
		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->isAuthor() ? __('labels.author') : __('labels.reader') }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.full_name') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->name }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.username') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ '@' . $userData->username }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.email') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ hide_email_address($userData->email) }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.country') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->country_name }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.city') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ empty($userData->city) ? __('labels.not_set') : $userData->city }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.joined_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->getCreatedAt()->getFormatted() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.last_seen') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->getLastActive()->getFormatted() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						IP address
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->ip_address }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.verification') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->verified ? '✅' : '❌' }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.website') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ empty($userData->website) ? __('labels.not_set') : $userData->website }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.phone') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ empty($userData->phone) ? __('labels.not_set') : $userData->phone }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.gender') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						@if($userData->gender == 'male')
							{{ __('labels.male') }}
						@elseif($userData->gender == 'female')
							{{ __('labels.female') }}
						@else
							{{ __('labels.not_set') }}
						@endif
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.birthday') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $userData->birth_date }}
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
					#{{ $userData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.wallet_balance') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $userData->wallet->balance->getFormattedAmount() }}
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>
<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteUser: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_user.title') }}",
						desc: "{{ __('admin/prompt.delete_user.description') }}",
						formAction: "{{ route('admin.users.destroy', $userData->id) }}"
					});
				},
				authorizeUser: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.authorize_user.title') }}",
						desc: "{{ __('admin/prompt.authorize_user.description') }}",
						confirmButtonText: "{{ __('admin/prompt.authorize_user.confirm_btn_text') }}",
						formAction: "{{ route('admin.users.authorize', $userData->id) }}"
					});
				},
				unauthorizeUser: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.unauthorize_user.title') }}",
						desc: "{{ __('admin/prompt.unauthorize_user.description') }}",
						confirmButtonText: "{{ __('admin/prompt.unauthorize_user.confirm_btn_text') }}",
						formAction: "{{ route('admin.users.unauthorize', $userData->id) }}"
					});
				},
				rejectAuthorization: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.reject_authorization.title') }}",
						desc: "{{ __('admin/prompt.reject_authorization.description') }}",
						confirmButtonText: "{{ __('admin/prompt.reject_authorization.confirm_btn_text') }}",
						formAction: "{{ route('admin.users.auth-reject', $userData->id) }}"
					});
				},
				verifyUser: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.verify_user.title') }}",
						desc: "{{ __('admin/prompt.verify_user.description') }}",
						confirmButtonText: "{{ __('admin/prompt.verify_user.confirm_btn_text') }}",
						formAction: "{{ route('admin.users.verify', $userData->id) }}"
					});
				},
				unverifyUser: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.unverify_user.title') }}",
						desc: "{{ __('admin/prompt.unverify_user.description') }}",
						confirmButtonText: "{{ __('admin/prompt.unverify_user.confirm_btn_text') }}",
						formAction: "{{ route('admin.users.unverify', $userData->id) }}"
					});
				}
			}
		});
	});
</script>
@endsection
