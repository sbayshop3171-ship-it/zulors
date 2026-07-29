<template>
	<PrimaryPillButton 
		v-on:click="handleFollow"
		v-bind="$attrs" 
		v-bind:loading="isLoading"
		v-bind:buttonText="$t(buttonText)"
		v-bind:buttonRole="relationship.following ? 'stroked' : 'default'"
		v-bind:buttonSize="buttonSize"></PrimaryPillButton>
</template>

<script>
    import { defineComponent, computed, ref } from 'vue';
	import { colibriFollow } from '@/kernel/services/follows/index.js';
	
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        props: {
			followableId: {
				type: Number,
				default: 0
			},
			followableType: {
				type: String,
				default: 'user'
			},
			relationship: {
				type: Object,
				default: {}
			},
			buttonSize: {
				type: String,
				default: 'md'
			}
        },
        setup: function(props) {
			const isLoading = ref(false);
			const relationship = ref(props.relationship);
			
			return {
				isLoading: isLoading,
				handleFollow: async () => {
					isLoading.value = true;

					let relationshipResponse = await colibriFollow().user(props.followableId).follow();

					if (relationshipResponse) {
						relationship.value = relationshipResponse.relationship.follow;
					}

					isLoading.value = false;
				},
				relationship: relationship,
				buttonText: computed(() => {
					if (relationship.value.following) {
						return 'labels.unfollow_button';
					}
					else if(relationship.value.requested) {
						return 'labels.follow_requested_button';
					}
					else {
						return 'labels.follow_button';
					}
				})
			}
        },
        components: {
			PrimaryPillButton: PrimaryPillButton
        }
    });
</script>