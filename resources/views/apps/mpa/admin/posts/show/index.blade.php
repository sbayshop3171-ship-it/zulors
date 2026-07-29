@extends('adminLayout::index')

@section('pageContent')
<div x-data="alpineComponent">
    <x-page-title titleText=" {{ __('admin/posts.show_title') }}"></x-page-title>

	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.publication :postData="$postData"></x-entity.previews.publication>
		</x-slot:sideContent>

		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $postData->user->avatar_url }}"
				name="{{ $postData->user->name }}"
			caption="{{ $postData->user->caption }}">

				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>

						@unless($isPinnedGlobal)
							<x-ui.dropdown.item tag="a" href="#" x-on:click="pinPost" itemText="{{ __('admin/dd.post.pin') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="pin-02"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@else
							<x-ui.dropdown.item tag="a" href="#" x-on:click="unpinPost" itemText="{{ __('admin/dd.post.unpin') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="pin-01"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif
						<x-div></x-div>


						<x-ui.dropdown.item tag="a" href="{{ $postData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="user-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-ui.dropdown.item tag="a" href="{{ $postData->url }}" target="_blank" itemText="{{ __('admin/dd.post.view_publication') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-div></x-div>
						<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deletePost" itemText="{{ __('admin/dd.post.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>

		<div class="mb-4">
			<x-entity.title title="{{ __('admin/posts.about_post') }}" caption="{{ $postData->created_at->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $postData->comments_count }}" captionText="{{ __('labels.comments') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $postData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $postData->bookmarks_count }}" captionText="{{ __('labels.bookmarks') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>

		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.author') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<a href="{{ route('admin.users.show', $postData->user->id) }}" target="_blank" class="underline">
							{{ $postData->user->name }}
						</a>
					</x-slot:labelValue>
				</x-line-table.row>
				@if($isPinnedGlobal)
					<x-line-table.row>
						<x-slot:labelText>
							{{ __('table.labels.pinned_global') }}
						</x-slot:labelText>
						<x-slot:labelValue>
							{{ __('labels.yes') }} 📌
						</x-slot:labelValue>
					</x-line-table.row>
				@endif
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $postData->type->label() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.media') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $postData->media_count }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.shares') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $postData->shares_count }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.quotes') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $postData->quotes_count }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.created_at') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $postData->created_at->getFormatted() }}
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
					{{ $postData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					Hash ID
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $postData->hash_id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.edited') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ empty($postData->edited) ? '❌' : '✅' }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.is_sensitive') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ empty($postData->is_sensitive) ? '❌' : '✅' }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.is_ai_generated') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ empty($postData->is_ai_generated) ? '❌' : '✅' }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.text_language') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					<span class="uppercase">{{ $postData->text_language }}</span>
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>

<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deletePost: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_post.title') }}",
						desc: "{{ __('admin/prompt.delete_post.description') }}",
						formAction: "{{ route('admin.posts.destroy', $postData->id) }}"
					});
				},
				pinPost: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.pin_post.title') }}",
						desc: "{{ __('admin/prompt.pin_post.description') }}",
						formAction: "{{ route('admin.posts.pin', $postData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.pin_post.confirm_btn_text') }}"
					});
				},
				unpinPost: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.unpin_post.title') }}",
						desc: "{{ __('admin/prompt.unpin_post.description') }}",
						formAction: "{{ route('admin.posts.unpin', $postData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.unpin_post.confirm_btn_text') }}"
					});
				}
			}
		});
	});
</script>

@endsection
