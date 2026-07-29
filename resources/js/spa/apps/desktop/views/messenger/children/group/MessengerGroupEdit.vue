<template>
	<div class="flex flex-1 h-full flex-col bg-no-repeat">
		<template v-if="state.isLoading">
			<ChatHeaderSkeleton></ChatHeaderSkeleton>
			<Border></Border>
		</template>
		<template v-else>
			<div class="h-16 flex items-center px-6 border-b border-bord-pr">
				<PageTitle v-bind:backStep="2" v-bind:titleText="editGroupData.is_draft ? $t('chat.create_group') : $t('chat.edit_group')"></PageTitle>
			</div>
		</template>
		<ProgressLineBar v-if="state.uploadProgress" v-bind:progress="state.uploadProgress"></ProgressLineBar>
		<div class="flex-1 overflow-x-hidden overflow-y-auto">
			<div class="flex justify-center py-top-offset">
				<div class="w-content max-w-content">
					<template v-if="state.isLoading">
						<GroupEditorSkeleton></GroupEditorSkeleton>
					</template>
					<template v-else>
						<form v-on:submit.prevent="submitForm">
							<div class="px-16 mb-8 text-center">
								<h2 class="text-title-3 font-bold text-lab-pr">
									{{ editGroupData.is_draft ? $t('chat.group_creator.title') : $t('chat.group_editor.title') }}
								</h2>
								<p class="text-par-m text-lab-sc">
									{{ editGroupData.is_draft ? $t('chat.group_creator.description') : $t('chat.group_editor.description') }}
								</p>
							</div>
					
							<div class="flex justify-center py-4">
								<div class="size-large-avatar relative">
									<AvatarLarge v-bind:hasBorder="false" v-bind:avatarSrc="editGroupData.avatar_url"></AvatarLarge>
									
									<div class="absolute -top-1 -right-1 size-10 bg-bg-pr rounded-full leading-zero">
										<PrimaryIconButton
											iconAreaSize="10"
											iconSize="icon-small"
											v-bind:disabled="state.isSubmitting"
											v-on:click="selectAvatar"
											iconName="pencil-02"
											buttonColor="text-brand-900"
											iconType="line"
										hoverBg="hover:bg-brand-tr"></PrimaryIconButton>
									</div>
								</div>
							</div>
					
							<div class="px-4 mb-8">
								<TextInput v-model.trim="formData.name"
									v-bind:textLength="100"
									v-bind:hasLabel="false"
								v-bind:placeholder="$t('chat.forms.group_editor.name_placeholder')">
								
										<template v-slot:feedbackInfo>
											<span v-html="$t('chat.forms.group_editor.name_helper')"></span>
										</template>
								</TextInput>
							</div>
							<template v-if="! editGroupData.is_draft">
								<div class="px-4 mb-8">
									<TextInput
										v-bind:asText="true"
										v-model.trim="formData.description"
										v-bind:textLength="240"
										v-bind:hasLabel="false"
									v-bind:placeholder="$t('chat.forms.group_editor.description_placeholder')">
											<template v-slot:feedbackInfo>
												<span v-html="$t('chat.forms.group_editor.description_helper')"></span>
											</template>
									</TextInput>
								</div>
							</template>
							<div class="px-4 mb-8">
								<SectionToggle
									iconName="globe-06"
									v-model.lazy="formData.visibility"
									v-bind:captionText="$t('chat.forms.group_editor.public_helper')"
								v-bind:titleText="$t('chat.forms.group_editor.public')"></SectionToggle>
							</div>
							<div class="px-4 flex items-center">
								<div class="ml-auto">
									<PrimaryPillButton v-bind:loading="state.isSubmitting" v-bind:disabled="submitButtonStatus" type="submit" v-bind:buttonText="editGroupData.is_draft ? $t('labels.continue') : $t('labels.save_changes')"></PrimaryPillButton>
								</div>
							</div>

							<input type="file" v-on:change="onAvatarSelect"  accept="image/*" ref="imageFileInput" class="hidden">
						</form>
					</template>
				</div>
			</div>
		</div>
	</div>
