<template>
    <div class="max-w-3xl">
        <h4 class="text-base font-semibold text-gray-900">{{ $t('Photo compression') }}</h4>
        <p class="mt-2 mb-4 text-sm text-gray-600">{{ $t('Reduce outbound photos to fit a shared 650 KB attachment budget. This applies to all messaging phone numbers in this account.') }}</p>
        <Vueform :endpoint="false" :display-errors="false" :default="defaults" @submit="save">
            <ToggleElement name="enabled" :text="$t('Compress outbound photos')" :true-value="true" :false-value="false"
                :disabled="!canManage || saving" />
            <ButtonElement v-if="canManage" name="save" :button-label="$t('Save')" :submits="true"
                :disabled="saving" :loading="saving" align="right" />
        </Vueform>
        <p class="mt-4 text-sm text-gray-600">{{ $t('HEIC, HEIF, WebP, AVIF, and TIFF photos always convert to JPEG. When compression is off, converted photos keep their dimensions and may exceed carrier size limits.') }}</p>
        <p class="mt-2 text-sm text-gray-600">{{ $t('GIFs, BMPs, videos, and other files are unchanged.') }}</p>
        <p v-if="!settings.available" class="mt-3 text-sm text-amber-700">{{ $t('Photo compression is unavailable. Please contact your administrator.') }}</p>
        <div class="mt-8 border-t border-gray-200 pt-6">
            <h4 class="text-base font-semibold text-gray-900">{{ $t('Messaging webhook') }}</h4>
            <p class="mt-2 text-sm text-gray-600">{{ $t('Send incoming messages and carrier status updates to this account URL.') }}</p>
            <Vueform :endpoint="false" :display-errors="false" :default="webhookDefaults" @submit="saveWebhook" class="mt-4">
                <TextElement name="webhook_url" :label="$t('Webhook URL')" :floating="false" input-type="url" />
                <ToggleElement name="webhook_enabled" :text="$t('Enable messaging webhook')" :true-value="true" :false-value="false"
                    :disabled="!canManage || savingWebhook" />
                <ButtonElement v-if="canManage" name="save_webhook" :button-label="$t('Save')" :submits="true"
                    :disabled="savingWebhook" :loading="savingWebhook" align="right" />
            </Vueform>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    settings: { type: Object, required: true },
    route: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['success', 'error']);
const saving = ref(false);
const savingWebhook = ref(false);
const defaults = { enabled: props.settings.enabled };
const webhookDefaults = {
    webhook_url: props.settings.webhook_url || '',
    webhook_enabled: !!props.settings.webhook_enabled,
};
const save = async (form$) => {
    if (saving.value || !props.canManage) return;
    saving.value = true;
    try {
        const { data } = await axios.put(props.route, { enabled: !!form$.requestData.enabled });
        emit('success', data.messages);
    } catch (error) {
        emit('error', error.response?.data?.errors || { request: [error.message] });
    } finally {
        saving.value = false;
    }
};

const saveWebhook = async (form$) => {
    if (savingWebhook.value || !props.canManage) return;
    savingWebhook.value = true;
    try {
        const { data } = await axios.put(props.route, form$.requestData);
        emit('success', data.messages);
    } catch (error) {
        emit('error', error.response?.data?.errors || { request: [error.message] });
    } finally {
        savingWebhook.value = false;
    }
};
</script>
