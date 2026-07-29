<template>
	<div class="group overflow-hidden">
		<template v-if="isProductSnapshot">
			<a v-bind:href="linkSnapshot.url" target="_blank" rel="noreferrer" class="block bg-bg-pr text-lab-pr2">
				<div class="flex flex-col sm:flex-row overflow-hidden">
					<div class="relative w-full sm:w-36 shrink-0 aspect-[4/3] bg-fill-fv overflow-hidden">
						<img
							v-if="previewImage"
							v-bind:src="previewImage"
							v-bind:alt="linkSnapshot.title"
							class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
						>
						<div v-else class="h-full w-full flex-center text-lab-sc text-par-s font-medium">
							{{ $t('labels.product') }}
						</div>
					</div>

					<div class="min-w-0 flex-1 p-3 md:p-4">
						<div class="flex items-center gap-2 min-w-0 mb-3">
							<div class="size-9 shrink-0 rounded-full bg-fill-fv overflow-hidden flex-center">
								<img v-if="sellerAvatar" v-bind:src="sellerAvatar" v-bind:alt="sellerName" class="h-full w-full object-cover">
								<span v-else class="text-par-s font-semibold text-lab-pr2">
									{{ sellerInitials }}
								</span>
							</div>

							<div class="min-w-0 flex-1">
								<p class="text-par-s font-semibold text-lab-pr2 truncate">
									{{ sellerName }}
								</p>
								<p class="text-par-xs text-lab-sc truncate">
									@{{ sellerUsername }}
								</p>
							</div>
						</div>

						<h4 class="text-par-m md:text-par-l font-semibold text-lab-pr2 line-clamp-2">
							{{ linkSnapshot.title }}
						</h4>

						<div class="mt-3 flex items-center justify-between gap-3">
							<p class="text-par-xs text-lab-sc truncate">
								{{ $t('labels.marketplace') }}
							</p>
							<span class="shrink-0 rounded-full bg-brand-900/10 px-3 py-1 text-par-s font-semibold text-brand-900 whitespace-nowrap">
								{{ priceLabel }}
							</span>
						</div>
					</div>
				</div>
			</a>
		</template>

		<template v-else>
			<div v-if="previewImage" class="max-h-96 overflow-hidden">
				<img v-bind:src="previewImage" class="w-full object-cover">
			</div>

			<div class="p-3 md:p-4 md:pr-12 leading-snug">
				<h4 class="text-lab-pr2 text-par-m font-semibold mb-1 line-clamp-2">
					{{ linkSnapshot.title }}
				</h4>

				<p v-if="linkSnapshot.description" class="text-lab-sc text-par-s line-clamp-2 mb-2.5">
					{{ linkSnapshot.description }}
				</p>

				<a v-bind:href="linkSnapshot.url" target="_blank" rel="noreferrer" class="text-lab-sc text-par-s block truncate group-hover:underline group-hover:text-brand-900">
					{{ linkSnapshot.url }}
				</a>
			</div>
		</template>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			linkSnapshot: {
				type: Object,
				required: true
			}
		},
		setup: function(props) {
			return {
				isProductSnapshot: computed(() => {
					return props.linkSnapshot?.metadata?.entity === 'product';
				}),
				previewImage: computed(() => {
					return props.linkSnapshot?.metadata?.preview_image_url
						|| props.linkSnapshot?.metadata?.preview_image_base64
						|| '';
				}),
				sellerName: computed(() => {
					return props.linkSnapshot?.metadata?.seller?.name
						|| props.linkSnapshot?.metadata?.seller?.username
						|| props.linkSnapshot?.title
						|| '';
				}),
				sellerUsername: computed(() => {
					return props.linkSnapshot?.metadata?.seller?.username || '';
				}),
				sellerAvatar: computed(() => {
					return props.linkSnapshot?.metadata?.seller?.avatar_url || '';
				}),
				sellerInitials: computed(() => {
					let name = props.linkSnapshot?.metadata?.seller?.name
						|| props.linkSnapshot?.metadata?.seller?.username
						|| '';

					if(! name) {
						return 'P';
					}

					return name
						.split(' ')
						.slice(0, 2)
						.map((word) => word?.[0] || '')
						.join('')
						.toUpperCase();
				}),
				priceLabel: computed(() => {
					return props.linkSnapshot?.metadata?.price?.formatted
						|| props.linkSnapshot?.metadata?.price?.label
						|| props.linkSnapshot?.metadata?.price
						|| '';
				})
			};
		}
	});
</script>
