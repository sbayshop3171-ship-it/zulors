<template>
	<div class="block">
		<div class="mb-4 px-4">
			<h4 class="text-lab-pr text-par-m font-medium">
				{{ $t('chat.participants_count', { count: chatData.chat_info.participants_count.formatted }) }}
			</h4>
		</div>
		<div v-if="state.isLoading" class="block">
			<div class="flex-center h-24">
				<PrimarySpinAnimation></PrimarySpinAnimation>
			</div>
		</div>
		<div v-else>
			<div class="block max-h-[500px] overflow-y-auto py-1">
				<ParticipantItem
					v-for="participantData in chatParticipants"
					v-bind:name="participantData.relations.user.name"
					v-bind:caption="$t('labels.was_online_at', { time: participantData.relations.user.last_active.time_ago })"
					v-bind:verified="participantData.relations.user.verified"
					v-bind:avatarSrc="participantData.relations.user.avatar_url"
				v-bind:username="participantData.relations.user.username"></ParticipantItem>
			</div>
		</div>
	</div>
</template>

<script>
	import { defineComponent, onMounted, computed, reactive } from 'vue';
	import { useChatStore } from '@D/store/chats/chat.store.js';
	
	import ParticipantItem from '@D/views/messenger/children/chat/parts/participants/ParticipantItem.vue';

	export default defineComponent({
		props: {
			chatData: {
				type: Object,
				required: true
			}
		},
		setup: function (props, context) {
			const state = reactive({
                isLoading: true
            });

			const chatStore = useChatStore();
			const chatParticipants = computed(() => {
                return chatStore.chatParticipants;
            });

            onMounted(async () => {
                try {
                    await chatStore.fetchChatParticipants();

                    state.isLoading = false;
                } catch (error) {
                    alert(error);
                }
            });

			return {
				chatParticipants: chatParticipants,
				state: state
			};
		},
		components: {
			ParticipantItem: ParticipantItem
		}
	});
</script>