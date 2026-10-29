import { colibriAPI } from '@/kernel/services/api-client/native/index.js';

// Native post actions keep their original engagement and also attribute the action to the campaign.
export function recordSponsoredSocialEvent(adData, placement, eventType) {
    if(!adData?.id || !adData?.delivery_id) return;
    colibriAPI().ads().params({ placement }).with({
        event_type: eventType,
        delivery_id: adData.delivery_id
    }).sendTo(`event/${adData.id}`).catch(() => {});
}
