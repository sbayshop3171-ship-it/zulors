<template>
    <div class="popup-card">
        <PopupPanel>
            <div class="p-2">
                <PanelSearchBar v-on:input="searchGifs" v-bind:placeholder="$t('labels.search_gifs')"></PanelSearchBar>
            </div>
    
            <template v-if="initialLoading">
                <div class="w-full h-16 flex items-center justify-center border-t border-fill-pr">
                    <span class="colibri-primary-animation"></span>
                </div>
            </template>
            <template v-else>
                <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-3 gap-0.5">
                        <div v-on:click="selectGif(gifItem)" v-for="gifItem in gifs" v-bind:key="gifItem.id" class="relative overflow-hidden cursor-pointer group">
                            <template v-if="gifItem.is_loading">
                                <div class="absolute inset-0 w-full aspect-square h-full object-cover overflow-hidden">
                                    <div class="skeleton-square size-full"></div>
                                </div>
                            </template>
                            <img v-on:load="gifItem.is_loading = false" v-bind:src="gifItem.url" loading="lazy" class="w-full aspect-square h-full object-cover smoothing bg-fill-tr hover:scale-105" alt="Image">
                        </div>
                    </div>
                </div>
            </template>
        </PopupPanel>
    </div>
</template>

<script>
    import { defineComponent, ref, onMounted } from 'vue';
    import { giphyAPI } from '@/kernel/services/api-client/giphy/index.js';

    import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';
    import PanelSearchBar from '@D/components/inter-ui/popups/parts/PanelSearchBar.vue';
    import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';

    export default defineComponent({
        emits: ['select'],
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

                    setTimeout(() => {
                        initialLoading.value = false;
                    }, 300);
                } catch (error) {
                    // Pass
                }
            });

            return {
                initialLoading: initialLoading,
                gifs: gifs,
                searchGifs: async (searchText) => {
                    try {
                        let response = await giphyAPI().limit(12).search(searchText);
                        
                        gifs.value = response.data.map(extractGif);
                    } catch (error) {
                        // Pass
                    }
                },
                selectGif: (gifItem) => {
                    context.emit('select', gifItem);
                }
            };
        },
        components: {
            PrimaryTextButton: PrimaryTextButton,
            PanelSearchBar: PanelSearchBar,
            PopupPanel: PopupPanel
        }
    });
</script>