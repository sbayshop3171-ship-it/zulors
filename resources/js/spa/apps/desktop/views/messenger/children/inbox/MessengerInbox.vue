<template>
    <InboxState></InboxState>
</template>

<script>
    import { defineComponent } from 'vue';

	import InboxState from '@D/views/messenger/parts/InboxState.vue';

    export default defineComponent({
        components: {
            InboxState: InboxState
        }
    });
</script>