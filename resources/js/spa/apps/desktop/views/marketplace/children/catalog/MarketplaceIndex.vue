<template>
    <SidedContentLayout>
        <template v-slot:content>
            <TimelineContainer>
                <CatalogHeader>
                </CatalogHeader>
                <Border></Border>
                <div class="sticky top-0 popup-background-tr z-10">
                    <div class="p-4">
                        <template v-if="state.isLoading">
                            <div class="space-y-4">
                                <div class="skeleton h-11 w-full"></div>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="skeleton h-6 w-40"></div>
                                    <div class="skeleton h-9 w-24"></div>
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="mb-4">
                                <SearchBar v-model.trim="searchQuery" v-bind:placeholder="$t('labels.search')"></SearchBar>
                            </div>
                            <div class="flex items-center">
                                <h4 class="text-par-l text-lab-pr2 font-semibold truncate">
                                    {{ selectedCategory ? selectedCategory.name : $t('labels.for_you') }}
                                </h4>
                
                                <div class="flex items-center gap-2 ml-auto">
                                    <template v-if="activeFiltersCount">
                                        <PrimaryTextButton buttonRole="danger" v-on:click="resetFilter" v-bind:buttonText="$t('market.reset_filters')"></PrimaryTextButton>
                                        <div class="w-px h-4 bg-bord-pr"></div>
                                    </template>
                                    <div class="relative">
                                        <div class="relative">
                                            <PrimaryTextButton v-on:click.stop="state.filterMenu.toggle" buttonRole="marginal" v-bind:buttonText="$t('labels.filters')"></PrimaryTextButton>
                                            <span class="absolute -top-1 -right-1">
                                                <BadgeCounter v-if="activeFiltersCount" v-bind:count="activeFiltersCount"></BadgeCounter>
                                            </span>
                                        </div>
    
                                        <div v-outside-click="state.filterMenu.close" v-if="state.filterMenu.status" class="absolute top-8 -right-4 bottom-0 w-content">
                                            <PopupPanel>
                                                <div class="popup-card bg-bg-pr">
                                                    <div class="p-4 border-b border-b-bord-pr">
                                                        <h4 class="text-par-l text-lab-pr2 font-semibold text-center">
                                                            {{ $t('labels.filters') }}
                                                        </h4>
                                                    </div>
                                                    <div class="overflow-y-auto h-[500px] ">
                                                        <div class="p-4">
                                                            <CategoriesFilter 
                                                                v-on:select="selectCategory"
                                                                v-bind:selected="filter.category_id"
                                                            v-bind:categories="categories"></CategoriesFilter>
                                                        </div>
    
                                                        <ProductsFilter></ProductsFilter>
                                                    </div>
    
                                                    <div class="block px-4 py-4 border-t border-t-bord-pr">
                                                        <PrimaryPillButton v-on:click="applyFilters" v-bind:buttonFluid="true" width="full" v-bind:buttonText="$t('market.apply_filters')"></PrimaryPillButton>
                                                    </div>
                                                </div>
                                            </PopupPanel>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <template v-if="state.isLoading || state.isSearchLoading">
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 px-4 gap-4">
                        <ProductSkeleton v-for="item in 6" v-bind:key="`product-skeleton-${item}`"></ProductSkeleton>
                    </div>
                </template>
                <template v-else>
                    <template v-if="state.isEmpty">
                        <FluidEmptyState v-bind:text="$t('empty_state.empty')" iconType="solid"></FluidEmptyState>
                    </template>
                    <template v-else>
                        <template v-if="products.length">
                            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 px-4 gap-4">
                                <RouterLink
                                    class="block"
                                    v-for="productData in products"
                                    v-bind:key="productData.hash_id"
                                    v-bind:to="{ name: 'marketplace_show', params: { product_id: productData.hash_id } }"
                                    v-on:mouseenter="prefetchProduct(productData.hash_id)"
                                    v-on:focus="prefetchProduct(productData.hash_id)"
                                    v-on:touchstart.passive="prefetchProduct(productData.hash_id)"
                                >
                                    <ProductCard v-bind:productData="productData"></ProductCard>
                                </RouterLink>
                            </div>
                        </template>
                        <template v-else>
                            <FluidEmptyState v-bind:text="$t('empty_state.market.filter')" iconType="solid"></FluidEmptyState>
                        </template>
                    </template>
                </template>
            </TimelineContainer>
        </template>
        <template v-slot:sidebar>
            <FollowRecommendationList></FollowRecommendationList>
            <AdGridItem></AdGridItem>
        </template>
    </SidedContentLayout>
</template>

