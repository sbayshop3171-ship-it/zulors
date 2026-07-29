<template>
    <div class="popup-card">
        <PopupPanel v-outside-click="close">
            <div class="flex flex-col h-full overflow-hidden">
                <div class="shrink-0 relative">
                    <div class="flex items-center px-1 border-b border-b-fill-pr">
                        <button v-on:click="switchEmojiCategory('fused')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'fused' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><circle fill="#99AAB5" cx="18" cy="18" r="18"></circle><circle fill="#E1E8ED" cx="18" cy="18" r="14"></circle><path fill="#66757F" d="M19 18c0 .553-.447 1-1 1-.552 0-1-.447-1-1V7c0-.552.448-1 1-1 .553 0 1 .448 1 1v11z"></path><path fill="#66757F" d="M26.661 13c.276.479.112 1.09-.366 1.366l-7.795 4.5c-.478.276-1.089.112-1.365-.366s-.112-1.09.365-1.366l7.795-4.5c.478-.276 1.09-.112 1.366.366z"></path></svg>
                        </button>
                        <button v-on:click="switchEmojiCategory('people_and_body')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'people_and_body' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><circle fill="#FFCC4D" cx="18" cy="18" r="18"></circle><path fill="#664500" d="M18 21c-3.623 0-6.027-.422-9-1-.679-.131-2 0-2 2 0 4 4.595 9 11 9 6.404 0 11-5 11-9 0-2-1.321-2.132-2-2-2.973.578-5.377 1-9 1z"></path><path fill="#FFF" d="M9 22s3 1 9 1 9-1 9-1-2 4-9 4-9-4-9-4z"></path><ellipse fill="#664500" cx="12" cy="13.5" rx="2.5" ry="3.5"></ellipse><ellipse fill="#664500" cx="24" cy="13.5" rx="2.5" ry="3.5"></ellipse></svg>
                        </button>
                        <button v-on:click="switchEmojiCategory('nature_and_animals')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'nature_and_animals' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><circle fill="#C1694F" cx="7" cy="6" r="6"></circle><circle fill="#C1694F" cx="29" cy="6" r="6"></circle><circle fill="#E6AAAA" cx="7" cy="6" r="4"></circle><circle fill="#E6AAAA" cx="29" cy="6" r="4"></circle><path fill="#C1694F" d="M35 22S33.692 0 18 0 1 22 1 22c0 5.872 4.499 10.323 12.216 11.61C14.311 35.06 16.044 36 18 36s3.688-.94 4.784-2.39C30.501 32.323 35 27.872 35 22z"></path><circle fill="#DD2E44" cx="18" cy="30" r="4"></circle><path fill="#D99E82" d="M18 20S7 23.687 7 27s2.687 6 6 6c2.088 0 3.925-1.067 5-2.685C19.074 31.933 20.912 33 23 33c3.313 0 6-2.687 6-6s-11-7-11-7z"></path><path fill="#272B2B" d="M11 17s0-2 2-2 2 2 2 2v2s0 2-2 2-2-2-2-2v-2zm10 0s0-2 2-2 2 2 2 2v2s0 2-2 2-2-2-2-2v-2zm-7.875 8c-1.624 1 3.25 4 4.875 4s6.499-3 4.874-4-8.124-1-9.749 0z"></path></svg>
                        </button>
                        <button v-on:click="switchEmojiCategory('objects')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'objects' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><circle fill="#F5F8FA" cx="18" cy="18" r="18"></circle><path d="M18 11c-.552 0-1-.448-1-1V3c0-.552.448-1 1-1s1 .448 1 1v7c0 .552-.448 1-1 1zm-6.583 4.5c-.1 0-.202-.015-.302-.047l-8.041-2.542c-.527-.167-.819-.728-.652-1.255.166-.527.73-.818 1.255-.652l8.042 2.542c.527.167.819.729.652 1.255-.136.426-.53.699-.954.699zm13.625-.291c-.434 0-.833-.285-.96-.722-.154-.531.151-1.085.682-1.239l6.75-1.958c.531-.153 1.085.153 1.238.682.154.531-.151 1.085-.682 1.239l-6.75 1.958c-.092.027-.186.04-.278.04zm2.001 14.958c-.306 0-.606-.14-.803-.403l-5.459-7.333c-.33-.442-.238-1.069.205-1.399.442-.331 1.069-.238 1.399.205l5.459 7.333c.33.442.238 1.069-.205 1.399-.179.134-.389.198-.596.198zm-18.294-.083c-.197 0-.395-.058-.57-.179-.454-.316-.565-.938-.25-1.392l5.125-7.375c.315-.454.938-.566 1.392-.251.454.315.565.939.25 1.392l-5.125 7.375c-.194.281-.506.43-.822.43zM3.5 27.062c-.44 0-.844-.293-.965-.738L.347 18.262c-.145-.533.17-1.082.704-1.227.535-.141 1.083.171 1.227.704l2.188 8.062c.145.533-.17 1.082-.704 1.226-.088.025-.176.035-.262.035zM22 34h-9c-.552 0-1-.447-1-1s.448-1 1-1h9c.553 0 1 .447 1 1s-.447 1-1 1zm10.126-6.875c-.079 0-.16-.009-.24-.029-.536-.132-.864-.674-.731-1.21l2.125-8.625c.133-.536.679-.862 1.21-.732.536.132.864.674.731 1.211l-2.125 8.625c-.113.455-.521.76-.97.76zM30.312 7.688c-.17 0-.342-.043-.5-.134L22.25 3.179c-.478-.277-.642-.888-.364-1.367.275-.478.886-.643 1.366-.365l7.562 4.375c.478.277.642.888.364 1.367-.185.32-.521.499-.866.499zm-24.811 0c-.312 0-.618-.145-.813-.417-.322-.45-.22-1.074.229-1.396l6.188-4.438c.449-.322 1.074-.219 1.396.229.322.449.219 1.074-.229 1.396L6.083 7.5c-.177.126-.38.188-.582.188z" fill="#CCD6DD"></path><path d="M25.493 13.516l-7.208-5.083c-.348-.245-.814-.243-1.161.006l-7.167 5.167c-.343.248-.494.684-.375 1.091l2.5 8.583c.124.426.515.72.96.72H22c.43 0 .81-.274.948-.681l2.917-8.667c.141-.419-.011-.881-.372-1.136zM1.292 19.542c.058 0 .117-.005.175-.016.294-.052.55-.233.697-.494l3.375-6c.051-.091.087-.188.108-.291L6.98 6.2c.06-.294-.016-.6-.206-.832C6.584 5.135 6.3 5 6 5h-.428C2.145 8.277 0 12.884 0 18c0 .266.028.525.04.788l.602.514c.182.156.413.24.65.24zm9.325-16.547c.106.219.313.373.553.412l6.375 1.042c.04.006.081.01.121.01.04 0 .081-.003.122-.01l6.084-1c.2-.033.38-.146.495-.314.116-.168.158-.375.118-.575l-.292-1.443C22.26.407 20.18 0 18 0c-2.425 0-4.734.486-6.845 1.356l-.521.95c-.117.213-.123.47-.017.689zm20.517 2.724l-1.504-.095c-.228-.013-.455.076-.609.249-.152.173-.218.402-.175.63l1.167 6.198c.017.086.048.148.093.224 1.492 2.504 3.152 5.301 3.381 5.782.024.084.062.079.114.151.14.195.372.142.612.142h.007c.198 0 .323.094 1.768-.753.001-.083.012-.164.012-.247 0-4.753-1.856-9.064-4.866-12.281zM14.541 33.376c.011-.199-.058-.395-.191-.544l-4.5-5c-.06-.066-.131-.122-.211-.163-5.885-3.069-5.994-3.105-6.066-3.13-.078-.025-.161-.039-.242-.039-.537 0-.695.065-1.185 2.024 2.236 4.149 6.053 7.316 10.644 8.703l1.5-1.333c.149-.132.239-.319.251-.518zm17.833-8.567c-.189-.08-.405-.078-.592.005l-6.083 2.667c-.106.046-.2.116-.274.205l-4.25 5.083c-.129.154-.19.352-.172.552.02.2.117.384.272.51.683.559 1.261 1.03 1.767 1.44 4.437-1.294 8.154-4.248 10.454-8.146l-.712-1.889c-.072-.193-.221-.347-.41-.427z" fill="#31373D"></path></svg>
                        </button>
                        <button v-on:click="switchEmojiCategory('travel_and_places')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'travel_and_places' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 36"><path fill="#292F33" d="M34 33c0 1.104-.896 2-2 2h-3c-1.104 0-2-.896-2-2v-8c0-1.104.896-2 2-2h3c1.104 0 2 .896 2 2v8zM9 33c0 1.104-.896 2-2 2H4c-1.104 0-2-.896-2-2v-8c0-1.104.896-2 2-2h3c1.104 0 2 .896 2 2v8z"></path><path fill="#3B88C3" d="M9 5c4-1 14-1 18 0 3.881.97 5 11 5 11s2 0 2 4v8s-.119 3.03-4 4c-4 1-7 1-12 1s-8 0-12-1c-3.88-.97-4-4-4-4v-8s0-4 2-4C4 16 5.12 5.97 9 5z"></path><path fill="#BBDDF5" d="M18 15c3.905 0 8.623.2 12 .561L29 10c-1-3-7-3-11-3S8 7 7 10l-1 5.561C9.377 15.2 14.095 15 18 15z"></path><path fill="#3B88C3" d="M5 15.5c0 .829-.671 1.5-1.5 1.5h-2C.671 17 0 16.329 0 15.5S.671 14 1.5 14h2c.829 0 1.5.671 1.5 1.5zm26 0c0 .829.672 1.5 1.5 1.5h2c.828 0 1.5-.671 1.5-1.5s-.672-1.5-1.5-1.5h-2c-.828 0-1.5.671-1.5 1.5z"></path><path fill="#FFCC4D" d="M11 23c0 1.657-1.343 3-3 3H7c-1.657 0-3-1.343-3-3s1.343-3 3-3h1c1.657 0 3 1.343 3 3zm21 0c0 1.657-1.344 3-3 3h-1c-1.656 0-3-1.343-3-3s1.344-3 3-3h1c1.656 0 3 1.343 3 3z"></path><path fill="#269" d="M12.001 22c-.323 0-.64-.156-.833-.445-1.742-2.614-6.319-3.565-6.365-3.574-.541-.109-.892-.636-.783-1.178.109-.541.632-.891 1.176-.783.221.044 5.431 1.119 7.636 4.426.306.46.182 1.08-.277 1.387-.171.112-.364.167-.554.167zm11.998 0c-.19 0-.383-.055-.554-.168-.46-.307-.584-.927-.277-1.387 2.204-3.307 7.415-4.382 7.636-4.426.54-.106 1.067.242 1.176.783.109.542-.241 1.068-.782 1.178-.046.009-4.623.96-6.365 3.574-.193.29-.511.446-.834.446z"></path><path fill="#55ACEE" d="M18 29c-5.663 0-12.639-.225-13.707-1.293-.391-.391-.391-1.023 0-1.414.344-.345.877-.386 1.267-.122.232.1 2.285.829 12.44.829s12.208-.729 12.44-.829c.391-.264.922-.223 1.267.122.391.391.391 1.023 0 1.414C30.639 28.775 23.663 29 18 29z"></path><path fill="#269" d="M25 29.5c0 1.5-3.134 2.5-7 2.5s-7-1-7-2.5c0-.828 3.134-.5 7-.5s7-.328 7 .5z"></path></svg>
                        </button>
                        <button v-on:click="switchEmojiCategory('symbols')" type="button" class="grayscale py-3 px-2 outline-hidden opacity-80 cursor-pointer hover:grayscale-0 relative" v-bind:class="{ 'grayscale-0 emojis-picker-active-tab': TS.activeCategory == 'symbols' }">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36" fill="none"><path d="M36 32C36 34.209 34.209 36 32 36H4C1.791 36 0 34.209 0 32V4C0 1.791 1.791 0 4 0H32C34.209 0 36 1.791 36 4V32Z" fill="#9266CC"></path> <path d="M18.964 4.033C11.251 3.501 4.566 9.322 4.033 17.036C3.5 24.75 9.322 31.435 17.035 31.967C24.749 32.5 31.434 26.678 31.966 18.965C32.499 11.251 26.678 4.566 18.964 4.033V4.033ZM17.781 30.016C14.464 29.956 11.823 27.217 11.884 23.898C11.943 20.583 14.683 17.94 18 18C21.317 18.06 24.056 15.419 24.118 12.103C24.164 9.545 22.594 7.355 20.36 6.446L20.511 6.275C26.028 7.449 30.123 12.371 30.017 18.218C29.895 24.857 24.42 30.137 17.781 30.016V30.016Z" fill="white"></path> <path d="M18 26.5C19.3807 26.5 20.5 25.3807 20.5 24C20.5 22.6193 19.3807 21.5 18 21.5C16.6193 21.5 15.5 22.6193 15.5 24C15.5 25.3807 16.6193 26.5 18 26.5Z" fill="white"></path> <path d="M18 14.5C19.3807 14.5 20.5 13.3807 20.5 12C20.5 10.6193 19.3807 9.5 18 9.5C16.6193 9.5 15.5 10.6193 15.5 12C15.5 13.3807 16.6193 14.5 18 14.5Z" fill="#9266CC"></path></svg>
                        </button>
                    </div>
                </div>
                <template v-if="isLoading">
                    <div class="h-full">
                        <div class="w-full h-16 flex items-center justify-center">
                            <span class="colibri-primary-animation"></span>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="max-h-96 overflow-y-auto">
                        <div class="flex-1 overflow-hidden py-4">
                            <div class="h-full overflow-y-auto">
                                <div class="px-2 mb-4 last:mb-0" v-for="emojisGroup in emojiMap">
                                    <div class="mb-2">
                                        <span class="text-lab-sc text-par-n">{{ $t(emojisGroup.title) }}</span>
                                    </div>
                                    <div class="grid grid-cols-6 gap-1">
                                        <div v-for="(emojiSymbol, emojiKey) in emojisGroup.emojis" v-bind:key="emojiKey">
                                            <button v-on:click="pickEmoji({ symbol: emojiSymbol, key: emojiKey })" type="button" class="inline-block w-full outline-hidden overflow-hidden rounded-full hover:bg-fill-sc smoothing cursor-pointer">
                                                <span class="text-title-1 leading-normal">{{ emojiSymbol }}</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </PopupPanel>
    </div>
