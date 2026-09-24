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
const defaults = { enabled: props.settings.enabled };
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

</script>
