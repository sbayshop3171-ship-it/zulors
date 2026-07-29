<template>
	<label class="inline-flex items-center cursor-pointer leading-zero overflow-hidden">
		<div class="shrink-0">
			<input type="checkbox" v-bind:checked="modelValue" v-on:change="updateValue" class="sr-only peer">
			<div class="relative w-10 h-6 bg-fill-pr peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-[16px] rtl:peer-checked:after:-translate-x-full peer-checked:after:border-bg-pr after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-bg-pr after:shadow-lg after:border-bord-pr after:rounded-full after:size-5 after:transition-all peer-checked:bg-lab-pr"></div>
		</div>
		<template v-if="label">
			<span class="text-par-s text-lab-pr2 ms-3">
				{{ label }}
			</span>
		</template>
	</label>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			modelValue: {
				type: Boolean,
				default: false
			},
			label: {
				type: String,
				default: ''
			}
		},
		emits: ['update:modelValue'],
		setup: function(props, context) {
			return {
				updateValue: (event) => {
					context.emit('update:modelValue', event.target.checked);
				}
			};
		},
		components: {
		}
	});
</script>