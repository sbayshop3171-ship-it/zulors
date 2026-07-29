<template>
    <Teleport v-if="showScrollTopButton" to="body">
        <div class="fixed bottom-6 right-6 size-14">
            <PrimaryFabButton iconName="chevron-up" v-on:click="scrollToTop"></PrimaryFabButton>
        </div>
    </Teleport>
</template>

<script>
	import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
	import PrimaryFabButton from '@D/components/inter-ui/buttons/PrimaryFabButton.vue';

	export default defineComponent({
		setup: function(props, context) {
			const showScrollTopButton = ref(false);

			onMounted(() => {
				window.addEventListener('scroll', scrollHandler);
			});
			

            const scrollHandler = () => {
                showScrollTopButton.value = (window.scrollY > 1200);
            }

            onUnmounted(() => {
                window.removeEventListener('scroll', scrollHandler);
            });

			return {
				showScrollTopButton: showScrollTopButton,
				scrollToTop: () => {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
			};
		},
		components: {
			PrimaryFabButton: PrimaryFabButton
		}
	});
</script>