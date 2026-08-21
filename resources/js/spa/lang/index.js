import { colibriAPI } from '@/kernel/services/api-client/native/index.js';
import { readCacheEntry, writeCache } from '@/kernel/services/cache/index.js';

const fallbackMessages = {
    labels: {
        hi_there: 'Hi, there 👋',
        one_moment: 'Just a moment',
        home: 'Home',
        explore: 'Explore',
        reels: 'Reels',
        notifications: 'Notifications',
        messages: 'Messages',
        marketplace: 'Marketplace',
        jobs: 'Jobs',
        my_profile: 'My profile',
        more: 'More',
        hello_user: 'Hello, {name}',
        new_story: 'New story',
        business_account: 'Business account',
        theme: 'Theme',
        help_center: 'Help center',
        privacy_policy: 'Privacy policy',
        cookies_policy: 'Cookies policy',
        terms: 'Terms',
        language: 'Language',
        translate_to: 'Translate to English',
        leave_comment: 'Leave a comment',
        edit_profile: 'Edit profile',
        posts: 'Posts',
        media: 'Media',
        info: 'Info',
        followers_count: 'Followers',
        following_count: 'Following',
        posts_count: 'Posts',
        image: 'Image',
        video: 'Video',
        file: 'File',
        location: 'Location',
        open: 'Open',
        joined_at_date: 'Joined at {date}',
        learn_more: 'Learn more',
        ai_generated: 'AI generated',
        image_message: 'Image message',
        document: 'Document',
        audio: 'Audio',
        wallet: 'Wallet',
        campaign: 'Campaign',
        overview: 'Overview',
        account_settings: 'Account settings',
        about_account: 'About account',
        logout: 'Logout',
        search: 'Search',
        for_you: 'For you',
        filters: 'Filters',
        view_profile: 'View profile',
        message: 'Message',
        send_message: 'Send message',
        follow_button: 'Follow',
        unfollow_button: 'Unfollow',
        follow_requested_button: 'Requested',
        follow_accepted_button: 'Following',
        follow_accept_button: 'Accept',
        more_suggestions: 'More suggestions',
        follow_recommendations: 'Who to follow',
        copy_link: 'Copy link',
        close: 'Close',
        continue: 'Continue',
        provider: 'Provider',
        information: 'Information',
        download: 'Download',
        loading: 'Loading',
        something_went_wrong: 'Something went wrong',
        share_post: 'Share post',
        new_posts: 'New posts',
        reply: 'Reply',
        today: 'Today',
        yesterday: 'Yesterday',
        online: 'Online',
        active_now: 'Active now',
        active_minutes_ago: 'Active {minutes}m ago',
        last_seen_ago: 'Last seen {time}',
        was_online_at: 'Was online at {time}',
        people: 'People',
        group: 'Group',
        privacy: 'Privacy',
        security: 'Security',
        save_changes: 'Save changes',
        edited: 'Edited',
        description: 'Description',
        condition: 'Condition',
        address: 'Address',
        currency: 'Currency',
        withdrawal: 'Withdrawal',
        bookmarks: 'Bookmarks',
        business_account: 'Business account',
        return_home: 'Return home',
        comment_number: '{count} comments',
        comment_was_deleted: 'This comment was deleted',
        profile_unavailable: 'Profile unavailable',
        profile_private_info_hiding: 'This information is private',
        ai_generated: 'AI generated'
    },
    chat: {
        no_messages_found: 'No messages yet',
        delete_message_for_all: 'Delete for everyone',
        message_text_copied: 'Message copied'
    },
    toast: {
        chat: {
            message_text_copied: 'Message copied'
        },
        post: {
            updated: 'Post updated',
            reel_hidden: 'Reel hidden',
            show_fewer_reels: "We'll show fewer reels like this"
        }
    },
    dd: {
        post: {
            edit_post: 'Edit post',
            not_interested: 'Not interested',
            hide_reel: 'Hide this reel'
        }
    },
    prompt: {
        delete_message: {
            title: 'Delete message?',
            description: 'This message will be deleted from the chat.'
        },
        delete_message_for_me: {
            description: 'This message will be deleted for you.',
            confirm: 'Delete for me'
        }
    },
    create_labels: {
        post: 'Post',
        story: 'Story',
        product: 'Product',
        job: 'Job',
        campaign: 'Campaign'
    },
    editor: {
        new_post: 'New post',
        edit_post: 'Edit post',
        publish: 'Publish',
        post_text_input_placeholder: "Hello, what's new?",
        edit_post_text_input_placeholder: 'Edit your post text',
        post_poll_input_placeholder: 'Add poll option',
        post_privacy: 'Anyone can see this post, comment, quote and react.',
        post_author_note: 'Write as yourself'
    }
};

