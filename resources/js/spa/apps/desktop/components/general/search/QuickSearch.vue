<template>
	<div class="block bg-fill-qt overflow-hidden rounded-xl relative">
		<div class="z-10 absolute left-3 top-0 bottom-0 inline-flex-center">
			<span class="size-icon-small text-lab-tr">
				<SvgIcon name="search-lg"></SvgIcon>
			</span>
		</div>
		<input v-on:input="updateModelValue"
			v-bind:value="modelValue"
			class="block w-full bg-transparent placeholder-shown:text-center outline-hidden text-par-m text-lab-pr px-10 h-10"
		v-bind:placeholder="placeholder" type="text">

		<button v-if="modelValue.length" v-on:click="cancelSearch" type="button" class="inline-flex-center cursor-pointer outline-hidden bg-fill-pr text-lab-tr rounded-full size-x-small-avatar absolute top-2 right-2">
			<span class="size-icon-small text-lab-tr">
				<SvgIcon name="x"></SvgIcon>
			</span>
		</button>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

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
		}
	});
</script>