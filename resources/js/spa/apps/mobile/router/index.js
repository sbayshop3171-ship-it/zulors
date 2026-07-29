import { createRouter, createWebHistory } from 'vue-router';

import { Layouts } from '@M/core/constants/layouts.js';

const Router = createRouter({
	history: createWebHistory(),
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return { top: savedPosition.top };
        }
    },
	routes: [
        {
            path: '/wallet',
            component: function() {
                return import('@M/views/wallet/WalletIndex.vue');
            },
            alias: '/wallet',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'wallet_index',
        },
		{
			path: '/',
			component: () => {
				return import('@M/views/home/HomeIndex.vue');
			},
			alias: '/home',
            meta: {
                layout: Layouts.MAIN,
                auth: true
            },
            name: 'home_index'
		},
        {
			path: '/messenger',
			component: function() {
                return import('@M/views/messenger/MessengerIndex.vue');
            },
			alias: '/messenger',
            meta: {
                layout: Layouts.MESSENGER,
                auth: true
            },
            name: 'messenger_index',
            redirect: {
                name: 'messenger_inbox'
            },
            children: [
                {
                    path: 'inbox',
                    component: function() {
                        return import('@M/views/messenger/children/inbox/MessengerInbox.vue');
                    },
                    name: 'messenger_inbox'
                },
                {
                    path: 'c/:chat_id/info',
                    component: function() {
                        return import('@M/views/messenger/children/chat/ChatInfoPage.vue');
                    },
                    name: 'messenger_chat_info'
                },
                {
                    path: 'c/:chat_id',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_chat'
                },
                {
                    path: 'group/:chat_id/show',
                    component: function() {
                        return import('@M/views/messenger/children/group/MessengerGroup.vue');
                    },
                    name: 'messenger_group',
                    props: true,
                },
                {
                    path: 'groups',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_groups_page'
                },
                {
                    path: 'archived',
                    component: function() {
                        return import('@M/views/messenger/children/chat/MessengerChat.vue');
                    },
                    name: 'messenger_archived_page'
                }
            ]
		},
        {
			path: '/settings',
			component: function() {
                return import('@M/views/settings/SettingsIndex.vue');
            },
			alias: '/settings',
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true,
                hideNavbar: true
            },
            name: 'settings_index',
            redirect: {
                name: 'settings_navigator'
            },
            children: [
                {
                    path: 'nav',
                    component: function() {
                        return import('@M/views/settings/children/navigators/SettingsNav.vue');
                    },
                    name: 'settings_navigator'
                },
                {
                    path: 'blocked',
                    component: function() {
                        return import('@M/views/settings/children/blocked/BlockSettings.vue');
                    },
                    name: 'settings_blocked'
                },
                {
                    path: 'account-settings',
                    component: function() {
                        return import('@M/views/settings/children/account/AccountSettings.vue');
                    },
                    name: 'settings_account'
                },
                {
                    path: 'authorship',
                    component: function() {
                        return import('@M/views/settings/children/authorship/AuthorshipSettings.vue');
                    },
                    name: 'settings_authorship'
                },
                {
                    path: 'credentials',
                    component: function() {
                        return import('@M/views/settings/children/navigators/credentials/CredentialSettings.vue');
                    },
                    name: 'settings_credentials'
                },
                {
                    path: 'notifications',
                    component: function() {
                        return import('@M/views/settings/children/navigators/notifications/NotificationSettings.vue');
                    },
                    name: 'settings_notifications'
                },
                {
                    path: 'privacy',
                    component: function() {
                        return import('@M/views/settings/children/account_privacy/AccountPrivacy.vue');
                    },
                    name: 'settings_privacy'
                },
                {
                    path: 'push-notifications',
                    component: function() {
                        return import('@M/views/settings/children/push_notifications/PushNotifications.vue');
                    },
                    name: 'settings_push_notifications'
                },
                {
                    path: 'email-notifications',
                    component: function() {
                        return import('@M/views/settings/children/email_notifications/EmailNotifications.vue');
                    },
                    name: 'settings_email_notifications'
                },
                {
                    path: 'language',
                    component: function() {
                        return import('@M/views/settings/children/language/LanguageSettings.vue');
                    },
                    name: 'settings_language'
                },
                {
                    path: 'social-media',
                    component: function() {
                        return import('@M/views/settings/children/social_media/SocialMediaSettings.vue');
                    },
                    name: 'settings_social_media'
                },
                {
                    path: 'theme',
                    component: function() {
                        return import('@M/views/settings/children/theme/ThemeSettings.vue');
                    },
                    name: 'settings_theme'
                },
                {
                    path: 'personal-info',
                    component: function() {
                        return import('@M/views/settings/children/navigators/personal_info/PersonalInfoSettings.vue');
                    },
                    name: 'settings_personal_info'
                },
                {
                    path: 'verification',
                    component: function() {
                        return import('@M/views/settings/children/verification/Verification.vue');
                    },
                    name: 'settings_verification'
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
                },
                {
                    path: 'sessions',
                    component: function() {
                        return import('@M/views/settings/children/sessions/SessionsSettings.vue');
                    },
                    name: 'settings_sessions'
                },
                {
                    path: 'password',
                    component: function() {
                        return import('@M/views/settings/children/password/PasswordSettings.vue');
                    },
                    name: 'settings_password'
                },
                {
                    path: 'email',
                    component: function() {
                        return import('@M/views/settings/children/email/EmailSettings.vue');
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
                        return import('@M/views/settings/children/phone/PhoneSettings.vue');
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
                    path: 'birthdate',
                    component: function() {
                        return import('@M/views/settings/children/birthdate/BirthdateSettings.vue');
                    },
                    name: 'settings_birthdate'
                },
                {
                    path: 'city',
                    component: function() {
                        return import('@M/views/settings/children/city/CitySettings.vue');
                    },
                    name: 'settings_city'
                },
                {
                    path: 'country',
                    component: function() {
                        return import('@M/views/settings/children/country/CountrySettings.vue');
                    },
                    name: 'settings_country'
                },
                {
                    path: 'actions',
                    component: function() {
                        return import('@M/views/settings/children/actions/ActionSettings.vue');
                    },
                    name: 'settings_actions'
                },
            ]
		},
		{
			path: '/publication/:hash_id',
			component: function() {
                return import('@M/views/publication/PublicationIndex.vue');
            },
            props: true,
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'publication_index'
		},
        {
			path: '/@:id([a-zA-Z0-9._]+)',
			component: function() {
                return import('@M/views/profile/ProfileIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'profile_index',
            props: true,
            redirect: { name: 'profile_posts' },
            children: [
                {
                    path: 'posts:?',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfilePosts.vue');
                    },
                    name: 'profile_posts'
                },
                {
                    path: 'media',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfileMedia.vue');
                    },
                    name: 'profile_media'
                },
                {
                    path: 'info',
                    component: function() {
                        return import('@M/views/profile/parts/tabs/ProfileInfo.vue');
                    },
                    name: 'profile_info'
                },
            ]
		},
        {
            path: '/new/post',
            name: 'post_editor',
            component: function() {
                return import('@M/views/editors/post/PostEditor.vue');
            },
            meta: {
                layout: Layouts.POST_EDITOR,
            }
        },
        {
            path: '/new/story',
            name: 'story_editor',
            component: function() {
                return import('@M/views/editors/stories/StoriesEditor.vue');
            },
            meta: {
                layout: Layouts.FLAT,
            }
        },
        {
            path: '/stories/:story_uuid',
            component: function() {
                return import('@M/views/stories/StoriesIndex.vue');
            },
            name: 'stories_index',
            meta: {
                layout: Layouts.FLAT,
                auth: true
            },
            props: true
        },
        {
			path: '/explore',
            name: 'explore_index',
			component: function() {
                return import('@M/views/explore/ExploreIndex.vue');
            },
            redirect: {
                name: 'explore_posts'
            },
            props: true,
            children: [
                {
                    path: 'posts',
                    component: function() {
                        return import('@M/views/explore/children/posts/ExplorePosts.vue');
                    },
                    name: 'explore_posts'
                },
                {
                    path: 'people',
                    component: function() {
                        return import('@M/views/explore/children/people/ExplorePeople.vue');
                    },
                    name: 'explore_people'
                }
            ],
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            }
		},
        {
            path: '/marketplace',
            component: function() {
                return import('@M/views/marketplace/MarketplaceIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'marketplace_index'
        },
        {
            path: '/marketplace/product/:product_id',
            component: function() {
                return import('@M/views/marketplace/ProductShow.vue');
            },
            props: true,
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'marketplace_show'
        },
        {
            path: '/jobs',
            component: function() {
                return import('@M/views/jobs/JobsIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'jobs_index'
        },
        {
            path: '/jobs/:job_id',
            component: function() {
                return import('@M/views/jobs/JobShow.vue');
            },
            props: true,
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true
            },
            name: 'jobs_show'
        },
        {
			path: '/bookmarks',
			component: function() {
                return import('@M/views/bookmarks/BookmarksIndex.vue');
            },
            meta: {
                layout: Layouts.MAIN,
                auth: true,
                hideHeader: true,
            },
            name: 'bookmarks_index'
		},
        {
            path: '/bootstrap-error',
            name: 'bootstrap_error',
            component: function() {
                return import('@M/views/errors/bootstrap/BootstrapError.vue');
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
                return import('@M/views/errors/err404/Error404.vue');
            },
			meta: {
                layout: Layouts.FLAT,
                auth: true
            }
        },
	]
});

export default Router;