const rawKeyPattern = /^[a-z][a-z0-9_]*(\.[a-zA-Z0-9_]+)+$/;
const translationsCacheTtl = 1000 * 60 * 60 * 24;
const translationsCacheVersion = 'v2';
const backendEmbeds = () => {
    return typeof BackendEmbeds === 'object' && BackendEmbeds !== null ? BackendEmbeds : {};
};
const translationsCacheKey = (locale) => {
    return `zulors.translations.${locale}.${translationsCacheVersion}`;
};

const normalizeMessages = (messages) => {
    return (messages && typeof messages === 'object' && !Array.isArray(messages)) ? messages : {};
};

const hasMessages = (messages) => {
    return Object.keys(normalizeMessages(messages)).length > 0;
};

const readCachedMessages = (locale) => {
    return readCacheEntry(translationsCacheKey(locale), translationsCacheTtl)?.data ?? null;
};

const writeCachedMessages = (locale, messages) => {
    if (locale && hasMessages(messages)) {
        writeCache(translationsCacheKey(locale), messages);
    }
};

const deepMerge = (...sources) => {
    return sources.reduce((mergedMessages, sourceMessages) => {
        Object.entries(normalizeMessages(sourceMessages)).forEach(([key, value]) => {
            if(value && typeof value === 'object' && !Array.isArray(value)) {
                mergedMessages[key] = deepMerge(mergedMessages[key], value);
            }
            else if(typeof value === 'string' && rawKeyPattern.test(value.trim())) {
                return;
            }
            else {
                mergedMessages[key] = value;
            }
        });

        return mergedMessages;
    }, {});
};

export default {
    langLocale: backendEmbeds().locale || 'en',
    startupMessages: function() {
        return deepMerge(
            fallbackMessages,
            normalizeMessages(backendEmbeds().startup_translations)
        );
    },
    embeddedMessages: function() {
        return deepMerge(
            this.startupMessages(),
            normalizeMessages(backendEmbeds().translations)
        );
    },
    messages: async function () {
        const locale = this.langLocale || 'en';
        const cachedMessages = readCachedMessages(locale);

        if (hasMessages(cachedMessages)) {
            return deepMerge(this.embeddedMessages(), cachedMessages);
        }

        try {
            const response = await this.fetchMergedMessages(locale);

            return response.messages;
        } catch (error) {
            console.error(`Could not load messages for locale: ${locale}`, error);

            return this.embeddedMessages();
        }
    },
    fetchMergedMessages: async function(locale = this.langLocale || 'en') {
        const fetchMessages = async (targetLocale) => {
            return await colibriAPI().translations().params({
                locale: targetLocale
            }).getFrom('app');
        };

        const englishResponse = await fetchMessages('en');
        const localeResponse = locale !== 'en' ? await fetchMessages(locale) : englishResponse;
        const englishMessages = normalizeMessages(englishResponse?.data?.data ?? {});
        const localeMessages = normalizeMessages(localeResponse?.data?.data ?? {});
        const messages = deepMerge(
            fallbackMessages,
            englishMessages,
            localeMessages,
            normalizeMessages(backendEmbeds().translations)
        );

        writeCachedMessages(locale, messages);

        return {
            messages: messages,
            serverTiming: localeResponse?.headers?.['server-timing'] ?? englishResponse?.headers?.['server-timing'] ?? null,
            cacheHeader: localeResponse?.headers?.['x-zulors-translations-cache'] ?? englishResponse?.headers?.['x-zulors-translations-cache'] ?? null
        };
    },
    hydrateI18n: async function(i18n) {
        if (!i18n?.global) {
            return null;
        }

        const locale = this.langLocale || 'en';
        let activeSource = 'startup';
        const applyMessages = (messages) => {
            const mergedMessages = deepMerge(fallbackMessages, messages);

            i18n.global.setLocaleMessage('en', mergedMessages);
            i18n.global.setLocaleMessage(locale, mergedMessages);
        };

        const embeddedMessages = normalizeMessages(backendEmbeds().translations);

        if (hasMessages(embeddedMessages)) {
            applyMessages(deepMerge(this.startupMessages(), embeddedMessages));
            activeSource = 'embedded';
        }

        const cachedMessages = readCachedMessages(locale);

        if (hasMessages(cachedMessages)) {
            applyMessages(deepMerge(this.startupMessages(), embeddedMessages, cachedMessages));
            activeSource = 'cache';
        }

        try {
            const response = await this.fetchMergedMessages(locale);

            applyMessages(response.messages);

            return {
                source: 'network',
                serverTiming: response.serverTiming,
                cacheHeader: response.cacheHeader
            };
        } catch (error) {
            console.error(`Could not hydrate messages for locale: ${locale}`, error);

            return {
                source: activeSource,
                serverTiming: null,
                cacheHeader: null
            };
        }
    }
}
