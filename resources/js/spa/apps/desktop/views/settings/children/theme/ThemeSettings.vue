<template>
    <div class="mb-8">
        <PageTitle v-bind:hasBack="true" v-bind:titleText="$t('settings.theme_settings')"></PageTitle>
    </div>

    <div class="mb-4">
        <h6 class="text-par-m text-lab-sc">
            {{ $t('settings.forms.theme.page_desc') }}
        </h6>
    </div>

    <div class="block mb-16">
        <RadioGroup v-model="defaultValue" v-on:update:modelValue="switchTheme" class="block divide-y divide-fill-pr overflow-hidden rounded-lg">
            <RadioGroupOption
                v-slot="{ checked }" 
                value="light"
            class="bg-input-pr px-4 py-4 cursor-pointer text-par-m">

                <div class="flex items-center">
                    <span class="inline-flex gap-3 items-center">
                        <span class="shrink-0">
                            <SvgIcon name="sun" type="solid" v-bind:classes="['size-icon-small', (checked ? 'text-brand-900' : 'text-lab-sc')].join(' ')"></SvgIcon>
                        </span>
                        <span v-bind:class="['text-par-s', (checked ? 'text-brand-900' : 'text-lab-sc')]">
                            {{ $t('settings.forms.theme.light') }}
                        </span>
                    </span>

                    <span class="ml-auto">
                        <SvgIcon v-if="checked" name="check-circle" type="solid" classes="size-icon-small text-brand-900"></SvgIcon>
                        <SvgIcon v-else name="placeholder" type="line" classes="size-icon-small text-lab-sc"></SvgIcon>
                    </span>
                </div>
            </RadioGroupOption>
            <RadioGroupOption
                v-slot="{ checked }" 
                value="dark"
            class="bg-input-pr px-4 py-4 cursor-pointer text-par-m">

                <div class="flex items-center">
                    <span class="inline-flex gap-3 items-center">
                        <span class="shrink-0">
                            <SvgIcon name="moon-02" type="solid" v-bind:classes="['size-icon-small', (checked ? 'text-brand-900' : 'text-lab-sc')].join(' ')"></SvgIcon>
                        </span>
                        <span v-bind:class="['text-par-s', (checked ? 'text-brand-900' : 'text-lab-sc')]">
                            {{ $t('settings.forms.theme.dark') }}
                        </span>
                    </span>

                    <span class="ml-auto">
                        <SvgIcon v-if="checked" name="check-circle" type="solid" classes="size-icon-small text-brand-900"></SvgIcon>
                        <SvgIcon v-else name="placeholder" type="line" classes="size-icon-small text-lab-sc"></SvgIcon>
                    </span>
                </div>
            </RadioGroupOption>
            <RadioGroupOption
                v-slot="{ checked }" 
                value="system"
            class="bg-input-pr px-4 py-4 cursor-pointer text-par-m">

                <div class="flex items-center">
                    <span class="inline-flex gap-3 items-center">
                        <span class="shrink-0">
                            <SvgIcon name="monitor-01" type="solid" v-bind:classes="['size-icon-small', (checked ? 'text-brand-900' : 'text-lab-sc')].join(' ')"></SvgIcon>
                        </span>
                        <span v-bind:class="['text-par-s', (checked ? 'text-brand-900' : 'text-lab-sc')]">
                            {{ $t('settings.forms.theme.system') }}
                        </span>
                    </span>

                    <span class="ml-auto">
                        <SvgIcon v-if="checked" name="check-circle" type="solid" classes="size-icon-small text-brand-900"></SvgIcon>
                        <SvgIcon v-else name="placeholder" type="line" classes="size-icon-small text-lab-sc"></SvgIcon>
                    </span>
                </div>
            </RadioGroupOption>
        </RadioGroup>
    </div>
</template>

<script>
    import { defineComponent, onMounted, ref } from 'vue';

    import { RadioGroup, RadioGroupOption } from '@headlessui/vue';
    import PageTitle from '@D/components/layout/PageTitle.vue';
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

    export default defineComponent({
        setup: function() {
            const currentTheme = ref(window.getThemePreference('light'));

            onMounted(() => {
                try {
                    localStorage.setItem('theme', currentTheme.value);
                } catch (error) {
                    //
                }
            });

            const getSystemThemeMode = () => {
                return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }

            return {
                defaultValue: currentTheme,
                switchTheme: async () => {
                    const preferenceTheme = currentTheme.value;
                    const resolvedTheme = preferenceTheme == 'system' ? getSystemThemeMode() : preferenceTheme;

                    try {
                        localStorage.setItem('theme', preferenceTheme);
                        localStorage.setItem('theme_runtime', resolvedTheme);
                    } catch (error) {
                        //
                    }

                    if(typeof window.writeThemePreferenceCookie == 'function') {
                        window.writeThemePreferenceCookie(preferenceTheme);
                    }

                    if(typeof window.writeThemeRuntimeCookie == 'function') {
                        window.writeThemeRuntimeCookie(resolvedTheme);
                    }
                    
                    colibriAPI().userSettings().with({
                        theme: preferenceTheme,
                        resolved_theme: resolvedTheme
                    }).putTo('account/theme/update').then(function(response) {
                        window.location.reload();
                    });
                }
            }
        },
        components: {
            PageTitle: PageTitle,
            RadioGroup: RadioGroup,
            RadioGroupOption: RadioGroupOption
        }
    });
</script>
