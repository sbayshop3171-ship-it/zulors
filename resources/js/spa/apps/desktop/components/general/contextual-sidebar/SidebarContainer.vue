<template>
    <div class="h-full w-full border-r border-bord-card pb-12 overflow-y-auto sticky-bar-bottom-offset">
        <div v-if="$slots.sidebarTitle" class="mt-top-offset">
            <slot name="sidebarTitle"></slot>
        </div>

        <div class="block">
            <slot name="sidebarBody"></slot>
        </div>
    </div>
</template>
