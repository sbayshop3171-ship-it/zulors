<template>
	<Toolbar v-on:close="$router.back" v-bind:title="$t('market.market_title')">
		<a v-if="$config('features.business_accounts.enabled')" v-bind:href="$getRoute('business_market_create')">
			<PrimaryIconButton iconName="plus" iconType="line"></PrimaryIconButton>
		</a>
	</Toolbar>

	<div class="px-4 pb-6">
		<div class="mobile-safe-sticky-top sticky z-20 bg-bg-pr pt-2 pb-3">
			<label class="flex items-center gap-2 rounded-full border border-bord-pr bg-fill-qt px-4 h-11">
				<span class="size-icon-small text-lab-sc">
					<SvgIcon name="search-lg" type="line"></SvgIcon>
				</span>
				<input
					v-model.trim="searchQuery"
					type="search"
					class="w-full bg-transparent outline-hidden text-par-m text-lab-pr placeholder:text-lab-sc"
				v-bind:placeholder="$t('market.search_product_placeholder')">
			</label>

			<div v-if="categories.length" class="mt-3 flex gap-2 overflow-x-auto pb-1 no-scrollbar">
				<button
					type="button"
					v-on:click="selectCategory(null)"
					class="shrink-0 rounded-full px-4 py-2 text-par-s font-semibold border"
				v-bind:class="selectedCategory ? 'border-bord-pr text-lab-sc bg-bg-pr' : 'border-lab-pr2 bg-lab-pr2 text-bg-pr'">
					{{ $t('labels.for_you') }}
				</button>
				<button
					v-for="categoryItem in categories"
					v-bind:key="categoryItem.id"
					type="button"
					v-on:click="selectCategory(categoryItem)"
					class="shrink-0 rounded-full px-4 py-2 text-par-s font-semibold border"
				v-bind:class="filter.category_id === categoryItem.id ? 'border-lab-pr2 bg-lab-pr2 text-bg-pr' : 'border-bord-pr text-lab-sc bg-bg-pr'">
					{{ categoryItem.name }}
				</button>
			</div>
		</div>

		<template v-if="state.isLoading || state.isSearchLoading">
			<div class="grid grid-cols-2 gap-4 pt-2">
				<div v-for="item in 6" v-bind:key="`product-skeleton-${item}`" class="space-y-2">
					<div class="skeleton aspect-square rounded-xl"></div>
					<div class="skeleton h-4 w-10/12"></div>
					<div class="skeleton h-4 w-7/12"></div>
				</div>
			</div>
		</template>

		<template v-else>
			<div v-if="products.length" class="grid grid-cols-2 gap-4 pt-2">
				<RouterLink
					v-for="productData in products"
					v-bind:key="productData.hash_id"
					v-bind:to="{ name: 'marketplace_show', params: { product_id: productData.hash_id } }"
					class="block"
				>
					<div class="overflow-hidden">
						<div class="aspect-square rounded-xl bg-fill-qt overflow-hidden mb-2 border border-bord-pr">
							<img v-bind:src="productData.preview_image_url" alt="Product image" class="size-full object-cover">
						</div>
						<h4 class="text-par-s leading-4 text-lab-pr2 font-medium line-clamp-2" v-html="productData.title"></h4>
						<div class="mt-1 flex items-baseline gap-1">
							<span class="text-par-s text-lab-pr font-bold">{{ productPrice(productData) }}</span>
							<span v-if="productData.sale_price" class="text-cap-l text-lab-sc line-through">{{ productData.price.formatted }}</span>
						</div>
						<p class="text-par-s text-lab-sc truncate">
							{{ productData.relations.merchant.name }}, {{ productData.category_name }}
						</p>
					</div>
				</RouterLink>
			</div>
			<div v-else class="py-32 text-center">
				<p class="text-par-s text-lab-sc">
					{{ state.isEmpty ? $t('empty_state.empty') : $t('empty_state.market.filter') }}
				</p>
			</div>
		</template>

		<div v-if="state.isLoadingContent" class="flex justify-center py-4">
			<div class="colibri-primary-animation"></div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, reactive, computed, onMounted, watch } from 'vue';
	import { useMarketStore } from '@D/store/market/market.store.js';
	import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';

	import Toolbar from '@M/components/layout/Toolbar.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';

	export default defineComponent({
		setup: function() {
			const marketStore = useMarketStore();
			const searchQuery = ref('');
			const selectedCategory = ref(null);
			const state = reactive({
				isLoading: true,
				isSearchLoading: false,
				isLoadingContent: false,
				noMoreContent: false,
				isEmpty: false
			});

			const products = computed(() => {
				return marketStore.products;
			});

			const categories = computed(() => {
				return marketStore.categories;
			});

			const filter = computed(() => {
				return marketStore.filter;
			});

			const applyFilters = async function() {
				state.noMoreContent = false;
				marketStore.filter.cursor = null;
				state.isSearchLoading = true;

				await marketStore.fetchProducts();

				state.isSearchLoading = false;
			};

			watch(searchQuery, () => {
				marketStore.filter.query = searchQuery.value;

				debounce(async () => {
					await applyFilters();
				}, 450);
			});

			useInfiniteScroll({
				callback: () => {
					debounce(async () => {
						if(! state.isLoadingContent && ! state.noMoreContent && products.value.length) {
							state.isLoadingContent = true;
							marketStore.filter.cursor = marketStore.getLastProductId();
							state.noMoreContent = ! await marketStore.loadMoreProducts();
							state.isLoadingContent = false;
						}
					}, 200);
				}
			});

			onMounted(async () => {
				marketStore.resetFilter();
				await marketStore.fetchCategories();
				await marketStore.fetchMetadata();
				await marketStore.fetchProducts();

				state.isEmpty = ! products.value.length;
				state.isLoading = false;
			});

			return {
				state: state,
				filter: filter,
				products: products,
				categories: categories,
				searchQuery: searchQuery,
				selectedCategory: selectedCategory,
				productPrice: (productData) => {
					return productData.sale_price ? productData.sale_price.formatted : productData.price.formatted;
				},
				selectCategory: async (categoryItem) => {
					selectedCategory.value = categoryItem;
					marketStore.filter.category_id = categoryItem ? categoryItem.id : null;
					await applyFilters();
				}
			};
		},
		components: {
			Toolbar: Toolbar,
			PrimaryIconButton: PrimaryIconButton
		}
	});
</script>
