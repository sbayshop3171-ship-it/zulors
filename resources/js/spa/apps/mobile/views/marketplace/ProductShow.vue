<template>
	<Toolbar v-on:close="$router.back" v-bind:title="$t('market.product_title')">
		<PrimaryIconButton
			v-if="! state.isLoading && productData"
			v-on:click.prevent="bookmarkProduct"
			iconName="bookmark"
			hoverText="hover:text-brand-900"
			v-bind:buttonColor="hasBookmarked ? 'text-brand-900' : 'text-lab-pr2'"
		v-bind:iconType="hasBookmarked ? 'solid' : 'line'"></PrimaryIconButton>
	</Toolbar>

	<div v-if="state.isLoading" class="px-4 py-8">
		<div class="skeleton aspect-square rounded-2xl mb-4"></div>
		<div class="skeleton h-6 w-8/12 mb-3"></div>
		<div class="skeleton h-5 w-5/12 mb-6"></div>
		<div class="skeleton h-20 w-full"></div>
	</div>

	<div v-else-if="productData" class="pb-24">
		<div class="bg-fill-qt border-y border-bord-pr overflow-hidden">
			<div class="flex gap-px overflow-x-auto snap-x snap-mandatory">
				<a
					v-for="mediaItem in productMedia"
					v-bind:key="mediaItem.source_url"
					v-bind:href="mediaItem.source_url"
					target="_blank"
					rel="noopener noreferrer"
					class="block min-w-full snap-start bg-fill-tr"
				>
					<img v-bind:src="mediaItem.source_url" alt="Product image" class="w-full max-h-[70vh] object-contain">
				</a>
			</div>
		</div>

		<div class="px-4 py-4 border-b border-bord-pr">
			<RouterLink v-bind:to="{ name: 'profile_index', params: { id: merchantData.username } }" class="flex items-center gap-3">
				<img v-bind:src="merchantData.avatar_url" v-bind:alt="merchantData.username" class="size-10 rounded-full object-cover">
				<div class="min-w-0 flex-1">
					<div class="flex items-center gap-1">
						<span class="text-par-m text-lab-pr2 font-semibold truncate">{{ merchantData.name }}</span>
						<span v-if="merchantData.verified" class="size-icon-small text-brand-900">
							<SvgIcon name="check-verified-02" type="solid"></SvgIcon>
						</span>
					</div>
					<span class="block text-par-s text-lab-sc truncate">{{ merchantData.caption }}</span>
				</div>
			</RouterLink>
		</div>

		<div class="px-4 py-4">
			<h1 class="text-title-2 text-lab-pr font-bold leading-tight" v-html="productData.title"></h1>
			<div class="mt-2 flex items-baseline gap-2">
				<span class="text-title-3 text-lab-pr2 font-bold">{{ price }}</span>
				<span v-if="productData.sale_price" class="text-par-s text-lab-sc line-through">{{ productData.price.formatted }}</span>
			</div>
			<p class="mt-2 text-par-s text-lab-sc">
				{{ productData.category_name }} · {{ stockLabel }} · {{ productData.date.time_ago }}
			</p>
			<p v-if="productData.address" class="mt-1 text-par-s text-lab-sc">
				{{ $t('labels.address') }}: {{ productData.address }}
			</p>

			<div class="mt-4 flex gap-3">
				<div v-if="canAskSeller" class="flex-1">
					<PrimaryPillButton
						v-on:click="state.chatMenu.open"
						buttonFluid
					v-bind:buttonText="$t('market.ask_seller')"></PrimaryPillButton>
				</div>
				<RouterLink v-bind:to="merchantRoute" class="flex-1">
					<PrimaryPillButton buttonFluid buttonRole="stroked" v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
				</RouterLink>
			</div>

			<div class="mt-6 border-t border-bord-pr pt-4">
				<h2 class="text-par-l text-lab-pr font-semibold mb-2">{{ $t('labels.description') }}</h2>
				<p class="text-par-m text-lab-pr2 leading-relaxed break-words" v-html="$mdInline(productData.description || '')"></p>
			</div>
		</div>
	</div>

	<Teleport v-if="state.chatMenu.status && productData" to="body">
		<ChatLauncher
			v-bind:userId="merchantData.id"
			v-bind:payload="chatLauncherPayload"
		v-on:close="state.chatMenu.close"></ChatLauncher>
	</Teleport>
</template>

<script>
	import { defineAsyncComponent, defineComponent, computed, onMounted, reactive } from 'vue';
	import { useRouter } from 'vue-router';
	import { useMarketStore } from '@D/store/market/market.store.js';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';

	export default defineComponent({
		props: {
			product_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const router = useRouter();
			const marketStore = useMarketStore();
			const state = reactive({
				isLoading: true,
				chatMenu: useMenu()
			});

			const productData = computed(() => {
				return marketStore.product;
			});

			onMounted(async () => {
				await marketStore.fetchProduct(props.product_id);

				if(! productData.value) {
					router.push({ name: 'error_404' });
				}

				state.isLoading = false;
			});

			return {
				state: state,
				productData: productData,
				merchantData: computed(() => {
					return productData.value.relations.merchant;
				}),
				merchantRoute: computed(() => {
					return {
						name: 'profile_index',
						params: {
							id: productData.value.relations.merchant.username
						}
					};
				}),
				productMedia: computed(() => {
					if(productData.value.relations.media.length) {
						return productData.value.relations.media;
					}

					return [{
						source_url: productData.value.preview_image_url
					}];
				}),
				price: computed(() => {
					return productData.value.sale_price ? productData.value.sale_price.formatted : productData.value.price.formatted;
				}),
				stockLabel: computed(() => {
					return productData.value.stock_quantity > 0 ? __t('market.in_stock') : __t('market.out_of_stock');
				}),
				hasBookmarked: computed(() => {
					return productData.value.meta.activity.bookmarked;
				}),
				canAskSeller: computed(() => {
					return ! productData.value.meta.is_owner;
				}),
				chatLauncherPayload: computed(() => {
					return {
						type: 'product',
						id: productData.value.id
					};
				}),
				bookmarkProduct: async function() {
					await marketStore.bookmarkProduct(productData.value.id).then((response) => {
						productData.value.meta.activity.bookmarked = response.data.data.bookmarked;
					}).catch((error) => {
						if(error.response) {
							alert(error.response.data.message);
						}
					});
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton,
			PrimaryPillButton: PrimaryPillButton,
			ChatLauncher: defineAsyncComponent(() => {
				return import('@M/components/inter-ui/chat/ChatLauncher.vue');
			})
		}
	});
</script>
