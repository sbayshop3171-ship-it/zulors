@extends('adminLayout::index')

@section('pageContent')
<x-page-title titleText=" {{ __('admin/market.show_title') }}"></x-page-title>

<div x-data="alpineComponent">
	<x-sided-content>
		<x-slot:sideContent>
			<x-entity.previews.product :productData="$productData"></x-entity.previews.product>
		</x-slot:sideContent>
		<div class="mb-8">
			<x-entity.header
				avatarUrl="{{ $productData->user->avatar_url }}"
				name="{{ $productData->user->name }}"
			caption="{{ $productData->user->caption }}">

				<x-slot:controlOptions>
					<x-ui.dropdown.dropdown>
						<x-slot:dropdownButton>
							<span class="opacity-50 hover:opacity-100">
								<x-ui.buttons.icon></x-ui.buttons.icon>
							</span>
						</x-slot:dropdownButton>

						<x-ui.dropdown.item tag="a" href="{{ $productData->user->profile_url }}" target="_blank" itemText="{{ __('admin/dd.user.view_profile') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="user-02"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
						<x-ui.dropdown.item tag="a" href="{{ $productData->url }}" target="_blank" itemText="{{ __('admin/dd.product.view_product') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="arrow-up-right"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>

						@if($productData->approval->isPending())
							<x-ui.dropdown.item x-on:click="approveProduct" itemText="{{ __('admin/dd.product.approve') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="line" name="shield-tick"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
							<x-ui.dropdown.item :danger="true" x-on:click="rejectProduct" itemText="{{ __('admin/dd.product.reject') }}">
								<x-slot:itemIcon>
									<x-ui-icon type="solid" name="slash-circle-01"></x-ui-icon>
								</x-slot:itemIcon>
							</x-ui.dropdown.item>
						@endif
						<x-div></x-div>
						<x-ui.dropdown.item tag="a" :danger="true" x-on:click="deleteProduct" itemText="{{ __('admin/dd.product.delete') }}">
							<x-slot:itemIcon>
								<x-ui-icon type="line" name="trash-04"></x-ui-icon>
							</x-slot:itemIcon>
						</x-ui.dropdown.item>
					</x-ui.dropdown.dropdown>
				</x-slot:controlOptions>
			</x-entity.header>
		</div>
		@if($productData->approval->isPending())
			<div class="mb-6">
				<div class="p-4 bg-input-pr rounded-2xl">
					<div class="mb-4">
						<h4 class="text-par-m font-medium mb-1">
							{{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
						</h4>
						<p class="text-par-n text-lab-sc">
							{{ __('admin/prompt.approve_product.description') }}
						</p>
					</div>
					<div class="flex gap-2">
						<x-ui.buttons.pill x-on:click="approveProduct" size="sm" btnText="{{ __('admin/dd.product.approve') }}"></x-ui.buttons.pill>
						<x-ui.buttons.pill x-on:click="rejectProduct" variant="danger" size="sm" btnText="{{ __('admin/dd.product.reject') }}"></x-ui.buttons.pill>
					</div>
				</div>
			</div>
		@endif
		<div class="mb-4">
			<x-entity.title title="{{ __('admin/market.about_product') }}" caption="{{ $productData->created_at->getFormatted() }}"></x-entity.title>
		</div>
		<div class="mb-6">
			<x-counter.counter>
				<x-counter.counter-item counterValue="{{ $productData->formatted_price }}" captionText="{{ __('labels.price') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $productData->views_count }}" captionText="{{ __('labels.views') }}"></x-counter.counter-item>
				<x-counter.counter-item counterValue="{{ $productData->contacts_count }}" captionText="{{ __('labels.contacts_count') }}"></x-counter.counter-item>
			</x-counter.counter>
		</div>
		<div class="mb-6">
			<x-line-table.table>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.seller') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<a href="{{ route('admin.users.show', $productData->user->id) }}" target="_blank" class="underline">
							{{ $productData->user->name }}
						</a>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.approval') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<x-badge variant="{{ $productData->approval->badgeVariant() }}">
							{{ $productData->approval->label() }} {{ $productData->approval->emoji() }}
						</x-badge>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.status') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						<x-badge variant="{{ $productData->status->badgeVariant() }}">
							{{ $productData->status->label() }} {{ $productData->status->emoji() }}
						</x-badge>
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.product_type') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $productData->type->label() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.discount') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						-{{ $productData->formatted_discount_amount }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.category') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $productData->category_name }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.rating') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ empty($productData->rating) ? __('labels.no_ratings') : $productData->rating }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.condition') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $productData->condition->label() }} {{ $productData->condition->emoji() }}
					</x-slot:labelValue>
				</x-line-table.row>
				<x-line-table.row>
					<x-slot:labelText>
						{{ __('table.labels.stock_quantity') }}
					</x-slot:labelText>
					<x-slot:labelValue>
						{{ $productData->stock_quantity }}
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
					{{ $productData->id }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.created_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $productData->created_at->getFormatted() }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.last_contacted_at') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					@if ($productData->last_contacted_at)
						{{ $productData->last_contacted_at->getFormatted() }}
					@else
						{{ __('labels.never') }}
					@endif
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.bookmarks') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $productData->bookmarks_count }}
				</x-slot:labelValue>
			</x-striped-table.row>
			<x-striped-table.row>
				<x-slot:labelText>
					{{ __('table.labels.reviews') }}
				</x-slot:labelText>
				<x-slot:labelValue>
					{{ $productData->reviews_count }}
				</x-slot:labelValue>
			</x-striped-table.row>
		</x-striped-table.table>
	</x-sided-content>
</div>
<script>
	window.addEventListener('alpine:init', () => {
		Alpine.data('alpineComponent', () => {
			return {
				deleteProduct: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.delete_product.title') }}",
						desc: "{{ __('admin/prompt.delete_product.description') }}",
						formAction: "{{ route('admin.market.destroy', $productData->id) }}"
					});
				},
				approveProduct: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.approve_product.title') }}",
						desc: "{{ __('admin/prompt.approve_product.description') }}",
						formAction: "{{ route('admin.market.approve', $productData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.approve_product.confirm_btn_text') }}"
					});
				},
				rejectProduct: () => {
					Alpine.store('confirmModal').open({
						title: "{{ __('admin/prompt.reject_product.title') }}",
						desc: "{{ __('admin/prompt.reject_product.description') }}",
						formAction: "{{ route('admin.market.reject', $productData->id) }}",
						confirmButtonText: "{{ __('admin/prompt.reject_product.confirm_btn_text') }}"
					});
				}
			}
		});
	});
</script>
@endsection
