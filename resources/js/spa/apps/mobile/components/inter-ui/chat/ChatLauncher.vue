<template>
	<ContentModal>
		<div class="overflow-hidden">
			<div class="flex h-14 items-center border-b border-bord-pr px-4">
				<div class="w-10 shrink-0"></div>
				<h3 class="flex-1 truncate text-center text-par-l font-bold text-lab-pr">
					{{ $t('market.write_message') }}
				</h3>
				<div class="flex w-10 shrink-0 justify-end">
					<PrimaryIconButton
						v-on:click="$emit('close')"
						iconName="x"
						iconType="line"
						iconSize="icon-normal"
					buttonColor="text-lab-pr2"></PrimaryIconButton>
				</div>
			</div>

			<template v-if="state.isLoading">
				<div class="flex justify-center py-16">
					<PrimarySpinAnimation></PrimarySpinAnimation>
				</div>
			</template>
			<template v-else>
				<div class="px-4 py-6 text-center">
					<div class="mb-3 flex justify-center">
						<AvatarMedium v-bind:avatarSrc="interlocutorData.avatar_url"></AvatarMedium>
					</div>
					<div class="flex min-w-0 justify-center gap-1">
						<h4 class="truncate text-title-3 font-bold text-lab-pr">
							{{ interlocutorData.name }}
						</h4>
						<span v-if="interlocutorData.verified" class="mt-1 size-icon-small shrink-0 text-brand-900">
							<SvgIcon name="check-verified-02" type="solid"></SvgIcon>
						</span>
					</div>
					<p class="mt-1 truncate text-par-s text-lab-sc">
						{{ interlocutorSubtitle }}
					</p>
					<p class="mt-1 text-par-s text-lab-sc">
						{{ interlocutorData.followers_count.formatted }} {{ $t('labels.followers_count', interlocutorData.followers_count.raw) }}
					</p>
					<div class="mt-4 flex justify-center">
						<RouterLink v-bind:to="profileRoute" v-on:click="$emit('close')">
							<PrimaryPillButton buttonSize="sm" v-bind:buttonText="$t('labels.view_profile')"></PrimaryPillButton>
						</RouterLink>
					</div>
				</div>

				<form class="block" v-on:submit.prevent="handleSubmit">
					<div class="border-t border-bord-pr"></div>
					<div class="relative">
						<textarea
							ref="messageTextInputField"
							v-on:input="textInputHandler"
							v-model="messageData.content"
							v-bind:maxlength="validationRules.content.max"
							class="min-h-32 max-h-64 w-full resize-none overflow-y-auto bg-transparent px-4 pb-8 pt-3 text-par-m leading-6 text-lab-pr outline-hidden placeholder:text-lab-sc"
						v-bind:placeholder="$t('chat.write_message')"></textarea>

						<span class="absolute bottom-3 left-4 text-cap-l text-lab-sc">
							{{ messageData.content.length }}/{{ validationRules.content.max }}
						</span>
					</div>
					<div class="border-t border-bord-pr px-4 py-4">
						<PrimaryPillButton
							buttonFluid
							buttonType="submit"
							v-bind:isDisabled="! isFormValid"
							v-bind:loading="state.isSubmitting"
						v-bind:buttonText="$t('labels.send_message')"></PrimaryPillButton>
					</div>
				</form>
			</template>
		</div>
	</ContentModal>
</template>

<script>
	import { computed, defineComponent, nextTick, onMounted, reactive, ref } from 'vue';
	import { useRouter } from 'vue-router';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useInputHandlers } from '@/kernel/vue/composables/input/index.js';

	import ContentModal from '@M/components/general/modals/ContentModal.vue';
	import AvatarMedium from '@M/components/general/avatars/AvatarMedium.vue';
	import PrimaryIconButton from '@M/components/inter-ui/buttons/PrimaryIconButton.vue';
	import PrimaryPillButton from '@M/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimarySpinAnimation from '@M/components/general/animations/PrimarySpinAnimation.vue';

	export default defineComponent({
		emits: ['close'],
		props: {
			userId: {
				type: Number,
				required: true
			},
			payload: {
				type: Object,
				default: function() {
					return {};
				}
			}
		},
		setup: function(props, context) {
			const router = useRouter();
			const { autoResize } = useInputHandlers();
			const messageTextInputField = ref(null);
			const validationRules = ref(null);
			const chatId = ref(null);
			const interlocutorData = ref(null);
			const messageData = reactive({
				content: ''
			});

			const state = reactive({
				isSubmitting: false,
				isLoading: true
			});

			onMounted(async () => {
				await colibriAPI().messenger().with({
					user_id: props.userId
				}).sendTo('chats/launch').then((response) => {
					interlocutorData.value = response.data.data.interlocutor;
					chatId.value = response.data.data.chat_id;
					validationRules.value = response.data.data.validation_rules;
					state.isLoading = false;

					nextTick(() => {
						if(messageTextInputField.value) {
							messageTextInputField.value.focus();
							autoResize(messageTextInputField.value);
						}
					});
				}).catch((error) => {
					if(error.response) {
						context.emit('close');
						toastError(error.response.data.message);
					}
				});
			});

			return {
				state: state,
				messageTextInputField: messageTextInputField,
				messageData: messageData,
				validationRules: validationRules,
				interlocutorData: interlocutorData,
				profileRoute: computed(() => {
					return {
						name: 'profile_index',
						params: {
							id: interlocutorData.value.username
						}
					};
				}),
				interlocutorSubtitle: computed(() => {
					if(interlocutorData.value.caption) {
						return interlocutorData.value.caption;
					}

					return __t('labels.was_online_at', {
						time: interlocutorData.value.last_active.formatted
					});
				}),
				isFormValid: computed(() => {
					return messageData.content.trim().length > 0;
				}),
				textInputHandler: function() {
					autoResize(messageTextInputField.value);
				},
				handleSubmit: async () => {
					if(state.isSubmitting || messageData.content.trim().length === 0) {
						return false;
					}

					state.isSubmitting = true;

					await colibriAPI().messenger().with({
						chat_id: chatId.value,
						content: messageData.content,
						payload: props.payload
					}).sendTo('chats/launcher-send').then(() => {
						messageData.content = '';

						context.emit('close');

						router.push({
							name: 'messenger_chat',
							params: {
								chat_id: chatId.value
							}
						});
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}

						state.isSubmitting = false;
					});
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			AvatarMedium: AvatarMedium,
			PrimaryIconButton: PrimaryIconButton,
			PrimaryPillButton: PrimaryPillButton,
			PrimarySpinAnimation: PrimarySpinAnimation
		}
	});
</script>
