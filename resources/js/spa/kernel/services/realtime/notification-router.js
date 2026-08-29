const getNotificationEnvelope = (event = {}) => {
    if(! event || typeof event !== 'object') {
        return {};
    }

    return {
        type: event.type || event?.data?.type || null,
        data: event.data || event || {},
    };
};

const routeRealtimeNotification = (event, context = {}) => {
    const envelope = getNotificationEnvelope(event);
    const {
        callStore,
        inboxStore,
        toastStore,
        sounds,
        authUserId,
        activeChatId = null,
        getChatToastText = (messageData = {}) => {
            const senderName = messageData.relations?.user?.name || 'New message';
            const previewText = messageData.preview || messageData.content || 'Message';

            return `${senderName}: ${previewText}`;
        },
    } = context;

    if(! envelope.type) {
        return false;
    }

    if(envelope.type === 'call.notification') {
        if(typeof callStore?.handleNotification === 'function') {
            callStore.handleNotification(envelope.data);

            return true;
        }

        return false;
    }

    if(envelope.type !== 'chat.notification') {
        return false;
    }

    if(typeof inboxStore?.handleIncomingMessageNotification !== 'function') {
        return false;
    }

    const shouldNotify = inboxStore.handleIncomingMessageNotification(
        envelope.data,
        authUserId,
        activeChatId
    );

    if(! shouldNotify) {
        return true;
    }

    if(typeof toastStore?.add === 'function') {
        toastStore.add(getChatToastText(envelope.data), 4000);
    }

    if(typeof sounds?.isNotificationsSoundEnabled === 'function' && sounds.isNotificationsSoundEnabled()) {
        if(typeof sounds.backgroundChatMessageReceived === 'function') {
            sounds.backgroundChatMessageReceived();
        }
    }

    return true;
};

export {
    routeRealtimeNotification,
    getNotificationEnvelope,
};

export default routeRealtimeNotification;