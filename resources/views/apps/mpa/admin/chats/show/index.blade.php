@extends('adminLayout::index')

@section('pageContent')
	<x-page-title titleText=" {{ __('admin/chats.show_title') }}"></x-page-title>

	<div x-data="alpineComponent">
		<x-sided-content>
			<x-slot:sideContent>
				@if($chatData->type->isDirect())
					<x-entity.previews.chat></x-entity.previews.chat>
				@else
					<x-entity.previews.group :groupData="$chatData->group"></x-entity.previews.group>
				@endif
			</x-slot:sideContent>

			<div class="mb-8">
				<x-entity.header
					avatarUrl="{{ asset('assets/icons/avatar/message-chat-circle.png') }}"
					name="{{ $chatData->type->label() }} {{ $chatData->type->emoji() }}">

					<x-slot:controlOptions>
						<x-ui.dropdown.dropdown>
							<x-slot:dropdownButton>
								<span class="opacity-50 hover:opacity-100">
									<x-ui.buttons.icon></x-ui.buttons.icon>
								</span>
							</x-slot:dropdownButton>

							<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteChat" itemText="{{ __('admin/dd.chat.delete_chat') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="trash-04"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						</x-ui.dropdown.dropdown>
					</x-slot:controlOptions>
				</x-entity.header>
			</div>
			<div class="mb-4">
				<x-entity.title title="{{ __('admin/lang.about_lang') }}"></x-entity.title>
			</div>
			<div class="mb-6">
				<x-counter.counter>
					<x-counter.counter-item counterValue="{{ $chatData->participants_count }}" captionText="{{ __('table.labels.participants') }}"></x-counter.counter-item>
					<x-counter.counter-item counterValue="{{ $chatData->messages_count }}" captionText="{{ __('table.labels.messages') }}"></x-counter.counter-item>
				</x-counter.counter>
			</div>
			<div class="mb-6">
				<x-line-table.table>
					@if($chatData->type->isDirect())
						<x-line-table.row>
							<x-slot:labelText>
								{{ __('table.labels.participants') }}
							</x-slot:labelText>
							<x-slot:labelValue>
								@if($chatData->participants->count())
									<div class="flex flex-wrap gap-2">
										@foreach($chatData->participants as $participant)
											<a href="{{ route('admin.users.show', $participant->user->id) }}" target="_blank" class="underline">
												{{ $participant->user->name }}
											</a>
										@endforeach
									</div>
								@else
									{{ __('admin/chats.no_participants') }}
								@endif
							</x-slot:labelValue>
						</x-line-table.row>
					@endif
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.type') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ $chatData->type->label() }} {{ $chatData->type->emoji() }}
						</x-slot:labelValue>
					</x-line-table.row>
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.last_activity') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							@if($chatData->last_activity)
								{{ $chatData->last_activity->getFormatted() }}
							@else
								{{ __('labels.never') }}
							@endif
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
						#HASH
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $chatData->chat_id }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						#ID
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $chatData->id }}
					</x-slot:labelValue>
				</x-striped-table.row>
				<x-striped-table.row>
					<x-slot:labelText>
						{{ __('table.labels.created_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $chatData->created_at->getFormatted() }}
					</x-slot:labelValue>
				</x-striped-table.row>
			</x-striped-table.table>
		</x-sided-content>
	</div>
	<script>
		window.addEventListener('alpine:init', () => {
			Alpine.data('alpineComponent', () => {
				return {
					deleteChat: () => {
						Alpine.store('confirmModal').open({
							title: "{{ __('admin/prompt.delete_chat.title') }}",
							desc: "{{ __('admin/prompt.delete_chat.description') }}",
							formAction: "{{ route('admin.chats.destroy', $chatData->id) }}"
						});
					}
				}
			});
		});
	</script>
@endsection
