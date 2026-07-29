<template>
	<div class="block">
		<div class="border border-bord-card/80 rounded-full">
			<div class="h-11 relative flex-1">
				<span class="absolute top-0 bottom-0 flex-center h-full left-3">
					<SvgIcon type="solid" name="search-lg" classes="size-icon-small text-lab-sc"></SvgIcon>
				</span>
				<input type="text"
					v-bind:value="modelValue"
					v-on:input.lazy.debounce.500="updateModelValue"
					class="bg-transparent placeholder-shown:text-center h-full w-full px-12 text-lab-pr2 text-par-l outline-hidden placeholder:text-lab-tr rounded-md border border-transparent"
				v-bind:placeholder="placeholder">
				<template v-if="$slots.default">
					<div class="absolute top-0 bottom-0 flex-center h-full right-2.5">
						<slot></slot>
					</div>
				</template>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	export default defineComponent({
		props: {
			placeholder: {
				type: String,
				default: ''
			},
			modelValue: {
				type: String,
				default: ''
			},
		},
		emits: ['update:modelValue'],
		setup(props, context) {
			return {
				modelValue: computed(() => {
                    return props.modelValue;
                }),
				updateModelValue: (event) => {
					context.emit('update:modelValue', event.target.value);
				}
			};
		}
	});
</script>