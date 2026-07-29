<template>
	<div class="block bg-fill-qt overflow-hidden rounded-xl relative">
		<div class="z-10 absolute left-3 top-0 bottom-0 inline-flex-center">
			<span class="size-icon-small text-lab-tr">
				<SvgIcon name="search-lg"></SvgIcon>
			</span>
		</div>
		<input
			v-on:input="updateModelValue"
			v-bind:value="modelValue"
			class="block w-full bg-transparent placeholder:text-center outline-hidden text-par-l placeholder:text-par-l text-lab-pr px-9 h-10"
			v-bind:placeholder="placeholder"
		type="text">

		<div v-if="modelValue.length" class="inline-flex items-center absolute top-0 bottom-0 right-3">
			<ClearButton v-on:click="cancelSearch"></ClearButton>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import ClearButton from '@M/components/inter-ui/buttons/ClearButton.vue';

	export default defineComponent({
		props: {
			modelValue: {
                type: String,
                default: ''
            },
			placeholder: {
                type: String,
                default: ''
            },
		},
		emits: ['cancel', 'update:modelValue'],
		setup(props, context) {
			return {
				modelValue: computed(() => {
                    return props.modelValue;
                }),
				updateModelValue: (event) => {
                    context.emit('update:modelValue', event.target.value);
                },
				cancelSearch: () => {
					context.emit('cancel');
				}
			};
		},
		components: {
			ClearButton: ClearButton
		}
	});
</script>