<template>
    <div class="flex flex-col">
        <div class="px-4 pb-4 border-b border-b-bord-pr text-center">
            <SheetTitle v-bind:title="$t('labels.edit_profile')"></SheetTitle>
        </div>

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
            <div class="mb-4">
                <div v-if="state.uploadProgress" class="h-1 bg-fill-qt overflow-hidden">
                    <div class="h-full bg-green-900" v-bind:style="{ width: state.uploadProgress + '%' }"></div>
                </div>

                <div class="bg-fill-fv overflow-hidden relative h-36">
                    <img class="block h-full w-full object-cover" v-on:error="onCoverError" v-bind:src="coverSrc" alt="Cover">
                    <button v-on:click="$refs.coverInput.click()" class="cursor-pointer absolute top-2 right-2 bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
                        <SvgIcon name="pencil-02" type="line" classes="size-icon-small text-white/90"></SvgIcon>
                    </button>
                </div>

                <div class="px-4 -mt-[35px] z-20 relative">
                    <div class="relative rounded-full overflow-hidden size-large-avatar border-4 border-fill-pr bg-bg-pr">
                        <img class="w-full z-20 relative" v-bind:src="profileData.avatar_url" alt="Image">

                        <div class="absolute cursor-pointer inset-0 z-20 flex items-center justify-center">
                            <button v-on:click="$refs.avatarInput.click()" class="bg-black/50 hover:bg-black/30 smoothing size-10 inline-flex-center rounded-full">
                                <SvgIcon name="camera-01" type="line" classes="size-icon-normal text-white/90"></SvgIcon>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-4 pb-8 text-par-s text-lab-sc flex-1">
                <p class="mb-2" v-html="$t('settings.forms.avatar_settings.desc')"></p>
                <p class="mb-2">
                    {{ $t('settings.forms.avatar_settings.formats') }}
                </p>
                <Border height="h-px" bg="bg-bord-pr"></Border>
                <p class="mt-2">
                    {{ $t('settings.forms.avatar_settings.cover_resolution') }}
                </p>
            </div>

            <ActionSheetGroup>
                <RouterLink v-bind:to="{ name: 'settings_navigator' }">
                    <ActionSheetItem iconName="settings-04" v-bind:textLabel="$t('labels.account_settings')"></ActionSheetItem>
                </RouterLink>
                <Border></Border>
                <ActionSheetItem
                    v-on:click="$emit('close')"
                    iconName="x"
                    iconType="solid"
                v-bind:textLabel="$t('labels.close')"></ActionSheetItem>
            </ActionSheetGroup>
        </template>

        <div class="hidden">
            <input v-on:change="openCropper($event, 'avatar')" type="file" ref="avatarInput" accept="image/*">
            <input v-on:change="openCropper($event, 'cover')" type="file" ref="coverInput" accept="image/*">
        </div>
    </div>
</template>

<script>
    import { defineComponent, inject, reactive, ref, watch } from 'vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    import { useAuthStore } from '@M/store/auth/auth.store.js';
    import ImageCropper from '@/kernel/vue/components/media/image/ImageCropper.vue';

    import SheetTitle from '@M/components/general/sheets/SheetTitle.vue';
    import ActionSheetItem from '@M/components/general/sheets/ActionSheetItem.vue';
    import ActionSheetGroup from '@M/components/general/sheets/ActionSheetGroup.vue';

    export default defineComponent({
        emits: ['close'],
        setup: function() {
            const authStore = useAuthStore();
            const profileData = inject('profileData');
            const fallbackCoverUrl = config('user.default_cover', '');
            const coverSrc = ref(profileData.value.cover_url || fallbackCoverUrl);
            const state = reactive({
				uploadProgress: 0,
                cropper: {
                    isOpen: false,
                    mode: 'avatar',
                    file: null
                }
			});

            watch(() => {
                return profileData.value.cover_url;
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
                    profileData.value[dataKey] = response.data.data[dataKey];
                    closeCropper();
                }).catch((error) => {
                    if(error.response) {
                        alert(error.response.data.message);
                    }
                });

                state.uploadProgress = 0;
            };

            return {
                profileData: profileData,
                coverSrc: coverSrc,
                state: state,
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
            SheetTitle: SheetTitle,
            ActionSheetItem: ActionSheetItem,
            ActionSheetGroup: ActionSheetGroup,
            ImageCropper: ImageCropper
        }
    });
</script>
