<template>
    <div class="max-w-3xl mb-8">
        <h2 class="text-lg font-semibold text-gray-900">{{ $t('Inbound webhook') }}</h2>
        <p class="mt-1 mb-4 text-sm text-gray-600">
            {{ $t('Forward incoming messages to another application. Numbers can use this account default or choose their own delivery destination.') }}
        </p>
        <p v-if="!current.available" class="text-sm text-gray-600">
            {{ $t('Messaging webhook setup is unavailable. Contact your administrator.') }}
        </p>
        <Vueform v-else ref="form$" :endpoint="false" :display-errors="false" @submit="save"
            :default="{ endpoint_url: current.endpoint_url || '' }" @mounted="form => form.disableValidation()">
            <TextElement name="endpoint_url" :label="$t('Default webhook URL')" :floating="false"
                placeholder="https://example.com/messages" :disabled="!canManage || saving"
                :description="$t('Incoming messages for all numbers go here by default. To keep a number with its members, choose Members in Message Settings after saving this URL.')" />
            <StaticElement v-if="canManage && secretRoute" name="signing_secret">
                <WebhookSigningSecret :route="secretRoute" :initial-secret="current.signing_secret" />
            </StaticElement>
            <ButtonElement v-if="canManage" name="save" :button-label="$t('Save')" :submits="true"
                :loading="saving" :disabled="saving" align="right" />
        </Vueform>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';
import WebhookSigningSecret from './WebhookSigningSecret.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    route: { type: String, required: true },
    secretRoute: { type: String, default: null },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['success', 'error']);
const form$ = ref(null);
const current = ref({ ...props.settings });
const saving = ref(false);
watch(() => props.settings, settings => {
    current.value = { ...settings };
    form$.value?.update({ endpoint_url: settings.endpoint_url || '' });
});

const save = async form => {
    if (saving.value || !props.canManage) return;
    clearServerFormErrors(form);
    saving.value = true;
    try {
        const { data } = await axios.put(props.route, { endpoint_url: form.requestData.endpoint_url || null });
        current.value = data;
        emit('success', data.messages);
    } catch (error) {
        showServerFormErrors(error.response, form);
        emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
    } finally {
        saving.value = false;
    }
};
</script>
