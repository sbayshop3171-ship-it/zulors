<template>
	<PrimaryPillButton 
		v-on:click="handleFollowAccept"
		v-bind="$attrs" 
		v-bind:loading="isLoading"
		v-bind:buttonText="$t(buttonText)"
		v-bind:buttonRole="isApproved ? 'marginal' : 'default'"
	buttonSize="md"></PrimaryPillButton>
</template>

<script>
    import { defineComponent, computed, ref, popScopeId } from 'vue';
	import { colibriFollow } from '@/kernel/services/follows/index.js';
	
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';

    export default defineComponent({
        props: {
			followableId: {
				type: Number,
				default: 0
			},
			isApproved: {
				type: Boolean,
				default: false
			}
        },
        setup: function(props) {
			const isLoading = ref(false);
			const isApproved = ref(props.isApproved);
			
			return {
				isLoading: isLoading,
				handleFollowAccept: () => {
					isLoading.value = true;
					isApproved.value = true;

					colibriFollow().user(props.followableId).accept();
					
					isLoading.value = false;
				},
				buttonText: computed(() => {
					if (isApproved.value) {
						return 'labels.follow_accepted_button';
					}
					else {
						return 'labels.follow_accept_button';
					}
				})
			}
        },
        components: {
			PrimaryPillButton: PrimaryPillButton
        }
    });
</script>