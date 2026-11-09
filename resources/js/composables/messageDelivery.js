// Message Settings offers "members" (the number's members and email), "inherit" (the account webhook) and
// "webhook" (a URL for this number). A saved "inherit" with no account webhook behaves as "members".

const storedMode = (item) => item?.inbound_delivery || 'inherit';

/** Form defaults for a saved number (or a new one when item is empty). */
export function messageDeliveryDefaults(item, settings) {
    if (!settings?.available) return {};
    const mode = storedMode(item);

    return {
        inbound_delivery: mode === 'inherit' && !settings.endpoint_url ? 'members' : mode,
        inbound_webhook_url: mode === 'webhook' ? (item?.inbound_webhook_url || '') : '',
    };
}

/** Converts the form choice back to the stored delivery mode. */
export function messageDeliveryRequest(data, item, settings) {
    if (!settings?.available || data.inbound_delivery !== 'members') return data;
    // With no account webhook to opt out of, keep following the account default if one is added later.
    if (!settings.endpoint_url && storedMode(item) !== 'members') {
        return { ...data, inbound_delivery: 'inherit' };
    }

    return data;
}
