<template>
	<Teleport to="body">
		<ContentModal v-on:close="$emit('close')">
			<ModalHeader v-bind:modalTitle="$t('labels.edit_profile')"></ModalHeader>
			<div class="overflow-hidden rounded-b-md">
				<div v-if="state.cropper.isOpen">
					<div v-if="state.uploadProgress" class="h-1 bg-fill-qt overflow-hidden">
						<div class="h-full bg-green-900" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
					</div>

					<ImageCropper
						v-bind:file="state.cropper.file"
						v-bind:mode="state.cropper.mode"
						v-bind:isSubmitting="state.uploadProgress > 0"
						v-on:cancel="closeCropper"
						v-on:save="uploadCroppedImage"
					></ImageCropper>
				</div>

				<template v-else>
					<div class="block mb-4">
						<div class="bg-fill-fv overflow-hidden relative h-[220px]">
							<img class="block h-full w-full object-cover" v-on:error="onCoverError" v-bind:src="coverSrc" alt="Cover">
							<button v-on:click="$refs.coverInput.click()" class="cursor-pointer absolute top-2 right-2 bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
								<SvgIcon name="pencil-02" type="line" classes="size-icon-small text-white/90"></SvgIcon>
							</button>
						</div>

						<div class="px-4 -mt-[56px] z-20 relative">
							<div class="relative rounded-full overflow-hidden size-large-avatar border-4 border-fill-pr bg-bg-pr">
								<img class="w-full z-20 relative" v-bind:src="userData.avatar_url" alt="Image">

								<div class="absolute cursor-pointer inset-0 z-20 flex items-center justify-center">
									<button v-on:click="$refs.avatarInput.click()" class="bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
										<SvgIcon name="camera-01" type="line" classes="size-icon-normal text-white/90"></SvgIcon>
									</button>
								</div>
							</div>
						</div>
					</div>
					<div class="px-4 pb-8 text-par-s text-lab-sc">
						<p class="mb-2" v-html="$t('settings.forms.avatar_settings.desc')"></p>
						<p class="mb-2">
							{{ $t('settings.forms.avatar_settings.formats') }}
						</p>
						<Border height="h-px" bg="bg-bord-pr"></Border>
						<p class="mt-2">
							{{ $t('settings.forms.avatar_settings.cover_resolution') }}
						</p>
					</div>
					
					<Border height="h-3" bg="bg-fill-qt" opacity="opacity-70"></Border>
					<RouterLink v-bind:to="{ name: 'settings_account' }">
						<ModalRowButton 
							v-bind:hasArrow="true"
							v-bind:buttonText="$t('labels.account_settings')"
						iconName="settings-01"></ModalRowButton>
					</RouterLink>
				</template>
			</div>

			<template v-slot:loadingSkeleton>
				<div class="flex justify-center py-20">
					<div class="colibri-primary-animation"></div>
				</div>
			</template>

			<div class="hidden">
				<input v-on:change="openCropper($event, 'avatar')" type="file" ref="avatarInput" accept="image/*">
				<input v-on:change="openCropper($event, 'cover')" type="file" ref="coverInput" accept="image/*">
			</div>
		</ContentModal>
	</Teleport>
</template>

<script>
	import { defineComponent, reactive, computed, ref, watch } from 'vue';
	import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
	import { useAuthStore } from '@D/store/auth/auth.store.js';
	import ImageCropper from '@/kernel/vue/components/media/image/ImageCropper.vue';

	import PrimaryIconButton from '@D/components/inter-ui/buttons/PrimaryIconButton.vue';
	import ModalRowButton from '@D/components/inter-ui/buttons/ModalRowButton.vue';
	import ContentModal from '@D/components/general/modals/ContentModal.vue';
	import ModalHeader from '@D/components/general/modals/parts/ModalHeader.vue'; 

	export default defineComponent({
		emits: ['close'],
		setup: function() {
			const state = reactive({
				isLoading: true,
				uploadProgress: 0,
				cropper: {
					isOpen: false,
					mode: 'avatar',
					file: null
				}
			});

			const authStore = useAuthStore();
			const fallbackCoverUrl = config('user.default_cover', '');
			const coverSrc = ref(authStore.userData.cover_url || fallbackCoverUrl);

			watch(() => {
				return authStore.userData.cover_url;
			}, (coverUrl) => {
				coverSrc.value = coverUrl || fallbackCoverUrl;
			});

			const closeCropper = () => {
				state.cropper.isOpen = false;
				state.cropper.mode = 'avatar';
				state.cropper.file = null;
			};

			const uploadProfileImage = async(file, mode) => {
				const formData = new FormData();
				const dataKey = (mode === 'cover') ? 'cover_url' : 'avatar_url';
				const endpoint = (mode === 'cover') ? 'account/cover/update' : 'account/avatar/update';

				formData.append(mode, file);

				await colibriAPI().userSettings().with(formData).uploadProgress((progressEvent) => {                    
					if(progressEvent.total) {
						state.uploadProgress = Math.round((progressEvent.loaded / progressEvent.total) * 100);
					}
				}).sendTo(endpoint).then((response) => {
					authStore.setProperty(dataKey, response.data.data[dataKey]);
					closeCropper();
				}).catch((error) => {
					if(error.response) {
						alert(error.response.data.message);
					}
				});

				state.uploadProgress = 0;
			};

			return {
				state: state,
				coverSrc: coverSrc,
				userData: computed(() => {
					return authStore.userData;
				}),
				onCoverError: function() {
					if(coverSrc.value !== fallbackCoverUrl) {
						coverSrc.value = fallbackCoverUrl;
					}
				},
				openCropper: (event, mode) => {
					event.preventDefault();

					const selectedFile = event.target.files[0];
					event.target.value = '';

					if(selectedFile) {
						state.cropper.mode = mode;
						state.cropper.file = selectedFile;
						state.cropper.isOpen = true;
					}
				},
				closeCropper: closeCropper,
				uploadCroppedImage: async(file) => {
					await uploadProfileImage(file, state.cropper.mode);
				}
			};
		},
		components: {
			ContentModal: ContentModal,
			ModalHeader: ModalHeader,
			ModalRowButton: ModalRowButton,
			PrimaryIconButton: PrimaryIconButton,
			ImageCropper: ImageCropper
		}
	});
</script>
