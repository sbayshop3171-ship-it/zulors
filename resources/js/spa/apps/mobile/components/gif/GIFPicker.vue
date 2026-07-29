<template>
    <ActionSheet v-on:close="close">
        <template v-if="initialLoading">
            <Border></Border>
            <div class="max-h-96 overflow-y-auto">
                <div class="grid grid-cols-3 gap-0.5">
                    <div v-for="i in 9" v-bind:key="i" class="aspect-square skeleton-square rounded-none"></div>
                </div>
            </div>
        </template>
        <template v-else>
            <div class="flex flex-col h-full">
                <div class="shrink-0 px-4 pb-4">
                    <SheetSearchBar v-on:input="searchGifs" v-bind:placeholder="$t('labels.search_gifs')"></SheetSearchBar>
                </div>
                <Border></Border>
                <div class="flex-1 overflow-y-auto">
                    <div v-if="gifs.length" class="grid grid-cols-3 gap-0.5">
                        <div v-on:click="selectGif(gifItem)" v-for="gifItem in gifs" v-bind:key="gifItem.id" class="relative overflow-hidden cursor-pointer group">
                            <template v-if="gifItem.is_loading">
                                <div class="absolute inset-0 w-full aspect-square h-full object-cover overflow-hidden">
                                    <div class="skeleton-square size-full"></div>
                                </div>
                            </template>
                            <img v-on:load="gifItem.is_loading = false" v-bind:src="gifItem.url" loading="lazy" class="w-full aspect-square h-full object-cover smoothing bg-fill-tr hover:scale-105" alt="Image">
                        </div>
                    </div>
                    <div v-else class="py-42">
						<TimelineEmptyState v-bind:desc="$t('empty_state.gifs.desc')"></TimelineEmptyState>
					</div>
                </div>
            </div>
        </template>
    </ActionSheet>
</template>

<script>
    import { defineComponent, computed, ref, onMounted } from 'vue';
    import { giphyAPI } from '@/kernel/services/api-client/giphy/index.js';

    import ActionSheet from '@M/components/general/sheets/ActionSheet.vue';
    import SheetSearchBar from '@M/components/general/sheets/SheetSearchBar.vue';
    import TimelineEmptyState from '@M/components/timeline/state/TimelineEmptyState.vue';

    export default defineComponent({
        emits: ['close', 'select'],
        setup: function(props, context) {
            const gifs = ref([]);
            const initialLoading = ref(true);

            const extractGif = (gifItem) => {
                return {
                    id: gifItem.id,
                    url: gifItem.images.fixed_width_small_still.url,
                    is_loading: true,
                    gif: {
                        preview: {
                            url: gifItem.images.preview_gif.url
                        },
                        original: {
                            url: gifItem.images.original.webp
                        }
                    }
                };
            };

            onMounted(async () => {
                try {
                    let response = await giphyAPI().getTrending();

                    gifs.value = response.data.map(extractGif);
                } catch (error) {
                    gifs.value = [];
                }

                initialLoading.value = false;
            });

            return {
                initialLoading: initialLoading,
                gifs: gifs,
                searchGifs: async (searchText) => {
                    try {
                        let response = await giphyAPI().limit(12).search(searchText);
                        
                        gifs.value = response.data.map(extractGif);
                    } catch (error) {
                        gifs.value = [];
                    }
                },
                close: () => {
                    context.emit('close');
                },
                selectGif: (gifItem) => {
                    context.emit('select', gifItem);
                }
            };
        },
        components: {
            ActionSheet: ActionSheet,
            SheetSearchBar: SheetSearchBar,
            TimelineEmptyState: TimelineEmptyState
        }
    });
</script>