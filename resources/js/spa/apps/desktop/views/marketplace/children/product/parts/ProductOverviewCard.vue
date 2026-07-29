<template>
	<div class="border-b border-bord-pr">
		<div class="overflow-hidden relative group bg-fill-tr">
			<swiper-container v-bind:slides-per-view="slidesPerView" space-between="1" mousewheel="true">
				<swiper-slide v-for="mediaItem in productMedia" v-bind:key="mediaItem.id">
					<div v-on:click="lightboxImages" class="cursor-pointer overflow-hidden">
						<img class="w-full hover:scale-105 smoothing" v-bind:src="mediaItem.source_url" alt="Image">
					</div>
				</swiper-slide>
			</swiper-container>
		</div>
	</div>
	<div class="p-4">
		<div class="flex justify-between items-center mb-2">
			<div class="shrink-0">
				<AvatarRightSided 
					v-bind:name="merchantData.name"
					v-bind:verified="merchantData.verified"
					v-bind:avatarSrc="merchantData.avatar_url"
					v-bind:linkRoute="merchantRoute"
				v-bind:caption="merchantData.caption"></AvatarRightSided>
			</div>
			<div class="shrink-0">
				<PrimaryIconButton 
					v-on:click.prevent="bookmarkProduct" 
					iconName="bookmark"
					hoverText="hover:text-brand-900"
					iconSize="icon-normal"
					v-bind:buttonColor="(hasBookmarked ? 'text-brand-900' : 'text-lab-pr2')"
				v-bind:iconType="(hasBookmarked ? 'solid' : 'line')"></PrimaryIconButton>
			</div>
		</div>
		<div class="mb-2">
			<h4 class="text-title-3 text-lab-pr2 leading-snug font-medium max-w-11/12" v-html="productData.title">
			</h4>
		</div>
		<div class="mb-1">
			<span class="leading-tight flex gap-2 items-center">
				<span class="text-title-3 text-lab-pr font-bold">
					{{ price }}
				</span>
				<span v-if="productData.sale_price" class="text-cap-l text-lab-sc line-through">
					{{ productData.price.formatted }}
				</span>
			</span>
		</div>
		<div class="mb-3">
			<span class="text-par-n text-lab-sc cursor-pointer">
				{{ productData.category_name }}, {{ $t('market.in_stock') }} &middot; {{ productData.date.time_ago }}
			</span>
		</div>
		
		<div class="flex gap-3 leading-none">
			<PrimaryPillButton
				v-if="! productData.meta.is_owner"
				v-on:click="$emit('ask')"
				buttonSize="lm"
			v-bind:buttonText="$t('market.ask_seller')"></PrimaryPillButton>

			<RouterLink v-bind:to="merchantRoute">
				<PrimaryPillButton buttonRole="stroked" buttonSize="lm" v-bind:buttonText="isStore ? $t('market.view_store') : $t('labels.view_profile')"></PrimaryPillButton>
			</RouterLink>
			<div class="shrink-0 self-end ml-auto">
				<ViewsCounter v-bind:counterValue="productData.views_count.formatted"></ViewsCounter>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, computed, reactive } from 'vue';
	import { useMarketStore } from '@D/store/market/market.store.js';
	import { register  } from 'swiper/element/bundle';
	import { useLightboxStore } from '@D/store/lightbox/lightbox.store.js';

    import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ViewsCounter from '@/kernel/vue/components/general/counters/ViewsCounter.vue';

	export default defineComponent({
		props: {
			productData: {
				type: Object,
				required: true
			}
		},
		emits: ['ask'],
		setup(props) {
			register();

			const lightboxStore = useLightboxStore();
			const productData = ref(props.productData);
			const isStore = productData.value.relations.merchant.is_store;
			const marketStore = useMarketStore();
			const state = reactive({
				isSubmitting: false
			});

			const merchantData = computed(() => {
				return productData.value.relations.merchant;
			});

			return {
				state: state,
				slidesPerView: computed(() => {
					if(productData.value.relations.media.length == 1) {
						return 1;
					}

					if(productData.value.relations.media.length == 2) {
						return 2;
					}

					return 2.4;
				}),
				bookmarkProduct: async function() {
                    state.isSubmitting = true;

					await marketStore.bookmarkProduct(productData.value.id).then((response) => {
                        if(response.data.data.bookmarked) {
                            productData.value.meta.activity.bookmarked = true;
                            toastSuccess(__t('toast.product.product_bookmarked'));
                        }
                        else{
                            productData.value.meta.activity.bookmarked = false;
                            toastSuccess(__t('toast.product.product_unbookmarked'));
                        }
                    }).catch((error) => {
                        if(error.response) {
                            alert(error.response.data.message);
                        }
                    });
					
                    state.isSubmitting = false;
                },
				productData: productData,
				isStore: isStore,
				price: computed(() => {
                    if(productData.value.sale_price) {
                        return productData.value.sale_price.formatted;
                    }

                    return productData.value.price.formatted;
                }),
				hasBookmarked: computed(() => {
                    return productData.value.meta.activity.bookmarked;
                }),
				merchantRoute: computed(() => {
                    return {
                        name: (isStore ? 'market_store_page' : 'profile_index'),
                        params: {
                            id: productData.value.relations.merchant.username
                        }
                    };
                }),
				merchantData: merchantData,
				productMedia: computed(() => {
					if(productData.value.relations.media.length) {
						return productData.value.relations.media;
					}

					return [
						{
							source_url: productData.value.preview_image_url
						}
					];
				}),
				lightboxImages: function() {
					lightboxStore.add({
						albumName: `${merchantData.value.name} ${merchantData.value.caption}`,
						images: productData.value.relations.media.map((mediaItem) => {
							return mediaItem.source_url;
						}),
						date: productData.value.date.iso
					});
				}
			};
		},
		components: {
			PrimaryPillButton: PrimaryPillButton,
			PrimaryIconButton: PrimaryIconButton,
            AvatarRightSided: AvatarRightSided,
			ViewsCounter: ViewsCounter
		}
	});
</script>