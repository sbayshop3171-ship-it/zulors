<template>
	<div v-bind:class="[disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer']" class="flex last:border-none border-b border-bord-tr items-center hover:bg-fill-qt py-3.5 px-4 leading-none">
		<RouterLink v-bind:to="{ name: 'profile_index', params: { id: username }}" target="_blank" class="shrink-0 flex-1 overflow-hidden">
			<div class="flex items-center gap-2.5">
				<div class="shrink-0">
					<AvatarSmall v-bind:avatarSrc="avatarSrc"></AvatarSmall>
				</div>
				<div class="flex-1 overflow-hidden">
					<h3 class="text-par-m font-semibold text-lab-pr2 truncate mb-1">
						{{ name }} <VerificationBadge v-if="verified" size="xs"></VerificationBadge>
					</h3>
					<p class="text-par-n text-lab-sc truncate">
						{{ caption }}

						<template v-if="$slots.caption">
							<slot name="caption"></slot>
						</template>
					</p>
				</div>
			</div>
		</RouterLink>
		<div v-if="! disabled" class="shrink-0">
			<slot></slot>
		</div>
	</div>
</template>

<script>
	import { defineComponent } from 'vue';

	import AvatarSmall from '@M/components/general/avatars/AvatarSmall.vue';

	export default defineComponent({
		props: {
			name: {
				type: String,
				default: ''
			},
			username: {
				type: String,
				default: ''
			},
			caption: {
				type: String,
				default: ''
			},
			verified: {
				type: Boolean,
				default: false
			},
			avatarSrc: {
				type: String,
				default: ''
			},
			disabled: {
				type: Boolean,
				default: false
			}
		},
		components: {
			AvatarSmall: AvatarSmall
		}
	});
</script>