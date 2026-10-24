<template>
    <div class="max-w-3xl">
        <h2 class="text-lg font-semibold text-gray-900">{{ $t('Outgoing photos') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ $t('Prepare photo attachments before sending messages to external phone numbers.') }}
        </p>

        <div class="my-6 border-l-2 border-indigo-200 pl-3" aria-live="polite">
            <p class="text-sm font-medium text-gray-900">
                <template v-if="system">{{ $t('System defaults') }}</template>
                <template v-else-if="overriding">{{ $t('Editing account override') }}</template>
                <template v-else-if="hasOverride">{{ $t('Account override') }}</template>
                <template v-else>{{ $t('Using system defaults') }}</template>
            </p>
            <p class="mt-1 text-sm text-gray-600">
                <template v-if="system">{{ $t('Every account inherits these settings unless it has its own override.') }}</template>
                <template v-else-if="overriding">{{ $t('Save to apply these options to all messaging phone numbers in this account.') }}</template>
                <template v-else-if="hasOverride">{{ $t('These saved options apply to all messaging phone numbers in this account. Revert to follow the system defaults again.') }}</template>
                <template v-else>{{ $t('The options below follow the system defaults. Override them to use different settings for this account.') }}</template>
            </p>
        </div>

        <p v-if="!currentSettings.available" class="mb-4 text-sm text-amber-700" role="status">
            {{ $t('Photo conversion and compression are unavailable on this server. Contact your administrator.') }}
        </p>

        <Vueform ref="form$" :endpoint="false" :display-errors="false" :default="defaults" @submit="save"
            @mounted="(form) => form.disableValidation()">
            <ToggleElement name="convert_photos" :text="$t('Convert photo formats to JPEG')"
                :description="$t('Converts HEIC/HEIF, WebP, AVIF and TIFF photos to JPEG. Conversion alone keeps the original dimensions and does not limit file size.')"
                :true-value="true" :false-value="false" :disabled="!canEdit || saving" />

            <StaticElement name="divider" tag="hr" top="1" bottom="1" />

            <ToggleElement name="enabled" :text="$t('Reduce photo file size')"
                :description="$t('Shrinks JPEG photos as needed to fit a 650 KB total attachment limit per message. May reduce image dimensions and quality.')"
                :true-value="true" :false-value="false" :disabled="!canEdit || saving" />
            <StaticElement name="compression_help" tag="p" :add-classes="{ StaticElement: { container: 'text-sm text-gray-600' } }">
                {{ $t('With JPEG conversion on, PNG and converted photos are compressed too. Without it, only JPEG photos are compressed.') }}
            </StaticElement>
            <StaticElement name="other_attachments" tag="p" :add-classes="{ StaticElement: { container: 'text-sm text-gray-600' } }">
                {{ $t('GIFs and other attachments are sent unchanged and count toward the total.') }}
            </StaticElement>

            <StaticElement v-if="canManage" name="actions" top="2">
                <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4">
                    <ButtonElement v-if="!canEdit" name="override" :button-label="$t('Override Defaults')"
                        :secondary="true" @click="overriding = true" />
                    <ButtonElement v-if="canEdit" name="save" :button-label="$t('Save')" :submits="true"
                        :disabled="saving" :loading="saving" />
                    <ButtonElement v-if="hasOverride && resetRoute" name="revert" :button-label="$t('Revert to Defaults')"
                        :secondary="true" :disabled="saving" @click="revert" />
                    <ButtonElement v-if="overriding" name="cancel" :button-label="$t('Cancel')"
                        :secondary="true" :disabled="saving" @click="cancel" />
                </div>
            </StaticElement>
        </Vueform>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';

const props = defineProps({
    settings: { type: Object, required: true },
    route: { type: String, required: true },
    resetRoute: { type: String, default: null },
    system: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
});
const emit = defineEmits(['success', 'error']);
const saving = ref(false);
const form$ = ref(null);
const overriding = ref(false);
const currentSettings = ref({ ...props.settings });
const hasOverride = computed(() => !props.system && currentSettings.value.scope === 'domain');
const canEdit = computed(() => props.canManage && (props.system || hasOverride.value || overriding.value));
const values = (settings) => ({ enabled: !!settings.enabled, convert_photos: settings.convert_photos ?? true });
const defaults = values(props.settings);

const applySettings = (settings) => {
    currentSettings.value = { ...settings };
    overriding.value = false;
    form$.value?.update(values(settings));
};
watch(() => props.settings, applySettings, { deep: true });

const cancel = () => {
    clearServerFormErrors(form$.value);
    applySettings(currentSettings.value);
};

const showError = (error) => {
    showServerFormErrors(error.response, form$.value);
    emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
};

const save = async (form) => {
    if (saving.value || !canEdit.value) return;
    clearServerFormErrors(form);
    saving.value = true;
    try {
        const { data } = await axios.put(props.route, {
            enabled: !!form.requestData.enabled,
            convert_photos: !!form.requestData.convert_photos,
        });
        applySettings(data);
        emit('success', data.messages);
    } catch (error) {
        showError(error);
    } finally {
        saving.value = false;
    }
};

const revert = async () => {
    if (saving.value || !props.canManage || !hasOverride.value || !props.resetRoute) return;
    clearServerFormErrors(form$.value);
    saving.value = true;
    try {
        const { data } = await axios.delete(props.resetRoute);
        applySettings(data);
        emit('success', data.messages);
    } catch (error) {
        showError(error);
    } finally {
        saving.value = false;
    }
};

</script>
