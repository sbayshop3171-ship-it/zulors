<template>
	<div class="block p-4">
		<h3 class="text-title-3 text-lab-pr2 font-semibold mb-4">
			{{ $t('labels.description') }}
		</h3>
		<div class="text-par-m text-lab-pr2">
			<ul class="mb-3 flex flex-col gap-2 text-lab-sc">
				<li class="text-par-s">
					<span class="font-semibold text-lab-pr2">{{ $t('labels.condition')}}:</span> {{ productData.condition }}
				</li>
				<li class="text-par-s">
					<span class="font-semibold text-lab-pr2">{{ $t('labels.published_at')}}:</span> {{ productData.date.iso }}
				</li>
				<template v-if="productData.address">
					<li class="text-par-s">
						<span class="font-semibold text-lab-pr2">{{ $t('labels.address')}}:</span> {{ productData.address }}
					</li>
				</template>
			</ul>

			<div class="block break-words">
				<p ref="productDescriptionHolder" v-bind:class="[!state.textExpanded ? 'line-clamp-[12]' : '']" v-html="$mdInline(productData.description)"></p>
			</div>
			<div v-if="state.textOverflow" class="block mt-2">
				<PrimaryTextButton
					v-on:click="state.textExpanded = !state.textExpanded"
					buttonRole="marginal"
				v-bind:buttonText="state.textExpanded ? $t('labels.see_less_description') : $t('labels.see_full_description')"></PrimaryTextButton>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, ref, onMounted, reactive } from 'vue';
	import { checkTextOverflow } from '@/kernel/helpers/html/index.js';

	import PrimaryTextButton from '@D/components/inter-ui/buttons/PrimaryTextButton.vue';

	export default defineComponent({
		props: {
			productData: {
				type: Object,
				required: true
			}
		},
		setup(props) {
			const productData = ref(props.productData);
			const productDescriptionHolder = ref(null);
			const state = reactive({
				textExpanded: false,
				textOverflow: false
			});

			onMounted(() => {
                state.textOverflow = checkTextOverflow(productDescriptionHolder.value, 12);
            });

			return {
				state: state,
				productData: productData,
				productDescriptionHolder: productDescriptionHolder
			};
		},
		components: {
			PrimaryTextButton: PrimaryTextButton
		}
	});
</script>