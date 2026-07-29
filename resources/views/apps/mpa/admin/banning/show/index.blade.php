@extends('adminLayout::index')

@section('headerButtons')
    <x-header-btn link="{{ route('admin.cache.reset') }}" btnText="{{ __('admin/labels.reset_cache') }}" iconName="data" iconType="solid"></x-header-btn>
@endsection

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/ban.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				<x-entity.previews.ban></x-entity.previews.ban>
			</x-slot:sideContent>

			<div class="mb-4">
				<x-entity.header
					avatarUrl="{{ asset('assets/icons/avatar/slash-octagon.png') }}"
					name="{{ __('labels.blacklisted_content') }}">

					<x-slot:controlOptions>
						<x-ui.buttons.icon iconName="trash-04" color="danger" iconType="line" label="{{ __('admin/prompt.remove_ban.title') }}" x-on:click="removeBan" ></x-ui.buttons.icon>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/ban.about_ban') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $blacklistedContent->type->label() }}" captionText="{{ __('table.labels.type') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.admin') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							@if($blacklistedContent->admin)
								{{ $blacklistedContent->admin->name }}
							@else
								{{ __('labels.unknown') }}
							@endif
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.content') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $blacklistedContent->blacklistable }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.added_at') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $blacklistedContent->added_at->getFormatted() }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.type') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $blacklistedContent->type->label() }} {{ $blacklistedContent->type->emoji() }}
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
						{{ $blacklistedContent->id }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.expires_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						@if($blacklistedContent->expires_at)
							{{ $blacklistedContent->expires_at->getFormatted() }}
						@else
							{{ __('labels.never') }} ❌
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
					removeBan: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.remove_ban.title') }}",
							desc: "{{ __('admin/prompt.remove_ban.description') }}",
							formAction: "{{ route('admin.banning.delete', $blacklistedContent->id) }}"
						});
					}
				}
			});
		});
	</script>
@endsection
