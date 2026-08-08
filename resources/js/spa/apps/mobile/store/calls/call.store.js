import { createCallStore } from '@/kernel/stores/calls/create-call-store.js';
import { useAuthStore } from '@M/store/auth/auth.store.js';

const useCallStore = createCallStore({
    storeId: 'mobile_call_store',
    useAuthStore: useAuthStore
});

export { useCallStore };
export default useCallStore;
