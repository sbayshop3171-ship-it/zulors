<template>
	<button type="button" class="border-b border-bord-sc last:border-b-0 outline-none w-full p-4 flex text-left gap-3 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed smoothing hover:bg-fill-qt/50" v-bind:disabled="disabled">
		<div class="shrink-0">
			<AvatarSmall v-if="avatarSrc" v-bind:avatarSrc="avatarSrc"></AvatarSmall>
			<div v-else class="size-small-avatar rounded-full overflow-hidden flex items-center justify-center" v-bind:class="iconShellClasses">
				<span class="size-icon-small" v-bind:class="iconClasses">
					<SvgIcon v-bind:name="iconName" v-bind:type="iconType"></SvgIcon>
				</span>
			</div>
		</div>
		<div class="flex-1 leading-snug">
			<h3 class="text-lab-pr font-bold text-par-n">
				{{ title }}
			</h3>
			<p class="text-lab-sc text-par-m">
				{{ description }}
			</p>
		</div>
		<div v-if="trailingIconName" class="shrink-0 size-icon-normal self-center text-lab-sc">
			<SvgIcon v-bind:name="trailingIconName" v-bind:type="trailingIconType"></SvgIcon>
		</div>
	</button>
</template>

<script>
	import { defineComponent } from 'vue';
	import AvatarSmall from '@D/components/general/avatars/AvatarSmall.vue';
	
	export default defineComponent({
		props: {
			avatarSrc: {
				type: String,
				default: '',
			},
			iconName: {
				type: String,
				default: 'x',
			},
			iconType: {
				type: String,
				default: 'solid',
			},
			plainIcon: {
				type: Boolean,
				default: false,
			},
			disabled: {
				type: Boolean,
				default: false,
			},
			title: {
				type: String,
				required: true,
			},
			description: {
				type: String,
				required: true,
			},
			trailingIconName: {
				type: String,
				default: '',
			},
			trailingIconType: {
				type: String,
				default: 'line',
			},
		},
		computed: {
			iconShellClasses: function() {
				return {
					'bg-fill-qt': (this.plainIcon !== true)
				};
			},
			iconClasses: function() {
				return (this.plainIcon === true) ? 'text-lab-pr' : 'text-brand-900';
			}
		},
		components: {
			AvatarSmall: AvatarSmall,
		}
	});
</script>
