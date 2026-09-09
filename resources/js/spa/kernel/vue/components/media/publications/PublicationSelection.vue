<template>
    <div v-if="items.length" class="publication-selection px-4 py-2">
        <div v-for="item in items" :key="item.client_uid" class="publication-preview">
            <video v-if="item.type === 'video' && item.preview_url" :src="item.preview_url" controls playsinline preload="metadata" />
            <img v-else-if="item.preview_url" :src="item.preview_url" :alt="item.name" />
            <span v-else class="text-par-s text-lab-pr break-words">{{ item.name }}</span>
            <button type="button" class="publication-remove" title="Remove media" aria-label="Remove media" :disabled="disabled" @click="$emit('remove', item)">
                <SvgIcon type="solid" name="x" classes="size-icon-small" />
            </button>
        </div>
    </div>
</template>
<script setup>
defineProps({ items: { type: Array, required: true }, disabled: Boolean });
defineEmits(['remove']);
</script>
<style scoped>
.publication-selection { display: flex; gap: 8px; overflow-x: auto; }
.publication-preview { position: relative; width: 160px; height: 140px; flex: 0 0 160px; background: #111; border-radius: 4px; overflow: hidden; }
.publication-preview img, .publication-preview video { width: 100%; height: 100%; object-fit: contain; }
.publication-remove { position: absolute; top: 4px; right: 4px; width: 32px; height: 32px; display: grid; place-items: center; background: white; color: #111; border-radius: 4px; }
</style>