</template>

<script>
    import { defineComponent, computed, ref, onMounted } from 'vue';
    import { NativeEmojisMap } from '@/assets/emojis/native/index.js';
    import PopupPanel from '@D/components/inter-ui/popups/PopupPanel.vue';

    export default defineComponent({
        emits:['pick', 'close'],
        setup: function(props, context) {
            const isLoading = ref(true);
            const _TS_ = ref({
                activeCategory: 'fused',
            });

            const FUSED_EMOJIS = ref([]);

            onMounted(() => {
                setTimeout(() => {
                    isLoading.value = false;
                }, 200);

                const fusedEmojisStorage = localStorage.getItem('FUSED_EMOJIS');
                
                if(fusedEmojisStorage) {
                    FUSED_EMOJIS.value = JSON.parse(fusedEmojisStorage);
                }
            });

            const pushEmojisToFused = (emojiItem) => {
                const duplicateEmojiItemIndex = FUSED_EMOJIS.value.findIndex((item) => {
                    return item.key == emojiItem.key;
                });

                if(duplicateEmojiItemIndex == -1) {
                    FUSED_EMOJIS.value.unshift(emojiItem);
                }
                
                if(FUSED_EMOJIS.value.length > 40) {
                    FUSED_EMOJIS.value = FUSED_EMOJIS.value.splice(0, 40);
                }

                localStorage.setItem('FUSED_EMOJIS', JSON.stringify(FUSED_EMOJIS.value)); 
            }

            return {
                TS: _TS_,
                emojiMap: computed(() => {
                    let emojiMap = NativeEmojisMap;

                    if(FUSED_EMOJIS.value.length > 0) {
                        emojiMap = emojiMap.map((emojiGroup) => {
                            if(emojiGroup.category == 'fused') {
                                emojiGroup.emojis = {};
                                
                                FUSED_EMOJIS.value.forEach((emojiItem) => {
                                    emojiGroup.emojis[emojiItem.key] = emojiItem.symbol;
                                });
                            }

                            return emojiGroup;
                        });
                    }

                    if(_TS_.value.activeCategory == 'fused') {
                        return emojiMap;
                    }
                    else{
                        return emojiMap.filter((emojiGroup) => {
                            return emojiGroup.category == _TS_.value.activeCategory;
                        });
                    }
                }),
                isLoading: isLoading,
                getEmojiUrl: (image) => {
                    return embedder('links.assets.emoji') + "/" + image;
                },
                close: () => {
                    context.emit('close');
                },
                pickEmoji: (emojiItem) => {

                    pushEmojisToFused(emojiItem);

                    context.emit('pick', emojiItem.symbol);
                },
                switchEmojiCategory: (emojiCategory) => {
                    _TS_.value.activeCategory = emojiCategory;
                }
            };
        },
        components: {
            PopupPanel: PopupPanel
        }
    });
</script>