</template>

<script>
    import { defineComponent, reactive, ref, computed, onMounted } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useGroupStore } from '@D/store/chats/group.store.js';
	import { useRouter } from 'vue-router';

	import AvatarLarge from '@D/components/general/avatars/AvatarLarge.vue';
	import PageTitle from '@D/components/layout/PageTitle.vue';
	import TextInput from '@D/components/forms/TextInput.vue';
	import PrimaryPillButton from '@D/components/inter-ui/buttons/PrimaryPillButton.vue';
	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import GroupEditorSkeleton from '@D/views/messenger/children/group/parts/skeletons/GroupEditorSkeleton.vue';
	import ParticipantSkeleton from '@D/views/messenger/children/group/parts/skeletons/ParticipantSkeleton.vue';
	import SectionToggle from '@D/components/forms/SectionToggle.vue';
	import ProgressLineBar from '@D/components/inter-ui/progress/ProgressLineBar.vue';
	import ChatHeaderSkeleton from '@D/views/messenger/children/chat/parts/skeletons/ChatHeaderSkeleton.vue';

    export default defineComponent({
		props: {
			chat_id: {
				type: String,
				required: true
			}
		},
        setup: function (props) {
			const searchQuery = ref('');
			const groupStore = useGroupStore();
			const router = useRouter();
			const imageFileInput = ref(null);

			const editGroupData = computed(() => {
				return groupStore.editGroupData;
			});

			const state = reactive({
				isSubmitting: false,
				uploadProgress: 0,
				isLoading: true
			});

			const formData = reactive({
				chat_id: props.chat_id,
				name: '',
				visibility: true
			});

			onMounted(async () => {
				await groupStore.fetchEditGroup(props.chat_id);

				if(! editGroupData.value) {
					router.push({ name: 'error_404' });
				}
				else {
					formData.name = editGroupData.value.name;
					formData.description = editGroupData.value.description;
					formData.visibility = (editGroupData.value.visibility == 'public');

					state.isLoading = false;
				}
			});

            return {
				formData: formData,
				searchQuery: searchQuery,
				state: state,
				editGroupData: editGroupData,
				imageFileInput: imageFileInput,
				isDraftGroup: false,
				submitButtonStatus: computed(() => {
					return state.isSubmitting || ! formData.name.length;
				}),
				selectAvatar: () => {
					if(state.isSubmitting) {
						return false;
					};
					
					imageFileInput.value.click();
				},
				submitForm: async () => {
					state.isSubmitting = true;
					
					await colibriAPI().messenger().with(formData).sendTo('groups/update').then((response) => {
						router.push({
							name: 'messenger_group_show',
							params: {
								chat_id: props.chat_id
							}
						});
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						}
					});

					state.isSubmitting = false;
				},
				onAvatarSelect: (event) => {
					state.isSubmitting = true;

					const formData = new FormData();
					formData.append('avatar_file', event.target.files[0]);
					formData.append('chat_id', props.chat_id);

					colibriAPI().messenger().with(formData).withHeaders({
                    	'Content-Type': 'multipart/form-data'
					}).uploadProgress((progressEvent) => {
						state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
					}).sendTo('groups/avatar/update').then((response) => {
						editGroupData.value.avatar_url = response.data.data.avatar_url;
						
						state.uploadProgress = 0;
					}).catch((error) => {
						if(error.response) {
							toastError(error.response.data.message);
						} else {
							state.uploadProgress = 0;
						}
					});

					state.isSubmitting = false;
				}
			};
        },
		components: {
			AvatarLarge: AvatarLarge,
			PageTitle: PageTitle,
			TextInput: TextInput,
			ProgressLineBar: ProgressLineBar,
			SectionToggle: SectionToggle,
			PrimaryIconButton: PrimaryIconButton,
			PrimaryPillButton: PrimaryPillButton,
			GroupEditorSkeleton: GroupEditorSkeleton,
			ParticipantSkeleton: ParticipantSkeleton,
			ChatHeaderSkeleton: ChatHeaderSkeleton
		}
    });
</script>