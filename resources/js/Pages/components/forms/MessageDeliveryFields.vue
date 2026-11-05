<template>
    <RadiogroupElement v-if="settings?.available" name="inbound_delivery" view="blocks"
        :label="$t('Incoming messages go to')" :items="deliveryOptions"
        :add-classes="{ RadiogroupRadio: { text: 'font-medium text-gray-900', description: 'break-words' } }" />

    <TextElement v-if="settings?.available" name="inbound_webhook_url" :label="$t('Webhook URL')" :floating="false"
        :conditions="[['inbound_delivery', 'webhook']]" placeholder="https://example.com/messages" />

    <StaticElement v-if="settings?.available && settings.secret_route" name="inbound_webhook_secret"
        :conditions="[['inbound_delivery', ['inherit', 'webhook']]]">
        <WebhookSigningSecret :route="settings.secret_route" :domain-uuid="settings.domain_uuid" />
    </StaticElement>

    <!-- Hidden fields are left out of the request; the server clears them for a number's own webhook. -->
    <TagsElement name="allowed_extension_uuids" :items="extensions" :close-on-select="false"
        :search="true" :native="false" :floating="false" :conditions="[showRecipients]"
        :label="$t('Members')" :placeholder="$t('Choose members')"
        :description="$t('Members share this number’s conversations and can send from it.')" />

    <TextElement name="email" :label="$t('Email')" :placeholder="$t('Optional')" :floating="false"
        :conditions="[showRecipients]" :description="$t('Incoming messages are also emailed to this address.')"
        :columns="{ sm: { container: 6 } }" />
</template>

<script setup>
import { computed } from 'vue';
import { trans } from '@i18n';
import WebhookSigningSecret from './WebhookSigningSecret.vue';

const props = defineProps({ settings: Object, extensions: Array });

// Vueform renders option text as HTML, so the stored URL must be escaped.
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const deliveryOptions = computed(() => {
    const accountUrl = props.settings?.endpoint_url;
    return [
        { value: 'members', label: trans('Members'), description: trans('Members read and reply to them.') },
        {
            value: 'inherit', label: trans('Account webhook'), disabled: !accountUrl,
            description: accountUrl ? escapeHtml(accountUrl) : trans('Not set up. Add one in Account Settings > Messaging.'),
        },
        { value: 'webhook', label: trans('Custom webhook'), description: trans('Use a URL just for this number.') },
    ];
});

const showRecipients = (form$) => !props.settings?.available || (form$?.data?.inbound_delivery ?? 'members') === 'members';
</script>
