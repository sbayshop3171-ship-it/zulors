<template>
    <Toolbar v-on:close="$router.back()" v-bind:title="$t('settings.theme_settings')"></Toolbar>
    <SettingsDesc v-bind:text="$t('settings.forms.theme.page_desc')"></SettingsDesc>

    <div class="px-4">
        <RadioGroup v-model="defaultValue" v-on:update:modelValue="switchTheme" class="block divide-y divide-bord-tr overflow-hidden rounded-2xl">
            <RadioGroupOption
                v-slot="{ checked }" 
                value="light"
            class="bg-fill-fv px-4 py-4 cursor-pointer text-par-m">

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
            class="bg-fill-fv px-4 py-4 cursor-pointer text-par-m">

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
            class="bg-fill-fv px-4 py-4 cursor-pointer text-par-m">

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
    import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
    
    import Toolbar from '@M/components/layout/Toolbar.vue';
    import SettingsDesc from '@M/views/settings/parts/SettingsDesc.vue';

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
            Toolbar: Toolbar,
            SettingsDesc: SettingsDesc,
            RadioGroup: RadioGroup,
            RadioGroupOption: RadioGroupOption
        }
    });
</script>