<script>
    import { defineComponent, watch, ref, reactive, onMounted, computed, onUnmounted } from 'vue';
    import { useMarketStore } from '@D/store/market/market.store.js';
    import { useInfiniteScroll } from '@/kernel/vue/composables/infinite-scroll/index.js';
    import { useMenu } from '@/kernel/vue/composables/menu/index.js';

    import SidedContentLayout from '@D/components/layout/SidedContentLayout.vue';
    import TimelineContainer from '@D/components/layout/TimelineContainer.vue';

    import ProductCard from '@D/views/marketplace/children/catalog/parts/ProductCard.vue';
    import ProductSkeleton from '@D/views/marketplace/children/catalog/parts/ProductSkeleton.vue';
    import CategoriesFilter from '@D/components/general/search/CategoriesFilter.vue';
    import SearchBar from '@D/components/general/search/SearchBar.vue';
    import ProductsFilter from '@D/views/marketplace/children/catalog/parts/ProductsFilter.vue';
    import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
    import FluidEmptyState from '@D/components/page-states/empty/FluidEmptyState.vue';
    import BadgeCounter from '@D/components/general/counters/BadgeCounter.vue';

    import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';
    import CatalogHeader from '@D/views/marketplace/children/catalog/parts/CatalogHeader.vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import FollowRecommendationList from '@D/components/recommend/follow/list/FollowRecommendationList.vue';
    import AdGridItem from '@D/components/ads/AdGridItem.vue';

    export default defineComponent({
        setup: function() {
            const state = reactive({
                isLoading: true,
                isSearchLoading: false,
                noMoreContent: false,
                isLoadingContent: false,
                filterMenu: useMenu(),
                isEmpty: false
            });
            
            const selectedCategory = ref(null);
            const searchQuery = ref(null);
            const marketStore = useMarketStore();
            const categories = computed(() => {
                return marketStore.categories;
            });

            const filter = computed(() => {
                return marketStore.filter;
            });

            const products = computed(() => {
                return marketStore.products;
            });

            const prefetchProductShowView = () => {
                void import('@D/views/marketplace/children/product/ProductShow.vue');
            };

            const prefetchProducts = () => {
                products.value.slice(0, 3).forEach((productData) => {
                    marketStore.prefetchProduct(productData.hash_id);
                });
            };
            
            onMounted(async () => {
                marketStore.resetFilter();
                prefetchProductShowView();
                await marketStore.fetchCategories();
                await marketStore.fetchProducts();
                await marketStore.fetchMetadata();
                prefetchProducts();

                debounce(() => {
                    if(! products.value.length) {
                        state.isEmpty = true;
                    }
                }, 500);

                state.isLoading = false;
            });

            onUnmounted(() => {
                marketStore.product = null;
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

            watch(searchQuery, () => {
                marketStore.filter.query = searchQuery.value;

                debounce(async () => {
                    await applyFilters();
                }, 500);
            });

            const applyFilters = async () => {
                state.noMoreContent = false;
                marketStore.filter.cursor = null;

                state.isSearchLoading = true;
                await marketStore.fetchProducts();
                prefetchProducts();
                state.isSearchLoading = false;

                state.filterMenu.close();
            };

            const activeFiltersCount = computed(() => {
                return marketStore.activeFiltersCount;
            });

            return {
                activeFiltersCount: activeFiltersCount,
                searchQuery: searchQuery,
                state: state,
                filter: filter,
                products: products,
                categories: categories,
                applyFilters: applyFilters,
                selectedCategory: selectedCategory,
                selectCategory: (categoryItem) => {
                    if(marketStore.filter.category_id == categoryItem.id) {
                        marketStore.filter.category_id = null;
                        selectedCategory.value = null;
                    }
                    else {
                        marketStore.filter.category_id = categoryItem.id;
                        selectedCategory.value = categoryItem;
                    }
                },
                resetFilter: async () => {
                    marketStore.resetFilter();
                    searchQuery.value = '';
                    selectedCategory.value = null;

                    state.isLoading = true;
                    await marketStore.fetchProducts();
                    prefetchProducts();
                    state.isLoading = false;
                },
                prefetchProduct: marketStore.prefetchProduct
            };
        },
        components: {
            SidedContentLayout: SidedContentLayout,
            TimelineContainer: TimelineContainer,
            PrimaryPillButton: PrimaryPillButton,
            ProductCard: ProductCard,
            ProductSkeleton: ProductSkeleton,
            CategoriesFilter: CategoriesFilter,
            SearchBar: SearchBar,
            ProductsFilter: ProductsFilter,
            FluidEmptyState: FluidEmptyState,
            BadgeCounter: BadgeCounter,
            PrimaryIconButton: PrimaryIconButton,
            FollowRecommendationList: FollowRecommendationList,
            AdGridItem: AdGridItem,
            PrimaryTextButton: PrimaryTextButton,
            PopupPanel: PopupPanel,
            CatalogHeader: CatalogHeader
        }
    });
</script>
