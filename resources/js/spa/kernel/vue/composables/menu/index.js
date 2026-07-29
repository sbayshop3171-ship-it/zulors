import { ref } from 'vue';

export function useMenu() {

	const status = ref(false);
	
    return {
        status: status,
        open: function() {
            status.value = true;
        },
        close: function() {
            status.value = false;
        },
        toggle: function() {
            status.value = !status.value;
        }
    };
}