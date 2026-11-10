<template>
    <div class="max-w-3xl">
        <Vueform ref="form$" :endpoint="false" :display-errors="false" :default="values(settings)" @submit="save"
            @mounted="form => form.disableValidation()">
            <template v-if="!system">
                <StaticElement name="webhook_heading" tag="h4" :content="$t('Inbound webhook')" />
                <TextElement v-if="current.webhook?.available" name="endpoint_url" :label="$t('Default webhook URL')"
                    :floating="false" placeholder="https://example.com/messages" :disabled="!canManage || saving"
                    :description="$t('Opt-out handling is unavailable while an account webhook is configured.')" />
                <StaticElement v-else name="webhook_unavailable">
                    {{ $t('Messaging webhook setup is unavailable. Contact your administrator.') }}
                </StaticElement>
                <StaticElement v-if="canManage && current.webhook?.available && secretRoute" name="secret">
                    <WebhookSigningSecret :route="secretRoute" />
                </StaticElement>
                <StaticElement name="webhook_divider" tag="hr" top="1" bottom="1" />
            </template>
            <SelectElement name="opt_out_scope" :label="$t('Opt-out handling')" :items="scopeOptions"
                :conditions="[showOptOut]" :native="false" :floating="false" :can-clear="false"
                :columns="{ default: { container: 12 }, sm: { container: 6 } }"
                :disabled="!canManage || saving" />
            <StaticElement name="opt_out_number_help" :conditions="[form => showOptOut(form) && effectiveOptOutScope(form) === 'number']">
                <p class="text-sm text-gray-600">{{ $t('Each phone number has its own opt-out list. Switching to this mode enables existing numbers that use this setting, and new numbers start enabled. To turn it off for an individual number, go to Message Settings, edit the number, and turn off Enable opt out.') }}</p>
            </StaticElement>
            <StaticElement name="opt_out_account_help" :conditions="[form => showOptOut(form) && effectiveOptOutScope(form) === 'account']">
                <p class="text-sm text-gray-600">{{ $t('Opt-out handling is enabled for every phone number in the account, including new numbers. All numbers share one opt-out list. Individual numbers cannot be excluded.') }}</p>
            </StaticElement>
            <StaticElement name="opt_out_disabled_help" :conditions="[form => showOptOut(form) && effectiveOptOutScope(form) === 'disabled']">
                <p class="text-sm text-gray-600">{{ $t('Opt-out handling is disabled for every phone number. Saved opt-outs are retained.') }}</p>
            </StaticElement>
            <StaticElement name="opt_out_help" :conditions="[showOptOut]">
                <p class="text-sm text-gray-600">{{ $t('Manage opted-out recipients in Message Settings. Existing opt-outs are preserved when changing modes. Customer API sends bypass these lists.') }}</p>
            </StaticElement>
            <TagsElement name="opt_out_keywords" :label="$t('Opt-out keywords')" :items="keywordOptions"
                :conditions="[showOptOut]" :floating="false" :search="true" :create="true" :close-on-select="false"
                :can-clear="false" :disabled="!canManage || saving"
                :description="$t('Type a keyword and press Enter. The entire SMS must match, ignoring letter case. STOP is always included and cannot be removed.')">
                <template #tag="{ option, handleTagRemove, disabled }">
                    <span class="inline-flex items-center gap-1 rounded bg-indigo-500 px-2 py-0.5 mr-1 my-0.5 text-sm font-semibold text-white">
                        <span>{{ option.label }}</span>
                        <button v-if="!disabled && option.value !== 'STOP'" type="button" class="inline-flex items-center justify-center rounded hover:bg-indigo-600 px-0.5"
                            :aria-label="$t('Remove :keyword', { keyword: option.label })" @click.stop="handleTagRemove(option, $event)">
                            <span aria-hidden="true">×</span>
                        </button>
                    </span>
                </template>
            </TagsElement>
            <StaticElement name="photos_heading" tag="h4" top="1" :content="$t('Outgoing photos')" />
            <StaticElement name="photo_status">
                <p class="text-sm text-gray-600">{{ $t('Prepare photo attachments before sending messages to external phone numbers.') }}</p>
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
            </StaticElement>
            <StaticElement v-if="!current.available" name="photos_unavailable">
                <p class="text-sm text-amber-700">{{ $t('Photo conversion and compression are unavailable on this server. Contact your administrator.') }}</p>
            </StaticElement>
            <ToggleElement name="convert_photos" :text="$t('Convert photo formats to JPEG')"
                :description="$t('Converts HEIC/HEIF, WebP, AVIF and TIFF photos to JPEG. Conversion alone keeps the original dimensions and does not limit file size.')"
                :true-value="true" :false-value="false" :disabled="!canEditPhotos" />
            <StaticElement name="photo_divider" tag="hr" top="1" bottom="1" />
            <ToggleElement name="enabled" :text="$t('Reduce photo file size')"
                :description="$t('Shrinks JPEG photos as needed to fit a 650 KB total attachment limit per message. May reduce image dimensions and quality.')"
                :true-value="true" :false-value="false" :disabled="!canEditPhotos" />
            <StaticElement name="photo_help">
                <p class="text-sm text-gray-600">{{ $t('With JPEG conversion on, PNG and converted photos are compressed too. Without it, only JPEG photos are compressed.') }}</p>
                <p class="text-sm text-gray-600">{{ $t('GIFs and other attachments are sent unchanged and count toward the total.') }}</p>
            </StaticElement>
            <StaticElement v-if="canManage && !system" name="photo_actions">
                <div class="flex flex-wrap items-center gap-3">
                    <ButtonElement v-if="!hasOverride && !overriding" name="override" :button-label="$t('Override Defaults')"
                        :secondary="true" :disabled="saving" @click="overriding = true" />
                    <ButtonElement v-if="hasOverride && resetRoute" name="revert" :button-label="$t('Revert to Defaults')"
                        :secondary="true" :disabled="saving" @click="revertPhotos" />
                    <ButtonElement v-if="overriding" name="cancel" :button-label="$t('Cancel')"
                        :secondary="true" :disabled="saving" @click="cancelPhotos" />
                </div>
            </StaticElement>
            <ButtonElement v-if="canManage" name="save" :button-label="$t('Save')" :submits="true"
                :disabled="saving" :loading="saving" align="right" top="1" />
        </Vueform>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { trans } from '@i18n';
