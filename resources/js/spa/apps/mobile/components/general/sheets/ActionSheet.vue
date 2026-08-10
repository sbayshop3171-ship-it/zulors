<template>
	<Teleport to="body">
		<div v-on:click.stop.self="close" class="zulors-action-sheet-overlay fixed inset-0 z-100 flex h-screen w-screen max-w-none flex-col bg-black/70 bg-cover p-0">
			<Transition name="slide-up">
				<div v-if="renderActionSheet" class="zulors-action-sheet relative mt-auto flex max-h-full w-screen max-w-none flex-col rounded-none shadow-none"
				v-bind:class="[isMuted ? 'bg-bg-sc' : 'bg-bg-pr']">
					<div v-on:click.stop.self="close"
					v-on:touchstart.stop.self="close"
					v-on:touchmove.stop.self="close"
					v-on:touchend.stop.self="close"
					class="flex justify-center cursor-pointer h-14 shrink-0 items-center">
						<div class="w-1/10 h-[3px] bg-lab-sc rounded-full" v-bind:class="{'opacity-50': isLocked}"></div>
					</div>
					<div class="flex-1 overflow-hidden"
					v-bind:class="{ 'pb-safe-bottom': $isStandalone() }">
						<slot></slot>
					</div>
				</div>
			</Transition>
		</div>
	</Teleport>
</template>

<script>
	import { defineComponent, ref, onMounted, onUnmounted } from 'vue';
	import { registerNativeBackHandler } from '@M/core/services/native-back/index.js';

	export default defineComponent({
		emits: ['close', 'click'],
		props: {
			isMuted: {
				type: Boolean,
				default: false
			},
			isLocked: {
				type: Boolean,
				default: false
			}
		},
		setup: function(props, context) {
			const renderActionSheet = ref(false);
			let unregisterNativeBackHandler = null;

			const close = function() {

				// If the action sheet is locked, do not close it.

				if(props.isLocked) {
					return false;
				}

				renderActionSheet.value = false;

				context.emit('close');
			}

			onMounted(function() {
				freezeScroll();
				unregisterNativeBackHandler = registerNativeBackHandler(() => {
					if(props.isLocked) {
						return false;
					}

					close();

					return true;
				});

				setTimeout(function() {
					renderActionSheet.value = true;
				}, 200);
			});

			onUnmounted(function() {
				unfreezeScroll();

				if(unregisterNativeBackHandler) {
					unregisterNativeBackHandler();
				}
			});

			return {
				close: close,
				renderActionSheet: renderActionSheet
			};
		}
	});
</script>

<style scoped>
	.slide-up-enter-active,
	.slide-up-leave-active {
	  transition: transform 0.1s ease, opacity 0.1s ease;
	}
	.slide-up-enter-from {
	  transform: translateY(100%);
	  opacity: 0;
	}
	.slide-up-enter-to {
	  transform: translateY(0%);
	  opacity: 1;
	}
	.slide-up-leave-from {
	  transform: translateY(0%);
	  opacity: 1;
	}
	.slide-up-leave-to {
	  transform: translateY(100%);
	}
</style>
