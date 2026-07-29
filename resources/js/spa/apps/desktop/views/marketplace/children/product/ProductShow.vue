<template>
	<SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
				<div class="sticky top-0 popup-background-tr z-10 border-b border-bord-pr">
					<ProductHeader></ProductHeader>
				</div>

				<template v-if="state.isLoading">
					<ProductOverviewSkeleton></ProductOverviewSkeleton>
				</template>
				<template v-else>
					<ProductOverviewCard
						v-on:ask="state.chatMenu.open"
					v-bind:productData="productData"></ProductOverviewCard>
					<Border></Border>
					<ProductDescriptionCard v-bind:productData="productData"></ProductDescriptionCard>
				</template>
			</TimelineContainer>
		</template>
		<template v-slot:sidebar>
			<FollowRecommendationList></FollowRecommendationList>
			<AdGridItem></AdGridItem>
		</template>
	</SidedContentLayout>

	<template v-if="! state.isLoading">
		<Teleport v-if="state.chatMenu.status" to="body">
			<PrimaryTransition>
				<ChatLauncher
					v-bind:userId="productData.relations.merchant.id"
					v-bind:payload="chatLauncherPayload"
				v-on:close="state.chatMenu.close"></ChatLauncher>
			</PrimaryTransition>
		</Teleport>
	</template>
</template>

<script>
	import { defineComponent, computed, reactive, ref, onMounted, defineAsyncComponent } from 'vue';
	import { useRouter } from 'vue-router';
	import { useMenu } from '@/kernel/vue/composables/menu/index.js';

	import { useMarketStore } from '@D/store/market/market.store.js';

	import ProductHeader from '@D/views/marketplace/children/product/parts/ProductHeader.vue';
	import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';
	import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';
	import ProductOverviewCard from '@D/views/marketplace/children/product/parts/ProductOverviewCard.vue';
	import ProductDescriptionCard from '@D/views/marketplace/children/product/parts/ProductDescriptionCard.vue';
	import ProductOverviewSkeleton from '@D/views/marketplace/children/product/parts/skeletons/ProductOverviewSkeleton.vue';

	export default defineComponent({
		props: {
			product_id: {
				type: String,
				required: true
			}
		},
		setup: function(props) {
			const state = reactive({
				isLoading: true,
				chatMenu: useMenu()
			});

			const marketStore = useMarketStore();
			const router = useRouter();

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
				chatLauncherPayload: computed(() => {
					return {
						type: 'product',
						id: productData.value.id
					}
				}),
			};
		},
		components: {
			ProductHeader: ProductHeader,
			SidedContentLayout: SidedContentLayout,
			TimelineContainer: TimelineContainer,
			FollowRecommendationList: FollowRecommendationList,
			AdGridItem: AdGridItem,
			ProductOverviewCard: ProductOverviewCard,
			ProductDescriptionCard: ProductDescriptionCard,
			ProductOverviewSkeleton: ProductOverviewSkeleton,
			ChatLauncher: defineAsyncComponent(function() {
                return import('@D/components/inter-ui/chat/ChatLauncher.vue');
            })
		}
	});
</script>