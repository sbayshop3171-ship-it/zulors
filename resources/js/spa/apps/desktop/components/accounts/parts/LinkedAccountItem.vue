<template>
	<div v-on:click="$emit('switch', accountData)"
		v-bind:class="[accountData.is_active ? 'cursor-default' : 'cursor-pointer']"
	class="block hover:bg-fill-qt smoothing border-b border-bord-pr p-4 last:border-none">
		<div class="flex justify-between items-center">
			<div class="flex-1 overflow-hidden pr-12">
				<AvatarRightSided
					v-bind:avatarSrc="accountData.avatar_url"
					v-bind:verified="accountData.verified"
					v-bind:name="accountData.name"
					v-bind:linkRoute="{ name: 'profile_index', params: { id: accountData.username } }"
				v-bind:caption="accountData.caption"></AvatarRightSided>
				<div v-if="hasBio || hasWebsite" class="pl-small-avatar mt-2">
					<div class="pl-2">
						<p v-if="hasBio" class="text-par-m text-lab-pr2 markdown-text line-clamp-2" v-html="$mdInline(accountData.bio)"></p>
						<a v-if="accountData.website" v-bind:href="accountData.website" class="inline-block text-par-m text-lab-pr2 markdown-text hover:underline leading-tight" target="_blank">
							{{ accountData.website }}
						</a>
					</div>
				</div>
			</div>
            <template v-if="$config('features.switch_account.enabled')">
                <div class="shrink-0">
                    <div v-if="accountData.isSwitching" class="w-6">
                        <PrimaryDotsAnimation></PrimaryDotsAnimation>
                    </div>
                    <div v-else class="size-icon-small">
                        <SvgIcon class="text-green-900" v-if="accountData.is_active" name="check-circle" type="solid"></SvgIcon>
                        <SvgIcon class="text-lab-sc" v-else name="circle" type="line"></SvgIcon>
                    </div>
                </div>
            </template>
		</div>
	</div>
</template>

<script>
	import { defineComponent, computed } from 'vue';

	import AvatarRightSided from '@D/components/general/avatars/sided/small/AvatarRightSided.vue';

	export default defineComponent({
		props: {
			accountData: {
				type: Object,
				required: true
			}
		},
		emits: ['switch'],
		setup: function(props) {
			return {
				hasBio: computed(() => {
					return props.accountData.bio.length > 0;
				}),
				hasWebsite: computed(() => {
					return props.accountData.website.length > 0;
				})
			};
		},
		components: {
			AvatarRightSided: AvatarRightSided
		}
	});
</script>
