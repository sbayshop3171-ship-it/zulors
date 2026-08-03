import { createRouter, createWebHistory } from 'vue-router';
import { Layouts } from '@D/core/constants/layouts.js';

const Router = createRouter({
	history: createWebHistory(),
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return { top: savedPosition.top };
        }
    },
	routes: [
        {
			path: '/bookmarks',
			component: function() {
                return import('@D/views/bookmarks/BookmarksIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
            },
            name: 'bookmarks_index'
		},
        {
			path: '/',
			component: function() {
				return import('@D/views/home/HomeIndex.vue');
			},
			alias: '/home',
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'home_index'
		},
        {
			path: '/jobs',
			component: function () {
                return import('@D/views/jobs/children/board/JobsIndex.vue');
            },
			alias: '/jobs',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'jobs',
            },
            name: 'jobs_index'
		},
        {
            path: '/saved/jobs',
            component: function() {
                return import('@D/views/jobs/children/saved/JobBookmarks.vue');
            },
            name: 'saved_jobs',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'jobs',
            }
        },
        {
            path: '/jobs/:job_id',
            component: function() {
                return import('@D/views/jobs/children/job/JobShow.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'jobs',
            },
            name: 'jobs_show',
            props: true
        },
        {
			path: '/marketplace',
			component: function() {
                return import('@D/views/marketplace/children/catalog/MarketplaceIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'marketplace'
            },
            name: 'marketplace_index',
		},
        {
            props: true,
			path: '/marketplace/product/:product_id',
			component: function() {
                return import('@D/views/marketplace/children/product/ProductShow.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'marketplace'
            },
            name: 'marketplace_show',
		},
        {
            path: '/saved/products',
            component: function() {
                return import('@D/views/marketplace/children/saved/ProductBookmarks.vue');
            },
            name: 'saved_products',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'marketplace',
            }
        },
        {
			path: '/messenger',
			component: function() {
                return import('@D/views/messenger/MessengerIndex.vue');
            },
			alias: '/messenger',
            meta: {
                sectionName: 'messenger',
                layout: Layouts.MESSENGER,
                auth: true,
                fluidLayout: true
            },
            name: 'messenger_index',
            redirect: {
                name: 'messenger_inbox'
            },
            children: [
                {
                    path: 'archive',
                    component: function() {
                        return import('@D/views/messenger/children/archive/MessengerArchive.vue');
                    },
                    name: 'messenger_archive',
                    meta: {
                        archive: true
                    }
                },
                {
                    path: 'inbox',
                    component: function() {
                        return import('@D/views/messenger/children/inbox/MessengerInbox.vue');
                    },
                    name: 'messenger_inbox',
                },
                {
                    path: 'group/create',
                    component: function() {
                        return import('@D/views/messenger/children/group/MessengerGroupCreate.vue');
                    },
                    name: 'messenger_group_create',
                },
                {
                    path: 'group/:chat_id/edit',
                    component: function() {
                        return import('@D/views/messenger/children/group/MessengerGroupEdit.vue');
                    },
                    name: 'messenger_group_edit',
                    props: true,
                },
                {
                    path: 'group/:chat_id/show',
                    component: function() {
                        return import('@D/views/messenger/children/group/MessengerGroupShow.vue');
                    },
                    name: 'messenger_group_show',
                    props: true,
                },
                {
                    path: 'c/:chat_id/info',
                    component: function() {
                        return import('@D/views/messenger/children/chat/ChatInfoPage.vue');
                    },
                    name: 'messenger_chat_info'
                },
                {
                    path: 'c/:chat_id',
                    component: function() {
                        return import('@D/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_chat'
                }
            ]
		},
        {
			path: '/settings',
			component: function() {
                return import('@D/views/settings/SettingsIndex.vue');
            },
			alias: '/settings',
            meta: {
                sectionName: 'settings',
                layout: Layouts.FLAT,
                auth: true,
                fluidLayout: true
            },
            name: 'settings_index',
            redirect: {
                name: 'settings_account'
            },
            children: [
                {
                    path: 'account-settings',
                    component: function() {
                        return import('@D/views/settings/children/account/AccountSettings.vue');
                    },
                    name: 'settings_account'
                },
                {
                    path: 'authorship',
                    component: function() {
                        return import('@D/views/settings/children/authorship/AuthorshipSettings.vue');
                    },
                    name: 'settings_authorship'
                },
                {
                    path: 'credentials',
                    component: function() {
                        return import('@D/views/settings/children/navigators/credentials/CredentialSettings.vue');
                    },
                    name: 'settings_credentials'
                },
                {
                    path: 'email',
                    component: function() {
                        return import('@D/views/settings/children/email/EmailSettings.vue');
                    },
                    name: 'settings_email'
                },
                {
                    path: 'confirm-email',
                    component: function() {
                        return import('@D/views/settings/children/confirm_email/EmailConfirmation.vue');
                    },
                    name: 'settings_email_confirm'
                },
                {
                    path: 'phone',
                    component: function() {
                        return import('@D/views/settings/children/phone/PhoneSettings.vue');
                    },
                    name: 'settings_phone'
                },
                {
                    path: 'confirm-phone',
                    component: function() {
                        return import('@D/views/settings/children/confirm_phone/PhoneConfirmation.vue');
                    },
                    name: 'settings_phone_confirm'
                },
                {
                    path: 'password',
                    component: function() {
                        return import('@D/views/settings/children/password/PasswordSettings.vue');
                    },
                    name: 'settings_password'
                },
                {
                    path: 'notifications',
                    component: function() {
                        return import('@D/views/settings/children/navigators/notifications/NotificationSettings.vue');
                    },
                    name: 'settings_notifications'
                },
                {
                    path: 'push-notifications',
                    component: function() {
                        return import('@D/views/settings/children/push_notifications/PushNotifications.vue');
                    },
                    name: 'settings_push_notifications'
                },
                {
                    path: 'email-notifications',
                    component: function() {
                        return import('@D/views/settings/children/email_notifications/EmailNotifications.vue');
                    },
                    name: 'settings_email_notifications'
                },
                {
                    path: 'privacy',
                    component: function() {
                        return import('@D/views/settings/children/account_privacy/AccountPrivacy.vue');
                    },
                    name: 'settings_privacy'
                },
                {
                    path: 'language',
                    component: function() {
                        return import('@D/views/settings/children/language/LanguageSettings.vue');
                    },
                    name: 'settings_language'
                },
                {
                    path: 'social-media',
                    component: function() {
                        return import('@D/views/settings/children/social_media/SocialMediaSettings.vue');
                    },
                    name: 'settings_social_media'
                },
                {
                    path: 'theme',
                    meta: {
                        feature: 'dark_theme',
                    },
                    component: function() {
                        return import('@D/views/settings/children/theme/ThemeSettings.vue');
                    },
                    name: 'settings_theme'
                },
                {
                    path: 'personal-info',
                    component: function() {
                        return import('@D/views/settings/children/navigators/personal_info/PersonalInfoSettings.vue');
                    },
                    name: 'settings_personal_info'
                },
                {
                    path: 'birthdate',
                    component: function() {
                        return import('@D/views/settings/children/birthdate/BirthdateSettings.vue');
                    },
                    name: 'settings_birthdate'
                },
                {
                    path: 'city',
                    component: function() {
                        return import('@D/views/settings/children/city/CitySettings.vue');
                    },
                    name: 'settings_city'
                },
                {
                    path: 'country',
                    component: function() {
                        return import('@D/views/settings/children/country/CountrySettings.vue');
                    },
                    name: 'settings_country'
                },
                {
                    path: 'verification',
                    component: function() {
                        return import('@D/views/settings/children/verification/Verification.vue');
                    },
                    name: 'settings_verification'
                },
                {
                    path: 'sessions',
                    component: function() {
                        return import('@D/views/settings/children/sessions/SessionsSettings.vue');
                    },
                    name: 'settings_sessions'
                },
                {
                    path: 'blocked',
                    component: function() {
                        return import('@D/views/settings/children/blocked/BlockSettings.vue');
                    },
                    name: 'settings_blocked'
                },
                {
                    path: 'actions',
                    component: function() {
                        return import('@D/views/settings/children/actions/ActionSettings.vue');
                    },
                    name: 'settings_actions'
                },
                {
                    path: 'hotkeys',
                    component: function() {
                        return import('@D/views/settings/children/hotkeys/HotkeySettings.vue');
                    },
                    name: 'settings_hotkey'
                },
                {
                    path: 'api',
                    component: function() {
                        return import('@D/views/settings/children/api/ApiSettings.vue');
                    },
                    name: 'settings_api'
                }
            ]
		},
        {
			path: '/publication/:hash_id',
			component: function() {
                return import('@D/views/publication/PublicationIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'publication_index'
		},
        {
			path: '/about-author',
			component: function() {
                return import('@D/views/mtl/MTLIndex.vue');
            },
            meta: {
                layout: Layouts.INFO,
                auth: false
            },
            name: 'mtl_index'
		},
        {
			path: '/live-stream',
			component: function() {
                return import('@D/views/live/LiveIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'live_index'
		},
        {
			path: '/explore',
            name: 'explore_index',
			component: function() {
                return import('@D/views/explore/ExploreIndex.vue');
            },
            redirect: {
                name: 'explore_posts'
            },
            props: true,
            children: [
                {
                    path: 'posts',
                    component: function() {
                        return import('@D/views/explore/children/posts/ExplorePosts.vue');
                    },
                    name: 'explore_posts'
                },
                {
                    path: 'reels/:hash_id?',
                    component: function() {
                        return import('@D/views/explore/children/reels/ExploreReels.vue');
                    },
                    name: 'explore_reels',
                    props: true
                },
                {
                    path: 'people',
                    component: function() {
                        return import('@D/views/explore/children/people/ExplorePeople.vue');
                    },
                    name: 'explore_people'
                }
            ],
            meta: {
                layout: Layouts.MAIN,
                auth: true
            }
		},
        {
            path: '/wallet',
            component: function() {
                return import('@D/views/wallet/WalletIndex.vue');
            },
            alias: '/wallet',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                feature: 'wallet'
            },
            name: 'wallet_index',
        },
        {
            path: '/stories/:story_uuid',
            component: function() {
                return import('@D/views/stories/StoriesIndex.vue');
            },
            name: 'stories_index',
            meta: {
                layout: Layouts.STORIES,
                auth: true
            },
            props: true
        },
        {
			path: '/@:id([a-zA-Z0-9._]+)',
			component: function() {
                return import('@D/views/profile/ProfileIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'profile_index',
            props: true,
            redirect: (to) => {
                return {
                    name: 'profile_posts',
                    params: to.params
                };
            },
            children: [
                {
                    path: 'posts:?',
                    component: function() {
                        return import('@D/views/profile/parts/tabs/ProfilePosts.vue');
                    },
                    name: 'profile_posts'
                },
                {
                    path: 'media',
                    component: function() {
                        return import('@D/views/profile/parts/tabs/ProfileMedia.vue');
                    },
                    name: 'profile_media'
                },
                {
                    path: 'info',
                    component: function() {
                        return import('@D/views/profile/parts/tabs/ProfileInfo.vue');
                    },
                    name: 'profile_info'
                },
            ]
		},
        {
            path: '/bootstrap-error',
            name: 'bootstrap_error',
            component: function() {
                return import('@D/views/errors/bootstrap/BootstrapErrorIndex.vue');
            },
            meta: {
                layout: Layouts.FLAT,
                auth: false
            }
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'error_404',
            component: function() {
                return import('@D/views/errors/err404/Error404Index.vue');
            },
			meta: {
                layout: Layouts.MAIN,
                auth: true
            }
        },
	],
	linkActiveClass: 'active',
	linkExactActiveClass: 'active',
});

Router.beforeEach((to, from) => {
    let feature = to.meta.feature || null;

    if(feature) {
        if(! config(`features.${feature}.enabled`)) {
            return { name: 'error_404' };
        }
    }
});

export default Router;
