<template>
    <div class="ad-reel-overlay pointer-events-none absolute inset-x-0 bottom-0 z-30 text-white">
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/95 via-black/50 to-transparent"></div>
        <div class="ad-reel-overlay-content relative min-w-0 space-y-2.5 px-4 pb-4 pt-12">
            <a v-if="adData.cta_enabled && adData.click_url" class="ad-reel-cta pointer-events-auto flex min-h-12 w-full items-center justify-between gap-3 rounded-xl bg-brand-900 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-black/25" v-bind:href="adData.click_url" target="_blank" rel="noopener" v-on:click.stop>
                <span class="min-w-0 truncate">{{ adData.cta_text }}</span>
                <span aria-hidden="true" class="shrink-0 text-xl leading-none">›</span>
            </a>
            <div class="flex min-w-0 items-center gap-2">
                <RouterLink v-if="advertiser.username" class="pointer-events-auto flex min-w-0 items-center gap-2" v-bind:to="{ name: 'profile_index', params: { id: advertiser.username } }" v-on:click.stop>
                    <img v-if="advertiser.avatar_url" class="size-8 shrink-0 rounded-full object-cover ring-1 ring-white/30" v-bind:src="advertiser.avatar_url" v-bind:alt="advertiser.name">
                    <span class="truncate text-sm font-semibold">{{ advertiser.name || advertiser.username }}</span>
                    <span v-if="advertiser.verified" aria-label="Verified" class="shrink-0 text-blue-400">●</span>
                </RouterLink>
                <span v-else class="truncate text-sm font-semibold">{{ advertiser.name }}</span>
                <button v-if="canFollow" type="button" class="pointer-events-auto shrink-0 rounded-lg border border-white/70 px-2.5 py-1 text-xs font-semibold text-white disabled:opacity-60" v-bind:disabled="followLoading" v-on:click.stop="toggleFollow">{{ followLabel }}</button>
            </div>
            <div v-if="caption" class="min-w-0 text-xs leading-5 text-white/90">
                <p v-bind:class="expanded ? 'max-h-28 overflow-y-auto' : 'line-clamp-2'">{{ caption }}</p>
                <button v-if="caption.length > 90" type="button" class="pointer-events-auto font-semibold text-white" v-on:click.stop="expanded = !expanded">{{ expanded ? 'See less' : 'See more' }}</button>
            </div>
            <div class="flex items-center gap-1 text-[11px] font-semibold text-white/75">
                <span>Ad</span>
                <AdDisclosureMenu class="pointer-events-auto" v-bind:adData="adData" placement="reels" v-on:hide="$emit('hide')"></AdDisclosureMenu>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { colibriFollow } from '@/kernel/services/follows/index.js';
import AdDisclosureMenu from './AdDisclosureMenu.vue';

const props = defineProps({ adData: { type: Object, required: true } });
defineEmits(['hide']);
const advertiser = computed(() => props.adData.advertiser || {});
const caption = computed(() => props.adData.primary_text || props.adData.content || props.adData.source_post?.content || props.adData.title || '');
const follow = ref({ ...advertiser.value.meta?.relationship?.follow });
const followLoading = ref(false);
const expanded = ref(false);
const canFollow = computed(() => !advertiser.value.is_auth_user && Boolean(follow.value.can_follow));
const followLabel = computed(() => follow.value.following ? 'Following' : (follow.value.requested ? 'Requested' : 'Follow'));

const toggleFollow = async () => {
    if(followLoading.value || !advertiser.value.id) return;
    followLoading.value = true;
    try {
        const result = await colibriFollow().user(advertiser.value.id).follow();
        if(result?.relationship?.follow) follow.value = { ...follow.value, ...result.relationship.follow };
    } finally {
        followLoading.value = false;
    }
};
</script>

<style scoped>
.ad-reel-overlay { right: 3.75rem; }
@media (max-height: 680px) {
    .ad-reel-overlay-content { row-gap: .25rem; }
    .ad-reel-overlay p { -webkit-line-clamp: 1; }
}
</style>
