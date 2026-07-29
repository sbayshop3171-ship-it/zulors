<template>
    <div v-if="networkStatus.status" class="fixed bottom-0 left-0 right-0 text-center text-par-s text-white z-[1000]">
        <span v-if="networkStatus.status == 'offline'" class="bg-red-900 block py-2">
            {{ $t('toast.network.connection_lost') }}
        </span>
        <span v-else-if="networkStatus.status == 'online'" class="bg-green-900 block py-2">
           {{ $t('toast.network.connection_restored') }} &checkmark;
        </span>
    </div>
</template>

<script>
    import { defineComponent, reactive, onMounted } from 'vue';

    export default defineComponent({
        setup: function(props) {
            const networkStatus = reactive({
                status: null,
                message: ''
            });

            onMounted(() => {
                window.addEventListener('offline', function () {
                    networkStatus.status = 'offline';
                });
    
                window.addEventListener('online', function () {
                    networkStatus.status = 'online';
    
                    setTimeout(() => {
                        networkStatus.status = null;
                    }, (1000 * 3));
                });
            });

            return {
                networkStatus: networkStatus
            };
        },
        components: {
        }
    });
</script>