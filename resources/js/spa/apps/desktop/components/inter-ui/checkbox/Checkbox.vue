<template>
	<div class="block">
		<div class="flex items-center cursor-pointer">
			<input 
				v-bind:id="uniqueId"
				v-bind:checked="modelValue"
				v-on:change="updateModelValue"
				type="checkbox"
				v-bind:name="name"
			class="size-icon-small bg-transparent border-2 border-edge-pr rounded-lg focus:ring-brand-900">
			<label v-bind:for="uniqueId" v-html="label" class="ms-2 cursor-pointer text-par-s font-medium text-lab-pr2 flex-1"></label>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	export default defineComponent({
		props: {
			label: {
				type: String,
				default: ''
			},
			name: {
				type: String,
				default: ''	
			},
			modelValue: {
                type: Boolean,
                default: false
            }
		},
		emits: [
            'update:modelValue'
        ],
		setup(props, context) {
			return {
				uniqueId: Math.random().toString(36).substring(7),
				updateModelValue: (event) => {
					context.emit('update:modelValue', event.target.checked);
				}
			};
		}
	});
</script>