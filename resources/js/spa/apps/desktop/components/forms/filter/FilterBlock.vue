<template>
	<div class="px-4 ">
		<div class="block py-3">
			<div class="mb-0 flex justify-between items-center cursor-pointer" v-on:click="toggle">
				<div class="flex-1">
					<h5 class="text-par-n font-medium text-lab-pr2">
						{{ title }}
					</h5>
				</div>
				<div class="shrink-0">
					<button
						class="size-icon-normal text-lab-tr outline-hidden leading-zero hover:text-brand-900 smoothing"
						aria-expanded="false"
						v-bind:class="[state.isExpanded ? '' : 'rotate-180']"
					type="button">
						<SvgIcon name="chevron-down"></SvgIcon>
					</button>
				</div>
			</div>
			<div v-bind:class="[state.isExpanded ? 'block mt-2' : 'hidden']">
				<slot></slot>

				<template v-if="caption">
					<div class="mt-2">
						<p class="text-par-s text-lab-sc" v-html="caption"></p>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, reactive } from 'vue';

	export default defineComponent({
		props: {
			title: {
				type: String,
				default: ''
			},
			caption: {
				type: String,
				default: ''
			},
			isExpanded: {
				type: Boolean,
				default: false
			}
		},
		setup: function(props) {
			const state = reactive({
				isExpanded: props.isExpanded
			});

			return {
				state: state,
				toggle: () => {
					state.isExpanded = (! state.isExpanded);
				}
			};
		}
	});
</script>