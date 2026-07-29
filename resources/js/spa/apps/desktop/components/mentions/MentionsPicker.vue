<template>
	<div v-if="mentions.length" class="block relative" v-outside-click="closePicker">
		<div v-bind:class="classes"
		class="max-h-96 overflow-y-auto">
			<MentionItem v-for="(userData, idx) in mentions" v-on:click="selectMention(userData)" v-bind:key="idx" v-bind:userData="userData"></MentionItem>
		</div>
	</div>
</template>

<script>
	import { defineComponent, reactive, onMounted, onUnmounted, ref } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { colibriEventBus } from '@/kernel/events/bus/index.js';

	import MentionItem from '@D/components/mentions/MentionItem.vue';
	
	export default defineComponent({
		emits: ['select'],
		props: {
			classes: {
				type: String,
				default: ''
			}
		},
		setup: function(props, context) {
			const state = reactive({
				isLoading: true,
				hoveredUserIndex: 0,
				isSearching: false
			});
			
			const mentions = ref([]);
			const fetchMentionedUsers = async function(username) {
				if(! state.isSearching) {
					state.isSearching = true;
					await colibriAPI().autocompletes().with({
						query: username
					}).sendTo('mentions').then(response => {
						mentions.value = response.data.data;
					}).catch((error) => {
						closePicker();
					});
	
					state.isSearching = false;
				}

				return false;
			}

			onMounted(() => {
				colibriEventBus.on('editor:mention-input', fetchMentionedUsers);
			});

			onUnmounted(() => {
				colibriEventBus.off('editor:mention-input', fetchMentionedUsers);
			});

			const closePicker = () => {
				mentions.value = [];
			}

			return {
				state: state,
				mentions: mentions,
				selectMention: (userData) => {
					closePicker();
					context.emit('select', userData.username);
				},
				closePicker: closePicker
			};
		},
		components: {
			MentionItem: MentionItem
		}
	});
</script>