import axios from 'axios';
import WebhookSigningSecret from './WebhookSigningSecret.vue';
import { clearServerFormErrors, showServerFormErrors } from '../../../composables/serverFormErrors.js';

const props = defineProps({
    settings: { type: Object, required: true }, route: { type: String, required: true },
    resetRoute: { type: String, default: null }, secretRoute: { type: String, default: null },
    system: Boolean, canManage: Boolean,
});
const emit = defineEmits(['success', 'error']);
const form$ = ref(null);
const saving = ref(false);
const current = ref({ ...props.settings });
const overriding = ref(false);
const hasOverride = computed(() => !props.system && current.value.scope === 'domain');
const values = s => ({
    enabled: !!s.enabled, convert_photos: s.convert_photos ?? true,
    opt_out_scope: s.opt_out_scope || (props.system ? 'disabled' : 'inherit'),
    opt_out_keywords: s.opt_out_keywords || ['STOP'],
    endpoint_url: s.webhook?.endpoint_url || '',
});
const scopeOptions = computed(() => [
    ...(!props.system ? [{ value: 'inherit', label: trans('Use system default (:value)', { value: scopeLabel(current.value.opt_out_default_scope) }) }] : []),
    { value: 'disabled', label: trans('Disabled') },
    { value: 'number', label: trans('Per phone number — individual control') }, { value: 'account', label: trans('Account-wide — all phone numbers') },
]);
const scopeLabel = value => value === 'account' ? trans('Account-wide — all phone numbers') : value === 'number' ? trans('Per phone number — individual control') : trans('Disabled');
const effectiveOptOutScope = form => form?.data?.opt_out_scope === 'inherit'
    ? current.value.opt_out_default_scope : form?.data?.opt_out_scope;
const keywordOptions = computed(() => (current.value.opt_out_keywords || ['STOP'])
    .map(value => ({ value, label: value, disabled: value === 'STOP', remove: value !== 'STOP' })));
const showOptOut = form => props.system || !String(form?.data?.endpoint_url || '').trim();
const canEditPhotos = computed(() => props.canManage && !saving.value && current.value.available
    && (props.system || hasOverride.value || overriding.value));
const apply = s => { current.value = { ...s }; overriding.value = false; form$.value?.update(values(s)); };
watch(() => props.settings, apply, { deep: true });
const cancelPhotos = () => {
    overriding.value = false;
    form$.value?.update({ enabled: !!current.value.enabled, convert_photos: current.value.convert_photos ?? true });
};
const revertPhotos = async () => {
    if (saving.value || !props.canManage || !hasOverride.value || !props.resetRoute) return;
    clearServerFormErrors(form$.value);
    saving.value = true;
    try {
        const { data } = await axios.delete(props.resetRoute);
        current.value = { ...current.value, ...data };
        cancelPhotos();
        emit('success', data.messages);
    } catch (error) {
        showServerFormErrors(error.response, form$.value);
        emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
    } finally { saving.value = false; }
};
const save = async form => {
    if (saving.value || !props.canManage) return;
    clearServerFormErrors(form);
    const fields = form.requestData;
    const data = {};
    if (props.system || !String(fields.endpoint_url || '').trim()) {
        data.opt_out_scope = fields.opt_out_scope;
        if (JSON.stringify(fields.opt_out_keywords) !== JSON.stringify(current.value.opt_out_keywords || ['STOP'])) {
            data.opt_out_keywords = fields.opt_out_keywords;
        }
    }
    if (!props.system && current.value.webhook?.available) data.endpoint_url = fields.endpoint_url || null;
    if (canEditPhotos.value && (overriding.value || !!fields.enabled !== !!current.value.enabled
        || !!fields.convert_photos !== (current.value.convert_photos ?? true))) {
        data.enabled = !!fields.enabled;
        data.convert_photos = !!fields.convert_photos;
    }
    saving.value = true;
    try {
        const { data: result } = await axios.put(props.route, data);
        apply(result);
        emit('success', result.messages);
    } catch (error) {
        showServerFormErrors(error.response, form);
        emit('error', error.response?.data?.errors || { request: [error.response?.data?.message || error.message] });
    } finally { saving.value = false; }
};
</script>
