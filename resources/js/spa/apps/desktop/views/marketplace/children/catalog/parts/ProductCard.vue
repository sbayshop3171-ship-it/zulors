<template>
    <div class="cursor-pointer">
        <div class="rounded-lg overflow-hidden mb-2">
            <img v-bind:src="productData.preview_image_url" alt="Image" class="w-full">
        </div>
        <div class="flex-1 overflow-hidden">
            <div class="mb-1 max-w-11/12">
                <h4 class="text-par-s line-clamp-2 text-lab-pr" v-html="productData.title">
                </h4>
            </div>
            <div class="block">
                <span class="text-lab-pr2 text-par-n font-semibold">{{ price }}</span>
                <span v-if="productData.sale_price" class="text-lab-sc text-par-s line-through">{{ productData.price.formatted }}</span>
            </div>
            <div class="text-par-s text-lab-sc overflow-hidden">
                <RouterLink v-bind:to="merchantRoute" class="truncate hover:underline">
                    {{ merchant.name }}
                </RouterLink>, <span class="text-lab-sc text-par-s">{{ productData.category_name }}</span>
            </div>
        </div>
    </div>
</template>

<script>
    import { defineComponent, ref, computed } from 'vue';
    import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';

    export default defineComponent({
        props: {
            productData: {
                type: Object,
                default: {}
            }
        },
        setup: function(props) {
            const productData = ref(props.productData);

            return {
                productData: productData,
                merchantRoute: computed(() => {
                    let isStore = productData.value.relations.merchant.is_store;

                    return {
                        name: (isStore ? 'market_store_page' : 'profile_index'),
                        params: {
                            id: productData.value.relations.merchant.username
                        }
                    };
                }),
                merchant: computed(() => {
                    return productData.value.relations.merchant;
                }),
                price: computed(() => {
                    if(productData.value.sale_price) {
                        return productData.value.sale_price.formatted;
                    }

                    return productData.value.price.formatted;
                })
            }
        },
        components: {
            PrimaryIconButton: PrimaryIconButton
        }
    });
</script>