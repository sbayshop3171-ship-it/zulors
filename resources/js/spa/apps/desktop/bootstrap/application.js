import '@D/bootstrap/initialization/index.js';

import { createApp, defineAsyncComponent } from 'vue';
import { createI18n } from 'vue-i18n';
import { createPinia } from 'pinia';
import { postDeleteListener } from '@/kernel/vue/plugins/pinia/post/delete-listener.js';

import outsideClickDirective from '@/kernel/vue/directives/click.outside.js';

import Router from '@D/router/index.js';
import LanguageMessages from '@/lang/index.js';
import { deferStartupTask, markStartupEvent } from '@/kernel/services/startup/index.js';

import ZulorsDesktop from '@D/bootstrap/boot/ZulorsDesktop.vue';
import PrimeVue from 'primevue/config';
import globalProperties from '@/kernel/vue/plugins/global.properties.js';

const Application = createApp(ZulorsDesktop);

function humanizeI18nKey(key) {
    return String(key)
        .split('.')
        .pop()
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (letter) => {
            return letter.toUpperCase();
        });
}

function normalizeI18nOutput(value, key) {
    if(typeof value !== 'string') {
        return value;
    }

    const output = value.trim();
    const rawKeyPattern = /^[a-z][a-z0-9_]*(\.[a-zA-Z0-9_]+)+$/;

    if((key && output === key) || rawKeyPattern.test(output)) {
        return humanizeI18nKey(output);
    }

    return value;
}

function initializeI18n() {
    const messages = LanguageMessages.startupMessages();
    const locale = LanguageMessages.langLocale || 'en';

    return createI18n({
        locale: locale,
        warnHtmlInMessage: false,
        warnHtmlMessage: false,
        legacy: false,
        globalInjection: true,
        fallbackLocale: 'en',
        missingWarn: false,
        fallbackWarn: false,
        missing: (locale, key) => {
            return humanizeI18nKey(key);
        },
        postTranslation: (value, key) => {
            return normalizeI18nOutput(value, key);
        },
        messages: {
            en: messages || {},
            [locale]: messages || {}
        }
    });
}

markStartupEvent('application_script_loaded', {
    variant: 'desktop'
});

const ZulorsI18n = initializeI18n();
const rawTranslate = ZulorsI18n.global.t.bind(ZulorsI18n.global);
const translate = (...args) => {
    return normalizeI18nOutput(rawTranslate(...args), args[0]);
};

window.__t = translate;

const PiniaInstance = createPinia();

PiniaInstance.use(postDeleteListener);

Application.use(PiniaInstance);

Application.directive('outside-click', outsideClickDirective);
Application.use(globalProperties);
Application.use(Router);
Application.use(PrimeVue, {
    unstyled: true
});

Application.use(ZulorsI18n);
Application.config.globalProperties.$t = translate;

Application.component('Border', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/Border.vue");
}));

Application.component('VerificationBadge', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/badges/VerificationBadge.vue");
}));

Application.component('SvgIcon', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/icons/SvgIcon.vue");
}));

Application.component('TimeAgo', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/general/date-time/TimeAgo.vue");
}));


Application.component('FileFormatIcon', defineAsyncComponent(() => {
    return import("@/kernel/vue/components/icons/FileFormatIcon.vue");
}));

Application.component('PrimaryTransition', defineAsyncComponent(() => {
    return import("@D/components/general/transitions/PrimaryTransition.vue");
}));

Application.component('PrimaryDotsAnimation', defineAsyncComponent(() => {
    return import("@D/components/general/animations/PrimaryDotsAnimation.vue");
}));

Application.component('PrimarySpinAnimation', defineAsyncComponent(() => {
    return import("@D/components/general/animations/PrimarySpinAnimation.vue");
}));

Application.mount("#zulors-desktop-app");
markStartupEvent('vue_mounted', {
    variant: 'desktop'
});

Router.isReady().then(() => {
    markStartupEvent('router_ready', {
        variant: 'desktop',
        route: Router.currentRoute.value?.name ?? null
    });
});

deferStartupTask(() => {
    LanguageMessages.hydrateI18n(ZulorsI18n)
        .then((meta) => {
            markStartupEvent('translations_hydrated', {
                variant: 'desktop',
                source: meta?.source ?? 'unknown',
                serverTiming: meta?.serverTiming ?? null,
                cacheHeader: meta?.cacheHeader ?? null
            });
        })
        .catch(() => {
            //
        });
}, 120);
