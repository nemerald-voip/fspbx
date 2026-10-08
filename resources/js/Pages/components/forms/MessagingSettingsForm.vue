<template>
    <div class="max-w-3xl">
        <h4 class="text-base font-semibold text-gray-900">{{ $t('Photo settings') }}</h4>
        <p class="mt-2 text-sm text-gray-600">{{ $t('Convert photos to JPEG before sending.') }}</p>
        <p class="mt-2 mb-4 text-sm text-gray-600">{{ $t('Reduce outbound photos to fit a shared 650 KB attachment budget. This applies to all messaging phone numbers in this account.') }}</p>
        <p v-if="!settings.available" class="mb-4 text-sm text-amber-700">{{ $t('Photo compression is unavailable. Please contact your administrator.') }}</p>
        <Vueform :endpoint="false" :display-errors="false" :default="defaults" @submit="save"
            @mounted="(form) => form.disableValidation()">
            <ToggleElement name="enabled" :text="$t('Compress outbound photos')" :true-value="true" :false-value="false"
                :disabled="!canManage || saving" />
            <ToggleElement name="convert_photos" :text="$t('Convert outbound photos to JPEG')" :true-value="true" :false-value="false"
                :disabled="!canManage || saving" />
            <ButtonElement v-if="canManage" name="save" :button-label="$t('Save')" :submits="true"
                :disabled="saving" :loading="saving" align="right" />
        </Vueform>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';

const props = defineProps({
    settings: { type: Object, required: true },
    route: { type: String, required: true },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['success', 'error']);
const saving = ref(false);
const defaults = { enabled: props.settings.enabled, convert_photos: props.settings.convert_photos ?? true };
const save = async (form$) => {
    if (saving.value || !props.canManage) return;
    clearServerFormErrors(form$);
    saving.value = true;
    try {
        const { data } = await axios.put(props.route, {
            enabled: !!form$.requestData.enabled,
            convert_photos: !!form$.requestData.convert_photos,
        });
        emit('success', data.messages);
    } catch (error) {
        showServerFormErrors(error.response, form$);
        emit('error', error.response?.data?.errors || { request: [error.message] });
    } finally {
        saving.value = false;
    }
};

</script>
